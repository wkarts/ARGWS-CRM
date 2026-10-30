<?php
require __DIR__.'/native_invoice_preview_recurring.php';
require_once APPPATH.'services/NativeCompanyInvoicePreviewData.php';
$fields=new NativeCompanyCustomFields($db,$p);$definition=NativeCompanyCustomFields::definition(['fieldto'=>'invoice','name'=>'Synthetic PDF field','type'=>'input','active'=>1,'show_on_pdf'=>1]);
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'invoice','invoice_preview_pdf',json_encode($definition,JSON_THROW_ON_ERROR)]);$field=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
IssuerTransaction::run($db,$p,array_unique(array_merge($fields->tableNames(),['invoices'])),fn()=>$fields->saveInOwnedTransaction($a,7,'invoice',$branded,[$field=>'Synthetic <private> PDF value']));
$preview=new NativeCompanyInvoicePreviewData($db,$p);$metadata=$preview->metadata($a,7,$branded);
essentialCheck(in_array('ZZZ Synthetic tag',$metadata['tags'],true)&&strlen($metadata['tags_revision'])===64,'preview reads actual issuer-scoped tags and revision');
essentialCheck(count($metadata['pdf_fields'])===1&&$metadata['pdf_fields'][0]['value']==='Synthetic <private> PDF value','preview uses exact authorized PDF custom-field value before HTML escaping');
essentialThrows(fn()=>$preview->metadata($b,7,$branded),'company_invoice_access_denied','foreign company cannot inspect tags or custom fields');
echo 'Native invoice preview metadata: owned tags, custom-field scope and revision passed.'.PHP_EOL;
