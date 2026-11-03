<?php
require __DIR__.'/task_billing.php';require_once APPPATH.'services/CompanyTaskNativeSnapshot.php';
$birth=fn()=>IssuerTransaction::run($db,$p,['company_tasks','company_task_followers','issuers_registry'],fn()=>CompanyTaskPriority::birthHash($db,$p,'project',$a,$ta));$source=$billing->status($a,7,$ta);$hash=$birth();
foreach(['company_tasks','company_standalone_tasks']as$table){$db->query('ALTER TABLE '.$p.$table.' ADD native_fields_json longtext NULL');$db->query('ALTER TABLE '.$p.$table.' ADD native_fields_revision int unsigned NULL');}
essentialCheck($birth()===$hash,'Additive NULL columns preserve historical birth hash exactly');
essentialCheck($billing->status($a,7,$ta)===$source,'Additive NULL columns preserve immutable financial source and binding exactly');
$historical=['id'=>'1','assignee_id'=>'7','due_date'=>'2026-11-01'];essentialCheck(CompanyTaskNativeSnapshot::row($historical+['native_fields_json'=>null,'native_fields_revision'=>null])===$historical,'Historical projection omits only undeclared new columns');
$native=['id'=>'2','assignee_id'=>null,'due_date'=>null,'native_fields_json'=>'{"billable":1}','native_fields_revision'=>1];essentialCheck(CompanyTaskNativeSnapshot::row($native)===$native,'Explicit native metadata and actual NULLs remain part of source proof');
essentialThrows(fn()=>CompanyTaskNativeSnapshot::row(array_replace($native,['native_fields_revision'=>null])),'native_task_form_state_invalid','Partial explicit declaration rejected');
echo 'Historical task snapshots: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
