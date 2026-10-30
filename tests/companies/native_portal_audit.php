<?php
/** Actual SQLite trigger suppression: no authenticated result without a fresh audit receipt. */
require __DIR__.'/native_portal_identity.php';
if($db->driver()!=='sqlite')throw new RuntimeException('sqlite_trigger_fixture_required');
$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit")->row()->n;
$ok=$portal->login($a,'new@example.invalid','final-new-password-a','password_verify');
essentialCheck($ok!==null,'successful predecessor creates audit receipt');
$receipt=$db->query("SELECT metadata_json FROM {$p}issuers_audit ORDER BY id DESC LIMIT 1")->row()->metadata_json;
$metadata=json_decode($receipt,true,512,JSON_THROW_ON_ERROR);
essentialCheck($metadata['actor_kind']==='contact'&&$metadata['contact_id']===$ca&&strlen($metadata['receipt_nonce'])===48,'audit preserves explicit contact actor without password/email/token');
$db->query("CREATE TRIGGER portal_audit_ignore BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event LIKE 'company_portal_%' BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$portal->login($a,'new@example.invalid','final-new-password-a','password_verify'),'issuer_portal_audit_unconfirmed','ignored audit cannot reuse preceding successful login receipt');
$db->query("DELETE FROM {$p}company_portal_tokens WHERE contact_id=?",[$ca]);
$queueBefore=(int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n;
essentialThrows(fn()=>$portal->requestRecovery($a,'new@example.invalid',$base,$decrypt),'issuer_portal_audit_unconfirmed','ignored recovery audit rolls back token and queue atomically');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===$queueBefore&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_portal_tokens WHERE contact_id=?",[$ca])->row()->n===0,'no queued recovery or token survives suppressed audit');
$db->query('DROP TRIGGER portal_audit_ignore');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit")->row()->n===$before+1,'failed operations add no audit or partial state');
$portal->requestRecovery($a,'new@example.invalid',$base,$decrypt);
$lastMessage=$db->query("SELECT message FROM {$p}mail_queue ORDER BY id DESC LIMIT 1")->row()->message;
preg_match('/#token=([a-f0-9]{64})/',$lastMessage,$tokenMatch);$newToken=$tokenMatch[1];
$db->query("CREATE TRIGGER portal_audit_ignore BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event LIKE 'company_portal_%' BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$portal->reset($a,$newToken,'synthetic-reset-rejected',fn($value)=>password_hash($value,PASSWORD_DEFAULT)),'issuer_portal_audit_unconfirmed','suppressed password audit rolls back password and token consumption');
$db->query('DROP TRIGGER portal_audit_ignore');
essentialCheck($portal->login($a,'new@example.invalid','final-new-password-a','password_verify')!==null&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_portal_tokens WHERE token_hash=?",[hash('sha256',$newToken)])->row()->n===1,'previous password and unconsumed token survive ignored audit');
echo "Native portal audit: eight actual SQLite receipt/rollback checks passed.\n";

$db->query("CREATE TRIGGER portal_limit_insert_ignore BEFORE INSERT ON {$p}company_portal_limits BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$portal->allowAttempt($a,'127.0.0.77','login'),'issuer_portal_throttle_unconfirmed','ignored initial throttle cannot authorize authentication');
$db->query('DROP TRIGGER portal_limit_insert_ignore');
essentialCheck($portal->allowAttempt($a,'127.0.0.77','login'),'new confirmed throttle permits first attempt');
$db->query("CREATE TRIGGER portal_limit_update_ignore BEFORE UPDATE ON {$p}company_portal_limits BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$portal->allowAttempt($a,'127.0.0.77','login'),'issuer_portal_throttle_unconfirmed','ignored throttle increment cannot authorize unlimited authentication');
$db->query('DROP TRIGGER portal_limit_update_ignore');
essentialCheck($portal->allowAttempt($a,'127.0.0.77','login'),'next attempt succeeds only after confirmed counter persistence');
echo "Native portal throttle: four actual SQLite suppression checks passed.\n";
