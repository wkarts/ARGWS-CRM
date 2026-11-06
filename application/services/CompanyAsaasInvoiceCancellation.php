<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';
require_once __DIR__.'/CompanyAsaasCancellationSource.php';
/** Native cancellation coordinator. Remote uncertainty never becomes local success. */
final class CompanyAsaasInvoiceCancellation
{
    public function __construct(private $db,private string $p){}
    public function cancel(int $issuer,int $actor,int $invoice,string $revision,callable $decrypt,?callable $transport=null):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_invoice_stale');
        $writer=new CompanyInvoices($this->db,$this->p);$remote=new \Asaas\CompanyRemoteOperations($this->db,$this->p);
        $providers=CompanyInvoiceProviderTables::current($this->db,$this->p);
        if(!in_array('asaas_payment_origins',$providers,true)){$writer->change($issuer,$actor,$invoice,$revision);return;}
        $rows=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'asaas_payment_origins WHERE invoice_id=?'.\Asaas\CompanyOriginGenerations::predicate($this->db,$this->p),[$invoice])->result_array();
        if(!$rows){$writer->change($issuer,$actor,$invoice,$revision);return;}
        $origin=IssuerTransaction::run($this->db,$this->p,$writer->transactionParticipants($remote->tables()),function()use($issuer,$actor,$invoice,$revision,$writer,$providers){
            $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
            (new \Asaas\CompanyService($this->db,$this->p))->authorize($issuer,$actor,'remote_remove');
            $module=IssuerStorage::query($this->db,'SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])->row_array();
            if(version_compare($module['installed_version']??'0','1.6.3','<'))throw new DomainException('asaas_invoice_cancellation_module_required');
            $origins=[];foreach($providers as $table){$found=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.$table.' WHERE invoice_id=?'.($table==='asaas_payment_origins'?\Asaas\CompanyOriginGenerations::predicate($this->db,$this->p):'').' FOR UPDATE',[$invoice])->result_array();if($found&&$table!=='asaas_payment_origins')throw new DomainException('company_invoice_provider_review_required');if($table==='asaas_payment_origins')$origins=$found;}
            if(count($origins)!==1)throw new DomainException('company_invoice_provider_review_required');$origin=(int)$origins[0]['id'];
            (new \Asaas\CompanyService($this->db,$this->p))->remoteSourceInOwnedTransaction($issuer,$actor,$origin);
            $receipt=IssuerStorage::query($this->db,'SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=?',[$issuer,$invoice,'company_asaas_invoice_cancelled'])->result_array();
            $receipt=array_filter($receipt,static fn($r)=>(json_decode($r['metadata_json'],true,16,JSON_THROW_ON_ERROR)['origin_id']??null)===$origin);
            if($receipt){(new CompanyAsaasCancellationSource($this->db,$this->p,$issuer,$actor,$invoice,$origin,$revision))->assertCurrent($this->db,$this->p,$issuer,$actor,$invoice,$revision);return null;}
            if(!hash_equals($revision,$writer->revision($issuer,$actor,$invoice)))throw new DomainException('company_invoice_stale');
            $row=IssuerStorage::query($this->db,'SELECT status FROM '.$this->p.'invoices WHERE issuer_id=? AND id=?',[$issuer,$invoice])->row_array();if(!in_array((int)($row['status']??0),[1,4],true))throw new DomainException('asaas_invoice_cancellation_state');
            return $origin;
        });
        if($origin===null)return;
        $result=$remote->remove($issuer,$actor,$origin,hash('sha256','company-asaas-delete:'.$issuer.':'.$origin),$decrypt,$transport);
        if($result['state']==='reserved')$result=$remote->dispatch($issuer,$actor,$origin,$decrypt,$transport);
        elseif(in_array($result['state'],['uncertain','awaiting_confirmation','review_required'],true))$result=$remote->refresh($issuer,$actor,$origin,$decrypt,$transport);
        if($result['state']!=='deleted')throw new DomainException('asaas_invoice_cancellation_pending');
        // Even a previously confirmed removal needs a fresh canonical observation before local mutation.
        (new \Asaas\CompanyService($this->db,$this->p))->query($issuer,$actor,$origin,$decrypt,$transport);
        $source=new CompanyAsaasCancellationSource($this->db,$this->p,$issuer,$actor,$invoice,$origin,$revision);
        $writer->change($issuer,$actor,$invoice,$revision,null,null,[],null,$source);
    }
}
