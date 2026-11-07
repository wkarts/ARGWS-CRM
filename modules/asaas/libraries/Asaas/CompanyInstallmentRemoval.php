<?php
declare(strict_types=1);
namespace Asaas;
/** Per-member deletion journal. Never deletes a native invoice or rewrites binding evidence. */
trait CompanyInstallmentRemoval
{
    private function installmentRemovalState(array $row):bool
    {
        if($row['state']==='installment_bound')return true;
        if($row['channel']!=='installment'||$row['state']!=='review_required'||$row['last_code']!=='company_adverse_event')return false;
        $op=$this->rows('SELECT sent_at FROM '.$this->p.'asaas_company_remote_operations WHERE origin_id=? AND issuer_id=? FOR UPDATE',[$row['id'],$row['issuer_id']])[0]??null;
        if(!$op||$op['sent_at']===null)return false;
        $events=$this->rows('SELECT e.event_type,e.state FROM '.$this->p.'asaas_company_events e JOIN '.$this->p.'asaas_company_accounts a ON a.id=e.account_row WHERE a.issuer_id=? AND a.account_id=? AND a.environment=? AND e.remote_id=? FOR UPDATE',[$row['issuer_id'],$row['account_id'],$row['environment'],$row['remote_id']]);$deleted=false;
        foreach($events as $event){if($event['state']==='conflict'||!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_DELETED'],true)||($event['state']==='review_required'&&$event['event_type']!=='PAYMENT_DELETED'))return false;if($event['event_type']==='PAYMENT_DELETED')$deleted=true;}
        return $deleted;
    }
    private function installmentRemovalSource(int $issuer,int $actor,int $group,string $remote):array
    {
        $this->authorize($issuer,$actor,'remote_remove');(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
        $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($version,'1.8.3','<'))throw new \DomainException('asaas_module_183_required');
        CompanyRemoteOperationsSchema::ensure($this->db,$this->p,false);
        $source=$this->installmentGroupInOwned($issuer,$actor,$group);
        if(!isset($source[5][$remote]))throw new \DomainException('asaas_installment_origin_denied');return $source;
    }
    private function installmentRemovalLedger(int $origin):?array
    {return $this->rows('SELECT * FROM '.$this->p.'asaas_company_remote_operations WHERE origin_id=? FOR UPDATE',[$origin])[0]??null;}
    private function installmentRemovalPublic(array $op):array
    {return array_intersect_key($op,array_flip(['origin_id','state','last_code','updated_at']));}
    private function installmentRemovalProof(array $source,string $remote,bool $deleted):array
    {
        [$root,$meta,$account,$parent,$review,$members]=$source;$member=$members[$remote];$origin=(int)$member['origin']['id'];
        if($this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? FOR UPDATE',[$origin])||$this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=? FOR UPDATE',[$root['invoice_id']]))throw new \DomainException('asaas_company_remote_financial_history');
        // Only receipts belonging to other reviewed members may coexist with this deletion.
        foreach($this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$root['invoice_id']]) as $payment){
            $receipt=$this->rows('SELECT r.origin_id FROM '.$this->p.'asaas_company_receipts r JOIN '.$this->p.'asaas_payment_origins o ON o.id=r.origin_id WHERE r.payment_id=? AND r.issuer_id=? AND o.invoice_id=? AND o.channel=? AND o.batch_id=? FOR UPDATE',[$payment['id'],$root['issuer_id'],$root['invoice_id'],'installment',$root['batch_id']]);
            if(count($receipt)!==1||(int)$receipt[0]['origin_id']===$origin)throw new \DomainException('asaas_company_remote_financial_history');
        }
        foreach(['bb_origins','efi_company_origins'] as $table)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT id FROM '.$this->p.$table.' WHERE invoice_id=? FOR UPDATE',[$root['invoice_id']]))throw new \DomainException('asaas_company_multiple_providers');
        $proof=$this->rows("SELECT * FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='parcel_query' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$origin])[0]??null;
        if(!$proof||$proof['state']!=='installment_observed'||$proof['account_revision']!==$account['revision']||$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300)||$proof['created_at']>gmdate('Y-m-d H:i:s'))throw new \DomainException('asaas_company_remote_fresh_query_required');
        $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($proof['evidence_hash'],self::hash($safe))||$safe['id']!==$remote||$safe['customer']!==$meta['customer_id']||$safe['external_reference']!==$member['member']['external_reference']||$safe['billing_type']!==$meta['billing_type']||$safe['value']!==$member['member']['value']||$safe['due_date']!==$member['member']['due_date']||!in_array($safe['status'],['PENDING','OVERDUE'],true)||$safe['deleted']!==$deleted||$safe['adverse']||$safe['payment_date']!==''||$safe['credit_date']!=='')throw new \DomainException('asaas_company_remote_unpaid_required');
        foreach($this->rows('SELECT event_type,state,created_at FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$account['id'],$remote]) as $event)if($event['created_at']>$proof['created_at']||$event['state']==='conflict'||!in_array($event['event_type'],$deleted?['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_DELETED']:['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE'],true)||($event['state']==='review_required'&&!($deleted&&$event['event_type']==='PAYMENT_DELETED')))throw new \DomainException('asaas_company_adverse_history');
        return $proof;
    }
    private function installmentRemovalTransition(int $issuer,int $actor,int $origin,string $state,string $code):array
    {
        $now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_remote_operations SET state=?,last_code=?,updated_at=? WHERE origin_id=? AND issuer_id=?',[$state,$code,$now,$origin,$issuer]);
        $this->audit($issuer,$actor,'company_asaas_parcel_'.$state,$origin);$op=$this->installmentRemovalLedger($origin);
        if(!$op||(int)$op['issuer_id']!==$issuer||$op['state']!==$state)throw new \RuntimeException('asaas_company_remote_state_unconfirmed');return $this->installmentRemovalPublic($op);
    }
    public function removeInstallment(int $issuer,int $actor,int $group,string $remote,string $key,callable $decrypt,?callable $transport=null):array
    {
        $remote=self::opaque($remote,64);if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new \DomainException('asaas_company_invalid_input');
        $read=function()use($issuer,$actor,$group,$remote){return $this->installmentRemovalSource($issuer,$actor,$group,$remote);};
        $old=$this->transaction(function()use($read,$remote){$s=$read();return $this->installmentRemovalLedger((int)$s[5][$remote]['origin']['id']);});
        if($old){if(!hash_equals($old['request_key'],$key))throw new \DomainException('asaas_company_remote_request_conflict');return $this->installmentRemovalPublic($old);}
        $this->observeInstallment($issuer,$actor,$group,$remote,$decrypt,$transport);
        $prepared=$this->transaction(function()use($read,$remote,$key,$issuer,$actor){
            $s=$read();$origin=(int)$s[5][$remote]['origin']['id'];$old=$this->installmentRemovalLedger($origin);
            if($old){if(!hash_equals($old['request_key'],$key))throw new \DomainException('asaas_company_remote_request_conflict');return ['existing'=>$this->installmentRemovalPublic($old)];}
            $proof=$this->installmentRemovalProof($s,$remote,false);$now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_remote_operations(origin_id,issuer_id,actor_id,account_revision,request_key,proof_id,state,last_code,created_at,updated_at,sent_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$origin,$issuer,$actor,$s[2]['revision'],$key,$proof['id'],'sending','delete_started',$now,$now,$now]);
            $this->audit($issuer,$actor,'company_asaas_parcel_sending',$origin);return ['origin'=>$origin,'account'=>$s[2]];
        });
        if(isset($prepared['existing']))return $prepared['existing'];$origin=$prepared['origin'];$account=$prepared['account'];
        try{$response=$this->client($account,$decrypt,$transport)->request('DELETE','payments/'.$remote);if(($response['id']??null)!==$remote||($response['deleted']??null)!==true)throw new \RuntimeException();$state='awaiting_confirmation';$code='delete_response_observed';}
        catch(\Throwable $error){$state='uncertain';$code='delete_unconfirmed';}
        // The already persisted intent authorizes technical observation after an in-flight ACL revocation.
        $result=\IssuerTransaction::run($this->db,$this->p,$this->tablesForRun(),function()use($issuer,$actor,$origin,$key,$state,$code){$op=$this->installmentRemovalLedger($origin);if(!$op||(int)$op['issuer_id']!==$issuer||(int)$op['actor_id']!==$actor||$op['request_key']!==$key)throw new \DomainException('asaas_company_remote_intent_conflict');if($op['state']!=='sending')return $this->installmentRemovalPublic($op);return $this->installmentRemovalTransition($issuer,$actor,$origin,$state,$code);});
        return $state==='uncertain'?$result:$this->refreshInstallmentRemoval($issuer,$actor,$group,$remote,$decrypt,$transport);
    }
    public function refreshInstallmentRemoval(int $issuer,int $actor,int $group,string $remote,callable $decrypt,?callable $transport=null):array
    {
        $remote=self::opaque($remote,64);$read=function()use($issuer,$actor,$group,$remote){$s=$this->installmentRemovalSource($issuer,$actor,$group,$remote);$op=$this->installmentRemovalLedger((int)$s[5][$remote]['origin']['id']);if(!$op||$op['sent_at']===null||(int)$op['issuer_id']!==$issuer||$op['account_revision']!==$s[2]['revision'])throw new \DomainException('asaas_company_remote_intent_conflict');return [$s,$op];};
        $this->transaction($read);$this->observeInstallment($issuer,$actor,$group,$remote,$decrypt,$transport);
        return $this->transaction(function()use($read,$issuer,$actor,$remote){[$s,$op]=$read();try{$this->installmentRemovalProof($s,$remote,true);$state='deleted';$code='canonical_deleted_confirmed';}catch(\DomainException $e){$state='review_required';$code='canonical_deletion_not_confirmed';}return $this->installmentRemovalTransition($issuer,$actor,(int)$op['origin_id'],$state,$code);});
    }
}
