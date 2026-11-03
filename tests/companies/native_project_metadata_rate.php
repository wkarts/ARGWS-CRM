<?php
require __DIR__.'/project_billing_rate.php';
require_once APPPATH.'services/NativeCompanyProjectMetadata.php';
register_shutdown_function(static function()use($db,$p):void{$db->query('DROP TABLE IF EXISTS '.$p.'company_project_native_metadata');});
NativeCompanyProjectMetadataSchema::ensure($db,$p);
$metadata=new NativeCompanyProjectMetadata($db,$p);$run=fn($fn)=>IssuerTransaction::run($db,$p,NativeCompanyProjectMetadata::tables($db,$p),$fn);
// A fresh active project has no prior billing reservation; no historical origin is inferred as configuration.
$project=(new CompanyProjects($db,$p))->create($a,7,['client_id'=>(int)$db->query('SELECT client_id FROM '.$p.'company_projects WHERE issuer_id=? AND id=?',[$a,$pa])->row_array()['client_id'],'responsible_id'=>7,'name'=>'Native hourly config','description'=>'','start_date'=>'2026-10-01','due_date'=>'2026-12-31','status'=>'active'],$tk('native-metadata-new-project'));
$input=['workflow_status'=>2,'billing_type'=>2,'currency_id'=>1,'project_cost'=>'0','project_rate_per_hour'=>'70','estimated_hours'=>null,'progress_from_tasks'=>1,'progress'=>0];
$before=count($db->query('SELECT id FROM '.$p.'company_project_billing_rates')->result_array());
$record=$run(fn()=>$metadata->writeInOwnedTransaction($a,7,$project,1,0,$input));
essentialCheck((int)$record['hourly_rate_id']>0&&$record['project_rate_per_hour']==='70.00','Metadata references actual canonical immutable rate');
$proof=$run(fn()=>$prices->inOwned($a,7,$project,(int)$record['hourly_rate_id'],true));essentialCheck($proof['payload']['rate']==='70.00'&&$proof['payload']['round_mode']==='none'&&$proof['payload']['round_minutes']===0,'Native exact-hour profile is not inferred rounding');
$hold=$run(fn()=>$metadata->writeInOwnedTransaction($a,7,$project,1,1,array_replace($input,['workflow_status'=>3])));essentialCheck($hold['hourly_rate_id']===$record['hourly_rate_id']&&count($db->query('SELECT id FROM '.$p.'company_project_billing_rates')->result_array())===$before+1,'Workflow edit reuses original price proof without creating a new rate');
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(91,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,91,1)",[$a]);foreach(['view','edit']as$cap)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,91,'projects',?)",[$a,$cap]);
essentialThrows(fn()=>$run(fn()=>$metadata->writeInOwnedTransaction($a,91,$project,1,2,array_replace($input,['project_rate_per_hour'=>'80']))),'company_project_rate_denied','Project editor alone cannot declare financial price');
essentialThrows(fn()=>$run(fn()=>$metadata->readInOwnedTransaction($a,91,$project)),'company_project_rate_denied','Project grant alone cannot read protected canonical price');
$rows=$db->query('SELECT * FROM '.$p.'company_project_billing_rates ORDER BY id')->result_array();
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$run(fn()=>$metadata->writeInOwnedTransaction($a,7,$project,1,2,array_replace($input,['project_rate_per_hour'=>'80']))),'issuer_storage_failed','Audit failure rolls back both new rate and configuration');$db->failOn=null;
essentialCheck($db->query('SELECT * FROM '.$p.'company_project_billing_rates ORDER BY id')->result_array()===$rows&&$run(fn()=>$metadata->readInOwnedTransaction($a,7,$project))===$hold,'No partial configuration or canonical rate after failed outer unit');
echo 'Native project metadata canonical rate: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
// Explicit first configuration records only present intent; historical billing payloads are never reclassified.
$billingBefore=$db->query('SELECT * FROM '.$p.'company_task_billing_origins ORDER BY id')->result_array();
$invoiceBefore=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$declared=$run(fn()=>$metadata->writeInOwnedTransaction($a,7,$pa,1,0,$input));
essentialCheck((int)$declared['revision']===1&&$db->query('SELECT * FROM '.$p.'company_task_billing_origins ORDER BY id')->result_array()===$billingBefore&&$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$invoiceBefore,'Explicit new configuration does not relabel or modify historical billing and invoices');
essentialThrows(fn()=>$run(fn()=>$metadata->writeInOwnedTransaction($a,7,$pa,1,1,array_replace($input,['billing_type'=>1]))),'company_task_billing_immutable','Once explicitly configured, changing mode cannot overlap finalized time billing');
echo 'Native project history preservation: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
