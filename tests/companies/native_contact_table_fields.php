<?php
require __DIR__.'/native_contact_form.php';
$start=$GLOBALS['essentialChecks'];$fields=new NativeCompanyCustomFields($db,$p);$service=new CompanyNativeContacts($db,$p);
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'contacts','name'=>'Table order','type'=>'input','show_on_table'=>'1']);
$db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,?)",[$a,'contacts','contacts_table_order',json_encode($definition,JSON_THROW_ON_ERROR),1]);$field=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
$all=$service->page($a,7,$client,['length'=>'100']);$ids=array_column($all['rows'],'id');essentialCheck(count($ids)>0,'owned fixture contains real contacts');
foreach($ids as$i=>$contact)IssuerTransaction::run($db,$p,array_merge($fields->tableNames(),CompanyNativeContacts::TABLES),fn()=>$fields->saveInOwnedTransaction($a,7,'contacts',(int)$contact,[$field=>sprintf('%03d',count($ids)-$i)]));
$sorted=$service->page($a,7,$client,['length'=>'100','order'=>[['column'=>'6','dir'=>'asc']]],true);
essentialCheck(array_column($sorted['rows'],'id')===array_reverse($ids),'native custom field column sorts owner values rather than falling back silently');
essentialCheck(count($sorted['custom_fields'])===1&&$sorted['custom_fields'][0]['id']===$field,'table schema and body use same authorized definitions');
essentialThrows(fn()=>$service->page($a,7,$client,['order'=>[['column'=>'7','dir'=>'asc']]],true),'company_client_invalid_page','unknown table custom column denied');
essentialThrows(fn()=>$service->page($a,7,$client,['order'=>'injected'],true),'company_client_invalid_page','malformed order does not enter query construction');
essentialThrows(fn()=>$service->page($b,7,$client,[],true),'company_client_access_denied','foreign table field ordering cannot read values');
echo 'Native contact table custom fields: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
