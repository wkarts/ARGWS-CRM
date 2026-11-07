<?php
declare(strict_types=1);
namespace Asaas;
/** Explicit reviewed recovery; no creation, update or settlement at the provider. */
trait CompanyLegacyBinding
{
    private function legacyTransaction(callable $work)
    {return $this->transaction($work,$this->db->table_exists($this->p.'asaas_payments_map')?['asaas_payments_map']:[]);}
    private function legacyAccess(int $issuer,int $actor):void
    {
        $this->authorize($issuer,$actor,'configure');$this->authorize($issuer,$actor,'issue');
        if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
        $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($version,'1.7.7','<'))throw new \DomainException('asaas_module_177_required');
    }
    /** The historical approval is separate from subsequent canonical observations. */
    private function legacyMeta(array $row,array $meta,bool $allow):array
    {
        $proofs=$this->rows("SELECT * FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='legacy_bind' ORDER BY id FOR UPDATE",[$row['id']]);
        $audits=$this->rows("SELECT * FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_asaas_legacy_bound' FOR UPDATE",[$row['issuer_id'],$row['id']]);
        if(!$proofs&&!$audits)return $meta;
        if(!$allow)throw new \DomainException('asaas_legacy_explicit_review_required');
        $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($version,'1.7.7','<'))throw new \DomainException('asaas_module_177_required');
        if(count($proofs)!==1||count($audits)!==1)throw new \DomainException('asaas_legacy_binding_conflict');
        $proof=$proofs[0];$safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);$binding=$safe['legacy_binding']??[];
        if(!hash_equals($proof['evidence_hash'],self::hash($safe))||$proof['state']!=='legacy_bound'||($binding['origin_id']??null)!==(int)$row['id']||($binding['issuer_id']??null)!==(int)$row['issuer_id']||($binding['account_row']??null)!==(int)$meta['account_row']||($binding['snapshot_hash']??null)!==$meta['snapshot_hash']||($binding['review_hash']??null)!==$meta['request_key']||($safe['id']??null)!==$row['remote_id']||($safe['external_reference']??null)!==$row['invoice_hash']||($safe['due_date']??null)!==$row['invoice_due_date']||(int)$meta['created_by']!==(int)$proof['actor_id']||($safe['customer']??null)!==$meta['customer_id']||($safe['billing_type']??null)!==$meta['billing_type']||(int)$audits[0]['actor_id']!==(int)$proof['actor_id']||($audits[0]['metadata_json']??null)!==self::legacyAudit((int)$proof['id'],$proof['evidence_hash']))throw new \DomainException('asaas_legacy_binding_conflict');
        CompanyOriginPolicy::assertManual($this->db,$this->p,(int)$row['id'],(int)$row['issuer_id'],$meta['request_key']);
        $amount=self::money($safe['value']??null);if(\CompanyInvoiceAmounts::minor($amount)<1||\CompanyInvoiceAmounts::minor($amount)>\CompanyInvoiceAmounts::minor((string)$row['invoice_total']))throw new \DomainException('asaas_legacy_binding_conflict');
        $meta['reviewed_amount']=$amount;$meta['legacy_reference']=$meta['request_key'];return $meta;
    }
    /** Capability gate only; callers must still prove their own remote/financial authority. */
    private function legacyLifecycleVersion(array $meta):void
    {
        if(!isset($meta['legacy_reference']))return;
        $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($version,'1.7.8','<'))throw new \DomainException('asaas_module_178_required');
    }
    private static function legacyAudit(int $proof,string $hash):string{return json_encode(['proof_id'=>$proof,'evidence_hash'=>$hash],JSON_THROW_ON_ERROR);}
    public function legacyReferenceInOwned(int $issuer,int $actor,int $origin,?string $editKey=null):?string
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,self::tables());$this->authorize($issuer,$actor,'configuration');
        [$row,$meta]=$this->source($issuer,$origin,$editKey,true);
        if(isset($meta['legacy_reference']))return $meta['legacy_reference'];
        if(!CompanyOriginPolicy::isManual($this->db,$this->p,$origin)||!$this->db->table_exists($this->p.'asaas_company_recreations'))return null;
        $links=$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_recreations WHERE successor_origin_id=? FOR UPDATE',[$origin]);
        if(!$links)return null; // Other explicit integrations do not gain monitor authority.
        $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($version,'1.7.9','<'))throw new \DomainException('asaas_module_179_required');
        CompanyOriginGenerations::postRefundOriginInOwned($this->db,$this->p,$issuer,(int)$row['invoice_id'],$origin);
        $cursor=$origin;$seen=[];
        while(true){
            if(isset($seen[$cursor]))throw new \DomainException('asaas_legacy_binding_conflict');$seen[$cursor]=true;
            $old=$this->rows('SELECT * FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$cursor,$issuer])[0]??null;
            $oldMeta=$this->rows('SELECT * FROM '.$this->p.'asaas_company_origin_meta WHERE origin_id=? FOR UPDATE',[$cursor])[0]??null;
            if(!$old||!$oldMeta)throw new \DomainException('asaas_legacy_binding_conflict');
            CompanyOriginPolicy::assertManual($this->db,$this->p,$cursor,$issuer,$oldMeta['request_key']);
            $ancestors=$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_recreations WHERE successor_origin_id=? FOR UPDATE',[$cursor]);
            if(!$ancestors){$root=$this->legacyMeta($old,$oldMeta,true);if(!isset($root['legacy_reference'])||self::money($root['reviewed_amount'])!==self::money($old['invoice_total']))throw new \DomainException('asaas_legacy_recreation_allocation_required');break;}
            if(count($ancestors)!==1)throw new \DomainException('asaas_legacy_binding_conflict');$cursor=(int)$ancestors[0]['origin_id'];
        }
        return $meta['request_key'];
    }
    /** Read-only group review. Its digest never authorizes single-charge binding or settlement. */
    public function reviewLegacyInstallments($issuer,$actor,$invoice,$account,string $remote,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$invoice=\IssuerPolicy::id($invoice);$account=\IssuerPolicy::id($account);$remote=self::opaque($remote,64);
        [$config,$parent,$balance]=$this->legacyTransaction(fn()=>$this->legacyLocal($issuer,$actor,$invoice,$account,$remote));
        $guard=function()use($issuer,$actor,$invoice,$account,$remote,$config,$parent,$balance):void{
            [$now,$current,$position]=$this->legacyLocal($issuer,$actor,$invoice,$account,$remote);
            $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
            if(version_compare($version,'1.8.1','<'))throw new \DomainException('asaas_module_181_required');
            if($now['revision']!==$config['revision']||self::snapshot($current)!==self::snapshot($parent)||$position!==$balance)throw new \DomainException('asaas_legacy_review_changed');
        };
        $this->legacyTransaction($guard);$client=$this->client($config,$decrypt,$transport);
        $get=function(string $path)use($client,$guard):array{$this->legacyTransaction($guard);return $client->request('GET',$path);};
        self::wallet($client,$config);$member=$get('payments/'.$remote);
        $groupId=self::opaque($member['installment']??null,120);$customer=self::opaque($member['customer']??null,64);
        $group=$get('installments/'.$groupId);$count=$group['installmentCount']??null;
        if(($member['id']??null)!==$remote||($group['id']??null)!==$groupId||($group['customer']??null)!==$customer||($group['billingType']??null)!==($member['billingType']??null)||!in_array($group['billingType']??null,['PIX','BOLETO'],true)||($group['deleted']??false)!==false||!is_int($count)||$count<2||$count>100)throw new \DomainException('asaas_installment_review_invalid');
        $total=self::money($group['value']??null);$totalMinor=\CompanyInvoiceAmounts::minor($total);
        if($totalMinor<1||$totalMinor>\CompanyInvoiceAmounts::minor($balance['receivable']))throw new \DomainException('asaas_legacy_amount_review');
        $this->legacyTransaction($guard);self::customer($client,$customer,$parent);
        $page=$get('installments/'.$groupId.'/payments');
        if(!is_array($page['data']??null)||!array_is_list($page['data'])||count($page['data'])!==$count||($page['hasMore']??null)!==false||($page['totalCount']??null)!==$count)throw new \DomainException('asaas_installment_review_incomplete');
        $members=[];$ids=[];$sum=0;
        foreach($page['data'] as $parcel){
            if(!is_array($parcel))throw new \DomainException('asaas_installment_review_invalid');
            $id=self::opaque($parcel['id']??null,64);$number=$parcel['installmentNumber']??null;
            if(($parcel['installment']??null)!==$groupId||($parcel['customer']??null)!==$customer||($parcel['billingType']??null)!==$group['billingType']||!is_int($number)||$number<1||$number>$count||isset($members[$number])||isset($ids[$id]))throw new \DomainException('asaas_installment_review_invalid');
            $amount=self::money($parcel['value']??null);$minor=\CompanyInvoiceAmounts::minor($amount);
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$parcel['dueDate']??'');
            if($minor<1||$minor>$totalMinor-$sum||!$date||$date->format('Y-m-d')!==$parcel['dueDate'])throw new \DomainException('asaas_installment_review_invalid');
            $local=$parent;$local['duedate']=$parcel['dueDate'];$candidate=$parcel;unset($candidate['installment']);
            $safe=self::normalize($candidate,['remote_id'=>$id],['customer_id'=>$customer,'billing_type'=>$group['billingType'],'reviewed_amount'=>$amount],$local,$id);
            if($safe['deleted']||$safe['adverse']||!in_array($safe['status'],['PENDING','OVERDUE','RECEIVED'],true))throw new \DomainException('asaas_legacy_payment_review');
            if($id===$remote){$original=$member;unset($original['installment']);$originalSafe=self::normalize($original,['remote_id'=>$id],['customer_id'=>$customer,'billing_type'=>$group['billingType'],'reviewed_amount'=>$amount],$local,$id);if(($member['installmentNumber']??null)!==$number||$originalSafe!==$safe)throw new \DomainException('asaas_legacy_review_changed');}
            $ids[$id]=true;$sum+=$minor;$members[$number]=['number'=>$number]+$safe;
        }
        if($sum!==$totalMinor||!isset($ids[$remote]))throw new \DomainException('asaas_installment_review_incomplete');
        ksort($members);$result=['bound'=>false,'read_only'=>true,'issuer_id'=>$issuer,'invoice_id'=>$invoice,'account_row'=>$account,'account_id'=>$config['account_id'],'environment'=>$config['environment'],'group_id'=>$groupId,'amount'=>$total,'receivable'=>$balance['receivable'],'billing_type'=>$group['billingType'],'members'=>array_values($members)];
        $result['review_hash']=self::hash(['installment_review_v1',$actor,$config['revision'],self::snapshot($parent),$balance,$result]);
        return $this->legacyTransaction(function()use($guard,$result){$guard();return $result;});
    }
    private function legacyLocal(int $issuer,int $actor,int $invoice,int $account,string $remote):array
    {
        $this->legacyAccess($issuer,$actor);$config=$this->account($issuer,$account);
        if(!(int)$config['enabled']||$config['verified_at']===null)throw new \DomainException('asaas_company_account_unverified');
        $parent=$this->parent($issuer,$invoice);$balance=(new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,$invoice);
        if(!in_array((int)$parent['status'],[1,3,4],true)||\CompanyInvoiceAmounts::minor($balance['receivable'])<1||count($this->rows('SELECT id FROM '.$this->p.'invoices WHERE hash=? FOR UPDATE',[$parent['hash']]))!==1)throw new \DomainException('asaas_legacy_invoice_review');
        foreach(['asaas_payment_origins'=>'invoice_id','bb_origins'=>'invoice_id','efi_company_origins'=>'invoice_id'] as $table=>$column)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT id FROM '.$this->p.$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice]))throw new \DomainException('asaas_legacy_origin_exists');
        if($this->db->table_exists($this->p.'asaas_payments_map')){
            \IssuerTransaction::assertOwned($this->db,$this->p,['asaas_payments_map']);
            foreach($this->rows('SELECT invoice_id,asaas_payment_id FROM '.$this->p.'asaas_payments_map WHERE invoice_id=? OR asaas_payment_id=? FOR UPDATE',[$invoice,$remote]) as $map)if((int)$map['invoice_id']!==$invoice||$map['asaas_payment_id']!==$remote)throw new \DomainException('asaas_legacy_map_conflict');
        }
        return [$config,$parent,$balance];
    }
    private function legacyReplayInOwned(int $issuer,int $actor,int $invoice,int $account,string $remote,string $approval):?array
    {
        $this->legacyAccess($issuer,$actor);$found=$this->rows('SELECT o.id,o.invoice_id,o.issuer_id,o.remote_id,m.account_row,m.created_by FROM '.$this->p.'asaas_company_origin_meta m JOIN '.$this->p.'asaas_payment_origins o ON o.id=m.origin_id WHERE m.request_key=? FOR UPDATE',[$approval]);
        if(!$found)return null;$row=$found[0];if((int)$row['issuer_id']!==$issuer||(int)$row['invoice_id']!==$invoice||(int)$row['account_row']!==$account||(int)$row['created_by']!==$actor||$row['remote_id']!==$remote||$this->legacyReferenceInOwned($issuer,$actor,(int)$row['id'])!==$approval)throw new \DomainException('asaas_legacy_review_changed');return ['origin_id'=>(int)$row['id'],'bound'=>true];
    }
    /** Empty approval performs preview; a matching explicit approval creates only an immutable local binding. */
    public function reviewLegacyBinding($issuer,$actor,$invoice,$account,string $remote,callable $decrypt,?callable $transport=null,string $approval=''):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$invoice=\IssuerPolicy::id($invoice);$account=\IssuerPolicy::id($account);$remote=self::opaque($remote,64);
        if($approval!==''&&!preg_match('/^[a-f0-9]{64}$/D',$approval))throw new \DomainException('asaas_legacy_review_changed');
        CompanyOriginPolicy::requireReady($this->db,$this->p);
        if($approval!==''){$old=$this->legacyTransaction(fn()=>$this->legacyReplayInOwned($issuer,$actor,$invoice,$account,$remote,$approval));if($old!==null)return $old;}
        try{[$config,$parent,$balance]=$this->legacyTransaction(fn()=>$this->legacyLocal($issuer,$actor,$invoice,$account,$remote));}
        catch(\DomainException $failure){if($approval!==''&&($old=$this->legacyTransaction(fn()=>$this->legacyReplayInOwned($issuer,$actor,$invoice,$account,$remote,$approval))))return $old;throw $failure;}
        $guard=function()use($issuer,$actor,$invoice,$account,$remote,$config,$parent,$balance,$approval):void{if($approval!==''&&$this->legacyReplayInOwned($issuer,$actor,$invoice,$account,$remote,$approval)!==null)return;[$now,$current,$position]=$this->legacyLocal($issuer,$actor,$invoice,$account,$remote);if($now['revision']!==$config['revision']||self::snapshot($current)!==self::snapshot($parent)||$position!==$balance)throw new \DomainException('asaas_legacy_review_changed');};
        $client=$this->client($config,$decrypt,$transport);self::wallet($client,$config);$this->legacyTransaction($guard);
        // Exhaust the bounded reference result; never select the first of ambiguous candidates.
        $candidates=[];$offset=0;$expectedTotal=null;
        do{
            $this->legacyTransaction($guard);$page=$client->request('GET','payments?externalReference='.rawurlencode($parent['hash']).'&limit=100&offset='.$offset);
            if(!is_array($page['data']??null)||!array_is_list($page['data'])||count($page['data'])>100||!is_bool($page['hasMore']??null)||($page['hasMore']&&count($page['data'])!==100))throw new \DomainException('asaas_legacy_response_invalid');
            $total=$page['totalCount']??null;if((!is_int($total)&&!is_string($total))||!preg_match('/^(?:0|[1-9][0-9]{0,5})$/D',(string)$total))throw new \DomainException('asaas_legacy_response_invalid');$total=(int)$total;
            if($expectedTotal!==null&&$total!==$expectedTotal)throw new \DomainException('asaas_legacy_reference_ambiguous');$expectedTotal=$total;
            if($total>1000||$total<$offset+count($page['data'])||($page['hasMore']?($total<=$offset+count($page['data'])):($total!==$offset+count($page['data']))))throw new \DomainException('asaas_legacy_response_invalid');
            foreach($page['data'] as $candidate){if(!is_array($candidate)||($candidate['externalReference']??null)!==$parent['hash']||!is_string($candidate['id']??null)||isset($candidates[$candidate['id']]))throw new \DomainException('asaas_legacy_response_invalid');$candidates[$candidate['id']]=$candidate;}
            $offset+=count($page['data']);if($page['hasMore']&&$offset>=1000)throw new \DomainException('asaas_legacy_reference_ambiguous');
        }while($page['hasMore']);
        // Even deleted/retired candidates require manual history review in this first adapter.
        if(count($candidates)!==1||!isset($candidates[$remote]))throw new \DomainException('asaas_legacy_reference_ambiguous');
        $this->legacyTransaction($guard);$response=$client->request('GET','payments/'.$remote);
        $customer=self::opaque($response['customer']??null,64);$billing=$response['billingType']??null;$amount=self::money($response['value']??null);
        if(!in_array($billing,['PIX','BOLETO'],true)||\CompanyInvoiceAmounts::minor($amount)<1||\CompanyInvoiceAmounts::minor($amount)>\CompanyInvoiceAmounts::minor($balance['receivable']))throw new \DomainException('asaas_legacy_amount_review');
        $this->legacyTransaction($guard);self::customer($client,$customer,$parent);
        $meta=['customer_id'=>$customer,'billing_type'=>$billing,'reviewed_amount'=>$amount];$safe=self::normalize($response,['remote_id'=>$remote],$meta,$parent,$remote);
        if($safe['adverse']||$safe['deleted']||!in_array($safe['status'],['PENDING','OVERDUE','RECEIVED'],true))throw new \DomainException('asaas_legacy_payment_review');
        $review=self::hash(['legacy_binding_v1',$issuer,$actor,$account,$config['revision'],self::snapshot($parent),$balance,$safe]);
        $result=['bound'=>false,'issuer_id'=>$issuer,'account_id'=>$config['account_id'],'environment'=>$config['environment'],'invoice_id'=>$invoice,'account_row'=>$account,'remote_id'=>$remote,'amount'=>$amount,'receivable'=>$balance['receivable'],'billing_type'=>$billing,'status'=>$safe['status'],'review_hash'=>$review];
        return $this->legacyTransaction(function()use($guard,$approval,$review,$result,$issuer,$actor,$invoice,$account,$config,$parent,$customer,$billing,$remote,$safe):array{
            if($approval!==''){if($replay=$this->legacyReplayInOwned($issuer,$actor,$invoice,$account,$remote,$approval))return $replay;}
            $guard();if($approval==='')return $result;if(!hash_equals($review,$approval))throw new \DomainException('asaas_legacy_review_changed');
            if($this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE account_id=? AND environment=? AND remote_id=? FOR UPDATE',[$config['account_id'],$config['environment'],$remote]))throw new \DomainException('asaas_legacy_origin_exists');
            $now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_payment_origins(invoice_id,invoice_hash,invoice_total,invoice_due_date,issuer_id,provider,account_id,environment,channel,remote_id,batch_id,state,last_code,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,NULL,?,?,?,?)',[$invoice,$parent['hash'],$parent['total'],$parent['duedate'],$issuer,'asaas',$config['account_id'],$config['environment'],'api',$remote,'issued','legacy_reviewed_binding',$now,$now]);$origin=(int)$this->db->insert_id();
            $snapshot=self::snapshot($parent);$snapshotHash=self::hash($snapshot);\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_origin_meta(origin_id,account_row,created_by,request_key,snapshot_hash,snapshot_json,customer_id,billing_type,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$origin,$account,$actor,$approval,$snapshotHash,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$customer,$billing,$now]);
            CompanyOriginPolicy::markManual($this->db,$this->p,$origin,$issuer,$approval);
            $safe['legacy_binding']=['origin_id'=>$origin,'issuer_id'=>$issuer,'account_row'=>$account,'snapshot_hash'=>$snapshotHash,'review_hash'=>$approval];$hash=self::hash($safe);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$config['revision'],$actor,'legacy_bind',json_encode($safe,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$hash,'legacy_bound',$now]);$proof=(int)$this->db->insert_id();
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$actor,'company_asaas_legacy_bound',$issuer,$origin,self::legacyAudit($proof,$hash),$now]);
            if($this->legacyReferenceInOwned($issuer,$actor,$origin)!==$approval)throw new \RuntimeException('asaas_legacy_binding_unconfirmed');return ['origin_id'=>$origin,'bound'=>true];
        });
    }
}
