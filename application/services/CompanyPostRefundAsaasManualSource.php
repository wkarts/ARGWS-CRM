<?php
declare(strict_types=1);
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyRefundStorage.php';
require_once __DIR__.'/CompanyPostRefundManualSchema.php';
require_once __DIR__.'/CompanyAsaasRefundPostings.php';
/** Closed authority for one NEW independent full manual receipt, never provider re-credit. */
final class CompanyPostRefundAsaasManualSource extends \Asaas\CompanyRefundStorage
{
    public function __construct($db,string $p,private int $issuer,private int $actor,private int $invoice,private string $refundRequest,private int $observation,private string $revision,private string $key,private array $receipt,private string $evidenceHash,private string $reason,private bool $confirmed)
    {
        parent::__construct($db,$p);
        foreach([$issuer,$actor,$invoice,$observation]as$id)IssuerPolicy::id($id);
        foreach([$refundRequest,$revision,$key,$evidenceHash]as$h)self::key($h);
        if($evidenceHash===str_repeat('0',64))throw new DomainException('company_post_refund_manual_independent_proof_required');
        if(!$confirmed||array_diff(array_keys($receipt),['amount','date','reference','note'])||!is_string($receipt['amount']??null)||!is_string($receipt['reference']??null)||trim($receipt['reference'])!==$receipt['reference']||strlen($receipt['reference'])<5||!is_string($receipt['note']??''))throw new DomainException('company_post_refund_manual_confirmation_required');
        if(trim($reason)!==$reason||strlen($reason)<10||strlen($reason)>1000||preg_match('//u',$reason)!==1||preg_match('/[\x00-\x1f\x7f]/',$reason))throw new DomainException('company_post_refund_manual_confirmation_required');
        $date=$receipt['date']??null;$dt=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!$dt||$dt->format('Y-m-d')!==$date||$date>gmdate('Y-m-d'))throw new DomainException('company_post_refund_manual_date_required');
    }
    public function assertStorage($db,string$p):void
    {
        if($this->db!==$db||$this->p!==$p)throw new DomainException('company_post_refund_manual_source_denied');
        $v=$this->rows('SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($v,'1.5.3','<'))throw new DomainException('company_post_refund_module_required');
        CompanyPostRefundManualSchema::ensure($db,$p,false);
    }
    public function tables():array{return array_values(array_unique(array_merge(parent::tables(),(new CompanyAsaasRefundPostings($this->db,$this->p))->tables(true),['company_post_refund_manual_receipts','company_invoice_requests','itemable','item_tax'])));}
    private function input():array{return ['issuer_id'=>$this->issuer,'actor_id'=>$this->actor,'invoice_id'=>$this->invoice,'refund_request'=>$this->refundRequest,'observation_id'=>$this->observation,'revision'=>$this->revision,'request_key'=>$this->key,'receipt'=>$this->receipt,'independent_evidence_hash'=>$this->evidenceHash,'reason'=>$this->reason,'new_manual_confirmed'=>true];}
    private static function json(array$v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    public static function receiptHeader(array$r):string{return hash('sha256',self::json([$r['request_key'],(int)$r['issuer_id'],(int)$r['invoice_id'],(int)$r['original_payment_id'],(int)$r['payment_id'],$r['input_hash'],$r['external_reference_key'],$r['posting_key'],$r['account_revision'],$r['grant_revision'],(int)$r['created_by'],$r['created_at']]));}
    public static function referenceKey(int$i,string$reference):string{return hash('sha256',self::json(['post-refund-independent-manual',$i,$reference]));}
    /** Live authority and original financial proof under the canonical mutex; no network or DDL. */
    public function claim($db,string$p,int$i,int$a,int$writtenPayment=0):array
    {
        $this->assertStorage($db,$p);if([$i,$a]!==[$this->issuer,$this->actor])throw new DomainException('company_post_refund_manual_source_denied');
        IssuerTransaction::assertOwned($db,$p,$this->tables());$acl=new IssuerResourceAccess($db,$p);$acl->lockFinancialIdentity($i,$a);$this->auth($i,$a,true);
        if(!$acl->can($i,$a,'invoices','view')||!$acl->can($i,$a,'payments','create'))throw new DomainException('company_payment_access_denied');
        $r=$this->requestRow($i,$this->refundRequest);[$s,$account]=$this->assertIntentCurrent($i,$a,$r,false);$grant=$this->grant($i,$a,(int)$account['id']);$id=$this->invoice;
        if((int)$s['invoice_id']!==$id||$s['payment_id']===null)throw new DomainException('company_post_refund_manual_source_denied');
        $store=new CompanyAsaasRefundPostings($db,$p);$d=$store->forInvoiceInOwned($i,$a,$id)[(int)$s['payment_id']]??null;
        if(!$d||$d['refund_request_key']!==$this->refundRequest||$d['origin_id']!==(int)$s['origin_id']||$d['account_row']!==(int)$account['id']||$d['remote_id']!==$s['remote_id'])throw new DomainException('company_post_refund_full_posting_required');
        if(in_array($this->evidenceHash,[$d['independent_evidence_hash'],$d['observation_hash'],$d['original_evidence_hash']],true))throw new DomainException('company_post_refund_manual_independent_proof_required');
        if($this->receipt['amount']!==$d['returned_paid'])throw new DomainException('company_post_refund_manual_full_amount_required');
        if($this->receipt['date']<$d['book_date'])throw new DomainException('company_post_refund_manual_date_required');
        $original=$this->rows('SELECT transactionid FROM '.$p.'invoicepaymentrecords WHERE id=? AND invoiceid=? FOR UPDATE',[$s['payment_id'],$id])[0]??null;
        if(!$original||$this->receipt['reference']===$original['transactionid']||$this->receipt['reference']===$s['remote_id']||$this->receipt['reference']===$d['external_reference'])throw new DomainException('company_post_refund_manual_independent_reference_required');
        $old=$this->rows('SELECT * FROM '.$p.'company_post_refund_manual_receipts WHERE request_key=? OR (issuer_id=? AND invoice_id=?) FOR UPDATE',[$this->key,$i,$id]);
        if(count($old)>1)throw new DomainException('company_post_refund_manual_conflict');$old=$old[0]??null;$input=self::json($this->input());$hash=hash('sha256',$input);$postingKey=CompanyAsaasRefundPostings::key($i,(int)$s['payment_id'],$s['receipt_key']);
        if($old&&($old['request_key']!==$this->key||$old['input_json']!==$input||!hash_equals($old['input_hash'],$hash)||(int)$old['issuer_id']!==$i||(int)$old['invoice_id']!==$id||(int)$old['original_payment_id']!==(int)$s['payment_id']||(int)$old['created_by']!==$a||$old['posting_key']!==$postingKey||$old['account_revision']!==$account['revision']||$old['grant_revision']!==$grant['revision']||!hash_equals($old['record_hash'],self::receiptHeader($old))))throw new DomainException('company_post_refund_manual_conflict');
        if($old&&$old['external_reference_key']!==self::referenceKey($i,$this->receipt['reference']))throw new DomainException('company_post_refund_manual_conflict');
        $referenceRows=$this->rows('SELECT request_key FROM '.$p.'company_post_refund_manual_receipts WHERE external_reference_key=? FOR UPDATE',[self::referenceKey($i,$this->receipt['reference'])]);if($referenceRows&&(!$old||count($referenceRows)!==1||$referenceRows[0]['request_key']!==$this->key))throw new DomainException('company_payment_reference_conflict');
        $newPayment=$old?(int)$old['payment_id']:$writtenPayment;
        if($writtenPayment&&$newPayment!==$writtenPayment)throw new DomainException('company_post_refund_manual_conflict');
        if($old){$metadata=self::json(['invoice_id'=>$id,'request_key'=>$this->key,'input_hash'=>$hash,'posting_key'=>$postingKey,'payment_id'=>$newPayment]);if(count($this->rows('SELECT id FROM '.$p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=? FOR UPDATE',[$i,$a,$s['origin_id'],'company_post_refund_manual_recorded',$metadata]))!==1)throw new DomainException('company_post_refund_manual_conflict');}
        $parent=$this->rows('SELECT status FROM '.$p.'invoices WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$id])[0]??null;
        if(!$parent||!in_array((int)$parent['status'],$newPayment?[2]:[1,4],true))throw new DomainException('company_post_refund_manual_invalid_state');
        if(!$newPayment&&!hash_equals($this->revision,(new CompanyInvoices($db,$p))->revision($i,$a,$id)))throw new DomainException('company_invoice_stale');
        if(!$old){
            $proof=$this->rows('SELECT * FROM '.$p.'asaas_company_refund_observations WHERE id=? AND issuer_id=? AND request_key=? FOR UPDATE',[$this->observation,$i,$this->refundRequest])[0]??null;
            if(!$proof||(int)$r['latest_observation']!==$this->observation||$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300)||$proof['created_at']>gmdate('Y-m-d H:i:s'))throw new DomainException('company_post_refund_fresh_query_required');
            $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);if(($safe['status']??null)!=='REFUNDED')throw new DomainException('company_post_refund_final_refunded_required');
            $fresh=$store->originalForStaffInOwned($i,$a,$id,(int)$s['payment_id'],$this->refundRequest,$this->observation);if($fresh['scope_hash']!==$d['scope_hash']||$fresh['amount']!==$d['returned_paid']||$fresh['remote_id']!==$d['remote_id'])throw new DomainException('company_post_refund_manual_source_denied');
        }
        $origins=$this->rows('SELECT id,state,last_code FROM '.$p.'asaas_payment_origins WHERE invoice_id=? FOR UPDATE',[$id]);
        if(count($origins)!==1||(int)$origins[0]['id']!==(int)$s['origin_id']||!in_array($origins[0]['state'],['issued','received_observed','review_required'],true))throw new DomainException('company_post_refund_adverse_history');
        $refundEvent=false;
        foreach($this->rows('SELECT event_type,state,created_at FROM '.$p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$account['id'],$s['remote_id']])as$e){
            if(!in_array($e['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_CONFIRMED','PAYMENT_RECEIVED','PAYMENT_OVERDUE','PAYMENT_REFUNDED','PAYMENT_REFUND_IN_PROGRESS'],true)||!in_array($e['state'],['observed','review_required'],true)||($e['state']==='review_required'&&!in_array($e['event_type'],['PAYMENT_REFUNDED','PAYMENT_REFUND_IN_PROGRESS'],true)))throw new DomainException('company_post_refund_adverse_history');
            if(!$old&&$e['created_at']>=$proof['created_at'])throw new DomainException('company_post_refund_fresh_query_required');
            if(in_array($e['event_type'],['PAYMENT_REFUNDED','PAYMENT_REFUND_IN_PROGRESS'],true))$refundEvent=true;
        }
        if($origins[0]['state']==='review_required'&&(!$refundEvent||$origins[0]['last_code']!=='company_adverse_event'))throw new DomainException('company_post_refund_adverse_history');
        foreach(['bb_origins'=>['invoice_id',$id],'asaas_company_remote_operations'=>['origin_id',$s['origin_id']]]as$t=>[$col,$v])if($db->table_exists($p.$t)&&$this->rows('SELECT '.$col.' FROM '.$p.$t.' WHERE '.$col.'=? FOR UPDATE',[$v]))throw new DomainException('company_post_refund_adverse_history');
        if($this->rows("SELECT event_id FROM {$p}asaas_company_jobs WHERE origin_id=? AND state IN ('pending','processing') FOR UPDATE",[$s['origin_id']]))throw new DomainException('company_post_refund_pending_operation');
        $payments=$this->rows('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id FOR UPDATE',[$id]);$expected=[(int)$s['payment_id']];if($newPayment)$expected[]=$newPayment;sort($expected);
        if(array_map('intval',array_column($payments,'id'))!==$expected||$this->rows('SELECT id FROM '.$p.'credits WHERE invoice_id=? FOR UPDATE',[$id]))throw new DomainException('company_post_refund_other_funds');
        $pos=(new CompanyInvoiceBalance($db,$p))->inOwned($i,$a,$id);$paid=$newPayment?$d['returned_paid']:'0.00';
        if($pos['received']!==$paid||$pos['applied_credit']!=='0.00'||$pos['noncash_reductions']!=='0.00'||$pos['receivable']!==($newPayment?'0.00':$pos['total'])||$pos['received_external_reversed']!==$d['returned_paid']||CompanyInvoiceAmounts::minor($pos['received_original'])!==CompanyInvoiceAmounts::minor($d['returned_paid'])+CompanyInvoiceAmounts::minor($paid))throw new DomainException('company_post_refund_other_funds');
        if($newPayment){$native=$this->rows('SELECT p.*,r.request_key,r.actor_id,r.input_hash FROM '.$p.'invoicepaymentrecords p JOIN '.$p.'company_payment_receipts r ON r.payment_id=p.id AND r.invoice_id=p.invoiceid AND r.issuer_id=? WHERE p.id=? AND p.invoiceid=? FOR UPDATE',[$i,$newPayment,$id])[0]??null;
            $payload=['invoiceid'=>$id,'amount'=>$this->receipt['amount'],'paymentmode'=>'company_manual','paymentmethod'=>'Recebimento manual','date'=>$this->receipt['date'],'transactionid'=>$this->receipt['reference'],'note'=>trim($this->receipt['note']??'')];
            if(!$native||$native['request_key']!==$this->key||(int)$native['actor_id']!==$a||!hash_equals($native['input_hash'],hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR))))throw new DomainException('company_post_refund_manual_conflict');
            foreach($payload as$f=>$v)if($f==='invoiceid'?(int)$native[$f]!==$v:($f==='amount'?CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor((string)$native[$f]))!==$v:$native[$f]!==$v))throw new DomainException('company_post_refund_manual_conflict');
        }elseif($this->rows('SELECT request_key FROM '.$p.'company_payment_receipts WHERE request_key=? FOR UPDATE',[$this->key]))throw new DomainException('company_post_refund_manual_conflict');
        return ['invoice'=>$id,'key'=>$this->key,'input'=>$this->receipt,'payment'=>$old?(int)$old['payment_id']:null,'posting_key'=>$postingKey,'original_payment'=>(int)$s['payment_id'],'account_revision'=>$account['revision'],'grant_revision'=>$grant['revision'],'origin_id'=>(int)$s['origin_id']];
    }
    public function applied($db,string$p,int$i,int$a,int$payment):void
    {
        $c=$this->claim($db,$p,$i,$a,$payment);if($c['payment']!==null)return;
        $input=self::json($this->input());$row=['request_key'=>$this->key,'issuer_id'=>$i,'invoice_id'=>$this->invoice,'original_payment_id'=>$c['original_payment'],'payment_id'=>$payment,'input_json'=>$input,'input_hash'=>hash('sha256',$input),'external_reference_key'=>self::referenceKey($i,$this->receipt['reference']),'posting_key'=>$c['posting_key'],'account_revision'=>$c['account_revision'],'grant_revision'=>$c['grant_revision'],'created_by'=>$a,'created_at'=>gmdate('Y-m-d H:i:s')];$row['record_hash']=self::receiptHeader($row);
        $this->insert('company_post_refund_manual_receipts',$row);$this->audit($i,$a,$c['origin_id'],'company_post_refund_manual_recorded',['invoice_id'=>$this->invoice,'request_key'=>$this->key,'input_hash'=>$row['input_hash'],'posting_key'=>$row['posting_key'],'payment_id'=>$payment]);
        if($this->claim($db,$p,$i,$a,$payment)['payment']!==$payment)throw new RuntimeException('issuer_post_refund_manual_unconfirmed');
    }
}
