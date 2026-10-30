<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPostRefundManualReversalSchema.php';

/** Closed local linkage; the original Asaas payment/refund is never projected or rewritten. */
final class CompanyPostRefundManualReversalEntries
{
    public static function json(array $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    public static function header(array $r):string{return hash('sha256',self::json([$r['request_key'],(int)$r['issuer_id'],(int)$r['invoice_id'],(int)$r['payment_id'],$r['manual_request_key'],$r['manual_record_hash'],$r['input_hash'],(int)$r['created_by'],$r['created_at']]));}
    public static function tables($db,string$p):array{return $db->table_exists($p.'company_post_refund_manual_reversals')?['company_post_refund_manual_reversals','company_post_refund_manual_receipts','company_credit_entries','company_financial_events','issuers_audit']:[];}
    public static function validateForInvoiceInOwned($db,string$p,int$i,int$id):void
    {
        if(!$db->table_exists($p.'company_post_refund_manual_reversals'))return;
        IssuerTransaction::assertOwned($db,$p,self::tables($db,$p));
        $rows=fn($q,$a=[])=>IssuerStorage::query($db,$q,$a)->result_array();$seen=[];$manuals=$rows('SELECT * FROM '.$p.'company_post_refund_manual_receipts WHERE invoice_id=? FOR UPDATE',[$id]);$links=$rows('SELECT request_key,issuer_id FROM '.$p.'company_post_refund_manual_reversals WHERE invoice_id=? FOR UPDATE',[$id]);if(!$manuals&&!$links)return;CompanyPostRefundManualReversalSchema::ensure($db,$p,false);
        foreach($manuals as$manual){
            if((int)$manual['issuer_id']!==$i)throw new DomainException('company_post_refund_manual_reversal_conflict');
            $native=$rows('SELECT * FROM '.$p.'invoicepaymentrecords WHERE id=? AND invoiceid=? FOR UPDATE',[$manual['payment_id'],$id])[0]??null;if(!$native)throw new DomainException('company_post_refund_manual_reversal_conflict');
            $v=self::readInOwned($db,$p,$i,$id,$manual,$native);if($v!==null)$seen[$v['request_key']]=true;
        }
        foreach($links as$record)if((int)$record['issuer_id']!==$i||!isset($seen[$record['request_key']]))throw new DomainException('company_post_refund_manual_reversal_conflict');
    }
    public static function readInOwned($db,string$p,int$i,int$id,array$manual,array$native):?array
    {
        $rows=fn($q,$a=[])=>IssuerStorage::query($db,$q,$a)->result_array();
        $entries=$db->table_exists($p.'company_credit_entries')?$rows("SELECT * FROM {$p}company_credit_entries WHERE native_id=? AND kind='manual_reverse' FOR UPDATE",[$manual['payment_id']]):[];
        $links=[];
        if($db->table_exists($p.'company_post_refund_manual_reversals')){
            CompanyPostRefundManualReversalSchema::ensure($db,$p,false);
            IssuerTransaction::assertOwned($db,$p,self::tables($db,$p));
            $links=$rows('SELECT * FROM '.$p.'company_post_refund_manual_reversals WHERE payment_id=? OR (issuer_id=? AND invoice_id=?) FOR UPDATE',[$manual['payment_id'],$i,$id]);
        }
        if(!$entries&&!$links)return null;
        if(count($entries)!==1||count($links)!==1)throw new DomainException('company_post_refund_manual_reversal_conflict');
        $manualHeader=hash('sha256',self::json([$manual['request_key'],(int)$manual['issuer_id'],(int)$manual['invoice_id'],(int)$manual['original_payment_id'],(int)$manual['payment_id'],$manual['input_hash'],$manual['external_reference_key'],$manual['posting_key'],$manual['account_revision'],$manual['grant_revision'],(int)$manual['created_by'],$manual['created_at']]));
        if(!hash_equals($manual['record_hash'],$manualHeader)||!hash_equals($manual['input_hash'],hash('sha256',$manual['input_json'])))throw new DomainException('company_post_refund_manual_reversal_conflict');
        $r=$links[0];$e=$entries[0];$v=json_decode($r['input_json'],true,32,JSON_THROW_ON_ERROR);
        $input=$v['entry']??[];$receipt=json_decode($manual['input_json'],true,32,JSON_THROW_ON_ERROR)['receipt']??[];
        $expected=['credit_id'=>null,'invoice_id'=>$id,'parent_key'=>null,'payment_id'=>(int)$manual['payment_id'],'amount'=>$receipt['amount']??null,'date'=>$input['date']??null,'reference'=>$input['reference']??null,'reason'=>$input['reason']??null];
        if((int)$r['issuer_id']!==$i||(int)$r['invoice_id']!==$id||(int)$r['payment_id']!==(int)$manual['payment_id']||$r['manual_request_key']!==$manual['request_key']||$r['manual_record_hash']!==$manual['record_hash']||!hash_equals($r['input_hash'],hash('sha256',$r['input_json']))||!hash_equals($r['record_hash'],self::header($r))||($v['request_key']??null)!==$r['request_key']||($v['manual_request_key']??null)!==$r['manual_request_key']||!is_string($v['revision']??null)||!preg_match('/^[a-f0-9]{64}$/D',$v['revision'])||(int)($v['issuer_id']??0)!==$i||(int)($v['invoice_id']??0)!==$id||(int)($v['actor_id']??0)!==(int)$r['created_by']||($v['local_reversal_confirmed']??false)!==true||!is_string($v['independent_evidence_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$v['independent_evidence_hash'])||$v['independent_evidence_hash']===str_repeat('0',64)||$input!==$expected)throw new DomainException('company_post_refund_manual_reversal_conflict');
        if($e['request_key']!==$r['request_key']||(int)$e['issuer_id']!==$i||(int)$e['actor_id']!==(int)$r['created_by']||(int)$e['invoice_id']!==$id||(int)$e['native_id']!==(int)$manual['payment_id']||$e['credit_id']!==null||$e['parent_key']!==null||$e['kind']!=='manual_reverse'||CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor((string)$e['amount']))!==$receipt['amount']||$e['reference']!==$input['reference']||$e['reference_hash']!==hash('sha256',$input['reference'])||$e['reason']!==$input['reason']||$e['book_date']!==$input['date']||!hash_equals($e['input_hash'],hash('sha256',json_encode(['manual_reverse',$input],JSON_THROW_ON_ERROR)))||CompanyInvoiceAmounts::minor((string)$native['amount'])!==0)throw new DomainException('company_post_refund_manual_reversal_conflict');
        $audit=json_encode(['request_key'=>$r['request_key']],JSON_THROW_ON_ERROR);
        if(count($rows('SELECT id FROM '.$p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=? FOR UPDATE',[$i,$r['created_by'],$id,'company_credit_manual_reverse',$audit]))!==1)throw new DomainException('company_post_refund_manual_reversal_conflict');
        if(count($rows('SELECT evidence_key FROM '.$p.'company_financial_events WHERE evidence_key=? AND issuer_id=? AND actor_id=? AND kind=? AND source_type=? AND source_id=? AND amount=? AND book_date=? FOR UPDATE',[hash('sha256','company-journal:credit-entry:'.$r['request_key']),$i,$r['created_by'],'receipt_reversed','payment',(string)$manual['payment_id'],$input['amount'],$input['date']]))!==1)throw new DomainException('company_post_refund_manual_reversal_conflict');
        return $v+['created_at'=>$r['created_at']];
    }
}
