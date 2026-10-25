<?php
require __DIR__.'/native_project_form.php';
require_once APPPATH.'services/NativeCompanyTasksRead.php';
$projectSource=(int)$savedSource['source_id'];$ts=new CompanyTasks($db,$p);
$task=$ts->create($a,7,['project_id'=>$projectSource,'assignee_id'=>7,'name'=>'Native overview real timer','description'=>'','due_date'=>'2026-10-27','status'=>'todo','checklist'=>[]],$tk('native-overview-task'));
$ts->activity($a,7,$task,1,'start',$tk('native-overview-start'));
$clock=time();$start=date('Y-m-d H:i:s',$clock-3600);$db->query('UPDATE '.$p.'company_task_timers SET started_at=? WHERE issuer_id=? AND task_id=?',[$start,$a,$task]);
$read=new NativeCompanyTasksRead($db,$p,date_default_timezone_get());$running=$read->projectTimers($a,7,$saved,$clock);
essentialCheck($running['total_seconds']===3600&&$running['member_seconds'][7]===3600&&$running['timers'][0]['end_time']===null,'Original overview derives elapsed running time from actual owned timer and fixed clock');
$ts->activity($a,7,$task,2,'stop',$tk('native-overview-stop'));
$stopped=$read->projectTimers($a,7,$saved,$clock+5);$raw=$db->query('SELECT seconds FROM '.$p.'company_task_timers WHERE issuer_id=? AND task_id=?',[$a,$task])->row_array();
essentialCheck($stopped['total_seconds']===(int)$raw['seconds']&&$stopped['timers'][0]['duration_basis']==='native_persisted_duration_seconds','Stopped overview duration uses persisted authoritative seconds, not guessed wall-clock subtraction');
essentialCheck($stopped['timers'][0]['task_id']===NativeCompanyResourceId::encode('company_task',$task),'Timer and task links carry source namespace, never raw owned IDs');
essentialThrows(fn()=>$read->projectTimers($b,7,$saved,$clock+5),'native_resource_reference_denied','Other company cannot retrieve same numeric timer source');
essentialThrows(fn()=>$read->projectTimers($a,92,$saved,$clock+5),'native_company_task_denied','Project-only create permission does not disclose task timing');
$old=$db->query('SELECT seconds FROM '.$p.'company_task_timers WHERE issuer_id=? AND task_id=?',[$a,$task])->row_array()['seconds'];$db->query('UPDATE '.$p.'company_task_timers SET seconds=-1 WHERE issuer_id=? AND task_id=?',[$a,$task]);essentialThrows(fn()=>$read->projectTimers($a,7,$saved,$clock+5),'native_company_task_timer_invalid','Malformed duration is refused instead of showing zero');$db->query('UPDATE '.$p.'company_task_timers SET seconds=? WHERE issuer_id=? AND task_id=?',[$old,$a,$task]);
echo 'Native project original time projection: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
require_once APPPATH.'services/NativeCompanyProjectHoursChart.php';
require_once APPPATH.'helpers/func_helper.php';require_once APPPATH.'services/utilities/Date.php';require_once APPPATH.'services/utilities/Format.php';
// Execute exact shared helper source without hydrating the unrelated global translator/bootstrap.
foreach(['general_helper'=>['get_weekdays','get_weekdays_original'],'misc_helper'=>['split_weeks_chart_label','get_weekdays_between_dates']]as$file=>$names){$source=file_get_contents(APPPATH.'helpers/'.$file.'.php');foreach($names as$name){$begin=strpos($source,'function '.$name.'(');$end=strpos($source,'/**',$begin);eval(substr($source,$begin,$end-$begin));}}
$chart=(new NativeCompanyProjectHoursChart($stopped,'this_week',true,7,'total_logged_time_only'))->get();
essentialCheck(count($chart['data']['labels'])===7&&array_sum($chart['data']['datasets'][0]['data'])===(float)sec2qty($stopped['total_seconds']),'Same native week chart consumes actual projected persisted duration');
$bad=$stopped;$bad['timers'][0]['total_seconds']='3600';essentialThrows(fn()=>new NativeCompanyProjectHoursChart($bad,'this_week',true,7,'total_logged_time_only'),'native_project_chart_invalid','Chart rejects untyped duration transport');
essentialThrows(fn()=>new NativeCompanyProjectHoursChart($stopped,'this_week',true,7,'billable_unbilled'),'native_project_chart_billing_required','Chart never guesses billable/billed state from timer or task status');
$private=(new NativeCompanyProjectHoursChart($stopped,'this_week',false,92,'total_logged_time_only'))->get();essentialCheck(array_sum($private['data']['datasets'][0]['data'])===0.0,'Original non-create chart privacy filters actual actor, not all company staff');
echo 'Native project same chart renderer composition: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
