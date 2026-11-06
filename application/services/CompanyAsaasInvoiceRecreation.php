<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';
require_once __DIR__.'/CompanyAsaasRecreationSource.php';
/** Native reopening coordinator. Existing origins are retained, and ambiguous POSTs are never retried. */
final class CompanyAsaasInvoiceRecreation
{
    public function __construct(private $db,private string $p){}
    public function reopen(int $issuer,int $actor,int $invoice,string $revision,callable $decrypt,?callable $transport=null,string $recoveryId=''):void
    {
        foreach([$issuer,$actor,$invoice] as $id)IssuerPolicy::id($id);
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_invoice_stale');
        $writer=new CompanyInvoices($this->db,$this->p);
        if(!$this->db->table_exists($this->p.'asaas_payment_origins')||!IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'asaas_payment_origins WHERE invoice_id=?',[$invoice])->row_array()){$writer->reopen($issuer,$actor,$invoice,$revision);return;}
        $service=new \Asaas\CompanyService($this->db,$this->p);$service->authorize($issuer,$actor,'remote_remove');$service->authorize($issuer,$actor,'issue');
        \Asaas\CompanyOriginGenerations::ensure($this->db,$this->p,false);\Asaas\CompanyRecreationSchema::ensure($this->db,$this->p,false);
        $saved=IssuerStorage::query($this->db,'SELECT origin_id,state,request_key FROM '.$this->p.'asaas_company_recreations WHERE issuer_id=? AND invoice_id=? AND invoice_revision=?',[$issuer,$invoice,$revision])->result_array();
        if(count($saved)>1)throw new DomainException('asaas_recreation_conflict');
        if($saved){$origin=(int)$saved[0]['origin_id'];$key=$saved[0]['request_key'];}
        else{
            $origins=IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'asaas_payment_origins WHERE issuer_id=? AND invoice_id=? AND active_slot=1',[$issuer,$invoice])->result_array();
            if(count($origins)!==1)throw new DomainException('asaas_recreation_conflict');$origin=(int)$origins[0]['id'];
            $key=hash('sha256','company-asaas-recreation:'.$issuer.':'.$origin.':'.$revision);
            $service->query($issuer,$actor,$origin,$decrypt,$transport);
            (new \Asaas\CompanyRecreationIntents($this->db,$this->p))->reserve($issuer,$actor,$invoice,$origin,$revision,$key);
        }
        $source=new CompanyAsaasRecreationSource($this->db,$this->p,$issuer,$actor,$origin,$key);
        if(($saved[0]['state']??'reserved')==='reopened'){$writer->reopen($issuer,$actor,$invoice,$revision,$source);return;}
        if(in_array($saved[0]['state']??'reserved',['reserved','prepared'],true))(new \Asaas\CompanyRecreationSuccessors($this->db,$this->p))->prepare($issuer,$actor,$invoice,$origin,$revision,$key);
        $service->dispatchRecreation($issuer,$actor,$origin,$key,$decrypt,$transport,$recoveryId);
        $writer->reopen($issuer,$actor,$invoice,$revision,$source);
    }
}
