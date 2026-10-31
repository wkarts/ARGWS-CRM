<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/application/services/CompanyInvoiceCopiesInput.php';
require_once dirname(__DIR__).'/application/services/CompanyInvoiceDraftsInput.php';
$checks=0;
function nativeFieldsCheck(bool $condition,string $label):void { ++$GLOBALS['checks']; if(!$condition)throw new RuntimeException($label); }
function nativeFieldsDenied(callable $work):void { try{$work();}catch(DomainException $expected){++$GLOBALS['checks'];return;}throw new RuntimeException('unsafe metadata accepted'); }
$input=['client_id'=>1,'date'=>'2026-10-25','due_date'=>'2026-11-25','currency'=>1,'items'=>[['description'=>'Synthetic','qty'=>'1.00','rate'=>'10.00','taxes'=>[]]],'note'=>'Synthetic'];
$old=CompanyInvoiceCopiesInput::canonical($input);
nativeFieldsCheck(!array_key_exists('native_fields',$old),'old canonical payload must not gain an empty key and change its receipt hash');
$plainDraft=CompanyInvoiceDraftsInput::normalize($input);
nativeFieldsCheck(!array_key_exists('native_fields',$plainDraft),'old drafts keep their exact absent-key semantics');
$new=$input+['native_fields'=>['show_quantity_as'=>'2','adminnote'=>'Private','terms'=>"Literal <script>\nTerms",'billing_country'=>'0']];
$expected=['adminnote'=>'Private','terms'=>"Literal <script>\nTerms",'billing_country'=>0,'show_quantity_as'=>2];
nativeFieldsCheck(CompanyInvoiceCopiesInput::canonical($new)['native_fields']===$expected,'canonical native fields have deterministic order and numeric types');
nativeFieldsCheck(CompanyInvoiceDraftsInput::normalize($new)['native_fields']===$expected,'drafts preserve exactly the same canonical native metadata');
nativeFieldsCheck(CompanyInvoiceCopiesInput::hash(CompanyInvoiceCopiesInput::canonical($new))!==CompanyInvoiceCopiesInput::hash($old),'new metadata contributes to request identity');
$oldStored=CompanyInvoiceDraftsInput::normalize($input);
$newStored=CompanyInvoiceDraftsInput::normalize($new);
nativeFieldsCheck(CompanyInvoiceCopiesInput::decode(CompanyInvoiceCopiesInput::json($oldStored))===$oldStored,'historical copy receipt payload still decodes without injected defaults');
nativeFieldsCheck(CompanyInvoiceCopiesInput::decode(CompanyInvoiceCopiesInput::json($newStored))===$newStored,'new copy payload round-trips every native field');
foreach([null,'text',['issuer_id'=>2],['total'=>'1.00'],['status'=>2],['paymentmode'=>'bank'],['show_quantity_as'=>0],['include_shipping'=>2],['billing_country'=>'01'],['terms'=>[]],['adminnote'=>"bad\0value"]]as$bad)nativeFieldsDenied(fn()=>CompanyInvoiceNativeFields::input($bad));
$project=$input+['native_project'=>['source_revision'=>'1','source_id'=>'7','source_kind'=>'company_project']];
$projectDraft=CompanyInvoiceDraftsInput::normalize($project);
nativeFieldsCheck($projectDraft['native_project']===['source_kind'=>'company_project','source_id'=>7,'source_revision'=>1],'project lineage normalized without lossy numeric aliases');
nativeFieldsCheck(CompanyInvoiceCopiesInput::decode(CompanyInvoiceCopiesInput::json($projectDraft))===$projectDraft,'project copy payload round-trips exactly');
nativeFieldsCheck(!array_key_exists('native_project',$old),'historical canonical request does not gain relation metadata');
foreach([['source_kind'=>'native_legacy_project','source_id'=>7,'source_revision'=>1],['source_kind'=>'company_project','source_id'=>7,'source_revision'=>0],['source_kind'=>'company_project','source_id'=>7,'source_revision'=>1,'issuer_id'=>2]]as$bad)nativeFieldsDenied(fn()=>CompanyInvoiceProjectInput::normalize($bad));
echo "Native invoice field canonical contract: {$checks} checks PASS. No database or provider calls.\n";
