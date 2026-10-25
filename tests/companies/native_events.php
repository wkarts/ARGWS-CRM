<?php
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/issuers/IssuerRegistry.php';
require_once APPPATH.'services/NativeCompanyEvents.php';
register_shutdown_function('essentialCleanup');
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$registry=new IssuerRegistry($db,$p);$a=$registry->saveIssuer(7,['legal_name'=>'Events A','tax_id'=>'11222333000181']);$b=$registry->saveIssuer(7,['legal_name'=>'Events B','tax_id'=>'52998224725']);
NativeCompanyEventSchema::ensure($db,$p);NativeCompanyEventSchema::ensure($db,$p);
$db->query("CREATE TABLE {$p}events(eventid INT NOT NULL PRIMARY KEY,userid INT NOT NULL,title VARCHAR(191),description TEXT,start DATETIME,end DATETIME,public INT,color VARCHAR(7),reminder_before INT,reminder_before_type VARCHAR(7),isstartnotified INT)");unset($db->data_cache['table_names']);
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(51,1,0),(52,1,0)");foreach([51,52]as$actor){$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,?,1)",[$a,$actor]);$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,'calendar','view')",[$a,$actor]);}
$events=new NativeCompanyEvents($db,$p);$read=new NativeCompanyCalendar($db,$p);
$input=['title'=>'Owned event','description'=>'Plain text','start'=>'2026-10-25 15:00:00','end'=>'2026-10-25 16:00:00','public'=>'0','color'=>'#123456','reminder_before'=>'10','reminder_before_type'=>'minutes'];
$created=$events->save($a,51,$input);
if($db->driver()==='sqlite'){
$db->query("CREATE TRIGGER {$p}ignore_event_source BEFORE INSERT ON {$p}company_events BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$events->save($a,51,$input),'issuer_storage_failed','ignored identical event cannot return old identity');
$db->query('DROP TRIGGER '.$p.'ignore_event_source');}
essentialCheck($created['revision']==='1','new native event has confirmed revision');
essentialCheck($read->event($a,51,$created['eventid'])['title']==='Owned event','same native event DTO');
essentialThrows(fn()=>$read->event($b,7,$created['eventid']),'native_event_denied','foreign company cannot read exact guessed ID');
essentialThrows(fn()=>$read->event($a,52,$created['eventid']),'native_event_denied','private event hidden from different member');
essentialThrows(fn()=>$events->save($a,52,$input,$created['eventid'],'1'),'native_event_denied','calendar view does not allow editing another member event');
$updated=$events->save($a,51,['title'=>'Changed','public'=>'1']+$input,$created['eventid'],'1');essentialCheck($updated['revision']==='2','native edit advances CAS');
essentialCheck($read->event($a,52,$created['eventid'])['title']==='Changed','public event visible only inside same company');
essentialThrows(fn()=>$events->save($a,51,$input,$created['eventid'],'1'),'native_event_stale','stale event form rejected');
if($db->driver()==='sqlite'){$db->query("CREATE TRIGGER {$p}event_audit_fail BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='native_event_saved' BEGIN SELECT RAISE(IGNORE); END");essentialThrows(fn()=>$events->save($a,51,['title'=>'Rollback']+$input,$created['eventid'],'2'),'issuer_storage_failed','ignored audit cannot reuse old receipt');essentialCheck($read->event($a,51,$created['eventid'])['title']==='Changed','audit failure rolls back event');$db->query('DROP TRIGGER '.$p.'event_audit_fail');}
$dashboard=NativeCompanyCalendar::dashboard($db,$p,$a,51,new DateTimeImmutable('2026-10-25 12:00:00'));essentialCheck(count($dashboard['upcoming_events'])===1,'last day of native week retains event');
$events->delete($a,51,$created['eventid'],'2');essentialThrows(fn()=>$read->event($a,51,$created['eventid']),'native_event_denied','deleted event cannot hydrate');
foreach([['start'=>'2026-02-30 12:00:00'],['end'=>'2026-10-24 12:00:00'],['public'=>'true'],['color'=>'red'],['issuer_id'=>$b]]as$bad)essentialThrows(fn()=>NativeCompanyEvents::prepare($bad+$input),'native_event_invalid','invalid or overposted event rejected');
echo "Native events PDO isolation/CAS/audit rollback/dashboard boundaries passed.\n";
