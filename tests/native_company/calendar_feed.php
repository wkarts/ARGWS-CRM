<?php
require __DIR__.'/../settings/calendar_setup.php';
require_once APPPATH.'services/NativeCompanyCalendarFeed.php';require_once APPPATH.'services/NativeCompanyEvents.php';
NativeCompanyEventSchema::ensure($db,$p);
foreach(['firstname','lastname']as$column)if(!IssuerStorage::fieldExists($db,$column,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$column.' varchar(80)');
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
essentialThrows(fn()=>$feed->read($calendarPrimary,49,$query),'native_calendar_primary_uses_historical_model','principal remains original historical calendar, never silently owned-only');
echo "Native calendar feed: 9 real SQLite checks passed; no provider requests.\n";
