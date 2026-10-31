<?php
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/issuers/IssuerRegistry.php';
require_once APPPATH.'services/NativeCompanyTasksRead.php';
register_shutdown_function(static function():void{foreach($GLOBALS['essentialFixtures']??[] as $f)foreach(['company_blank_task_timers','taskstimers','tasks'] as $t)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.$t);essentialCleanup();});
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
NativeCompanyBlankTimerSchema::ensure($db,$p);
// Explicit pre-474 synthetic read fixture, not evidence of an installed upgrade.
if(!$db->table_exists($p.'migrations')){$db->query('CREATE TABLE '.$p.'migrations(version INT NOT NULL)');$db->query('INSERT INTO '.$p.'migrations(version) VALUES(447)');unset($db->data_cache['table_names']);}

$r=new IssuerRegistry($db,$p);$a=$r->saveIssuer(7,['legal_name'=>'Timer A','tax_id'=>'11222333000181']);$b=$r->saveIssuer(7,['legal_name'=>'Timer B','tax_id'=>'52998224725']);
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(51,1,0)");
foreach([$a,$b] as $issuer){$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,51,1)",[$issuer]);foreach(['projects','tasks'] as $feature)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,?,'view')",[$issuer,$feature]);}
foreach(['company_tasks','company_standalone_tasks'] as $table)$db->query("CREATE TABLE {$p}{$table}(id INT PRIMARY KEY,issuer_id INT NOT NULL,name VARCHAR(100) NOT NULL,revision INT NOT NULL".($table==='company_tasks'?',project_id INT NOT NULL':'').')');
$db->query('CREATE TABLE '.$p.'company_projects(id INT PRIMARY KEY,issuer_id INT NOT NULL,client_id INT NOT NULL)');
$db->query("INSERT INTO {$p}clients VALUES(11,'Project client A'),(12,'Project client B')");
$db->query("INSERT INTO {$p}issuers_clients VALUES(11,?,'11144477735',7,'2026-10-25 12:00:00'),(12,?,'12345678909',7,'2026-10-25 12:00:00')",[$a,$b]);
$db->query("INSERT INTO {$p}company_projects VALUES(1,?,11),(2,?,12)",[$a,$b]);
foreach(['company_task_timers','company_standalone_timers'] as $table)$db->query("CREATE TABLE {$p}{$table}(id INT PRIMARY KEY,issuer_id INT NOT NULL,task_id INT NOT NULL,actor_id INT NOT NULL,started_at DATETIME NOT NULL,stopped_at DATETIME NULL,seconds INT NOT NULL)");
$db->query("CREATE TABLE {$p}tasks(id INT PRIMARY KEY,name VARCHAR(100) NOT NULL,rel_type VARCHAR(32) NULL,rel_id INT NULL)");$db->query("CREATE TABLE {$p}taskstimers(id INT PRIMARY KEY,task_id INT,staff_id INT NOT NULL,start_time BIGINT NOT NULL,end_time BIGINT NULL)");
$db->query("INSERT INTO {$p}company_tasks VALUES(1,?,'Project A',2,1),(2,?,'Project B',3,2)",[$a,$b]);
$db->query("INSERT INTO {$p}company_standalone_tasks VALUES(1,?,'Standalone A',4),(2,?,'Standalone B',5)",[$a,$b]);
$db->query("INSERT INTO {$p}company_task_timers VALUES(1,?,1,51,'2026-10-25 12:00:00',NULL,0),(2,?,2,51,'2026-10-25 12:00:00',NULL,0),(3,?,1,51,'2026-10-24 12:00:00','2026-10-24 12:01:00',60),(4,?,1,7,'2026-10-25 12:00:00',NULL,0)",[$a,$b,$a,$a]);
$db->query("INSERT INTO {$p}company_standalone_timers VALUES(1,?,1,51,'2026-10-25 12:00:00',NULL,0),(2,?,2,51,'2026-10-25 12:00:00',NULL,0)",[$a,$b]);
$now=strtotime('2026-10-25 12:01:00 UTC');$db->query("INSERT INTO {$p}tasks VALUES(1,'Legacy only',NULL,NULL)");$db->query("INSERT INTO {$p}taskstimers VALUES(1,1,7,?,NULL)",[$now-30]);unset($db->data_cache['table_names']);
$reader=new NativeCompanyTasksRead($db,$p);$rows=$reader->startedTimers($a,51,$now);
essentialCheck(count($rows)===2&&array_unique(array_column($rows,'issuer_id'))===[$a],'A timers exclude B, other actor and legacy');
$ids=array_column($rows,'id');essentialCheck(count(array_unique($ids))===2,'same source timer id is namespaced');
$by=[];foreach($rows as $row)$by[NativeCompanyResourceId::decode('task',$row['task_id'])['source_kind']]=$row;
essentialCheck($by['company_task']['total_seconds']===120&&$by['company_standalone_task']['total_seconds']===60,'real own completed and running seconds projected');
essentialCheck($by['company_task']['revision']===2&&$by['company_standalone_task']['revision']===4,'task revision retained for writes');
$db->query("INSERT INTO {$p}clients VALUES(10,'Private A')");$db->query("INSERT INTO {$p}issuers_clients VALUES(10,?,'52998224725',7,'2026-10-25 12:00:00')",[$a]);
$db->query("INSERT INTO {$p}tasks VALUES(2,'Private related task','customer',10)");$db->query("INSERT INTO {$p}taskstimers VALUES(2,2,7,?,NULL)",[$now-30]);
$legacy=$reader->startedTimers(1,7,$now);essentialCheck(count($legacy)===1&&$legacy[0]['id']==='1'&&$legacy[0]['lineage']==='legacy','principal excludes legacy timer related to private secondary customer');
essentialCheck(is_string($legacy[0]['revision'])&&preg_match('/^[a-f0-9]{64}$/D',$legacy[0]['revision'])&&$legacy[0]['note_revision']===0,'Legacy timer has exact row fingerprint, not a fabricated integer revision');
$legacyOriginal=$legacy[0]['revision'];$db->query('UPDATE '.$p.'taskstimers SET start_time=start_time-1 WHERE id=1');essentialCheck($reader->startedTimers(1,7,$now)[0]['revision']!==$legacyOriginal,'Changed legacy timer changes CAS fingerprint');$db->query('UPDATE '.$p.'taskstimers SET start_time=start_time+1 WHERE id=1');
$db->query('UPDATE '.$p.'migrations SET version=474');essentialThrows(fn()=>$reader->startedTimers($a,51,$now),'native_timer_details_schema_invalid','474 marker cannot hide a missing snapshot schema');$db->query('UPDATE '.$p.'migrations SET version=447');
$localClock=(new DateTimeImmutable('2026-10-25 12:01:00',new DateTimeZone('America/Sao_Paulo')))->getTimestamp();
$localRows=(new NativeCompanyTasksRead($db,$p,'America/Sao_Paulo'))->startedTimers($a,51,$localClock);$localBy=[];foreach($localRows as$row)$localBy[NativeCompanyResourceId::decode('task',$row['task_id'])['source_kind']]=$row;
essentialCheck($localBy['company_task']['total_seconds']===120&&$localBy['company_task']['storage_timezone']==='America/Sao_Paulo'&&$localBy['company_task']['start_time']===$localClock-60,'owned historical datetime uses the same explicit runtime clock basis as its canonical writer');
$db->query('UPDATE '.$p.'company_tasks SET project_id=2 WHERE id=1');$db->failOn='SELECT name FROM '.$p.'company_tasks';essentialThrows(fn()=>$reader->startedTimers($a,51,$now),'native_company_task_denied','Cross-owner project parent denies before task label hydration');$db->failOn=null;$db->query('UPDATE '.$p.'company_tasks SET project_id=1 WHERE id=1');
$db->query('UPDATE '.$p.'company_projects SET client_id=12 WHERE id=1');essentialThrows(fn()=>$reader->startedTimers($a,51,$now),'native_company_task_denied','Project owner does not override another company customer');$db->query('UPDATE '.$p.'company_projects SET client_id=11 WHERE id=1');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=51 AND feature='projects'",[$a]);
$standalone=$reader->startedTimers($a,51,$now);essentialCheck(count($standalone)===1&&$standalone[0]['task_subject']==='Standalone A','revoked project grant cannot hide legitimate standalone timer or expose project');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=51",[$a]);
essentialThrows(fn()=>$reader->startedTimers($a,51,$now),'native_company_task_denied','revoked company membership denies all timers');
echo "Native task timer read: {$GLOBALS['essentialChecks']} checks passed.\n";
