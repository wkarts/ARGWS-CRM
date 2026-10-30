<?php
/** Actual native token completion guards; synthetic keys and no external deliveries. */
require __DIR__.'/portal_recovery.php';
$now=date('Y-m-d H:i:s');
$statement=$pdo->prepare('UPDATE q_contacts SET new_pass_key=?,new_pass_key_requested=? WHERE id=?');
$statement->execute(['synthetic-legacy-token',$now,10]);
$statement->execute(['synthetic-private-token',$now,20]);
foreach(['can_reset_password','can_set_password'] as $method){
 check($auth->$method(false,10,'synthetic-legacy-token')===true,'legacy active pair retains native token validation: '.$method);
 check($auth->$method(false,20,'synthetic-private-token')===false,'preexisting private token cannot enter global completion: '.$method);
 check($auth->$method(false,10,'synthetic-private-token')===false,'foreign contact key cannot match another ID: '.$method);
 $pdo->exec('UPDATE q_clients SET active=0 WHERE userid=1');
 check($auth->$method(false,10,'synthetic-legacy-token')===false,'inactive parent invalidates completion: '.$method);
 $pdo->exec('UPDATE q_clients SET active=1 WHERE userid=1');
 $pdo->exec('UPDATE q_contacts SET active=0 WHERE id=10');
 check($auth->$method(false,10,'synthetic-legacy-token')===false,'inactive contact invalidates completion: '.$method);
 $pdo->exec('UPDATE q_contacts SET active=1 WHERE id=10');
}
$before=$pdo->query('SELECT * FROM q_contacts WHERE id=20')->fetch(PDO::FETCH_ASSOC);$deliveries=count($GLOBALS['recoveryDeliveries']);
foreach(['reset_password','set_password'] as $method)check($auth->$method(false,20,'synthetic-private-token','not-a-real-password')===['expired'=>true],'private completion denied before password mutation: '.$method);
check($pdo->query('SELECT * FROM q_contacts WHERE id=20')->fetch(PDO::FETCH_ASSOC)===$before && count($GLOBALS['recoveryDeliveries'])===$deliveries,'private completion leaves password/token unchanged and does not send');
$pdo->prepare('UPDATE q_contacts SET new_pass_key_requested=? WHERE id=10')->execute([date('Y-m-d H:i:s',time()-49*3600)]);
check(!$auth->can_set_password(false,10,'synthetic-legacy-token') && !$auth->can_reset_password(false,10,'synthetic-legacy-token'),'native expiry windows preserved');
$pdo->prepare('UPDATE q_staff SET new_pass_key=?,new_pass_key_requested=? WHERE staffid=7')->execute(['synthetic-staff-token',$now]);
$staffBeforeTokenValidation=$pdo->query('SELECT * FROM q_staff ORDER BY staffid')->fetchAll(PDO::FETCH_ASSOC);$staffDeliveries=count($GLOBALS['recoveryDeliveries']);
foreach(['can_set_password','can_reset_password'] as $method){
 check($auth->$method(true,7,'synthetic-staff-token')===false,'ambiguous staff token validation fails closed: '.$method);
}
check($pdo->query('SELECT * FROM q_staff ORDER BY staffid')->fetchAll(PDO::FETCH_ASSOC)===$staffBeforeTokenValidation && count($GLOBALS['recoveryDeliveries'])===$staffDeliveries,'ambiguous staff token validation preserves identities, keys and deliveries');
$pdo->exec("UPDATE q_staff SET email='other-staff@example.invalid' WHERE staffid=8");
check($auth->can_set_password(true,7,'synthetic-staff-token') && $auth->can_reset_password(true,7,'synthetic-staff-token'),'staff token validation remains separate');
echo "Native recovery token boundaries passed; private end-to-end recovery and atomic completion remain pending.\n";
