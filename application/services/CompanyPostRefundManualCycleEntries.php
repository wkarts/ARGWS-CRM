<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyFinancialJournal.php';

/** Read recorded two-history closures without mutable provider grants or remote calls. */
final class CompanyPostRefundManualCycleEntries
{
    public static function tables($db,string$p):array{return $db->table_exists($p.'company_post_refund_cancellations')?['company_post_refund_cancellations']:[];}
    private static function json(array$v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    public static function validateForInvoiceInOwned($db,string$p,int$i,int$id):void
    {
        if(!$db->table_exists($p.'company_post_refund_cancellations')||!$db->table_exists($p.'company_post_refund_manual_receipts'))return;
        IssuerTransaction::assertOwned($db,$p,self::tables($db,$p));$rows=fn($q,$args=[])=>IssuerStorage::query($db,$q,$args)->result_array();
        $records=$rows('SELECT * FROM '.$p.'company_post_refund_cancellations WHERE invoice_id=? FOR UPDATE',[$id]);if(!$records)return; // Canonical transition precedes its receipt insert.
        $manuals=$rows('SELECT * FROM '.$p.'company_post_refund_manual_receipts WHERE invoice_id=? FOR UPDATE',[$id]);if(!$manuals)return; // Original one-payment cancellation remains its existing contract.
        $fail=static function():never{throw new DomainException('company_post_refund_manual_cycle_conflict');};
        if(count($records)!==1||count($manuals)!==1||!$db->table_exists($p.'company_post_refund_manual_reversals'))$fail();
        $r=$records[0];$m=$manuals[0];$links=$rows('SELECT * FROM '.$p.'company_post_refund_manual_reversals WHERE invoice_id=? FOR UPDATE',[$id]);if(count($links)!==1)$fail();$l=$links[0];
        $input=json_decode($r['input_json'],true,32,JSON_THROW_ON_ERROR);$proof=['basis'=>'asaas_full_return_plus_fully_reversed_new_manual_440','manual_request_key'=>$m['request_key'],'manual_record_hash'=>$m['record_hash'],'reversal_request_key'=>$l['request_key'],'reversal_record_hash'=>$l['record_hash']];
        $header=[$r['request_key'],(int)$r['issuer_id'],(int)$r['invoice_id'],(int)$r['payment_id'],$r['input_hash'],$r['posting_key'],$r['account_revision'],$r['grant_revision'],(int)$r['created_by'],$r['created_at']];
        if((int)$r['issuer_id']!==$i||(int)$m['issuer_id']!==$i||(int)$l['issuer_id']!==$i||(int)$r['payment_id']!==(int)$m['original_payment_id']||($input['manual_reversal_proof']??null)!==$proof||($input['request_key']??null)!==$r['request_key']||(int)($input['issuer_id']??0)!==$i||(int)($input['invoice_id']??0)!==$id||(int)($input['actor_id']??0)!==(int)$r['created_by']||($input['local_only_confirmed']??false)!==true||!hash_equals($r['input_hash'],hash('sha256',$r['input_json']))||!hash_equals($r['record_hash'],hash('sha256',self::json($header))))$fail();
        $payments=$rows('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id FOR UPDATE',[$id]);$expected=[(int)$m['original_payment_id'],(int)$m['payment_id']];sort($expected);if(array_map('intval',array_column($payments,'id'))!==$expected||$rows('SELECT id FROM '.$p.'credits WHERE invoice_id=? FOR UPDATE',[$id]))$fail();
        $posting=$rows('SELECT * FROM '.$p.'company_asaas_refund_postings WHERE invoice_id=? AND issuer_id=? FOR UPDATE',[$id,$i]);if(count($posting)!==1)$fail();$posting=$posting[0];$data=json_decode($posting['payload_json'],true,32,JSON_THROW_ON_ERROR);
        if((int)$posting['payment_id']!==(int)$r['payment_id']||$posting['request_key']!==$r['posting_key']||($input['refund_request']??null)!==$data['refund_request_key'])$fail();
        $metadata=self::json(['invoice_id'=>$id,'request_key'=>$r['request_key'],'input_hash'=>$r['input_hash'],'posting_key'=>$r['posting_key'],'observation_id'=>$input['observation_id']??null]);
        if(count($rows('SELECT id FROM '.$p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=? FOR UPDATE',[$i,$r['created_by'],$posting['origin_id'],'company_post_refund_invoice_cancelled',$metadata]))!==1)$fail();
        $invoice=$rows('SELECT status,date,total,currency FROM '.$p.'invoices WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$id])[0]??null;if(!$invoice||(int)$invoice['status']!==5)$fail();
        $key=hash('sha256','company-journal:invoice-cancelled:'.$i.':'.$id.':'.($input['revision']??''));$events=$rows('SELECT * FROM '.$p.'company_financial_events WHERE evidence_key=? FOR UPDATE',[$key]);if(count($events)!==1)$fail();
        try{$event=(new CompanyFinancialJournal($db,$p))->decode($events[0]);}catch(Throwable$e){$fail();}
        if((int)$event['issuer_id']!==$i||(int)$event['actor_id']!==(int)$r['created_by']||$event['kind']!=='invoice_cancelled'||$event['source_type']!=='invoice'||$event['source_id']!==(string)$id||$event['amount']!==CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor((string)$invoice['total']))||($event['before']['revision']??null)!==($input['revision']??null)||!in_array($event['before']['status']??null,[1,4],true)||($event['after']['status']??null)!==5||($event['after']['date']??null)!==$invoice['date']||($event['after']['total']??null)!==$event['amount'])$fail();
    }
}
