<?php
require __DIR__.'/calendar_feed.php';require_once APPPATH.'services/NativeCompanyNotifications.php';
if(!$db->table_exists($p.'contacts'))$db->query('CREATE TABLE '.$p.'contacts(id INT PRIMARY KEY)');
if($db->driver()==='sqlite'){$parts=NativeCompanyHeaderSchema::definitions($p)[NativeCompanyHeaderSchema::NOTIFICATIONS];$parts=array_values(array_filter($parts,static fn($x)=>!str_starts_with($x,'KEY ')&&$x!=='PRIMARY KEY (`id`)'));foreach($parts as&$part){$part=preg_replace('/ CHARACTER SET [a-z0-9]+ COLLATE [a-z0-9_]+/','',$part);$part=preg_replace('/UNIQUE KEY `[^`]+`/','UNIQUE',$part);if(str_starts_with($part,'`id`'))$part='`id` INTEGER PRIMARY KEY AUTOINCREMENT';}unset($part);$db->query('CREATE TABLE '.$p.NativeCompanyHeaderSchema::NOTIFICATIONS.' ('.implode(',',$parts).')');}else NativeCompanyHeaderSchema::ensure($db,$p);
$notifications=new NativeCompanyNotifications($db,$p);$source=NativeCompanyResourceId::decode('event',$event['eventid'])['source_id'];
$record=fn($issuer,$recipient)=>IssuerTransaction::run($db,$p,NativeCompanyNotifications::tables('event'),fn()=>$notifications->recordInOwnedTransaction($issuer,49,$recipient,'event',$source));
$id=$record($a,49);essentialCheck($id===$record($a,49),'event notification replay is unique per source revision/recipient');
essentialThrows(fn()=>$record($b,49),'issuer_notification_source_denied','foreign company event cannot publish notification');
essentialThrows(fn()=>$record($a,50),'issuer_notification_source_denied','private event cannot notify another authorized calendar member');
$seen=$notifications->read($a,49);essentialCheck(count($seen['items'])===1&&str_contains($seen['items'][0]['url'],'utilities/calendar?eventid='),'event notification resolves native canonical calendar link');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=49 AND feature='calendar'",[$a]);
essentialCheck($notifications->read($a,49)['items']===[],'revocation removes event notification from reader');
echo "Native event notifications: 5 SQLite checks passed, no SMTP or cron claim.\n";
