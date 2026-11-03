<?php
/** CNAB400 invoice comparison uses real parser fixtures and never authorizes payment. */
declare(strict_types=1);
require __DIR__.'/banco_brasil_cnab400.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/Cnab400InvoiceComparison.php';
use BancoBrasil\Cnab400InvoiceComparison;

$event=$parser400->parse($a400,bb400Mutate($wire400,1,117,str_pad('CRM42',10)))['events'][0];
$row=['id'=>71,'issuer_id'=>1,'scope_key'=>$event['scope_key']];
$invoice=['id'=>42,'issuer_id'=>1,'total'=>'200.00','duedate'=>'2026-10-16'];
$document=['invoice_id'=>42,'issuer_id'=>1];
$result=Cnab400InvoiceComparison::compare(1,$row,$event,$invoice,$document);
check($result['all_equal']===true&&$result['checks']===['document'=>true,'due_date'=>true,'nominal_amount'=>true],'same explicit document, due date and nominal amount');
check($result['identity_authority']==='not_established'&&$result['currency_evidence']==='not_present_in_file'&&$result['financial_effect']==='not_applied','coincidence never certifies source, currency or credit');
foreach ([['document','CRM99'],['due_date','2026-10-17'],['nominal_amount','199.99']] as [$key,$value]) {
    $changed=array_replace($event,[$key=>$value]);
    $comparison=Cnab400InvoiceComparison::compare(1,$row,$changed,$invoice,$document);
    check($comparison['all_equal']===false&&!$comparison['checks'][$key==='nominal_amount'?'nominal_amount':$key],'different '.$key.' detected without link');
}
foreach ([['issuer_id'=>2],['invoice_id'=>99]] as $different) rejects(fn()=>Cnab400InvoiceComparison::compare(1,$row,$event,$invoice,array_replace($document,$different)),'foreign document rejected');
rejects(fn()=>Cnab400InvoiceComparison::compare(2,$row,$event,$invoice,$document),'foreign company rejected');
rejects(fn()=>Cnab400InvoiceComparison::compare(1,$row,array_replace($event,['layout'=>\BancoBrasil\Agreement::LAYOUT_ID]),$invoice,$document),'different layout rejected');
rejects(fn()=>Cnab400InvoiceComparison::compare(1,$row,array_replace($event,['financial_effect'=>'applied']),$invoice,$document),'mutated financial effect rejected');
rejects(fn()=>Cnab400InvoiceComparison::compare(1,$row,array_replace($event,['scope_key'=>str_repeat('f',64)]),$invoice,$document),'scope mismatch rejected');
rejects(fn()=>Cnab400InvoiceComparison::compare(1,$row,array_replace($event,['nominal_amount'=>'2e2']),$invoice,$document),'invalid non-decimal amount rejected');
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/Cnab400ReceiptAssessment.php';
$assessment=\BancoBrasil\Cnab400ReceiptAssessment::assess($event,false);
check($assessment['settlement_reported']&&$assessment['positive_paid']&&!$assessment['paid_equals_nominal']&&$assessment['adjustments_present'],'assessment preserves partial amount and known fee without converting either into payment');
check(!$assessment['origin_matches']&&!$assessment['payment_authorized']&&!$assessment['bank_file_authenticated']&&$assessment['financial_effect']==='not_applied','assessment never grants payment or infers authenticated bank evidence');
check(in_array('cnab400_financial_writer_not_operational',$assessment['blockers'],true)&&in_array('currency_and_layout_contract',$assessment['blockers'],true),'missing local writer and bank contract remain explicit blockers');
$cheque=$parser400->parse($a400,bb400Fixture($a400,'06','04'))['events'][0];
check(!\BancoBrasil\Cnab400ReceiptAssessment::assess($cheque,true)['settlement_reported'],'pending cheque is never a settlement');
rejects(fn()=>\BancoBrasil\Cnab400ReceiptAssessment::assess(array_replace($event,['financial_effect'=>'applied']),false),'mutated financial authority denied');
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/Cnab400GeneratedSource.php';
$sourceOrigin=['issuer_id'=>1,'invoice_id'=>42,'scope_key'=>$a400->scope->key(),'remote_id'=>$a400->ourNumber('1'),'control_reference'=>$title400['control']];
$sourceSnapshot=['connection'=>['id'=>1,'account_id'=>$a400->scope->accountId,'environment'=>'sandbox','agreement_number'=>$a400->scope->agreement,'issuer_tax_id'=>$a400->taxId,'issuer_name'=>$a400->name,'variation'=>$a400->variation,'wallet'=>$a400->wallet],'beneficiary_name'=>$a400->name,'invoice'=>['id'=>42],'title'=>$title400,'document_snapshot'=>['recipient'=>['tax_id'=>$title400['payer_tax_id']]]];
$sourceBatch=['content'=>$candidate['content'],'content_hash'=>hash('sha256',$candidate['content']),'file_sequence'=>1];
\BancoBrasil\Cnab400GeneratedSource::validate($sourceOrigin,$sourceSnapshot,$sourceBatch);
foreach([[0,27,'9999'],[1,39,'X'],[1,64,'9'],[1,127,'9'],[1,221,'9'],[2,2,'X']] as [$line,$position,$value]){
    $changed=bb400Mutate($candidate['content'],$line,$position,$value);
    rejects(fn()=>\BancoBrasil\Cnab400GeneratedSource::validate($sourceOrigin,$sourceSnapshot,array_replace($sourceBatch,['content'=>$changed,'content_hash'=>hash('sha256',$changed)])),'rehashed invalid source field denied '.$line.'/'.$position);
}
rejects(fn()=>\BancoBrasil\Cnab400GeneratedSource::validate($sourceOrigin,$sourceSnapshot,array_replace($sourceBatch,['content_hash'=>str_repeat('0',64)])),'source content hash mismatch denied');
if(!defined('BASEPATH'))define('BASEPATH',dirname(__DIR__).'/system/');
if(!defined('APPPATH'))define('APPPATH',dirname(__DIR__).'/application/');
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/Cnab400ReviewedReceipt.php';
$creditProof=['credit_confirmed'=>'1','currency_confirmed'=>'1','homologation_confirmed'=>'1','reason'=>'Synthetic review','reference'=>'Synthetic independent credit','homologation_reference'=>'Synthetic bank confirmation','confirmed_amount'=>'200.00'];
new \BancoBrasil\Cnab400ReviewedReceipt(1,$creditProof);
foreach(['credit_confirmed','currency_confirmed','homologation_confirmed'] as $field)rejects(fn()=>new \BancoBrasil\Cnab400ReviewedReceipt(1,array_replace($creditProof,[$field=>'0'])),'credit requires explicit '.$field);
foreach(['0.00','2e2','200.001',200] as $bad)rejects(fn()=>new \BancoBrasil\Cnab400ReviewedReceipt(1,array_replace($creditProof,['confirmed_amount'=>$bad])),'exact positive independent amount required');
echo "CNAB400 invoice comparison: parser-based read-only checks passed.\n";
