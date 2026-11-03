<?php
require __DIR__.'/projects_operations.php';
require_once APPPATH.'services/NativeCompanyProjectRead.php';
register_shutdown_function(static function()use($db,$p):void{$db->query('DROP TABLE IF EXISTS '.$p.'projects');});
$legacyClient=$db->query('SELECT * FROM '.$p.'clients WHERE userid=?',[$cp])->row_array();$legacyClient['userid']=9;$legacyClient['company']='Legacy choice client';$db->insert($p.'clients',$legacyClient);
$db->query("ALTER TABLE {$p}projects ADD clientid INT NOT NULL DEFAULT 9");$db->query("ALTER TABLE {$p}projects ADD status INT NOT NULL DEFAULT 1");
$db->query("INSERT INTO {$p}projects(id,name,clientid,status) VALUES(?,'Legacy distinct',9,4),(?,'Forbidden private client',?,2)",[$pp,$pp+1000,$ca]);unset($db->data_cache['table_names']);
$new=$ps->create($a,7,array_replace($input,['name'=>'Native 100% exact']),$key('choice-new'));
$read=new NativeCompanyProjectRead($db,$p);$choices=$read->choices($a,7,$ca,'100%');
essentialCheck(count($choices)===1&&$choices[0]['id']===NativeCompanyResourceId::encode('company_project',$new)&&$choices[0]['source_revision']===1&&$choices[0]['clientid']===$ca,'Native project selection has canonical identity and current revision');
essentialCheck($read->choices($a,7,$ca,'100_')===[],'Search wildcard input is literal, not caller SQL wildcard');
essentialThrows(fn()=>$read->choices($b,7,$ca),'native_resource_reference_denied','Revoked shared client cannot be selected for new association');
$legacy=$read->choices($primary,7,9,'Legacy distinct');essentialCheck(count($legacy)===1&&$legacy[0]['source_kind']==='project'&&$legacy[0]['id']===(string)$pp&&$legacy[0]['status']===4,'Principal keeps original legacy project identity without claiming it is billable');
$owned=$read->choices($primary,7,$cp);essentialCheck(count($owned)===1&&$owned[0]['source_kind']==='company_project'&&$owned[0]['id']!==$legacy[0]['id'],'Colliding underlying project IDs stay disjoint');
essentialThrows(fn()=>$read->choices($a,7,$ca,str_repeat('x',101)),'native_project_query_invalid','Unbounded query rejected');
$db->query("UPDATE {$p}company_projects SET status='completed' WHERE id=?",[$new]);essentialCheck($read->choices($a,7,$ca,'100%')===[],'Owned association choices match existing invoice writer active guard');
echo 'Native project association choices: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
