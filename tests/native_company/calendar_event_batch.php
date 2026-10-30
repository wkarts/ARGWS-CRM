<?php
require __DIR__.'/calendar_event_mail.php';
// Real writer fixtures: a future prefix must not starve a due event after a full batch.
for($n=0;$n<501;$n++)$events->save(41,7,array_replace($input,['title'=>'Future batch '.$n,'start'=>'2027-01-01 10:00:00']));
$due=$events->save(41,7,array_replace($input,['title'=>'Due after batch']));
$result=$worker->run('2026-10-25 13:00:00','https://example.test/crm',$decrypt);
essentialCheck($result===['notified'=>1,'deferred'=>0],'keyset scan crosses 500 future entries to process a due event');
essentialCheck($worker->run('2026-10-25 13:00:00','https://example.test/crm',$decrypt)===['notified'=>0,'deferred'=>0],'full future batches terminate without duplicate dispatch');
echo "Calendar event finite keyset batch: two actual SQLite checks passed.\n";
