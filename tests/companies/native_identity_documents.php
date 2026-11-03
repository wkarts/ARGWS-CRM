<?php
require __DIR__.'/invoices.php';
$start=$GLOBALS['essentialChecks'];$identity=new CompanySettingsIdentity($db,$p);
$prior=$s->document($a,7,$branded)->data()['identity'];
$identity->save($a,7,['profile'=>['company_trade_name'=>'Nome de vitrine','company_state_registration'=>'ISENTO','invoice_company_address'=>'Rua de teste','company_address_number'=>'123','company_address_complement'=>'Sala 4','company_address_district'=>'Bairro de teste']]);
$id=$s->create($a,7,$legacyInput,hash('sha256','structured-company-address'));
$dto=$s->document($a,7,$id);$current=$dto->data()['identity'];
essentialCheck($current['address']==='Rua de teste, 123, Sala 4, Bairro de teste','new document captures structured canonical address');
essentialCheck($current['legal_name']===$prior['legal_name']&&$current['tax_id']===$prior['tax_id'],'trade name never replaces legal fiscal identity');
essentialCheck($s->document($a,7,$branded)->data()['identity']===$prior,'old snapshot remains byte-equivalent');
$pdf=(new Issuer_invoice_pdf($dto))->prepare()->Output('','S');essentialCheck(str_starts_with($pdf,'%PDF-'),'PDF renders actual new snapshot');
$identity->save($a,7,['profile'=>['company_address_number'=>'999']]);
essentialCheck($s->document($a,7,$id)->data()['identity']===$current,'later registry edits cannot rewrite new issued snapshot');
echo 'Structured company identity documents: '.($GLOBALS['essentialChecks']-$start).' added checks passed ('.$db->driver().").\n";
