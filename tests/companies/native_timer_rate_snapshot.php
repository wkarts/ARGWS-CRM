<?php
require __DIR__.'/native_blank_timer_candidates.php';
$rates=new NativeCompanyStaffRates($db,$p);$details=new NativeCompanyTaskTimerDetails($db,$p);
$saveRate=function(string$value,int$revision)use($db,$p,$rates,$a):void{IssuerTransaction::run($db,$p,$rates->tables(),fn()=>$rates->saveInOwned($a,7,7,$value,$revision));};
$service->discard($a,7,$next,1,hash('sha256','discard-before-rate-start'));
$saveRate('42.50',0);$blankRate=$service->start($a,7,$off,hash('sha256','rate-snapshot-start'));
$captured=$details->forStaffTimesheet($a,7,7,'company_blank_task_timer',$blankRate);
essentialCheck($captured['hourly_rate']==='42.50'&&$captured['rate_basis']['revision']===1&&$captured['note']==='','Start captures the real company staff rate and explicit empty native note');
$saveRate('99.00',1);essentialCheck($details->forStaffTimesheet($a,7,7,'company_blank_task_timer',$blankRate)===$captured,'Later staff rate does not rewrite historical timer snapshot');
$service->assign($a,7,$blankRate,1,'company_standalone_task',$task,1,'Original native note',hash('sha256','rate-assign'));
$transferred=$db->query('SELECT target_timer_id FROM '.$p.'company_blank_task_timers WHERE id=?',[$blankRate])->row_array();$timer=(int)$transferred['target_timer_id'];
$target=$details->forStaffTimesheet($a,7,7,'company_standalone_timer',$timer);essentialCheck($target['hourly_rate']==='42.50'&&$target['rate_basis']===$captured['rate_basis']&&$target['note']==='Original native note','Blank assignment transfers captured rate, not current99 rate, and preserves supplied note');
$historical=$details->forStaffTimesheet($a,7,7,'company_standalone_timer',(int)$history[0]['id']);essentialCheck($historical===['hourly_rate'=>null,'note'=>null,'revision'=>0,'rate_basis'=>null,'declared'=>false],'Preexisting timer remains undeclared, never retroactively priced');
essentialThrows(fn()=>$details->forStaffTimesheet($b,7,7,'company_standalone_timer',$timer),'native_timer_source_denied','Cross issuer snapshot denied');
essentialThrows(fn()=>$details->forStaffTimesheet($a,51,7,'company_standalone_timer',$timer),'native_timer_staff_denied','Tasks permission alone does not reveal another staff rate');
$db->query('INSERT INTO '.$p.'staff(staffid,active,admin) VALUES(53,1,0)');$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,53,1)',[$a]);$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,53,?,?)',[$a,'staff','view']);
essentialCheck($details->forStaffTimesheet($a,53,7,'company_standalone_timer',$timer)===$target,'Staff report authority does not require or imply task route permission');
essentialCheck(!(new IssuerResourceAccess($db,$p))->can($a,53,'tasks','view'),'Staff snapshot read never grants task permission');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,$details->tables(),fn()=>$details->captureNewInOwned($a,7,'company_standalone_timer',$timer,new stdClass(),'fake')),'native_timer_creation_proof_required','Owned transaction alone cannot fabricate historical snapshot');
$db->query('UPDATE '.$p.'company_task_timer_details SET issuer_id=? WHERE source_kind=? AND timer_id=?',[$b,'company_standalone_timer',$timer]);essentialThrows(fn()=>$details->forStaffTimesheet($a,7,7,'company_standalone_timer',$timer),'native_timer_source_denied','Corrupted adjunct ownership is not trusted over canonical timer');
$db->query('UPDATE '.$p.'company_task_timer_details SET issuer_id=? WHERE source_kind=? AND timer_id=?',[$a,'company_standalone_timer',$timer]);
echo 'Native timer rate snapshots: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
$raw=$db->query('SELECT rate_snapshot_json FROM '.$p.'company_task_timer_details WHERE source_kind=? AND timer_id=?',['company_standalone_timer',$timer])->row_array();$corrupt=json_decode($raw['rate_snapshot_json'],true);$corrupt['source_id']=51;$db->query('UPDATE '.$p.'company_task_timer_details SET rate_snapshot_json=? WHERE source_kind=? AND timer_id=?',[json_encode($corrupt),'company_standalone_timer',$timer]);
essentialThrows(fn()=>$details->forStaffTimesheet($a,7,7,'company_standalone_timer',$timer),'native_timer_snapshot_invalid','Another staff snapshot cannot be transplanted onto timer');$db->query('UPDATE '.$p.'company_task_timer_details SET rate_snapshot_json=? WHERE source_kind=? AND timer_id=?',[$raw['rate_snapshot_json'],'company_standalone_timer',$timer]);
if($db->driver()==='sqlite'){$db->query('ALTER TABLE '.$p.'company_task_timer_details ADD unexpected TEXT NULL');essentialThrows(fn()=>NativeCompanyTaskTimerDetailsSchema::assertValid($db,$p),'native_timer_details_schema_invalid','Unexpected storage shape fails closed');$db->query('ALTER TABLE '.$p.'company_task_timer_details DROP COLUMN unexpected');NativeCompanyTaskTimerDetailsSchema::assertValid($db,$p);}
echo 'Native snapshot ownership/schema: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";

essentialCheck($details->forOwnTimesheet($a,7,'company_standalone_timer',$timer)===$target,'Own timesheet uses exact actor source');
essentialThrows(fn()=>$details->forOwnTimesheet($a,53,'company_standalone_timer',$timer),'native_timer_source_denied','Staff report authority never changes own-timesheet actor');
$db->query('UPDATE '.$p.'company_standalone_timers SET actor_id=53 WHERE id=?',[$history[0]['id']]);
essentialCheck($details->forOwnTimesheet($a,53,'company_standalone_timer',(int)$history[0]['id'])['declared']===false,'Own historical timesheet needs membership but no tasks permission');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=53',[$a]);
essentialThrows(fn()=>$details->forOwnTimesheet($a,53,'company_standalone_timer',(int)$history[0]['id']),'native_timer_source_denied','Revoked membership denies own timesheet');
echo 'Own timesheet snapshot authority: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
if($db->driver()==='sqlite'){
 $indexes=$db->query('PRAGMA index_list(`'.$p.'company_task_timer_details`)')->result_array();$ownerIndex=array_values(array_filter($indexes,fn($row)=>$row['name']===$p.'ntd_owner'));essentialCheck(count($ownerIndex)===1&&(int)$ownerIndex[0]['unique']===0,'Explicit issuer FK-support index is non-unique, separate from canonical timer primary key');
 $db->query('DROP INDEX `'.$p.'ntd_owner`');essentialThrows(fn()=>NativeCompanyTaskTimerDetailsSchema::assertValid($db,$p),'native_timer_details_schema_invalid','Missing issuer lookup index is not accepted');NativeCompanyTaskTimerDetailsSchema::ensure($db,$p);
 $db->query('CREATE INDEX `'.$p.'unplanned_timer_index` ON `'.$p.'company_task_timer_details` (actor_id)');essentialThrows(fn()=>NativeCompanyTaskTimerDetailsSchema::assertValid($db,$p),'native_timer_details_schema_invalid','Unknown additional index fails exact schema signature');$db->query('DROP INDEX `'.$p.'unplanned_timer_index`');NativeCompanyTaskTimerDetailsSchema::ensure($db,$p);
 essentialCheck($details->forOwnTimesheet($a,7,'company_standalone_timer',$timer)===$target,'Schema replay preserves captured rate and note');
}
echo 'Native timer explicit index guards: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
