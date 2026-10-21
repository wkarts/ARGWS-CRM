<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPostRefundManual.php';
require_once __DIR__.'/CompanyPostRefundManualReversalEntries.php';

/** Exact authority for a local full reversal of the NEW manual receipt, never the Asaas receipt. */
final class CompanyPostRefundManualReversalSource
{
    public function __construct(private $db,private string$p,private int$i,private int$a,private int$id,private string$manualKey,private string$revision,private string$key,private array$entry,private string$evidence,private bool$confirmed)
    {
        foreach([$i,$a,$id]as$v)IssuerPolicy::id($v);
        foreach([$manualKey,$revision,$key,$evidence]as$v)if(!preg_match('/^[a-f0-9]{64}$/D',$v))throw new DomainException('company_post_refund_manual_reversal_invalid');
        if(!$confirmed||$evidence===str_repeat('0',64)||array_diff(array_keys($entry),['amount','date','reference','reason']))throw new DomainException('company_post_refund_manual_reversal_confirmation_required');
        foreach(['reference'=>191,'reason'=>1000]as$f=>$max){$v=$entry[$f]??null;if(!is_string($v)||trim($v)!==$v||strlen($v)<($f==='reason'?10:5)||strlen($v)>$max||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new DomainException('company_post_refund_manual_reversal_invalid');}
        $date=$entry['date']??null;$d=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;if(!$d||$d->format('Y-m-d')!==$date||$date>gmdate('Y-m-d'))throw new DomainException('company_post_refund_manual_reversal_invalid');
        if(!is_string($entry['amount']??null)||CompanyInvoiceAmounts::minor($entry['amount'])<1)throw new DomainException('company_post_refund_manual_reversal_invalid');
    }
    public function assertStorage($db,string$p):void{if($this->db!==$db||$this->p!==$p)throw new DomainException('company_post_refund_manual_reversal_source_denied');CompanyPostRefundManualReversalSchema::ensure($db,$p,false);}
    public function tables():array{return array_values(array_unique(array_merge((new CompanyPostRefundManual($this->db,$this->p))->tables(),['company_post_refund_manual_reversals','company_credit_entries','company_financial_events','issuers_audit'])));}
    public function requestKey():string{return $this->key;}
    public function input():array{return ['invoice_id'=>$this->id,'amount'=>$this->entry['amount'],'date'=>$this->entry['date'],'reference'=>$this->entry['reference'],'reason'=>$this->entry['reason'],'payment_id'=>$this->paymentId()];}
    private function paymentId():int{$r=IssuerStorage::query($this->db,'SELECT payment_id FROM '.$this->p.'company_post_refund_manual_receipts WHERE request_key=? AND issuer_id=? AND invoice_id=?',[$this->manualKey,$this->i,$this->id])->row_array();if(!$r)throw new DomainException('company_post_refund_manual_reversal_source_denied');return IssuerPolicy::id($r['payment_id']);}
    private function payload(array$input):array{return ['issuer_id'=>$this->i,'actor_id'=>$this->a,'invoice_id'=>$this->id,'manual_request_key'=>$this->manualKey,'revision'=>$this->revision,'request_key'=>$this->key,'entry'=>$input,'independent_evidence_hash'=>$this->evidence,'local_reversal_confirmed'=>true];}
    public function claim($db,string$p,int$i,int$a,array$input,string$key):array
    {
        $this->assertStorage($db,$p);if([$i,$a,$key]!==[$this->i,$this->a,$this->key]||$input!==['credit_id'=>null,'invoice_id'=>$this->id,'parent_key'=>null,'payment_id'=>$this->paymentId(),'amount'=>$this->entry['amount'],'date'=>$this->entry['date'],'reference'=>$this->entry['reference'],'reason'=>$this->entry['reason']])throw new DomainException('company_post_refund_manual_reversal_source_denied');
        IssuerTransaction::assertOwned($db,$p,$this->tables());
        $page=(new CompanyPostRefundManual($db,$p))->inOwned($i,$a,$this->id,true);
        if(!$page['manual_recorded']||$page['manual_request_key']!==$this->manualKey||$page['new_payment_id']!==$input['payment_id']||$page['declared_refund']!==$input['amount'])throw new DomainException('company_post_refund_manual_reversal_source_denied');
        $manual=IssuerStorage::query($db,'SELECT * FROM '.$p.'company_post_refund_manual_receipts WHERE request_key=? FOR UPDATE',[$this->manualKey])->row_array();$oldInput=json_decode($manual['input_json'],true,32,JSON_THROW_ON_ERROR);$receipt=$oldInput['receipt'];
        if($input['date']<$receipt['date']||$input['reference']===$receipt['reference']||in_array($this->evidence,[$oldInput['independent_evidence_hash'],$manual['input_hash'],$manual['record_hash']],true))throw new DomainException('company_post_refund_manual_reversal_independent_proof_required');
        $payments=IssuerStorage::query($db,'SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id FOR UPDATE',[$this->id])->result_array();$expected=[(int)$manual['original_payment_id'],(int)$manual['payment_id']];sort($expected);if(array_map('intval',array_column($payments,'id'))!==$expected||IssuerStorage::query($db,'SELECT id FROM '.$p.'credits WHERE invoice_id=? FOR UPDATE',[$this->id])->result_array())throw new DomainException('company_post_refund_other_funds');
        $posted=IssuerStorage::query($db,'SELECT payload_json FROM '.$p.'company_asaas_refund_postings WHERE request_key=? FOR UPDATE',[$manual['posting_key']])->row_array();$posted=$posted?json_decode($posted['payload_json'],true,32,JSON_THROW_ON_ERROR):[];
        if(in_array($this->evidence,array_intersect_key($posted,array_flip(['independent_evidence_hash','observation_hash','original_evidence_hash'])),true)||$input['reference']===($posted['external_reference']??null))throw new DomainException('company_post_refund_manual_reversal_independent_proof_required');
        $old=IssuerStorage::query($db,'SELECT * FROM '.$p.'company_post_refund_manual_reversals WHERE request_key=? OR payment_id=? FOR UPDATE',[$key,$input['payment_id']])->result_array();$payload=CompanyPostRefundManualReversalEntries::json($this->payload($input));
        if($old){if(count($old)!==1||$old[0]['request_key']!==$key||$old[0]['input_json']!==$payload||$page['manual_reversal']===null)throw new DomainException('company_post_refund_manual_reversal_conflict');}
        elseif($page['manual_reversal']!==null||!$page['can_reverse']||!hash_equals($this->revision,$page['revision']))throw new DomainException('company_invoice_stale');
        return ['invoice'=>IssuerStorage::query($db,'SELECT * FROM '.$p.'invoices WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$this->id])->row_array(),'manual'=>$manual,'payload'=>$payload,'old'=>(bool)$old];
    }
    public function applied($db,string$p,int$i,int$a,array$input,string$key,array$claim):void
    {
        if($claim['old'])return;
        $r=['request_key'=>$key,'issuer_id'=>$i,'invoice_id'=>$this->id,'payment_id'=>$input['payment_id'],'manual_request_key'=>$this->manualKey,'manual_record_hash'=>$claim['manual']['record_hash'],'input_json'=>$claim['payload'],'input_hash'=>hash('sha256',$claim['payload']),'created_by'=>$a,'created_at'=>gmdate('Y-m-d H:i:s')];$r['record_hash']=CompanyPostRefundManualReversalEntries::header($r);
        IssuerStorage::query($db,'INSERT INTO '.$p.'company_post_refund_manual_reversals (`'.implode('`,`',array_keys($r)).'`) VALUES ('.implode(',',array_fill(0,count($r),'?')).')',array_values($r));
        if(!$this->claim($db,$p,$i,$a,$input,$key)['old'])throw new RuntimeException('issuer_post_refund_manual_reversal_unconfirmed');
    }
}
