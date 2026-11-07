<?php
declare(strict_types=1);
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRecreationSuccessors.php';
/** Closed authority to reopen exactly one native cancelled invoice after an unpaid successor is observed. */
final class CompanyAsaasRecreationSource
{
    public function __construct(private $db,private string $p,private int $issuer,private int $actor,private int $origin,private string $key){}
    public function tables():array{return (new \Asaas\CompanyRecreationSuccessors($this->db,$this->p))->tables();}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function assertCurrent($db,string $p,int $issuer,int $actor,int $invoice,string $revision):void
    {$this->validate($db,$p,$issuer,$actor,$invoice,$revision,false);}
    public function assertJournal($db,string $p,int $issuer,int $actor,int $invoice,string $revision):void
    {$this->validate($db,$p,$issuer,$actor,$invoice,$revision,true);}
    private function validate($db,string $p,int $issuer,int $actor,int $invoice,string $revision,bool $duringReopening):void
    {
        if($db!==$this->db||[$p,$issuer,$actor]!==[$this->p,$this->issuer,$this->actor])throw new DomainException('asaas_recreation_conflict');
        IssuerTransaction::assertOwned($db,$p,$this->tables());(new IssuerResourceAccess($db,$p))->lockFinancialIdentity($issuer,$actor);
        $service=new \Asaas\CompanyService($db,$p);$service->authorize($issuer,$actor,'remote_remove');$service->authorize($issuer,$actor,'issue');
        $intent=$this->rows('SELECT * FROM '.$p.'asaas_company_recreations WHERE origin_id=? AND issuer_id=? FOR UPDATE',[$this->origin,$issuer])[0]??null;
        if(!$intent||(int)$intent['actor_id']!==$actor||(int)$intent['invoice_id']!==$invoice||$intent['invoice_revision']!==$revision||$intent['request_key']!==$this->key||$intent['successor_origin_id']===null)throw new DomainException('asaas_recreation_conflict');
        if($intent['state']==='reopened'&&!$duringReopening){
            [$row,$meta,$account]=$service->remoteSourceInOwnedTransaction($issuer,$actor,(int)$intent['successor_origin_id']);
            if((int)$row['invoice_id']!==$invoice||$meta['request_key']!==$this->key||$account['revision']!==$intent['account_revision'])throw new DomainException('asaas_recreation_conflict');return;
        }
        [$intent,$row,$meta,$account]=(new \Asaas\CompanyRecreationSuccessors($db,$p))->assertPreparedInOwned($issuer,$actor,$this->origin,$this->key,$duringReopening);
        if(!in_array($intent['state'],['sending','uncertain'],true)||$row['remote_id']===null||$row['state']!=='issued')throw new DomainException('asaas_recreation_unconfirmed');
        $proof=$this->rows('SELECT * FROM '.$p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$row['id']])[0]??null;
        if(!$proof||$proof['operation']!=='query'||$proof['account_revision']!==$account['revision']||$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300)||$proof['created_at']>gmdate('Y-m-d H:i:s'))throw new DomainException('asaas_recreation_fresh_query_required');
        $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($proof['evidence_hash'],hash('sha256',json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))||!in_array($safe['status']??'',['PENDING','OVERDUE'],true)||($safe['deleted']??true)||($safe['adverse']??true)||($safe['payment_date']??'')!==''||($safe['credit_date']??'')!=='')throw new DomainException('asaas_recreation_unpaid_required');
        foreach($this->rows('SELECT event_type,state,created_at FROM '.$p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$account['id'],$row['remote_id']]) as $event)
            if($event['created_at']>$proof['created_at']||!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE'],true)||in_array($event['state'],['conflict','review_required'],true))throw new DomainException('asaas_recreation_event_review');
        // Every historical identity must be a predecessor in the same immutable chain.
        $origins=$this->rows('SELECT id,issuer_id,state,active_slot FROM '.$p.'asaas_payment_origins WHERE invoice_id=? FOR UPDATE',[$invoice]);$seen=[(int)$row['id']];$cursor=(int)$row['id'];
        while($link=$this->rows('SELECT origin_id,issuer_id,invoice_id,state FROM '.$p.'asaas_company_recreations WHERE successor_origin_id=? FOR UPDATE',[$cursor])[0]??null){
            $id=(int)$link['origin_id'];if(in_array($id,$seen,true)||(int)$link['issuer_id']!==$issuer||(int)$link['invoice_id']!==$invoice||($id!==$this->origin&&$link['state']!=='reopened'))throw new DomainException('asaas_recreation_conflict');$seen[]=$id;$cursor=$id;
        }
        foreach($origins as $old)if(!in_array((int)$old['id'],$seen,true)||(int)$old['issuer_id']!==$issuer||((int)$old['id']!==(int)$row['id']&&($old['active_slot']!==null||$old['state']!=='remote_removed')))throw new DomainException('asaas_recreation_conflict');
    }
    public function confirmed():void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_recreations SET state='reopened',updated_at=? WHERE origin_id=? AND issuer_id=? AND request_key=? AND state IN ('sending','uncertain')",[gmdate('Y-m-d H:i:s'),$this->origin,$this->issuer,$this->key]);
        if(($this->rows('SELECT state FROM '.$this->p.'asaas_company_recreations WHERE origin_id=?',[$this->origin])[0]['state']??'')!=='reopened')throw new RuntimeException('asaas_recreation_unconfirmed');
    }
}
