<?php
require __DIR__.'/native_clients.php';
$start=$GLOBALS['essentialChecks'];
$input=['company'=>'Same native audit','vat'=>''];$new=$clients->create(41,12,$input,hash('sha256','audit-created'));
$details=$clients->details(41,12,$new);$clients->update(41,12,$new,$input,$details['revision']);
$prior=$db->query("SELECT * FROM {$p}issuers_audit ORDER BY id DESC LIMIT 1")->row_array();
essentialCheck(strlen(json_decode($prior['metadata_json'],true)['native_write_nonce']??'')===64,'each native write audit carries non-secret random confirmation nonce');
$db->query("CREATE TRIGGER {$p}ignore_native_audit BEFORE INSERT ON {$p}issuers_audit BEGIN SELECT RAISE(IGNORE); END");
// Repeating identical subject/actor/event in the same clock second must not reuse the last receipt.
$revision=$clients->details(41,12,$new)['revision'];
essentialThrows(fn()=>$clients->update(41,12,$new,$input,$revision),'issuer_client_write_unconfirmed','ignored identical audit cannot reuse prior successful insert ID');
essentialCheck($db->query("SELECT * FROM {$p}issuers_audit ORDER BY id DESC LIMIT 1")->row_array()===$prior,'failed attempt preserves prior immutable receipt');
$changed=['company'=>'Must roll back','vat'=>''];essentialThrows(fn()=>$clients->update(41,12,$new,$changed,$revision),'issuer_client_write_unconfirmed','ignored audit fails transaction even if customer mutation succeeded');
essentialCheck($clients->details(41,12,$new)['client']['company']==='Same native audit','missing receipt rolls back business mutation');
$db->query("DROP TRIGGER {$p}ignore_native_audit");
$clients->update(41,12,$new,$changed,$revision);$after=$db->query("SELECT * FROM {$p}issuers_audit ORDER BY id DESC LIMIT 1")->row_array();
essentialCheck($after['metadata_json']!==$prior['metadata_json'],'next valid write records fresh nonce instead of reusing old receipt');
echo 'Native customer audit confirmation: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
