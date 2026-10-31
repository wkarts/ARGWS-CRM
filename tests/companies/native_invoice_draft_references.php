<?php
require __DIR__.'/invoice_drafts.php';
require_once APPPATH.'services/NativeCompanyResourceReferences.php';
$private=$drafts->create($a,7,$draftInput,hash('sha256','native-reference-private'));
$encoded=NativeCompanyResourceId::encode('company_invoice_draft',$private);
$ref=NativeCompanyResourceReferences::resolve($db,$p,$a,7,'invoice',$encoded);
essentialCheck($ref['source_kind']==='company_invoice_draft'&&(string)$ref['source_id']===(string)$private&&$ref['state']==='open'&&$ref['issued_invoice_id']===null,'draft resolves its real private source without issuing or aliasing invoices');
essentialCheck($ref['url']==='invoices/list_invoices/'.$encoded,'draft uses same native route with unambiguous canonical identity');
essentialThrows(fn()=>NativeCompanyResourceReferences::resolve($db,$p,$b,7,'invoice',$encoded),'company_invoice_draft_unavailable','B cannot resolve A private draft even with same privileged actor');
$owned=IssuerTransaction::run($db,$p,$drafts->tables(),fn()=>NativeCompanyResourceReferences::resolve($db,$p,$a,7,'invoice',$encoded,'edit'));
essentialCheck($owned['revision']===$ref['revision'],'exact owned draft transaction composes resolver without opening nested transaction');
$db->trans_begin();try{essentialThrows(fn()=>NativeCompanyResourceReferences::resolve($db,$p,$a,7,'invoice',$encoded),'issuer_outer_transaction_not_allowed','plain caller transaction cannot impersonate owned draft boundary');}finally{$db->trans_rollback();}
$issuedRef=NativeCompanyResourceId::encode('company_invoice_draft',$draftId);
essentialThrows(fn()=>NativeCompanyResourceReferences::resolve($db,$p,$a,7,'invoice',$issuedRef,'edit'),'native_resource_reference_denied','issued draft cannot be edited through original draft identity');
echo 'Native private invoice draft reference: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
require __DIR__.'/native_invoice_table_schema_fixture.php';
require_once APPPATH.'services/NativeCompanyInvoiceManage.php';
if(!function_exists('_l')){function _l($s){return$s;}}
$data=(new NativeCompanyInvoiceManage($db,$p))->data($a,7,$encoded);
essentialCheck($data['invoiceid']===$encoded,'actual manage preselects open private draft without assuming nonexistent draft state');
echo "Native manage private draft selection PASS; preview remains separate.\n";
