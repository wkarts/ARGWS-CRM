<?php
require __DIR__.'/native_task_form_milestones.php';
$preferences=new CompanyTaskPreferences($db,$p);(new CompanySettingsStorage($db,$p))->mutate($a,7,'add','time_format','24');
foreach(['1'=>'todo','2'=>'awaiting_feedback','3'=>'testing','4'=>'in_progress','5'=>'done','auto'=>'auto']as$id=>$state){essentialCheck(CompanyTaskPreferences::fromNativeStatus((string)$id)===$state&&CompanyTaskPreferences::toNativeStatus($state)===(is_numeric($id)?(int)$id:$id),'Workflow defaults use original IDs without catalog-source aliases');}
essentialThrows(fn()=>CompanyTaskPreferences::fromNativeStatus('21'),'company_task_preferences_invalid','Catalog namespace is not native workflow ID');
essentialCheck(CompanyTaskPreferences::forStartDate('auto','2026-10-25','2026-10-25')==='in_progress'&&CompanyTaskPreferences::forStartDate('auto','2026-10-26','2026-10-25')==='todo','Auto uses actual declared start date and current day exactly as native');
$states=['awaiting_feedback','testing','done','auto'];
foreach($states as$state){
 $current=$preferences->page($a,7);$preferences->save($a,7,$current['revision'],[CompanyTaskPreferences::STATUS=>$state,CompanyTaskPreferences::ASSIGN=>'1']);
 $body=$wire;$body['name']='Native default '.$state;$body['rel_type']='';$body['rel_id']='';$body['milestone']='';unset($body['native_milestone_revision']);$body['checklist_items']=[];$body['native_request_key']=$key('workflow-default-'.$state);$body['startdate']='25/10/2026';if($state==='done')$body['datefinished']='25/10/2026 12:34:00';else unset($body['datefinished']);
 if($state==='done'){$projection=$form->newForm($a,7,[],'2026-10-25');$html=$controller->load->view('admin/tasks/task',$projection,true);essentialCheck(str_contains($html,'name="datefinished"'),'Original new-task modal requests completion time when default is completed');$missing=$body;unset($missing['datefinished']);essentialThrows(fn()=>$form->save($a,7,null,$missing),'native_task_form_date_invalid','Completed default does not invent a completion date');}
 $canonical=$form->save($a,7,null,$body);$id=NativeCompanyResourceId::decode('task',$canonical)['source_id'];$row=(new CompanyStandaloneTasks($db,$p))->get($a,7,$id);
 essentialCheck($row['status']===($state==='auto'?(date('Y-m-d')>='2026-10-25'?'in_progress':'todo'):$state),'Original form persists exact default workflow '.$state);
 if($state==='done')essentialCheck(json_decode($row['native_fields_json'],true)['date_finished']==='2026-10-25 12:34:00','Completed creation retains supplied completion date, never fabricates a timestamp');
}
$current=$preferences->page($a,7);essentialThrows(fn()=>$preferences->saveInOwnedTransaction($a,7,$current['revision'],[CompanyTaskPreferences::STATUS=>'todo',CompanyTaskPreferences::ASSIGN=>'1']),'issuer_owned_transaction_required','Settings compositor cannot join arbitrary authority');
$before=$preferences->page($a,7);try{IssuerTransaction::run($db,$p,$preferences->tables(),function()use($preferences,$a,$before){$preferences->saveInOwnedTransaction($a,7,$before['revision'],[CompanyTaskPreferences::STATUS=>'todo',CompanyTaskPreferences::ASSIGN=>'0']);throw new DomainException('synthetic_group_failed');});}catch(DomainException$e){essentialCheck($e->getMessage()==='synthetic_group_failed','Group failure propagated');}
essentialCheck($preferences->page($a,7)===$before,'Atomic group failure restores both task preferences');
echo 'Native task workflow defaults: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";

essentialThrows(fn()=>CompanyTaskPreferences::fromNativeStatus(['1']),'company_task_preferences_invalid','Malformed native status rejected without coercion');
echo 'Native workflow/default atomic contract: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
if($db->driver()==='sqlite'){
 $snapshot=$db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$a])->row_array();$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_standalone_tasks WHERE issuer_id=?',[$a])->row()->n;
 $db->query('CREATE TRIGGER '.$p.'mutate_creation_policy AFTER INSERT ON '.$p."issuers_audit WHEN NEW.issuer_id=".$a." AND NEW.event LIKE '%native_created' BEGIN UPDATE ".$p."issuers_registry SET settings_json='{}' WHERE id=NEW.issuer_id; END");
 $body['native_request_key']=$key('late-default-policy');essentialThrows(fn()=>$form->save($a,7,null,$body),'issuer_project_write_unconfirmed','Policy mutation after task insertion fails final same-transaction confirmation');
 essentialCheck($snapshot===$db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=?',[$a])->row_array()&&$count===(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_standalone_tasks WHERE issuer_id=?',[$a])->row()->n,'Late policy mutation rolls back new task and settings together');$db->query('DROP TRIGGER '.$p.'mutate_creation_policy');
}
echo 'Native defaults final write confirmation: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
