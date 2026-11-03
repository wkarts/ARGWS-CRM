<?php
require __DIR__.'/staff_read.php';require_once APPPATH.'services/NativeCompanyStaffTimesheets.php';
$db->query('UPDATE '.$p.'issuers_registry SET active=1 WHERE id=82');$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE staff_id=12');
$tables=[
'company_projects'=>'id INT PRIMARY KEY,issuer_id INT,client_id INT,name TEXT',
'company_tasks'=>'id INT PRIMARY KEY,issuer_id INT,project_id INT,name TEXT',
'company_standalone_tasks'=>'id INT PRIMARY KEY,issuer_id INT,name TEXT',
'company_task_timers'=>'id INT PRIMARY KEY,issuer_id INT,task_id INT,actor_id INT,started_at TEXT,stopped_at TEXT,seconds INT',
'company_standalone_timers'=>'id INT PRIMARY KEY,issuer_id INT,task_id INT,actor_id INT,started_at TEXT,stopped_at TEXT,seconds INT',
'company_blank_task_timers'=>'id INT PRIMARY KEY,issuer_id INT,actor_id INT,started_at TEXT,closed_at TEXT,state TEXT,revision INT,target_kind TEXT,target_id INT,target_timer_id INT,note TEXT',
'company_invoice_requests'=>'request_key TEXT PRIMARY KEY,issuer_id INT,invoice_id INT',
'company_time_billing_claims'=>'timer_id INT PRIMARY KEY,issuer_id INT,task_id INT,origin_id INT,state TEXT,project_id INT',
'company_task_time_batch_claims'=>'timer_id INT PRIMARY KEY,issuer_id INT,task_id INT,origin_id INT,state TEXT,project_id INT',
'company_standalone_time_claims'=>'timer_id INT PRIMARY KEY,issuer_id INT,task_id INT,origin_id INT,state TEXT',
'company_time_billing_batches'=>'origin_id INT PRIMARY KEY,issuer_id INT,state TEXT,invoice_id INT',
'company_task_time_batches'=>'origin_id INT PRIMARY KEY,issuer_id INT,state TEXT,invoice_id INT',
'company_time_billing_origins'=>'id INT PRIMARY KEY,issuer_id INT,project_id INT',
'company_task_time_batch_origins'=>'id INT PRIMARY KEY,issuer_id INT,source_kind TEXT',
];foreach(['company_task_billing'=>'task_id','company_standalone_billing'=>'task_id','company_project_fixed_billing'=>'project_id']as$family=>$field){$tables[$family.'_links']=$field.' INT PRIMARY KEY,issuer_id INT,origin_id INT,state TEXT,invoice_id INT';$tables[$family.'_origins']='id INT PRIMARY KEY,issuer_id INT,'.$field.' INT';}
foreach($tables as$t=>$columns)if(!$db->table_exists($p.$t))$db->query('CREATE TABLE '.$p.$t.'('.$columns.')');
NativeCompanyTaskTimerDetailsSchema::ensure($db,$p);
$db->query('INSERT INTO '.$p.'company_projects VALUES(201,41,101,?),(202,82,102,?)',['Project A','Project B']);
// Fixture client ownership is authoritative; no customer label is read by the timesheet.
$db->query('DELETE FROM '.$p.'issuers_clients WHERE client_id IN(101,102)');$db->query('INSERT INTO '.$p.'issuers_clients(owner_issuer_id,client_id,created_by,created_at) VALUES(41,101,7,"2026-10-25 00:00:00"),(82,102,7,"2026-10-25 00:00:00")');
$db->query('INSERT INTO '.$p.'company_tasks VALUES(301,41,201,?),(302,82,202,?)',['Task A','Secret B']);
$db->query('INSERT INTO '.$p.'company_standalone_tasks VALUES(401,41,?)',['Standalone A']);
$db->query('INSERT INTO '.$p.'company_task_timers VALUES(1,41,301,12,?,?,3600),(2,82,302,12,?,?,7200)',['2026-10-25 08:00:00','2026-10-25 09:00:00','2026-10-25 08:00:00','2026-10-25 10:00:00']);
$db->query('INSERT INTO '.$p.'company_standalone_timers VALUES(1,41,401,12,?,?,1800)',['2026-10-25 09:00:00','2026-10-25 09:30:00']);
$db->query('INSERT INTO '.$p.'company_blank_task_timers VALUES(1,41,12,?,NULL,?,1,NULL,NULL,NULL,?)',['2026-10-25 11:00:00','active','Blank native note']);
$rate=['hourly_rate'=>'42.50','revision'=>1,'source_kind'=>'company_staff_rate','source_id'=>12];$db->query('INSERT INTO '.$p.'company_task_timer_details VALUES(?,?,41,12,?,?,1)',['company_task_timer',1,json_encode($rate),'Saved note']);
$reader=new NativeCompanyStaffTimesheets($db,$p,'UTC');$now=strtotime('2026-10-25 12:00:00 UTC');$result=$reader->read(41,12,12,['today'=>true],false,$now);
essentialCheck($result['total']===9000&&count($result['timesheets'])===3&&!str_contains(json_encode($result),'Secret B'),'staff report unions three real namespaces without another company timer');
$project=NativeCompanyResourceId::encode('company_task_timer',1);$standalone=NativeCompanyResourceId::encode('company_standalone_timer',1);$blank=NativeCompanyResourceId::encode('company_blank_task_timer',1);
essentialCheck($result['timesheets'][$project]['hourly_rate']==='42.50'&&$result['timesheets'][$standalone]['hourly_rate']===null&&$result['timesheets'][$blank]['note']==='Blank native note','captured wage remains explicit and historical unknown never becomes current global wage');
essentialCheck(!$result['timesheets'][$project]['can_open_task'],'staff view does not grant task route access');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE staff_id=12 AND feature=?',['staff']);$own=$reader->read(41,12,12,['today'=>true],true,$now);essentialCheck($own===$result,'own authenticated report needs membership not staff or task capability');
settingThrows(fn()=>$reader->read(41,12,14,[],true,$now),'native_staff_timesheet_denied','own report cannot target another employee');settingThrows(fn()=>$reader->read(41,12,12,[],false,$now),'native_staff_timesheet_denied','administrative report cannot borrow self authority');
$db->query('INSERT INTO '.$p.'issuers_resource_permissions VALUES(41,12,?,?)',['staff','view']);
$period=$reader->read(41,12,12,['period-from'=>'2026-10-24','period-to'=>'2026-10-24'],false,$now);essentialCheck($period['timesheets']===[]&&$period['total']===9000,'native period filter preserves actual lifetime totals');
settingThrows(fn()=>$reader->read(41,12,12,['period-from'=>'2026-02-30','period-to'=>'2026-10-25'],false,$now),'native_staff_timesheet_filter_invalid','impossible dates not normalized silently');
$db->query('UPDATE '.$p.'company_projects SET issuer_id=82 WHERE id=201');settingThrows(fn()=>$reader->read(41,12,12,[],false,$now),'native_staff_timesheet_denied','cross-company parent fails before task label');$db->query('UPDATE '.$p.'company_projects SET issuer_id=41 WHERE id=201');
$db->query('INSERT INTO '.$p.'company_task_billing_origins VALUES(1,41,301)');$db->query('INSERT INTO '.$p.'company_task_billing_links VALUES(301,41,1,?,NULL)',['converting']);$pending=$reader->read(41,12,12,['today'=>true],false,$now);essentialCheck($pending['timesheets'][$project]['billed']===null&&$pending['timesheets'][$project]['billing_state']==='converting','uncertain allocation is not shown as unbilled or confirmed billed');
$db->query('UPDATE '.$p.'company_task_billing_links SET state=?,invoice_id=999 WHERE task_id=301',['converted']);settingThrows(fn()=>$reader->read(41,12,12,[],false,$now),'native_staff_billing_unconfirmed','converted link without native receipt cannot fabricate billing');
echo "Native staff timesheets: eleven real SQLite reader checks; no route admission or browser.\n";
if(!$db->table_exists($p.'invoices'))$db->query('CREATE TABLE '.$p.'invoices(id INT PRIMARY KEY,issuer_id INT)');
if(!$db->field_exists('issuer_id',$p.'invoices'))$db->query('ALTER TABLE '.$p.'invoices ADD issuer_id INT NULL');
$db->query('INSERT INTO '.$p.'invoices(id,issuer_id) VALUES(999,41)');$key=hash('sha256','company-task-invoice:41:301');$db->query('INSERT INTO '.$p.'company_invoice_requests(request_key,issuer_id,invoice_id) VALUES(?,41,999)',[$key]);
$confirmed=$reader->read(41,12,12,['today'=>true],false,$now);essentialCheck($confirmed['timesheets'][$project]['billed']===true,'real owned native invoice receipt confirms allocation without exposing values');
$db->query('UPDATE '.$p.'invoices SET issuer_id=82 WHERE id=999');settingThrows(fn()=>$reader->read(41,12,12,[],false,$now),'native_staff_billing_unconfirmed','foreign invoice cannot substantiate allocation');$db->query('UPDATE '.$p.'invoices SET issuer_id=41 WHERE id=999');
$db->query('DELETE FROM '.$p.'company_invoice_requests');$db->query('DELETE FROM '.$p.'company_task_billing_links');
$db->query('INSERT INTO '.$p.'company_task_time_batch_origins VALUES(20,41,?)',['projects']);$db->query('INSERT INTO '.$p.'company_task_time_batch_claims VALUES(1,41,301,20,?,201)',['converted']);$db->query('INSERT INTO '.$p.'company_task_time_batches VALUES(20,41,?,999)',['converted']);$db->query('INSERT INTO '.$p.'company_invoice_requests(request_key,issuer_id,invoice_id) VALUES(?,41,999)',[hash('sha256','company-task-time-multi-batch-invoice:41:20')]);
$batch=$reader->read(41,12,12,['today'=>true],false,$now);essentialCheck($batch['timesheets'][$project]['billed']===true&&!$batch['timesheets'][$standalone]['billed'],'timer-specific batch does not mark another namespace timer billed');
$db->query('UPDATE '.$p.'company_task_time_batch_claims SET issuer_id=82 WHERE timer_id=1');settingThrows(fn()=>$reader->read(41,12,12,[],false,$now),'native_staff_billing_unconfirmed','transplanted timer claim fails closed');$db->query('UPDATE '.$p.'company_task_time_batch_claims SET issuer_id=41 WHERE timer_id=1');
$db->query('UPDATE '.$p.'company_projects SET issuer_id=82 WHERE id=201');$offset=count($db->queries);settingThrows(fn()=>$reader->read(41,12,12,[],false,$now),'native_staff_timesheet_denied','parent authority rechecked before label');$queries=array_slice($db->queries,$offset);essentialCheck(count(array_filter($queries,fn($q)=>str_contains($q[0],'SELECT name FROM')))===0,'query spy proves no task/project label hydration after foreign parent');$db->query('UPDATE '.$p.'company_projects SET issuer_id=41 WHERE id=201');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12');settingThrows(fn()=>$reader->read(41,12,12,[],true,$now),'native_staff_timesheet_denied','revoked membership blocks self report');
echo "Native staff timesheets: seven additional actual SQLite allocation and pre-hydration checks.\n";
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12');
$db->query('INSERT INTO '.$p.'company_blank_task_timers VALUES(2,41,12,?,?,?,2,?,401,1,?)',['2026-10-25 09:00:00','2026-10-25 09:30:00','assigned','company_standalone_task','Transferred']);$transferred=$reader->read(41,12,12,['today'=>true],false,$now);essentialCheck($transferred['total']===9000&&count($transferred['timesheets'])===3,'transferred blank interval is represented once by its actual target timer');
$db->query('UPDATE '.$p.'company_standalone_timers SET started_at=?,stopped_at=? WHERE id=1',['2026-10-25 10:00:00','2026-10-25 10:30:00']);settingThrows(fn()=>$reader->read(41,12,12,[],false,$now),'native_staff_timesheet_invalid','same-duration different transfer interval is rejected');
echo "Staff timer transfer: two exact interval/deduplication checks.\n";
$db->query('UPDATE '.$p.'company_standalone_timers SET started_at=?,stopped_at=? WHERE id=1',['2026-10-25 09:00:00','2026-10-25 09:30:00']);$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=12');$inactive=$reader->read(41,7,12,['today'=>true],false,$now);essentialCheck($inactive['total']===9000&&$inactive['timesheets'][$project]['hourly_rate']==='42.50','inactive target retains real administrative history and captured wage');settingThrows(fn()=>$reader->read(41,12,12,['today'=>true],true,$now),'native_staff_timesheet_denied','inactive actor cannot use own timesheet authority');echo "Inactive staff timer history: two actual SQLite checks.\n";
$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=12');
