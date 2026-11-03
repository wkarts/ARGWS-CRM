<?php
require __DIR__.'/native_invoice_editor_items.php';
require_once APPPATH.'services/NativeCompanyInvoiceForm.php';
if(!function_exists('_l')){function _l($key,$args=[]){return$key;}} // Explicit translation seam only.
// Actual auxiliary schemas for the original item selector, not placeholder DTOs.
foreach(['company_taxes','company_item_groups','company_items']as$table){$parts=NativeCompanyAuxiliarySchema::definitions($p)[$table];$ddl='CREATE TABLE '.$p.$table.'('.implode(',',$parts).') ENGINE=InnoDB';if($db->driver()==='sqlite')$ddl=preg_replace('/ CHARACTER SET utf8mb4 COLLATE utf8mb4_bin/','',$ddl);$db->query($ddl);}
NativeCompanyPaymentModesSchema::ensure($db,$p);
// Use the installation template table and its issuer column, not a capability stub.
preg_match('/CREATE TABLE `tblemailtemplates` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$templateMatch);
if(!$templateMatch)throw new RuntimeException('native email template schema missing');
$templateSql=str_replace('`tblemailtemplates`','`'.$p.'emailtemplates`',$templateMatch[0]);
$templateSql=str_replace('`emailtemplateid` int NOT NULL,','`emailtemplateid` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$templateSql);
if($db->driver()==='sqlite'){$templateSql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$templateSql);$templateSql=preg_replace('/enum\([^)]*\)/','varchar(50)',$templateSql);}
$db->query($templateSql);unset($db->data_cache['table_names']);
if($db->driver()==='mysql')CompanyEmailTemplateSchema::ensure($db,$p);else $db->query('ALTER TABLE '.$p.'emailtemplates ADD issuer_id int unsigned');

$db->query('INSERT INTO '.$p.'company_item_groups(id,issuer_id,name,revision) VALUES(1,?,?,1)',[$a,'A native group']);
$db->query('INSERT INTO '.$p.'company_item_groups(id,issuer_id,name,revision) VALUES(2,?,?,1)',[$b,'Private B group']);
$db->query('INSERT INTO '.$p.'company_items(id,issuer_id,description,long_description,rate,unit,tax,tax2,group_id,currency_rates_json,revision) VALUES(1,?,?,?,?,?,NULL,NULL,1,?,1)',[$a,'A original editor item','A description','12.50','unit','{}']);
$db->query('INSERT INTO '.$p.'company_items(id,issuer_id,description,long_description,rate,unit,tax,tax2,group_id,currency_rates_json,revision) VALUES(2,?,?,?,?,?,NULL,NULL,2,?,1)',[$b,'Private B item','Private B description','99.00','unit','{}']);
$db->query('INSERT INTO '.$p.'company_taxes(id,issuer_id,name,taxrate,revision) VALUES(1,?,?,?,1)',[$a,'A tax \"<synthetic>','5.0000']);
$form=new NativeCompanyInvoiceForm($db,$p);$before=[];foreach(['invoices','issuers_sequences','issuers_audit','company_items']as$table)$before[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
$data=$form->createData($a,12);
essentialCheck($data['native_invoice_editor_items']instanceof NativeCompanyInvoiceEditorItems&&$data['native_invoice_editor_items']->rows()===[],'new form uses the real typed editor without invented item identity');
essentialCheck($data['items'][NativeCompanyResourceId::encode('company_item_group',1)][0]['description']==='A original editor item'&&!str_contains(json_encode($data['items']),'Private B'),'create-only form receives real issuer catalogue, not empty substitute or foreign rows');
essentialCheck($data['native_invoice_options']['next_invoice_number']===$data['native_invoice_number_selection']['next_number'],'displayed native form number comes from the actual INV allocator');
essentialCheck($data['base_currency']->isdefault==1&&count($data['currencies'])>0,'original currency selectors receive real public default currency flag');
essentialCheck(preg_match('/^[a-f0-9]{64}$/D',$data['native_invoice_request_key'])===1,'original form receives a fresh request identity, not an issuer or actor override');
essentialThrows(fn()=>$form->createData($b,12),'native_invoice_editor_denied','create-only actor cannot open another issuer form');
essentialThrows(fn()=>$form->createData(1,7),'native_invoice_editor_denied','primary stays on its original controller/model path');
foreach($before as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'render data does not mutate '.$table);
essentialCheck($data['native_invoice_reminders_enabled']===false&&!$data['native_invoice_reminder_sources']['delivery_ready'],'empty actual template scope does not invent reminder readiness');
foreach([$b,null] as$templateIssuer)$db->query('INSERT INTO '.$p.'emailtemplates(type,slug,language,name,subject,message,fromname,active,`order`,issuer_id) VALUES(?,?,?,?,?,?,?,1,0,?)',['invoice','invoice-overdue-notice','english','Synthetic','Synthetic subject','PRIVATE TEMPLATE BODY','Synthetic',$templateIssuer]);
essentialCheck($form->createData($a,12)['native_invoice_reminders_enabled']===false,'foreign and principal active templates cannot enable named form checkbox');
$db->query('INSERT INTO '.$p.'emailtemplates(type,slug,language,name,subject,message,fromname,active,`order`,issuer_id) VALUES(?,?,?,?,?,?,?,1,0,?)',['invoice','invoice-overdue-notice','english','Synthetic','Synthetic subject','PRIVATE TEMPLATE BODY','Synthetic',$a]);
$data=$form->createData($a,12);
essentialCheck($data['native_invoice_reminders_enabled']===true&&!$data['native_invoice_reminder_sources']['delivery_ready']&&!str_contains(json_encode($data),'PRIVATE TEMPLATE BODY'),'owned active source enables only its original checkbox without body or delivery claim');
// Actual task tables are queried even when the selected customer's initial catalogue is empty.
require_once APPPATH.'services/CompanyStandaloneTasksSchema.php';
foreach(CompanyStandaloneTasksSchema::definitions($p)as$table=>$parts)if(!$db->table_exists($p.$table))$db->query('CREATE TABLE '.$p.$table.'('.preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',implode(',',$parts)).') ENGINE=InnoDB');
unset($db->data_cache['table_names']);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'tasks','view')",[$a]);
$queryStart=count($db->queries);$data=$form->createData($a,12,$ca);
essentialCheck($data['customer_id']===$ca&&$data['native_invoice_task_selection']['basis']==='selection_only_not_billing_authority'&&$data['billable_tasks']===[],'selected customer gets actual task selection result, not billing authority');
$queries=array_slice($db->queries,$queryStart);essentialCheck(count(array_filter($queries,fn($q)=>str_contains($q[0],'SELECT id FROM '.$p.'company_standalone_tasks WHERE issuer_id=?')))===2,'selected customer empty catalogue is queried and revalidated, not hardcoded');
echo 'Original new invoice form real data: '.$GLOBALS['essentialChecks']." cumulative PASS. Full GET/POST and browser still require wiring.\n";
