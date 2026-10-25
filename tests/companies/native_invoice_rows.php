<?php
require __DIR__.'/invoice_recurring_modes.php';
require_once APPPATH.'services/NativeCompanyInvoiceRows.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
$rowsPresenter=new NativeCompanyInvoiceRows($db,$p);
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$dto=$rowsPresenter->presentation($a,7,$branded);
essentialCheck($dto['aRow']['id']==$branded&&$dto['formattedNumber']===$s->document($a,7,$branded)->data()['number'],'row presenter uses immutable owned number and canonical totals');
essentialCheck($dto['aRow']['company']===$s->document($a,7,$branded)->data()['recipient']['name'],'row customer name is historical snapshot');
essentialThrows(fn()=>$rowsPresenter->presentation($b,7,$branded),'company_invoice_access_denied','row presenter denies foreign invoice before raw metadata');
$primary=(new IssuerResourceAccess($db,$p))->primaryId();
$store=new CompanySettingsStorage($db,$p);$store->mutate($primary,7,'update','number_padding_prefixes','6');
$legacyDto=$rowsPresenter->presentation($primary,7,42);
essentialCheck($legacyDto['aRow']['issuer_id']===null&&$legacyDto['formattedNumber']===SalesNumberPresentation::format($legacyBefore[0]['number'],$legacyBefore[0]['number_format'],$legacyBefore[0]['prefix'],$legacyBefore[0]['date'],6),'legacy primary formats existing number without adopting snapshot or repairing row');
essentialCheck($legacyDto['invoiceCustomerLink']===true,'authorized existing principal legacy client retains native navigation');
$db->query('UPDATE '.$p.'invoices SET clientid=99999999 WHERE id=42');
try{essentialThrows(fn()=>$rowsPresenter->presentation($primary,7,42),'native_resource_reference_denied','missing historical customer denies the complete financial reference');}
finally{$db->query('UPDATE '.$p.'invoices SET clientid=? WHERE id=42',[$legacyBefore[0]['clientid']]);}

$db->query('UPDATE '.$p.'invoices SET clientid=? WHERE id=42',[$cb]);
try{essentialThrows(fn()=>$rowsPresenter->presentation($primary,7,42),'native_resource_reference_denied','NULL invoice cannot expose an owned secondary customer through tags or row DTO');}
finally{$db->query('UPDATE '.$p.'invoices SET clientid=? WHERE id=42',[$legacyBefore[0]['clientid']]);}

essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'presenter changes no invoice fields');
require_once APPPATH.'services/NativeCompanyInvoiceRead.php';
$tagService=new NativeCompanyResourceTags($db,$p);$tagRevision=$tagService->read($a,7,'invoice',$branded)['revision'];
IssuerTransaction::run($db,$p,array_merge(NativeCompanyResourceTags::tableNames(),['invoices']),fn()=>$tagService->replaceInvoiceInOwnedTransaction($a,7,$branded,$tagRevision,['ZZZ Synthetic tag']));
$tagPage=(new NativeCompanyInvoiceRead($db,$p))->page($a,7,['order'=>'tags','direction'=>'desc']);
essentialCheck($tagPage['documents'][0]['invoice_id']===$branded,'tags ordering executes over owned ordered links, never global suggestions');
$tagSearch=(new NativeCompanyInvoiceRead($db,$p))->page($a,7,['search'=>'ZZZ Synthetic tag']);
essentialCheck($tagSearch['recordsFiltered']===1&&$tagSearch['documents'][0]['invoice_id']===$branded,'global native search includes real owned tag labels');

// Framework formatting is an explicit presentation double; service/DB/partial are real.
if(!function_exists('_l')){function _l($s){return $s;}}
if(!function_exists('e')){function e($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('admin_url')){function admin_url($p){return '/admin/'.$p;}}
if(!function_exists('site_url')){function site_url($p){return '/'.$p;}}
if(!function_exists('app_format_money')){function app_format_money($v,$c){return $c.' '.$v;}}
if(!function_exists('_d')){function _d($v){return (string)$v;}}
if(!function_exists('format_invoice_status')){function format_invoice_status($s){return '<span>'.(int)$s.'</span>';}}
$rendered=$rowsPresenter->render($a,7,$branded);
essentialCheck(count($rendered)===11&&$rendered['DT_RowClass']==='has-row-options'&&str_contains($rendered[0],'init_invoice('.$branded.')'),'production DTO renders same native partial/actions/classes, no alternate table');
echo 'Native invoice row data/lineages: '.$GLOBALS['essentialChecks']." cumulative checks PASS.\n";
