<?php
declare(strict_types=1);
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRemoteOperations.php';
/** Closed proof for cancellation of one unpaid invoice after canonical remote deletion. */
final class CompanyAsaasCancellationSource
{
    public function __construct(private $db,private string $p,private int $issuer,private int $actor,private int $invoice,private int $originId,private string $revision){}
    public function origin():int{return $this->originId;}
    public function tables():array{return (new \Asaas\CompanyRemoteOperations($this->db,$this->p))->tables();}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function metadata():string{return json_encode(['invoice_id'=>$this->invoice,'origin_id'=>$this->originId,'invoice_revision'=>$this->revision],JSON_THROW_ON_ERROR);}
    public function assertCurrent($db,string $p,int $issuer,int $actor,int $invoice,string $revision):bool
    {
        if($db!==$this->db||[$p,$issuer,$actor,$invoice,$revision]!==[$this->p,$this->issuer,$this->actor,$this->invoice,$this->revision])throw new DomainException('asaas_invoice_cancellation_scope');
        IssuerTransaction::assertOwned($db,$p,$this->tables());
        $acl=new IssuerResourceAccess($db,$p);$acl->lockFinancialIdentity($issuer,$actor);
        (new \Asaas\CompanyService($db,$p))->authorize($issuer,$actor,'remote_remove');
        $module=$this->rows('SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])[0]??[];
        if(version_compare($module['installed_version']??'0','1.6.3','<'))throw new DomainException('asaas_invoice_cancellation_module_required');
        $row=$this->rows('SELECT status FROM '.$p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
        $receipts=$this->rows('SELECT actor_id,metadata_json FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? FOR UPDATE',[$issuer,$invoice,'company_asaas_invoice_cancelled']);
        $receipts=array_values(array_filter($receipts,fn($r)=>(json_decode($r['metadata_json'],true,16,JSON_THROW_ON_ERROR)['origin_id']??null)===$this->originId));
        if($receipts){if(count($receipts)!==1||(int)$receipts[0]['actor_id']!==$actor||$receipts[0]['metadata_json']!==$this->metadata()||(int)($row['status']??0)!==5)throw new DomainException('asaas_invoice_cancellation_conflict');return true;}
        if(!in_array((int)($row['status']??0),[1,4],true))throw new DomainException('asaas_invoice_cancellation_state');
        if(!hash_equals($revision,(new CompanyInvoices($db,$p))->revision($issuer,$actor,$invoice)))throw new DomainException('company_invoice_stale');
        (new \Asaas\CompanyRemoteOperations($db,$p))->assertCancellationInOwned($issuer,$actor,$invoice,$this->originId);
        return false;
    }
    public function confirmed():void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$this->actor,'company_asaas_invoice_cancelled',$this->issuer,$this->invoice,gmdate('Y-m-d H:i:s'),$this->metadata()]);
        if(!$this->assertCurrent($this->db,$this->p,$this->issuer,$this->actor,$this->invoice,$this->revision))throw new RuntimeException('asaas_invoice_cancellation_unconfirmed');
    }
}
