<?php
require __DIR__.'/native_portal_historical_verification.php';
$start=$GLOBALS['essentialChecks'];$portal=new CompanyPortal($db,$p,$portalSecret);$key=bin2hex(random_bytes(16));$password='SyntheticPasswordOnly123!';$hash=fn($v)=>password_hash($v,PASSWORD_BCRYPT);
$db->query('UPDATE '.$p.'clients SET active=1,registration_confirmed=1 WHERE userid=8001');$db->query('UPDATE '.$p.'contacts SET active=1,new_pass_key=?,new_pass_key_requested=? WHERE id=8002',[$key,gmdate('Y-m-d H:i:s')]);
essentialCheck(!$portal->resetHistoricalPrimary($b,8002,$key,$password,$hash),'named issuer cannot use historical principal reset');
essentialCheck(!$portal->resetHistoricalPrimary($primary,8002,str_repeat('f',32),$password,$hash),'wrong reset key denied');
essentialCheck($portal->resetHistoricalPrimary($primary,8002,$key,$password,$hash)&&!$portal->resetHistoricalPrimary($primary,8002,$key,$password,$hash),'historical reset consumes once under issuer lock');
essentialCheck(password_verify($password,$db->query('SELECT password FROM '.$p.'contacts WHERE id=8002')->row()->password),'actual native password hash is persisted');
$db->query('UPDATE '.$p.'contacts SET new_pass_key=?,new_pass_key_requested=? WHERE id=8002',[$key,gmdate('Y-m-d H:i:s',time()-3601)]);essentialCheck(!$portal->resetHistoricalPrimary($primary,8002,$key,$password,$hash),'historical reset retains one-hour validity');
$db->query('UPDATE '.$p.'contacts SET new_pass_key_requested=? WHERE id=8002',[gmdate('Y-m-d H:i:s')]);$db->query("CREATE TRIGGER ignore_historical_reset_audit BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='company_portal_historical_password_changed' BEGIN SELECT RAISE(IGNORE); END");essentialThrows(fn()=>$portal->resetHistoricalPrimary($primary,8002,$key,$password,$hash),'issuer_portal_audit_unconfirmed','missing reset audit rolls back');essentialCheck($db->query('SELECT new_pass_key FROM '.$p.'contacts WHERE id=8002')->row()->new_pass_key===$key,'reset failure retains token');$db->query('DROP TRIGGER ignore_historical_reset_audit');
echo 'Native historical principal reset: '.($GLOBALS['essentialChecks']-$start).' actual SQLite checks passed.'.PHP_EOL;
