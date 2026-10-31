<?php
require __DIR__.'/native_task_timer_picker.php';
require_once APPPATH.'services/NativeCompanyBillableTaskRead.php';
$selection=new NativeCompanyBillableTaskRead($db,$p);$projectCode=NativeCompanyResourceId::encode('company_project',$project);
$origins=fn()=>$db->table_exists($p.'company_task_billing_origins')?$db->query('SELECT * FROM '.$p.'company_task_billing_origins ORDER BY id')->result_array():null;$before=$origins();
$result=$selection->forCustomer($a,7,$ca,$projectCode);
essentialCheck($result['basis']==='selection_only_not_billing_authority','Selector makes no quote or conversion claim');
essentialCheck(count($result['items'])>0,'Declared billable project tasks are available without preparing a quote');
$selected=array_values(array_filter($result['items'],static fn($r)=>$r['id']===$task));
essentialCheck(count($selected)===1&&$selected[0]['rel_id']===$projectCode&&$selected[0]['hourly_rate']==='9.50','Original selection preserves explicit task rate and canonical project');
essentialCheck($before===$origins(),'Readonly selector creates no billing origin');
essentialCheck($selection->forCustomer($a,7,$ca)['items']===[],'Without project, actual relation-less standalone records are not falsely attached to selected customer');
essentialThrows(fn()=>$selection->forCustomer($b,7,$ca,$projectCode),'native_resource_reference_denied','Cross company cannot select A task');
$db->query('UPDATE '.$p.'tasks SET billable=1,hourly_rate=12.25,rel_type=?,rel_id=? WHERE id=701',['customer',$ca]);
essentialThrows(fn()=>$selection->forCustomer($primary,7,$ca),'native_resource_reference_denied','Principal cannot attach secondary-owned customer task');
$legacyClient=$db->query('SELECT * FROM '.$p.'clients WHERE userid=?',[$ca])->row_array();$legacyClient['userid']=99001;$legacyClient['company']='Historical billable client';$db->insert($p.'clients',$legacyClient);$db->query('UPDATE '.$p.'tasks SET rel_id=99001 WHERE id=701');
if(!$db->table_exists($p.'taskstimers')){$seed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');preg_match('/CREATE TABLE `tbltaskstimers` \(.*?\) ENGINE=InnoDB[^;]*;/s',$seed,$ddl);$sql=str_replace('`tbltaskstimers`','`'.$p.'taskstimers`',$ddl[0]);if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [a-zA-Z0-9_]+ COLLATE [a-zA-Z0-9_]+/','',$sql);$db->query($sql);unset($db->data_cache['table_names']);}
$legacy=$selection->forCustomer($primary,7,99001);essentialCheck(count($legacy['items'])===1&&$legacy['items'][0]['id']==='701'&&$legacy['items'][0]['hourly_rate']==='12.25','Principal retains actual legacy selection, rate and identity');
echo 'Native billable task selection: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
$rawId=NativeCompanyResourceId::decode('task',$task)['source_id'];$taskRow=$db->query('SELECT * FROM '.$p.'company_tasks WHERE issuer_id=? AND id=?',[$a,$rawId])->row_array();
$stored=json_decode($taskRow['native_fields_json'],true,32,JSON_THROW_ON_ERROR);$stored['billable']=0;$db->query('UPDATE '.$p.'company_tasks SET native_fields_json=? WHERE issuer_id=? AND id=?',[json_encode($stored,JSON_THROW_ON_ERROR),$a,$rawId]);
essentialCheck(!in_array($task,array_column($selection->forCustomer($a,7,$ca,$projectCode)['items'],'id'),true),'Explicit nonbillable task is not a financial candidate');
$db->query('UPDATE '.$p.'company_tasks SET native_fields_json=NULL,native_fields_revision=NULL WHERE issuer_id=? AND id=?',[$a,$rawId]);$unknown=$selection->forCustomer($a,7,$ca,$projectCode);
essentialCheck(in_array($task,array_column($unknown['undeclared'],'id'),true)&&!in_array($task,array_column($unknown['items'],'id'),true),'Historical missing classification is explicit, not false or zero');
$db->query('UPDATE '.$p.'company_tasks SET native_fields_json=?,native_fields_revision=? WHERE issuer_id=? AND id=?',[$taskRow['native_fields_json'],$taskRow['native_fields_revision'],$a,$rawId]);
$db->query('INSERT INTO '.$p.'company_task_timers(issuer_id,task_id,actor_id,started_at,stopped_at,seconds) VALUES(?,?,7,?,NULL,0)',[$a,$rawId,'2026-10-25 12:00:00']);$timerId=(int)$db->insert_id();
$running=$selection->forCustomer($a,7,$ca,$projectCode);$entry=array_values(array_filter($running['items'],static fn($r)=>$r['id']===$task))[0];essentialCheck($entry['started_timers']===true,'Actual running timer disables original selector without fabricating billing state');
$db->query('DELETE FROM '.$p.'company_task_timers WHERE issuer_id=? AND id=?',[$a,$timerId]);
// Actual billing schema: fixtures populate a source link only to exercise selection exclusion, never claim a quote/proof.
if($db->driver()==='mysql')CompanyTaskBillingSchema::ensure($db,$p);else foreach(CompanyTaskBillingSchema::definitions()as$table=>$fields){$columns=[];foreach($fields as$name=>$type)$columns[]=$name.' '.(str_contains($type,'AUTO_INCREMENT')?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.(str_ends_with($type,' NULL')?'':' NOT NULL'));foreach(CompanyTaskBillingSchema::indexes($table)as$index=>[$names,$nonunique])if($index==='PRIMARY'&&!isset($fields['id']))$columns[]='PRIMARY KEY('.implode(',',$names).')';elseif($index!=='PRIMARY'&&!$nonunique)$columns[]='UNIQUE('.implode(',',$names).')';$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$columns).')');}unset($db->data_cache['table_names']);
$origin=[];foreach(CompanyTaskBillingSchema::definitions()['company_task_billing_origins']as$field=>$type)$origin[$field]=match($field){'id'=>991,'issuer_id'=>$a,'task_id'=>$rawId,'actor_id'=>7,'created_at'=>'2026-10-25 12:00:00',default=>str_contains($type,'text')?'{}':hash('sha256','selection-fixture-'.$field)};
$db->insert($p.'company_task_billing_origins',$origin);$db->query('INSERT INTO '.$p.'company_task_billing_links(task_id,issuer_id,origin_id,state,conversion_actor_id,invoice_id,updated_at) VALUES(?,?,991,?,7,NULL,?)',[$rawId,$a,'converting','2026-10-25 12:00:00']);
$locked=$selection->forCustomer($a,7,$ca,$projectCode);essentialCheck(!in_array($task,array_column($locked['items'],'id'),true)&&in_array($task,array_column($locked['blocked'],'id'),true),'Pending actual billing claim is not offered for another conversion');
echo 'Native billable task selection boundaries: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";

$relationMethod=new ReflectionMethod(NativeCompanyBillableTaskRead::class,'relationCustomer');
essentialThrows(fn()=>$relationMethod->invoke($selection,['source_kind'=>'company_invoice_draft','source_id'=>42],'invoice'),'native_billable_task_relation_unsupported','Draft source never falls back to raw invoice id');
essentialThrows(fn()=>$relationMethod->invoke($selection,['source_kind'=>'company_project','source_id'=>42],'expense'),'native_billable_task_relation_unsupported','Source type mismatch never hydrates another domain');
echo 'Native billable source identity: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
