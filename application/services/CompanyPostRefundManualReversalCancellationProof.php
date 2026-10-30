<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPostRefundManual.php';

/** Closed proof for exactly original Asaas return + fully reversed independent manual439. */
final class CompanyPostRefundManualReversalCancellationProof
{
    public const BASIS='asaas_full_return_plus_fully_reversed_new_manual_440';
    public function __construct(private $db,private string$p,private int$i,private int$a,private int$id,private array$proof)
    {
        foreach([$i,$a,$id]as$v)IssuerPolicy::id($v);
        if(array_keys($proof)!==['basis','manual_request_key','manual_record_hash','reversal_request_key','reversal_record_hash']||$proof['basis']!==self::BASIS)throw new DomainException('company_post_refund_manual_cycle_proof_required');
        foreach(array_slice($proof,1)as$v)if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new DomainException('company_post_refund_manual_cycle_proof_required');
    }
    public function data():array{return$this->proof;}
    public function assertStorage($db,string$p):void{if($this->db!==$db||$this->p!==$p)throw new DomainException('company_post_refund_manual_cycle_source_denied');CompanyPostRefundManualReversalSchema::ensure($db,$p,false);}
    public function tables():array{return(new CompanyPostRefundManual($this->db,$this->p))->tables();}
    public static function captureInOwned($db,string$p,int$i,int$a,int$id,int$original,string$amount):?array
    {
        if(!$db->table_exists($p.'company_post_refund_manual_receipts'))return null;
        $rows=fn($q,$args=[])=>IssuerStorage::query($db,$q,$args)->result_array();
        $manuals=$rows('SELECT * FROM '.$p.'company_post_refund_manual_receipts WHERE invoice_id=? FOR UPDATE',[$id]);if(!$manuals)return null;if(count($manuals)!==1||(int)$manuals[0]['issuer_id']!==$i)throw new DomainException('company_post_refund_manual_cycle_conflict');
        if(!$db->table_exists($p.'company_post_refund_manual_reversals'))throw new DomainException('company_post_refund_manual_cycle_reversal_required');
        IssuerTransaction::assertOwned($db,$p,(new CompanyPostRefundManual($db,$p))->tables());
        $page=(new CompanyPostRefundManual($db,$p))->inOwned($i,$a,$id,true);$manual=$manuals[0];
        if(!$page['manual_recorded']||$page['manual_reversal']===null)throw new DomainException('company_post_refund_manual_cycle_reversal_required');
        if((int)$manual['original_payment_id']!==$original||$page['original_payment']!==$original||$page['declared_refund']!==$amount||$page['new_payment_id']===$original)throw new DomainException('company_post_refund_manual_cycle_conflict');
        $links=$rows('SELECT * FROM '.$p.'company_post_refund_manual_reversals WHERE issuer_id=? AND invoice_id=? FOR UPDATE',[$i,$id]);if(count($links)!==1||(int)$links[0]['payment_id']!==$page['new_payment_id']||$links[0]['manual_request_key']!==$manual['request_key'])throw new DomainException('company_post_refund_manual_cycle_conflict');
        $payments=$rows('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id FOR UPDATE',[$id]);$expected=[$original,$page['new_payment_id']];sort($expected);if(array_map('intval',array_column($payments,'id'))!==$expected||$rows('SELECT id FROM '.$p.'credits WHERE invoice_id=? FOR UPDATE',[$id]))throw new DomainException('company_post_refund_other_funds');
        $position=(new CompanyInvoiceBalance($db,$p))->inOwned($i,$a,$id);$twice=CompanyInvoiceAmounts::minor($amount)*2;if($twice>CompanyInvoiceAmounts::MAX)throw new DomainException('company_post_refund_other_funds');
        if($position['received']!=='0.00'||$position['applied_credit']!=='0.00'||$position['noncash_reductions']!=='0.00'||$position['received_external_reversed']!==$amount||$position['received_manual_reversed']!==$amount||$position['received_original']!==CompanyInvoiceAmounts::decimal($twice)||$position['receivable']!==$position['total'])throw new DomainException('company_post_refund_other_funds');
        return ['basis'=>self::BASIS,'manual_request_key'=>$manual['request_key'],'manual_record_hash'=>$manual['record_hash'],'reversal_request_key'=>$links[0]['request_key'],'reversal_record_hash'=>$links[0]['record_hash']];
    }
    public function assertCurrent($db,string$p,int$i,int$a,int$id,int$original,string$amount):void
    {
        $this->assertStorage($db,$p);if([$i,$a,$id]!==[$this->i,$this->a,$this->id])throw new DomainException('company_post_refund_manual_cycle_source_denied');
        $now=self::captureInOwned($db,$p,$i,$a,$id,$original,$amount);if($now!==$this->proof)throw new DomainException('company_post_refund_manual_cycle_conflict');
    }
}
