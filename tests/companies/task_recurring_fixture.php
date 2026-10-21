<?php
register_shutdown_function(static function(){global$db,$p;if(isset($db,$p))foreach(['company_task_schedule_runs','company_task_schedule_requests','company_task_schedules','company_task_schedule_cursor']as$t)$db->query('DROP TABLE IF EXISTS '.$p.$t);});
require __DIR__.'/projects_operations.php';
require_once APPPATH.'services/CompanyTaskRecurring.php';
if($db->driver()!=='mysql'){fwrite(STDERR,'Task recurrence requires an actual MySQL/MariaDB test database.'.PHP_EOL);exit(2);}
$recurringStart=$GLOBALS['essentialChecks'];$db->queries=[];
CompanyTaskPrioritySchema::ensure($db,$p);CompanyApiSchema::ensure($db,$p);CompanyTaskRecurringSchema::ensure($db,$p);CompanyTaskRecurringSchema::ensure($db,$p);
$db->query("UPDATE {$p}issuers_registry SET tax_id='11144477735' WHERE id=?",[$primary]);
$rr=new CompanyTaskRecurring($db,$p);$standalone=new CompanyStandaloneTasks($db,$p);$sources=[];$rrProjects=[];
function rrCheck(bool $v,string $label):void{global$db;essentialCheck($v,$label);$db->queries=[];}
function rrThrows(callable $f,string $expected,string $label):void{global$db;essentialThrows($f,$expected,$label);$db->queries=[];}
function rrCalendar(array $replace=[]):array{return array_replace(['unit'=>'day','interval'=>1,'first_date'=>'2026-10-31','timezone'=>'UTC','cycles'=>3,'due_days'=>1,'require_previous_done'=>0],$replace);}
function rrTaskInput(array $row,array $changes=[]):array{$out=[];foreach(['project_id','assignee_id','name','description','due_date','status','priority']as$f)if(array_key_exists($f,$row))$out[$f]=$row[$f];$out['checklist']=$row['checklist'];return array_replace($out,$changes);}
function rrSnapshot():array{global$db,$p;$out=[];foreach(['company_tasks','company_standalone_tasks','company_task_priorities','company_standalone_priorities','company_task_followers','company_task_comments','company_standalone_comments','company_task_timers','company_standalone_timers','company_task_schedules','company_task_schedule_runs','company_task_schedule_requests','issuers_audit']as$t)$out[$t]=$db->query('SELECT * FROM '.$p.$t)->result_array();$db->queries=[];return $out;}
foreach([[$a,$ca],[$b,$cb],[$primary,$cp]]as[$i,$client]){
    $pr=$ps->create($i,7,['client_id'=>$client,'responsible_id'=>7,'name'=>'Recurrence parent '.$i,'description'=>'Private parent','start_date'=>'2026-01-01','due_date'=>'2099-12-31'],$key('recurrence-project-'.$i));$rrProjects[$i]=$pr;
    foreach(['project','standalone']as$kind){$w=$kind==='project'?$ts:$standalone;$in=['assignee_id'=>7,'name'=>'Recurring model '.$kind.' '.$i,'description'=>'Private immutable description','due_date'=>'2026-10-25','status'=>'todo','checklist'=>[['text'=>'Original check','done'=>0]],'priority'=>3];if($kind==='project')$in=['project_id'=>$pr]+$in;$id=$w->create($i,7,$in,$key('rr-source-'.$kind.'-'.$i));$w->activity($i,7,$id,1,'comment',$key('rr-comment-'.$kind.'-'.$i),'History stays on source');$w->activity($i,7,$id,2,'start',$key('rr-timer-'.$kind.'-'.$i));$w->activity($i,7,$id,3,'stop',$key('rr-stop-'.$kind.'-'.$i));$w->update($i,7,$id,4,array_replace($in,['status'=>'done','checklist'=>[['text'=>'Original check','done'=>1]]]),$key('rr-done-'.$kind.'-'.$i));$sources[$kind][$i]=$id;$db->queries=[];}
}
$financialBefore=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
