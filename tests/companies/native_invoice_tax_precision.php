<?php
require __DIR__.'/native_invoice_form_post.php';
foreach(['5'=>'5.00','5.0'=>'5.00','5.0000'=>'5.00','1.2300'=>'1.23','0.0100'=>'0.01']as$input=>$expected)essentialCheck(NativeCompanyTaxRate::exact((string)$input)===$expected,'native tax rate normalizes only representable values '.$input);
foreach(['1.2345','0.0010','1e2','-1','1.00000']as$input)essentialThrows(fn()=>NativeCompanyTaxRate::exact($input),'native_tax_precision_review_required','incompatible rate is not rounded '.$input);
$selection=(new NativeCompanyInvoiceSequencePreferences($db,$p))->readForInvoiceCreation($a,12);
$taxWire=$wire;$taxWire['_native_invoice_request_key']=hash('sha256','native-exact-tax-wire');$taxWire['_native_invoice_number_revision']=$selection['revision'];$taxWire['number']=$selection['next_number'];
// Obtain the actual existing catalogue name, not a second synthetic spelling.
$taxName=$db->query('SELECT name FROM '.$p.'company_taxes WHERE issuer_id=? AND id=1',[$a])->row_array()['name'];$taxWire['newitems']['7']['taxname']=[$taxName.'|5.0000'];$taxWire['total']='105.00';
$taxReceipt=$form->create($a,12,$taxWire);$taxInvoice=$db->query('SELECT total FROM '.$p.'invoices WHERE id=?',[$taxReceipt['result']['invoice_id']])->row_array();
essentialCheck(CompanyInvoiceAmounts::minor((string)$taxInvoice['total'])===10500,'actual original invoice POST accepts exact four-place serialization with native two-place tax semantics');
$before=[];foreach(['invoices','itemable','item_tax','issuers_audit','issuers_sequences','company_taxes']as$table)$before[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
$bad=$taxWire;$bad['_native_invoice_request_key']=hash('sha256','native-incompatible-tax-wire');$bad['newitems']['7']['taxname']=[$taxName.'|1.2345'];essentialThrows(fn()=>$form->create($a,12,$bad),'native_tax_precision_review_required','original POST refuses unsupported tax precision before writes');
essentialThrows(fn()=>NativeCompanyCatalog::simpleFields(['name'=>'Rejected rate','taxrate'=>'1.2345'],true),'native_tax_precision_review_required','catalogue write normalizer rejects unsupported precision');
foreach($before as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'precision rejection preserves '.$table);
$db->query('UPDATE '.$p.'company_taxes SET taxrate=? WHERE issuer_id=? AND id=1',['1.2345',$a]);
$storedBefore=$db->query('SELECT * FROM '.$p.'company_taxes WHERE issuer_id=? AND id=1',[$a])->row_array();
$catalog=new NativeCompanyCatalog($db,$p,$a,12);
$db->query('UPDATE '.$p.'company_items SET tax=1 WHERE issuer_id=? AND id=1',[$a]);essentialThrows(fn()=>$catalog->itemForInvoiceEditor(1),'native_tax_precision_review_required','item selection also refuses an incompatible persisted tax');
essentialThrows(fn()=>$catalog->taxes(),'native_tax_precision_review_required','historical incompatible catalogue rate requires explicit review');
essentialCheck($db->query('SELECT * FROM '.$p.'company_taxes WHERE issuer_id=? AND id=1',[$a])->row_array()===$storedBefore,'reader never repairs stored fiscal precision');
echo 'Native tax precision: '.$GLOBALS['essentialChecks']." cumulative PASS; SQLite original POST, no MySQL/browser claim.\n";
