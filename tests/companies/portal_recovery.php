<?php
/** Native recovery model with fake delivery: never sends email or creates usable production tokens. */
require __DIR__.'/../support/client_issuer_test_support.php';
define('APPPATH',realpath(__DIR__.'/../../application').'/');
require APPPATH.'models/Authentication_model.php';
function app_generate_hash(){return 'synthetic-reset-token-not-production';}
function send_mail_template(...$args){$GLOBALS['recoveryDeliveries'][]=$args;return true;}
$auth=(new ReflectionClass(Authentication_model::class))->newInstanceWithoutConstructor();$auth->db=$model->db;
$auth->input=new class {function ip_address(){return '127.0.0.1';}};
$GLOBALS['recoveryDeliveries']=[];
foreach(['forgot_password','set_password_email'] as $method){
 check($auth->$method('l@example.test')===true,'legacy '.$method.' retains native positive recovery');
 $sent=count($GLOBALS['recoveryDeliveries']);$before=$pdo->query('SELECT * FROM q_contacts WHERE id=20')->fetch(PDO::FETCH_ASSOC);
 check($auth->$method('x@example.test')===false,'private '.$method.' cannot use implicit global mail origin');
 check(count($GLOBALS['recoveryDeliveries'])===$sent && $pdo->query('SELECT * FROM q_contacts WHERE id=20')->fetch(PDO::FETCH_ASSOC)===$before,'private rejection neither rotates key nor dispatches template');
 $pdo->exec('UPDATE q_clients SET active=0 WHERE userid=1');
 check($auth->$method('l@example.test')===false && count($GLOBALS['recoveryDeliveries'])===$sent,'inactive legacy client cannot request recovery despite active contact');
 $pdo->exec('UPDATE q_clients SET active=1 WHERE userid=1');
}
check(count($GLOBALS['recoveryDeliveries'])===2,'only two explicit synthetic legacy deliveries');
echo "Portal recovery transport boundary: $n synthetic checks passed; secondary recovery remains unavailable\n";
$pdo->exec("CREATE TABLE q_staff(staffid INT,email TEXT,active INT,new_pass_key TEXT,new_pass_key_requested TEXT); INSERT INTO q_staff VALUES(7,'staff@example.invalid',1,'old','');");
check($auth->forgot_password('staff@example.invalid',true)===true,'staff recovery retains its original separate path');
$before=count($GLOBALS['recoveryDeliveries']);
check($auth->forgot_password('missing@example.invalid')===false && count($GLOBALS['recoveryDeliveries'])===$before,'unknown email cannot dispatch');
echo "Portal recovery legacy/staff: $n synthetic checks passed\n";

$pdo->exec("UPDATE q_contacts SET email='same@example.invalid' WHERE id IN (10,20)");
$count=count($GLOBALS['recoveryDeliveries']);
check($auth->forgot_password('same@example.invalid')===false && $auth->set_password_email('same@example.invalid')===false,'global recovery refuses duplicate email without rotating arbitrary owner');
check(count($GLOBALS['recoveryDeliveries'])===$count,'ambiguous recovery sends no global template');
$pdo->exec("UPDATE q_contacts SET email='l@example.test' WHERE id=10; UPDATE q_contacts SET email='x@example.test' WHERE id=20");
