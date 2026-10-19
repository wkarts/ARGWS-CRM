<?php
require __DIR__.'/portal_recovery_tokens.php';
$reset=static function()use($pdo){$pdo->prepare('UPDATE q_contacts SET new_pass_key=?,new_pass_key_requested=?,password=? WHERE id=10')->execute(['synthetic-once',date('Y-m-d H:i:s'),'old-password']);};
foreach(['set_password','reset_password'] as $method){
 $reset();$writes=$auth->db->writes;
 check($auth->$method(false,10,'synthetic-once','fixture-password')===true,'legacy positive completion: '.$method);
 $row=$pdo->query('SELECT password,new_pass_key,new_pass_key_requested FROM q_contacts WHERE id=10')->fetch(PDO::FETCH_ASSOC);
 check(password_verify('fixture-password',$row['password']) && $row['new_pass_key']===null && $row['new_pass_key_requested']===null && $auth->db->writes===$writes+1,'password and token consumed in one native update: '.$method);
 check($auth->$method(false,10,'synthetic-once','replayed')===['expired'=>true],'consumed token cannot be replayed: '.$method);
}
$reset();$GLOBALS['beforeRecoveryHash']=fn()=>$pdo->exec("UPDATE q_contacts SET new_pass_key='replacement-token' WHERE id=10");
check($auth->reset_password(false,10,'synthetic-once','stale')===null,'intervening token replacement defeats stale completion');
check($pdo->query('SELECT password FROM q_contacts WHERE id=10')->fetchColumn()==='old-password','lost token comparison never writes password');
$reset();$GLOBALS['beforeRecoveryHash']=fn()=>$pdo->exec('INSERT INTO q_issuers_clients VALUES(1,22)');
check($auth->reset_password(false,10,'synthetic-once','wrong-origin')===null,'ownership adoption between validation and update defeats completion');
check($pdo->query('SELECT password FROM q_contacts WHERE id=10')->fetchColumn()==='old-password','ownership race leaves password unchanged');
$pdo->exec('DELETE FROM q_issuers_clients WHERE client_id=1');
$reset();$GLOBALS['beforeRecoveryHash']=fn()=>$pdo->exec('UPDATE q_clients SET active=0 WHERE userid=1');
check($auth->set_password(false,10,'synthetic-once','inactive')===null,'parent deactivation between validation and update defeats completion');
$pdo->exec('UPDATE q_clients SET active=1 WHERE userid=1');
echo "Native contact completion: positive legacy, single-write token consumption and simulated intervening-change denials passed; MySQL races remain pending.\n";
