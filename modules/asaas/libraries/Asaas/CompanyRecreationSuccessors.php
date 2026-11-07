<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyRecreationIntents.php';
require_once __DIR__.'/CompanyOriginGenerations.php';
/** Atomic successor reservation. The cancelled invoice stays cancelled until remote proof exists. */
final class CompanyRecreationSuccessors
{
    public function __construct(private $db,private string $p){}
    public function tables():array{return array_values(array_unique(array_merge((new \CompanyInvoices($this->db,$this->p))->transactionParticipants(),(new CompanyRemoteOperations($this->db,$this->p))->tables(),CompanyPreferencesSchema::tables(),[CompanyRecreationSchema::TABLE],$this->db->table_exists($this->p.'asaas_company_refund_requests')?['asaas_company_refund_requests']:[])));}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function prepare(int $issuer,int $actor,int $invoice,int $origin,string $revision,string $key):int
    {
        foreach([$issuer,$actor,$invoice,$origin] as $id)\IssuerPolicy::id($id);
        foreach([$revision,$key] as $value)if(!preg_match('/^[a-f0-9]{64}$/D',$value))throw new \DomainException('asaas_recreation_invalid');
        CompanyOriginGenerations::ensure($this->db,$this->p,false);CompanyRecreationSchema::ensure($this->db,$this->p,false);
        return \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$invoice,$origin,$revision,$key):int{
            (new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
            $service=new CompanyService($this->db,$this->p);$service->authorize($issuer,$actor,'remote_remove');$service->authorize($issuer,$actor,'issue');
            $module=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]??[];
            if(version_compare($module['installed_version']??'0','1.6.5','<'))throw new \DomainException('asaas_recreation_upgrade_required');
            $intent=$this->rows('SELECT * FROM '.$this->p.CompanyRecreationSchema::TABLE.' WHERE origin_id=? FOR UPDATE',[$origin])[0]??null;
            if(!$intent)throw new \DomainException('asaas_recreation_intent_required');
            foreach(['issuer_id'=>$issuer,'actor_id'=>$actor,'invoice_id'=>$invoice] as $field=>$value)if((int)$intent[$field]!==$value)throw new \DomainException('asaas_recreation_conflict');
            if($intent['invoice_revision']!==$revision||$intent['request_key']!==$key)throw new \DomainException('asaas_recreation_conflict');
            $parent=$this->rows('SELECT status FROM '.$this->p.'invoices WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$invoice])[0]??null;
            if((int)($parent['status']??0)!==5||!hash_equals($revision,(new \CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,$invoice)))throw new \DomainException('asaas_recreation_stale');
            if($intent['successor_origin_id']!==null){
                if($intent['state']!=='prepared')throw new \DomainException('asaas_recreation_conflict');
                [$next,$meta,$account]=$service->remoteSourceInOwnedTransaction($issuer,$actor,(int)$intent['successor_origin_id']);
                if((int)$next['invoice_id']!==$invoice||$meta['request_key']!==$key||$account['revision']!==$intent['account_revision'])throw new \DomainException('asaas_recreation_conflict');
                return (int)$next['id'];
            }
            if($intent['state']!=='reserved')throw new \DomainException('asaas_recreation_conflict');
            (new CompanyRemoteOperations($this->db,$this->p))->assertCancellationInOwned($issuer,$actor,$invoice,$origin);
            [$old,$meta,$account,$document]=$service->remoteSourceInOwnedTransaction($issuer,$actor,$origin);
            if($account['revision']!==$intent['account_revision']||(int)$old['invoice_id']!==$invoice)throw new \DomainException('asaas_recreation_conflict');
            $legacyReference=CompanyOriginPolicy::isManual($this->db,$this->p,$origin)?$service->legacyReferenceInOwned($issuer,$actor,$origin):null;
            if($legacyReference!==null){
                if(version_compare($module['installed_version']??'0','1.7.9','<'))throw new \DomainException('asaas_module_179_required');
                if(isset($meta['reviewed_amount'])&&\CompanyInvoiceAmounts::minor($meta['reviewed_amount'])!==\CompanyInvoiceAmounts::minor($document['total']))throw new \DomainException('asaas_legacy_recreation_allocation_required');
            }else CompanyOriginPolicy::assertAutomaticAllowed($this->db,$this->p,$origin);
            if(!in_array($meta['billing_type'],['PIX','BOLETO'],true))throw new \DomainException('asaas_recreation_method_requires_review');
            $policy=new CompanyPreferences($this->db,$this->p);$policy->requireReady();
            $profile=$policy->snapshotInOwned($issuer,$actor,$account,$meta['billing_type'],$document['formatted_number'],null);
            $proof=$this->rows('SELECT id FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin])[0];
            \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_payment_origins SET active_slot=NULL WHERE id=? AND issuer_id=? AND active_slot=1',[$origin,$issuer]);
            $now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_payment_origins(invoice_id,invoice_hash,invoice_total,invoice_due_date,issuer_id,provider,account_id,environment,channel,remote_id,batch_id,state,last_code,created_at,updated_at,active_slot) VALUES(?,?,?,?,?,?,?,?,?,NULL,NULL,?,?,?,?,1)',[$invoice,$old['invoice_hash'],$old['invoice_total'],$old['invoice_due_date'],$issuer,$old['provider'],$old['account_id'],$old['environment'],$old['channel'],'issuing','recreation_reserved',$now,$now]);
            $next=(int)$this->db->insert_id();if($next<1||$next===$origin)throw new \RuntimeException('asaas_recreation_unconfirmed');
            $snapshot=json_decode($meta['snapshot_json'],true,32,JSON_THROW_ON_ERROR);$snapshot['asaas_profile_contract']='company_profile_v1';$json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_origin_meta(origin_id,account_row,created_by,request_key,snapshot_hash,snapshot_json,customer_id,billing_type,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$next,$account['id'],$actor,$key,hash('sha256',$json),$json,$meta['customer_id'],$meta['billing_type'],$now]);
            $policy->persistInOwned($next,$profile);
            if($legacyReference!==null)CompanyOriginPolicy::markManual($this->db,$this->p,$next,$issuer,$key);
            \IssuerStorage::query($this->db,'UPDATE '.$this->p.CompanyRecreationSchema::TABLE.' SET successor_origin_id=?,proof_id=?,state=?,updated_at=? WHERE origin_id=?',[$next,$proof['id'],'prepared',$now,$origin]);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'company_asaas_recreation_prepared',$issuer,$next,$now]);
            $stored=$this->rows('SELECT successor_origin_id,state FROM '.$this->p.CompanyRecreationSchema::TABLE.' WHERE origin_id=?',[$origin])[0]??[];
            if((int)($stored['successor_origin_id']??0)!==$next||($stored['state']??'')!=='prepared')throw new \RuntimeException('asaas_recreation_unconfirmed');
            $service->remoteSourceInOwnedTransaction($issuer,$actor,$next);return $next;
        });
    }
    /** Closed state check, including current ACL, revision, company and account, inside the writer lock. */
    public function assertPreparedInOwned(int $issuer,int $actor,int $origin,string $key,bool $duringReopening=false):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        (new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
        $service=new CompanyService($this->db,$this->p);$service->authorize($issuer,$actor,'remote_remove');$service->authorize($issuer,$actor,'issue');
        $module=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]??[];
        if(version_compare($module['installed_version']??'0','1.6.5','<'))throw new \DomainException('asaas_recreation_upgrade_required');
        $intent=$this->rows('SELECT * FROM '.$this->p.CompanyRecreationSchema::TABLE.' WHERE origin_id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer])[0]??null;
        if(!$intent||(int)$intent['actor_id']!==$actor||$intent['request_key']!==$key||!in_array($intent['state'],['prepared','sending','uncertain'],true)||$intent['successor_origin_id']===null)throw new \DomainException('asaas_recreation_conflict');
        [$row,$meta,$account,$parent]=$service->remoteSourceInOwnedTransaction($issuer,$actor,(int)$intent['successor_origin_id']);
        if((int)$row['invoice_id']!==(int)$intent['invoice_id']||$meta['request_key']!==$key||$account['revision']!==$intent['account_revision']||(!$duringReopening&&((int)$parent['status']!==5||!hash_equals($intent['invoice_revision'],(new \CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,(int)$intent['invoice_id']))))||($duringReopening&&!in_array((int)$parent['status'],[1,4],true)))throw new \DomainException('asaas_recreation_stale');
        $old=$this->rows('SELECT remote_id,state,active_slot FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND invoice_id=? FOR UPDATE',[$origin,$issuer,$intent['invoice_id']])[0]??null;
        if(!$old||$old['active_slot']!==null||$old['state']!=='remote_removed')throw new \DomainException('asaas_recreation_conflict');
        $proof=$this->rows('SELECT created_at FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=?',[$intent['proof_id'],$origin])[0]??null;
        if(!$proof)throw new \DomainException('asaas_recreation_conflict');
        if($intent['state']==='prepared'&&($proof['created_at']<gmdate('Y-m-d H:i:s',time()-300)||$proof['created_at']>gmdate('Y-m-d H:i:s')))throw new \DomainException('asaas_recreation_fresh_query_required');
        foreach($this->rows('SELECT event_type,state,created_at FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$account['id'],$old['remote_id']]) as $event){
            if($event['created_at']>$proof['created_at']||!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_DELETED'],true)||$event['state']==='conflict')throw new \DomainException('asaas_recreation_historical_event_review');
        }
        return [$intent,$row,$meta,$account,$parent];
    }

}
