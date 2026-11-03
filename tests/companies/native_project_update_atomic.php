<?php
require __DIR__.'/native_project_metadata_rate.php';
$service=new CompanyProjects($db,$p);
$raw=$db->query('SELECT * FROM '.$p.'company_projects WHERE issuer_id=? AND id=?',[$a,$project])->row_array();
$fields=['client_id','responsible_id','name','description','start_date','due_date','status'];$form=array_intersect_key($raw,array_fill_keys($fields,true));$form['name']='Native updated project';
$configuration=array_replace($input,['workflow_status'=>1]);$save=$tk('native-project-atomic-update');
$id=$service->updateNative($a,7,$project,1,$form,2,$configuration,$save);
essentialCheck($id===$project&&$service->updateNative($a,7,$project,1,$form,2,$configuration,$save)===$project,'Project and metadata save uses existing durable operation receipt');
$current=$db->query('SELECT * FROM '.$p.'company_projects WHERE id=?',[$project])->row_array();$config=$run(fn()=>$metadata->readInOwnedTransaction($a,7,$project));
essentialCheck((int)$current['revision']===2&&$current['name']==='Native updated project'&&(int)$config['revision']===3&&(int)$config['workflow_status']===1,'One save changes actual project and explicit workflow in same unit');
essentialThrows(fn()=>$service->updateNative($a,7,$project,2,$form,2,$configuration,$tk('native-stale-metadata')),'native_project_configuration_stale','Metadata conflict rolls back prior project update');
essentialCheck($db->query('SELECT * FROM '.$p.'company_projects WHERE id=?',[$project])->row_array()===$current,'No partial project write after metadata CAS failure');
if($db->driver()==='sqlite'){
$db->query("CREATE TRIGGER {$p}native_project_audit_fault AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='native_project_configuration_updated' BEGIN UPDATE {$p}company_projects SET name='tampered' WHERE id=NEW.subject_id AND issuer_id=NEW.issuer_id; END");
essentialThrows(fn()=>$service->updateNative($a,7,$project,2,$form,3,$configuration,$tk('native-project-audit-fault')),'issuer_storage_failed','Audit-time project tamper rolls back both projections');$db->query('DROP TRIGGER '.$p.'native_project_audit_fault');
essentialCheck($db->query('SELECT * FROM '.$p.'company_projects WHERE id=?',[$project])->row_array()===$current&&$run(fn()=>$metadata->readInOwnedTransaction($a,7,$project))===$config,'Source and metadata remain byte-identical after rollback');
}
require_once APPPATH.'services/NativeCompanyProjectRead.php';
$nativeRead=new NativeCompanyProjectRead($db,$p);$counts=$nativeRead->ownedStatusCounts($a,7);
essentialCheck(($counts['not_started']??0)>=1,'Actual explicit workflow is counted without mapping historical active');
$sum=$nativeRead->summary($a,7,static fn($k)=>$k);essentialCheck($sum['status_counts'][NativeCompanyWorkStatusCatalog::id('project','not_started')]===$counts['not_started'],'Native projects summary shares actual workflow reader');
$finished=array_replace($form,['status'=>'completed']);$service->update($a,7,$project,2,$finished,$tk('historical-api-native-project-completion'));
$closed=$db->query('SELECT * FROM '.$p.'company_projects WHERE id=?',[$project])->row_array();$closedConfig=$run(fn()=>$metadata->readInOwnedTransaction($a,7,$project));
essentialCheck($closed['status']==='completed'&&(int)$closedConfig['workflow_status']===4&&(int)$closedConfig['revision']===4,'Existing update API synchronizes explicit native terminal workflow transactionally');
essentialCheck($closedConfig['hourly_rate_id']===$config['hourly_rate_id']&&$closedConfig['project_rate_per_hour']===$config['project_rate_per_hour'],'Lifecycle sync does not redeclare or modify financial price');
$counts=$nativeRead->ownedStatusCounts($a,7);essentialCheck(($counts['completed']??0)>=1,'Historical lifecycle write is visible in native phase counts');
$db->query('UPDATE '.$p.'company_project_native_metadata SET workflow_status=2 WHERE issuer_id=? AND project_id=?',[$a,$project]);
essentialThrows(fn()=>$nativeRead->ownedStatusCounts($a,7),'native_project_workflow_lifecycle_conflict','Conflicting declared phase is never silently relabelled');
echo 'Native project atomic update: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
