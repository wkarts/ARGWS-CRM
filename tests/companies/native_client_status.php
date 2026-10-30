<?php
require __DIR__.'/native_clients.php';
$a=41;$b=82;$client=$nativeA;$actor=12;
if(!$db->table_exists($p.'company_portal_tokens'))$db->query('CREATE TABLE '.$p.'company_portal_tokens(contact_id int)');
if(!$db->table_exists($p.'user_auto_login'))$db->query('CREATE TABLE '.$p.'user_auto_login(user_id int,staff int)');
unset($db->data_cache['table_names']);
$db->query("INSERT INTO {$p}contacts(id,userid,active,is_primary) VALUES(9001,?,1,1)",[$client]);
$db->query("INSERT INTO {$p}contacts(id,userid,active,is_primary) VALUES(9002,?,1,1)",[$nativeB]);
$db->query("INSERT INTO {$p}company_portal_tokens(contact_id) VALUES(9001),(9002)");
$db->query("INSERT INTO {$p}user_auto_login(user_id,staff) VALUES(9001,0),(9001,1),(9002,0)");
$start=$GLOBALS['essentialChecks'];$writer=new CompanyClients($db,$p);$before=$writer->details($a,$actor,$client);$contactBefore=$db->query("SELECT * FROM {$p}contacts WHERE userid=?",[$client])->result_array();
$writer->setActive($a,$actor,$client,false,$before['revision']);$disabled=$writer->details($a,$actor,$client);essentialCheck(array_column($db->query("SELECT contact_id FROM {$p}company_portal_tokens ORDER BY contact_id")->result_array(),'contact_id')===[9002],'client deactivation invalidates only own contact recovery tokens');essentialCheck(count($db->query("SELECT * FROM {$p}user_auto_login")->result_array())===2,'client deactivation retains staff and foreign auto-login rows');essentialCheck($disabled['client']['active']===0,'native client status disables only selected owned identity');
essentialCheck($contactBefore===$db->query("SELECT * FROM {$p}contacts WHERE userid=?",[$client])->result_array(),'client disable preserves contact records and permissions');
essentialThrows(fn()=>$writer->setActive($a,$actor,$client,true,$before['revision']),'company_client_stale','client status requires current native revision');
essentialThrows(fn()=>$writer->setActive($b,$actor,$client,true,$disabled['revision']),'company_client_access_denied','shared/foreign issuer cannot toggle owner status');
$writer->setActive($a,$actor,$client,true,$disabled['revision']);$active=$writer->details($a,$actor,$client);essentialCheck($active['client']['active']===1,'same native client reactivation works without global model');
$db->query("CREATE TRIGGER {$p}ignore_status_audit BEFORE INSERT ON {$p}issuers_audit BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$writer->setActive($a,$actor,$client,false,$active['revision']),'issuer_client_write_unconfirmed','status requires exact new audit receipt');essentialCheck($writer->details($a,$actor,$client)['client']['active']===1,'audit failure rolls status back');
echo 'Native customer status: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
