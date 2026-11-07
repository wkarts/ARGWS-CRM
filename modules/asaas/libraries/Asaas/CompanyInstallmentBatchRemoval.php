<?php
declare(strict_types=1);
namespace Asaas;
/** Explicit reviewed set of member removals; remote operations are not an atomic group DELETE. */
trait CompanyInstallmentBatchRemoval
{
    private function installmentBatchSource(int $issuer,int $actor,int $group):array
    {
        $this->authorize($issuer,$actor,'remote_remove');
        (new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
        $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($version,'1.8.4','<'))throw new \DomainException('asaas_module_184_required');
        return $this->installmentGroupInOwned($issuer,$actor,$group);
    }
    private function installmentBatchIntent(int $issuer,int $actor,array $source):?array
    {
        $root=$source[0];$proofs=$this->rows("SELECT * FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='group_remove' FOR UPDATE",[$root['id']]);
        if(!$proofs)return null;
        if(count($proofs)!==1)throw new \DomainException('asaas_installment_batch_conflict');$proof=$proofs[0];
        $plan=json_decode($proof['result_json'],true,64,JSON_THROW_ON_ERROR);$digest=$plan['review_hash']??'';$input=$plan;unset($input['review_hash']);
        if($proof['state']!=='group_removal'||(int)$proof['actor_id']!==$actor||$proof['account_revision']!==$source[2]['revision']||!hash_equals($proof['evidence_hash'],self::hash($plan))||!hash_equals($digest,self::hash($input))||($plan['issuer_id']??null)!==$issuer||($plan['actor_id']??null)!==$actor||($plan['group_origin']??null)!==(int)$root['id']||($plan['account_revision']??null)!==$source[2]['revision']||($plan['snapshot_hash']??null)!==$source[1]['snapshot_hash'])throw new \DomainException('asaas_installment_batch_conflict');
        $audit=$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=? FOR UPDATE',[$issuer,$actor,$root['id'],'company_asaas_group_removal',self::legacyAudit((int)$proof['id'],$proof['evidence_hash'])]);
        if(count($audit)!==1||!is_array($plan['members']??null)||!$plan['members'])throw new \DomainException('asaas_installment_batch_conflict');
        $seen=[];foreach($plan['members'] as $member){$current=$source[5][$member['id']??'']??null;if(!$current||isset($seen[$member['id']])||$member!==$current['member'])throw new \DomainException('asaas_installment_batch_conflict');$seen[$member['id']]=true;}
        return ['proof_id'=>(int)$proof['id'],'plan'=>$plan];
    }
    /** Requery the full immutable group before presenting the precise selected/excluded set. */
    public function reviewInstallmentRemoval(int $issuer,int $actor,int $group,callable $decrypt,?callable $transport=null):array
    {
        $source=$this->transaction(fn()=>$this->installmentBatchSource($issuer,$actor,$group));
        $existing=$this->transaction(fn()=>$this->installmentBatchIntent($issuer,$actor,$this->installmentBatchSource($issuer,$actor,$group)));
        if($existing)return $existing['plan']+['reserved'=>true];
        foreach($source[5] as $remote=>$member)$this->observeInstallment($issuer,$actor,$group,$remote,$decrypt,$transport);
        return $this->transaction(function()use($issuer,$actor,$group){
            $source=$this->installmentBatchSource($issuer,$actor,$group);$existing=$this->installmentBatchIntent($issuer,$actor,$source);if($existing)return $existing['plan']+['reserved'=>true];
            $selected=[];$excluded=[];
            foreach($source[5] as $remote=>$member){
                $origin=(int)$member['origin']['id'];$receipt=$this->rows('SELECT payment_id FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? FOR UPDATE',[$origin]);
                if($receipt){$excluded[]=['id'=>$remote,'reason'=>'received_local'];continue;}
                if($this->installmentRemovalLedger($origin)!==null){$excluded[]=['id'=>$remote,'reason'=>'individual_removal_intent'];continue;}
                $proof=$this->rows("SELECT result_json,evidence_hash FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='parcel_query' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$origin])[0]??null;
                if(!$proof)throw new \DomainException('asaas_company_remote_fresh_query_required');$safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
                if(!hash_equals($proof['evidence_hash'],self::hash($safe)))throw new \DomainException('asaas_installment_batch_conflict');
                if(in_array($safe['status']??'', ['RECEIVED','CONFIRMED'],true)){$excluded[]=['id'=>$remote,'reason'=>'received_remote'];continue;}
                $this->installmentRemovalProof($source,$remote,false);$selected[]=$member['member'];
            }
            if(!$selected)throw new \DomainException('asaas_installment_batch_empty');
            $plan=['issuer_id'=>$issuer,'actor_id'=>$actor,'group_origin'=>$group,'group_id'=>$source[0]['batch_id'],'invoice_id'=>(int)$source[0]['invoice_id'],'account_revision'=>$source[2]['revision'],'snapshot_hash'=>$source[1]['snapshot_hash'],'members'=>$selected,'excluded'=>$excluded];$plan['review_hash']=self::hash($plan);
            return $plan+['reserved'=>false];
        });
    }
    /** Freeze an approved set before dispatch. Retry reuses that exact set and each member journal. */
    public function removeInstallmentBatch(int $issuer,int $actor,int $group,string $approval,callable $decrypt,?callable $transport=null):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$approval))throw new \DomainException('asaas_company_invalid_input');
        $review=$this->reviewInstallmentRemoval($issuer,$actor,$group,$decrypt,$transport);
        if(!hash_equals($review['review_hash'],$approval))throw new \DomainException('asaas_legacy_review_changed');
        $intent=$this->transaction(function()use($issuer,$actor,$group,$review,$approval){
            $source=$this->installmentBatchSource($issuer,$actor,$group);$existing=$this->installmentBatchIntent($issuer,$actor,$source);if($existing){if($existing['plan']['review_hash']!==$approval)throw new \DomainException('asaas_legacy_review_changed');return $existing;}
            if($source[2]['revision']!==$review['account_revision'])throw new \DomainException('asaas_company_stale');
            foreach($review['members'] as $member){$this->installmentRemovalProof($source,$member['id'],false);if($this->installmentRemovalLedger((int)$source[5][$member['id']]['origin']['id'])!==null)throw new \DomainException('asaas_legacy_review_changed');}
            $plan=$review;unset($plan['reserved']);$hash=self::hash($plan);$now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$group,$source[2]['revision'],$actor,'group_remove',json_encode($plan,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,'group_removal',$now]);$id=(int)$this->db->insert_id();
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_group_removal',$issuer,$group,self::legacyAudit($id,$hash),$now]);
            return $this->installmentBatchIntent($issuer,$actor,$source);
        });
        $items=[];
        foreach($intent['plan']['members'] as $member){
            $remote=$member['id'];$old=$this->transaction(function()use($issuer,$actor,$group,$remote,$intent){$source=$this->installmentBatchSource($issuer,$actor,$group);$current=$this->installmentBatchIntent($issuer,$actor,$source);if($current!==$intent)throw new \DomainException('asaas_installment_batch_conflict');return $this->installmentRemovalLedger((int)$source[5][$remote]['origin']['id']);});
            try{
                if($old){$result=$old['state']==='sending'?$this->installmentRemovalPublic($old):$this->refreshInstallmentRemoval($issuer,$actor,$group,$remote,$decrypt,$transport);}
                else $result=$this->removeInstallment($issuer,$actor,$group,$remote,hash('sha256','installment-removal:'.$issuer.':'.$group.':'.$remote),$decrypt,$transport);
                $items[]=['id'=>$remote,'state'=>$result['state']];
            }catch(\DomainException $error){$items[]=['id'=>$remote,'state'=>'blocked'];}
        }
        return $this->transaction(function()use($issuer,$actor,$group,$intent,$items){
            $source=$this->installmentBatchSource($issuer,$actor,$group);if($this->installmentBatchIntent($issuer,$actor,$source)!==$intent)throw new \DomainException('asaas_installment_batch_conflict');
            $currentItems=[];foreach($items as $item){$op=$this->installmentRemovalLedger((int)$source[5][$item['id']]['origin']['id']);if($op&&(int)$op['issuer_id']!==$issuer)throw new \DomainException('asaas_installment_batch_conflict');$currentItems[]=['id'=>$item['id'],'state'=>$op?$op['state']:$item['state']];}
            $result=['intent_proof'=>$intent['proof_id'],'group_origin'=>$group,'items'=>$currentItems,'complete'=>!array_filter($currentItems,static fn($item)=>$item['state']!=='deleted')];
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$group,$source[2]['revision'],$actor,'group_result',json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),self::hash($result),'group_removal_result',gmdate('Y-m-d H:i:s')]);
            return $result;
        });
    }
}
