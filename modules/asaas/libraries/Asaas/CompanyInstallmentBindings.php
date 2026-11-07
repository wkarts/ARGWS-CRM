<?php
declare(strict_types=1);
namespace Asaas;
/** Immutable group/member identities. Ordinary single-charge services never accept these channels. */
trait CompanyInstallmentBindings
{
    private function installmentModule():void
    {
        $this->module();$v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($v,'1.8.2','<'))throw new \DomainException('asaas_module_182_required');
        CompanyOriginGenerations::ensure($this->db,$this->p,false);
    }
    private function bindInstallmentReview(int $issuer,int $actor,array $review,array $config,array $parent,string $approval):array
    {
        $this->installmentModule();$this->legacyAccess($issuer,$actor);
        if(!hash_equals($review['review_hash'],$approval))throw new \DomainException('asaas_legacy_review_changed');
        $snapshot=self::snapshot($parent);$snapshotJson=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');$groupOrigin=null;
        $records=[['id'=>'grp_'.substr(hash('sha256',$review['group_id']),0,60),'due_date'=>$parent['duedate'],'customer'=>$review['members'][0]['customer']]];
        foreach($review['members'] as $member)$records[]=$member;
        foreach($records as $index=>$member){
            $channel=$index===0?'installments':'installment';$key=$index===0?$approval:self::hash(['installment-member',$approval,$member['id']]);
            if($this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE provider=? AND account_id=? AND environment=? AND remote_id=? FOR UPDATE',['asaas',$config['account_id'],$config['environment'],$member['id']]))throw new \DomainException('asaas_legacy_origin_exists');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_payment_origins(invoice_id,invoice_hash,invoice_total,invoice_due_date,issuer_id,provider,account_id,environment,channel,remote_id,batch_id,state,last_code,created_at,updated_at,active_slot) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$parent['id'],$parent['hash'],$parent['total'],$member['due_date'],$issuer,'asaas',$config['account_id'],$config['environment'],$channel,$member['id'],$review['group_id'],'installment_bound','reviewed_group',$now,$now,$index===0?1:null]);
            $origin=(int)$this->db->insert_id();if($index===0)$groupOrigin=$origin;
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_origin_meta(origin_id,account_row,created_by,request_key,snapshot_hash,snapshot_json,customer_id,billing_type,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$origin,$config['id'],$actor,$key,hash('sha256',$snapshotJson),$snapshotJson,$member['customer'],$review['billing_type'],$now]);
            CompanyOriginPolicy::markManual($this->db,$this->p,$origin,$issuer,$key);
            $proof=['group_origin'=>$groupOrigin,'origin_id'=>$origin,'issuer_id'=>$issuer,'account_row'=>(int)$config['id'],'snapshot_hash'=>hash('sha256',$snapshotJson),'review'=>$index===0?$review:null,'member'=>$index===0?null:$member];$hash=self::hash($proof);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$config['revision'],$actor,'installment_bind',json_encode($proof,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,'installment_bound',$now]);$proofId=(int)$this->db->insert_id();
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_installment_bound',$issuer,$origin,self::legacyAudit($proofId,$hash),$now]);
        }
        $this->installmentGroupInOwned($issuer,$actor,$groupOrigin);
        return ['bound'=>true,'origin_id'=>$groupOrigin];
    }
    public function installmentGroupInOwned(int $issuer,int $actor,int $groupOrigin,?string $ownEdit=null):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tablesForRun());$this->installmentModule();$this->authorize($issuer,$actor,'configuration');$this->authorize($issuer,$actor,'query');
        if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
        $root=$this->rows('SELECT * FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND channel=? FOR UPDATE',[$groupOrigin,$issuer,'installments'])[0]??null;
        if(!$root)throw new \DomainException('asaas_installment_origin_denied');CompanyOriginGenerations::assertActive($root);
        $all=$this->rows('SELECT * FROM '.$this->p.'asaas_payment_origins WHERE invoice_id=? ORDER BY id FOR UPDATE',[$root['invoice_id']]);$members=[];$review=null;$rootMeta=null;$config=null;
        foreach($all as $row){
            $meta=$this->rows('SELECT * FROM '.$this->p.'asaas_company_origin_meta WHERE origin_id=? FOR UPDATE',[$row['id']])[0]??null;
            $proofs=$this->rows("SELECT * FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='installment_bind' FOR UPDATE",[$row['id']]);
            $audits=$this->rows("SELECT * FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_asaas_installment_bound' FOR UPDATE",[$issuer,$row['id']]);
            if(!$meta||count($proofs)!==1||count($audits)!==1||$proofs[0]['state']!=='installment_bound'||!$this->installmentRemovalState($row)||$row['provider']!=='asaas')throw new \DomainException('asaas_installment_binding_conflict');
            $proof=$proofs[0];$safe=json_decode($proof['result_json'],true,64,JSON_THROW_ON_ERROR);
            foreach(['issuer_id','invoice_id','invoice_hash','invoice_total','account_id','environment','batch_id'] as $field)if((string)$row[$field]!== (string)$root[$field])throw new \DomainException('asaas_installment_binding_conflict');
            if(!hash_equals($proof['evidence_hash'],self::hash($safe))||($safe['group_origin']??null)!==$groupOrigin||($safe['origin_id']??null)!==(int)$row['id']||($safe['issuer_id']??null)!==$issuer||($safe['account_row']??null)!==(int)$meta['account_row']||($safe['snapshot_hash']??null)!==$meta['snapshot_hash']||!hash_equals($meta['snapshot_hash'],hash('sha256',$meta['snapshot_json']))||(int)$proof['actor_id']!==(int)$meta['created_by']||(int)$audits[0]['actor_id']!==(int)$meta['created_by']||$audits[0]['metadata_json']!==self::legacyAudit((int)$proof['id'],$proof['evidence_hash']))throw new \DomainException('asaas_installment_binding_conflict');
            CompanyOriginPolicy::assertManual($this->db,$this->p,(int)$row['id'],$issuer,$meta['request_key']);
            if((int)$row['id']===$groupOrigin){$review=$safe['review'];$rootMeta=$meta;$config=$this->account($issuer,(int)$meta['account_row']);if(($review['review_hash']??null)!==$meta['request_key']||($review['group_id']??null)!==$row['batch_id']||$row['remote_id']!=='grp_'.substr(hash('sha256',$row['batch_id']),0,60))throw new \DomainException('asaas_installment_binding_conflict');}
            else{$member=$safe['member']??null;if($row['active_slot']!==null||$row['channel']!=='installment'||!is_array($member)||$member['id']!==$row['remote_id']||$member['due_date']!==$row['invoice_due_date']||$meta['request_key']!==self::hash(['installment-member',$rootMeta['request_key'],$row['remote_id']])||$meta['account_row']!==$rootMeta['account_row']||$meta['snapshot_json']!==$rootMeta['snapshot_json']||$meta['customer_id']!==$rootMeta['customer_id']||$meta['billing_type']!==$rootMeta['billing_type'])throw new \DomainException('asaas_installment_binding_conflict');$members[$row['remote_id']]=['origin'=>$row,'meta'=>$meta,'member'=>$member];}
        }
        if(!$review||$rootMeta['customer_id']!==($review['members'][0]['customer']??null)||$rootMeta['billing_type']!==($review['billing_type']??null)||count($members)!==count($review['members']))throw new \DomainException('asaas_installment_binding_conflict');
        foreach($review['members'] as $member)if(($members[$member['id']]['member']??null)!==$member)throw new \DomainException('asaas_installment_binding_conflict');
        if(!(int)$config['enabled']||$config['verified_at']===null||$root['account_id']!==$config['account_id']||$root['environment']!==$config['environment'])throw new \DomainException('asaas_company_account_unverified');
        $parent=$this->parent($issuer,(int)$root['invoice_id']);$this->unchanged($rootMeta,$parent);if($root['invoice_hash']!==$parent['hash']||self::money($root['invoice_total'])!==self::money($parent['total'])||$root['invoice_due_date']!==$parent['duedate'])throw new \DomainException('asaas_installment_binding_conflict');
        [$review,$members]=CompanyInstallmentEditJournal::effective($this->db,$this->p,$root,$review,$members,$ownEdit);
        return [$root,$rootMeta,$config,$parent,$review,$members];
    }
    public function installmentGroups(int $issuer,int $actor):array
    {
        return $this->transaction(function()use($issuer,$actor){$acl=new \IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'settings','view')||!$acl->can($issuer,$actor,'invoices','view'))return [];$version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($version,'1.8.2','<'))return [];return $this->rows("SELECT id,invoice_id,batch_id,account_id,environment FROM {$this->p}asaas_payment_origins WHERE issuer_id=? AND channel='installments' AND active_slot=1 ORDER BY id DESC LIMIT 100",[$issuer]);});
    }
    public function installmentGroupPage(int $issuer,int $actor,int $groupOrigin):array
    {
        return $this->transaction(function()use($issuer,$actor,$groupOrigin){
            $this->authorize($issuer,$actor,'configuration');$this->authorize($issuer,$actor,'query');
            if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
            require_once __DIR__.'/CompanyInstallmentIdentity.php';
            $group=$this->rows('SELECT * FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND channel=? FOR UPDATE',[$groupOrigin,$issuer,'installments'])[0]??null;
            if(!$group)throw new \DomainException('asaas_installment_origin_denied');
            $member=$this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE invoice_id=? AND issuer_id=? AND account_id=? AND environment=? AND batch_id=? AND channel=? ORDER BY id LIMIT 1 FOR UPDATE',[$group['invoice_id'],$issuer,$group['account_id'],$group['environment'],$group['batch_id'],'installment'])[0]??null;
            if(!$member)throw new \DomainException('asaas_installment_origin_denied');
            $historical=CompanyInstallmentIdentity::member($this->db,$this->p,$issuer,(int)$member['id']);
            if((int)$historical['root']['id']!==$groupOrigin)throw new \DomainException('asaas_installment_identity_conflict');
            $root=$historical['root'];$meta=$historical['root_meta'];$config=$historical['account'];$review=$historical['review'];$members=$historical['members'];$this->unchanged($meta,$this->parent($issuer,(int)$root['invoice_id']));$out=[];

            foreach($review['members'] as $member){$origin=(int)$members[$member['id']]['origin']['id'];$proof=$this->rows("SELECT result_json,evidence_hash FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='parcel_query' ORDER BY id DESC LIMIT 1",[$origin])[0]??null;
                if($proof){$safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);if(!hash_equals($proof['evidence_hash'],self::hash($safe)))throw new \DomainException('asaas_installment_binding_conflict');$member['status']=$safe['status'];}
                $receipt=$this->rows('SELECT payment_id FROM '.$this->p.'asaas_company_receipts WHERE origin_id=?',[$origin])[0]??null;$member['origin_id']=$origin;$member['payment_id']=$receipt?(int)$receipt['payment_id']:null;$op=$this->installmentRemovalLedger($origin);$member['removal']=$op?$this->installmentRemovalPublic($op):null;$out[]=$member;
            }
            return ['origin_id'=>$groupOrigin,'invoice_id'=>(int)$root['invoice_id'],'group_id'=>$root['batch_id'],'account_id'=>$config['account_id'],'environment'=>$config['environment'],'members'=>$out];
        });
    }
    public function observeInstallment(int $issuer,int $actor,int $groupOrigin,string $remote,callable $decrypt,?callable $transport=null):int
    {
        $remote=self::opaque($remote,64);
        $read=function()use($issuer,$actor,$groupOrigin,$remote){$source=$this->installmentGroupInOwned($issuer,$actor,$groupOrigin);if(!isset($source[5][$remote]))throw new \DomainException('asaas_installment_origin_denied');return $source;};
        [$root,$meta,$config,$parent,$review,$members]=$this->transaction($read);
        $client=$this->client($config,$decrypt,$transport);self::wallet($client,$config);$this->transaction($read);self::customer($client,$meta['customer_id'],$parent);$this->transaction($read);
        $response=$client->request('GET','payments/'.$remote);$member=$members[$remote]['member'];
        if(($response['installment']??null)!==$review['group_id']||($response['installmentNumber']??null)!==$member['number'])throw new \DomainException('asaas_installment_binding_conflict');
        unset($response['installment']);$local=$parent;$local['duedate']=$member['due_date'];
        $safe=self::normalize($response,['remote_id'=>$remote],['customer_id'=>$meta['customer_id'],'billing_type'=>$meta['billing_type'],'reviewed_amount'=>$member['value']],$local,$remote);
        return $this->transaction(function()use($read,$config,$parent,$safe,$remote,$actor){
            [$root,$meta,$now,$current,,$members]=$read();if($now['revision']!==$config['revision']||self::snapshot($parent)!==self::snapshot($current))throw new \DomainException('asaas_company_stale');
            $origin=(int)$members[$remote]['origin']['id'];$hash=self::hash($safe);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$config['revision'],$actor,'parcel_query',json_encode($safe,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,'installment_observed',gmdate('Y-m-d H:i:s')]);return (int)$this->db->insert_id();
        });
    }
    public function installmentReceiptClaim(int $issuer,int $actor,int $groupOrigin,string $remote,int $proofId):array
    {
        $this->authorize($issuer,$actor,'settle');[$root,$meta,$config,$parent,$review,$members]=$this->installmentGroupInOwned($issuer,$actor,$groupOrigin);
        if(!isset($members[$remote]))throw new \DomainException('asaas_installment_origin_denied');$member=$members[$remote]['member'];$origin=(int)$members[$remote]['origin']['id'];
        if($this->installmentRemovalLedger($origin)!==null)throw new \DomainException('asaas_installment_removal_pending');
        foreach(['bb_origins','efi_company_origins'] as $table)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT id FROM '.$this->p.$table.' WHERE invoice_id=? FOR UPDATE',[$root['invoice_id']]))throw new \DomainException('asaas_company_multiple_providers');
        $proof=$this->rows("SELECT * FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='parcel_query' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$origin])[0]??null;
        if(!$proof||(int)$proof['id']!==$proofId||$proof['state']!=='installment_observed'||$proof['created_at']>gmdate('Y-m-d H:i:s')||$proof['account_revision']!==$config['revision'])throw new \DomainException('asaas_company_receipt_requires_review');
        $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($proof['evidence_hash'],self::hash($safe))||$safe['id']!==$remote||$safe['external_reference']!==$parent['hash']||$safe['customer']!==$meta['customer_id']||$safe['billing_type']!==$meta['billing_type']||$safe['due_date']!==$member['due_date']||$safe['value']!==$member['value']||$safe['status']!=='RECEIVED'||$safe['adverse']||$safe['deleted']||$safe['fee']===''||$safe['net_value']==='')throw new \DomainException('asaas_company_receipt_requires_review');
        $date=$safe['payment_date'];$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);$today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        if(!$parsed||$parsed->format('Y-m-d')!==$date||$date<$parent['date']||$date>$today)throw new \DomainException('asaas_company_receipt_requires_review');
        foreach($this->rows('SELECT event_type,state,created_at FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$config['id'],$remote]) as $event)if(!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_RECEIVED'],true)||in_array($event['state'],['conflict','review_required'],true)||$event['created_at']>$proof['created_at'])throw new \DomainException('asaas_company_adverse_history');
        $key=self::hash(['company-asaas-received',$issuer,$config['account_id'],$config['environment'],$remote]);$evidence=self::hash([$meta['snapshot_hash'],$safe['value'],$safe['net_value'],$safe['fee'],$date,$safe['billing_type'],$remote]);
        $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? OR canonical_key=? FOR UPDATE',[$origin,$key]);
        if(count($old)>1||($old&&((int)$old[0]['origin_id']!==$origin||(int)$old[0]['issuer_id']!==$issuer||$old[0]['canonical_key']!==$key||$old[0]['evidence_hash']!==$evidence)))throw new \DomainException('asaas_company_receipt_conflict');
        if(!$old&&$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300))throw new \DomainException('asaas_company_stale');
        return ['group_origin'=>$groupOrigin,'remote_id'=>$remote,'invoice'=>(int)$root['invoice_id'],'origin'=>$origin,'key'=>$key,'input'=>['amount'=>$safe['value'],'date'=>$date,'reference'=>'ASAAS-'.$key,'note'=>'Recebimento nominal de parcela Asaas. Líquido '.$safe['net_value'].'; tarifa observada '.$safe['fee'].'. Tarifa não contabilizada como despesa.'],'evidence'=>$evidence,'proof'=>$proofId];
    }
    public function installmentReceiptApplied(int $issuer,int $actor,array $claim,int $payment):void
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tablesForRun());
        if($this->installmentReceiptClaim($issuer,$actor,$claim['group_origin'],$claim['remote_id'],$claim['proof'])!==$claim)throw new \DomainException('asaas_company_receipt_conflict');
        $stored=$this->rows('SELECT invoiceid,amount,transactionid,paymentmode FROM '.$this->p.'invoicepaymentrecords WHERE id=?',[$payment])[0]??null;
        $native=$this->rows('SELECT payment_id FROM '.$this->p.'company_payment_receipts WHERE request_key=? AND issuer_id=? AND invoice_id=?',[$claim['key'],$issuer,$claim['invoice']])[0]??null;
        if(!$stored||!$native||(int)$native['payment_id']!==$payment||(int)$stored['invoiceid']!==$claim['invoice']||self::money($stored['amount'])!==$claim['input']['amount']||$stored['transactionid']!==$claim['input']['reference']||$stored['paymentmode']!=='asaas_company_received')throw new \DomainException('asaas_company_receipt_conflict');
        $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? FOR UPDATE',[$claim['origin']])[0]??null;
        if($old){if((int)$old['payment_id']!==$payment||$old['canonical_key']!==$claim['key']||$old['evidence_hash']!==$claim['evidence'])throw new \DomainException('asaas_company_receipt_conflict');return;}
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_receipts(origin_id,issuer_id,proof_id,canonical_key,evidence_hash,payment_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$claim['origin'],$issuer,$claim['proof'],$claim['key'],$claim['evidence'],$payment,$actor,gmdate('Y-m-d H:i:s')]);
        $saved=$this->rows('SELECT payment_id FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? AND canonical_key=?',[$claim['origin'],$claim['key']])[0]??null;if(!$saved||(int)$saved['payment_id']!==$payment)throw new \RuntimeException('issuer_asaas_receipt_unconfirmed');$this->audit($issuer,$actor,'company_asaas_installment_received',$claim['origin']);
    }
}
