<?php
/** Recreated after recovery: exact full-refund DTO boundaries, no database or remote I/O. */
declare(strict_types=1);
require_once dirname(__DIR__,2).'/application/services/CompanyAsaasRefundData.php';
$checks=0;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$denied=static function(callable $fn,string $message)use($check):void{try{$fn();}catch(DomainException $e){$check($e->getMessage()===$message,'Expected '.$message.', got '.$e->getMessage());return;}throw new RuntimeException('Expected '.$message);};
$original=['amount'=>'100.00','payment_date'=>'2026-10-19','currency'=>'BRL','receipt_key'=>str_repeat('a',64),'receipt_evidence_hash'=>str_repeat('b',64),'scope_hash'=>str_repeat('c',64),'refund_request_key'=>str_repeat('d',64),'observation_id'=>5,'observation_hash'=>str_repeat('e',64),'origin_id'=>12,'account_row'=>3,'remote_id'=>'pay_synthetic'];
$input=['external_refund_confirmed'=>'1','full_cash_return_confirmed'=>'1','returned_amount'=>'100.00','date'=>'2026-10-20','reference'=>'refund-bank-evidence-1','reason'=>'Independent external refund declaration','independent_evidence_hash'=>str_repeat('f',64)];
$dto=CompanyAsaasRefundData::declare($original,$input,'2026-10-20');
$check($dto->cashPrincipal()==='100.00'&&$dto->principalReinstated()==='100.00','full cash restores principal exactly');
$check($dto->data()['charges_aggregate_reversed']==='0.00'&&$dto->data()['net_reversed']===null,'refund never fabricates fee/net reversal');
$check(CompanyAsaasRefundData::fromCaptured($dto->data(),$original)->fingerprint()===$dto->fingerprint(),'captured proof roundtrips exactly');
$check(CompanyAsaasRefundData::declare($original,$input,'2026-10-20')->externalReferenceKey()===$dto->externalReferenceKey(),'reference key is deterministic');
foreach(['0','yes',true,1] as $v)$denied(fn()=>CompanyAsaasRefundData::declare($original,array_replace($input,['external_refund_confirmed'=>$v]),'2026-10-20'),'company_asaas_full_refund_confirmation_required');
$denied(fn()=>CompanyAsaasRefundData::declare($original,$input+['unknown'=>'x'],'2026-10-20'),'company_asaas_full_refund_confirmation_required');
foreach(['99.99','100.01',100] as $value)$denied(fn()=>CompanyAsaasRefundData::declare($original,array_replace($input,['returned_amount'=>$value]),'2026-10-20'),'company_asaas_full_refund_full_amount_required');
foreach(['2026-10-18','2026-10-21','2026-02-30'] as $value)$denied(fn()=>CompanyAsaasRefundData::declare($original,array_replace($input,['date'=>$value]),'2026-10-20'),'company_asaas_full_refund_date_requires_review');
foreach([$original['receipt_evidence_hash'],$original['observation_hash'],str_repeat('0',64)] as $value)$denied(fn()=>CompanyAsaasRefundData::declare($original,array_replace($input,['independent_evidence_hash'=>$value]),'2026-10-20'),'company_asaas_full_refund_independent_proof_required');
foreach(['bad',str_repeat('A',64)] as $value)$denied(fn()=>CompanyAsaasRefundData::declare($original,array_replace($input,['independent_evidence_hash'=>$value]),'2026-10-20'),'company_asaas_full_refund_invalid');
foreach(['source_type'=>'forged','cash_principal_reversed'=>'99.00','principal_reinstated'=>'99.00','returned_paid'=>'100.00x'] as $field=>$value){$changed=$dto->data();$changed[$field]=$value;try{CompanyAsaasRefundData::fromCaptured($changed,$original);throw new RuntimeException('altered captured proof accepted');}catch(DomainException $expected){$check(true,'altered captured proof rejected');}}
$other=$original;$other['scope_hash']=str_repeat('9',64);$check(CompanyAsaasRefundData::declare($other,$input,'2026-10-20')->externalReferenceKey()!==$dto->externalReferenceKey(),'different account/company scope does not share reference identity');
echo 'Recreated439 full-refund DTO: '.$checks.' checks passed. No provider or financial posting claim.'.PHP_EOL;
