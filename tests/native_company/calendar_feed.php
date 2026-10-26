<?php
require __DIR__.'/../settings/calendar_setup.php';
require_once APPPATH.'services/NativeCompanyCalendarFeed.php';require_once APPPATH.'services/NativeCompanyEvents.php';
NativeCompanyEventSchema::ensure($db,$p);
foreach(['firstname','lastname']as$column)if(!IssuerStorage::fieldExists($db,$column,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$column.' varchar(80)');
if(!IssuerStorage::fieldExists($db,'profile_image',$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD profile_image varchar(191) NULL');
$db->query("UPDATE {$p}staff SET firstname='Synthetic',lastname='Staff'");
if($db->driver()==='sqlite'){$parts=NativeCompanyAuxiliarySchema::definitions($p)['company_reminders'];$parts=array_values(array_filter($parts,static fn($x)=>!str_starts_with($x,'KEY ')&&$x!=='PRIMARY KEY (`id`)'));foreach($parts as&$part){$part=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$part);$part=preg_replace('/UNIQUE KEY `[^`]+`/','UNIQUE',$part);if(str_starts_with($part,'`id`'))$part='`id` INTEGER PRIMARY KEY AUTOINCREMENT';}unset($part);$db->query('CREATE TABLE '.$p.'company_reminders ('.implode(',',$parts).')');}else NativeCompanyAuxiliarySchema::ensure($db,$p);
foreach([$a,$b]as$i)$calendarPreferences->save($i,49,0,$calendarAll);
$event=(new NativeCompanyEvents($db,$p))->save($a,49,['title'=>'Own native event','description'=>'Synthetic','start'=>'2026-10-23 12:00:00','end'=>'','public'=>0,'color'=>'#112233','reminder_before'=>0,'reminder_before_type'=>'minutes']);
$db->query('INSERT INTO '.$p.'company_reminders(issuer_id,rel_type,rel_id,source_kind,source_id,staff,creator,description,date,isnotified,notify_by_email,revision) VALUES(?,?,?,?,?,?,?,?,?,0,0,1)',[$a,'invoice',$calendarRows[$a]['invoices'],'invoice',$calendarRows[$a]['invoices'],49,49,'Calendar reminder','2026-10-23 14:00:00']);
$feed=new NativeCompanyCalendarFeed($db,$p);$query=['start'=>'2026-10-01T00:00:00-03:00','end'=>'2026-11-01T00:00:00-03:00'];$rows=$feed->read($a,49,$query);
essentialCheck(count($rows)===9,'seven real document sources plus actual event and reminder');
essentialCheck(!str_contains(json_encode($rows),'company_'),'native feed does not expose parallel route/source links');
essentialCheck(count(array_filter($rows,fn($r)=>isset($r['native_path'])&&str_starts_with($r['native_path'],'invoices/list_invoices/')))===2,'document and reminder link to original invoice route');
essentialCheck(count($feed->read($b,49,$query))===7,'company B cannot see A event or reminder');
essentialCheck(count($feed->read($a,49,$query+['calendar_filters'=>'true','events'=>'true']))===1,'original checkbox filter selects only events');
foreach([['start'=>'2026-02-30','end'=>'2026-03-01'],['start'=>'2026-10-01T99:00:00Z','end'=>'2026-11-01'],['start'=>'2026-01-01','end'=>'2027-01-01']]as$bad)essentialThrows(fn()=>NativeCompanyCalendarFeed::filters($bad),'native_calendar_range_invalid','malformed or excessive range denied');
// Principal uses this exact feed and retains both canonical and historical lineage.
$calendarPreferences->save($calendarPrimary,49,0,$calendarAll);
foreach(NativeCompanyCalendarPreferences::defaults()as$key=>$default){$value=$calendarAll[$key]??$default;if($db->query('SELECT name FROM '.$p.'options WHERE name=?',[$key])->row_array())$db->query('UPDATE '.$p.'options SET value=? WHERE name=?',[$value,$key]);else$db->query('INSERT INTO '.$p.'options(name,value) VALUES(?,?)',[$key,$value]);}
if(!$db->table_exists($p.'events'))$db->query('CREATE TABLE '.$p.'events(eventid INTEGER PRIMARY KEY,userid INTEGER,title VARCHAR(100),description TEXT,start DATETIME,end DATETIME,public INTEGER,color VARCHAR(7))');
$db->query('INSERT INTO '.$p.'events(eventid,userid,title,description,start,end,public,color) VALUES(900,49,?,?,?,?,0,?)',['Historical principal event','Synthetic legacy source','2026-10-23 12:00:00',null,'#112233']);
$db->query('INSERT INTO '.$p.'invoices(clientid,number,datecreated,date,duedate,currency,subtotal,total,hash,discount_type,include_shipping,status) VALUES(?,?,?,?,?,1,7,7,?,?,0,1)',[9999,901,'2026-10-01 00:00:00','2026-10-01','2026-10-23',str_repeat('c',32),'after_tax']);$historical=(int)$db->insert_id();
// The persisted legacy client proves historical ownership; a private customer's
// legacy-looking invoice must never be exposed through that same table.
$db->query('INSERT INTO '.$p.'invoices(clientid,number,datecreated,date,duedate,currency,subtotal,total,hash,discount_type,include_shipping,status) VALUES(?,?,?,?,?,1,7,7,?,?,0,1)',[$ca,902,'2026-10-01 00:00:00','2026-10-01','2026-10-23',str_repeat('d',32),'after_tax']);$foreignLegacy=(int)$db->insert_id();
$principal=$feed->read($calendarPrimary,49,$query);$ids=array_column($principal,'id');
essentialCheck(in_array('event:900',$ids,true)&&in_array('legacy_invoices:'.$historical,$ids,true),'principal retains historical event and invoice in the shared native DTO');
essentialCheck(!in_array('legacy_invoices:'.$foreignLegacy,$ids,true)&&!str_contains(json_encode($principal),'Private project <script> '.$a),'principal never inherits a named company document through legacy storage');
$secondaryIds=array_column($feed->read($a,49,$query),'id');essentialCheck(!in_array('event:900',$secondaryIds,true)&&!in_array('legacy_invoices:'.$historical,$secondaryIds,true),'secondary never receives principal historical IDs');
$reader=new NativeCompanyLegacyCalendarRead($db,$p);$reader->read($calendarPrimary,49,NativeCompanyCalendarFeed::filters($query), (new NativeCompanyCalendarPreferences($db,$p))->page($calendarPrimary,49)['values']);
$db->query('UPDATE '.$p.'invoices SET duedate=? WHERE id=?',['2026-11-05',$historical]);essentialThrows(fn()=>$reader->confirm($calendarPrimary,49),'native_calendar_stale','prepared historical frame is invalidated when document changes');
$db->query('UPDATE '.$p.'invoices SET duedate=? WHERE id=?',['2026-10-23',$historical]);
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=49 AND feature=?',[$calendarPrimary,'invoices']);
essentialCheck(!in_array('legacy_invoices:'.$historical,array_column($feed->read($calendarPrimary,49,$query),'id'),true),'current source permission revocation prevents historical title delivery');
echo "Native calendar feed: 9 real SQLite checks passed; no provider requests.\n";
