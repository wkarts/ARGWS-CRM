<?php
/** Real authentication method, synthetic validated-cookie provider and SQLite. No browser cookies or transport. */
require __DIR__.'/../support/client_issuer_test_support.php';
define('APPPATH',realpath(__DIR__.'/../../application').'/');
require APPPATH.'models/Authentication_model.php';
class PortalLoginDb extends ClientModelDb {
 private array $pending=[];
 function set($key,$value){$this->pending[$key]=$value;return $this;}
 function update($table,$data=null){$values=$data??$this->pending;$this->pending=[];return parent::update($table,$values);}
}
function is_logged_in(){return false;}
function get_cookie($name,$filter){return json_encode(['user_id'=>$GLOBALS['cookieContact'],'key'=>'synthetic-test-cookie']);}
function delete_cookie(...$args){++$GLOBALS['deletedCookies'];}
function set_cookie($data){++$GLOBALS['renewedCookies'];}
$pdo->exec("ALTER TABLE q_contacts ADD COLUMN last_ip TEXT; ALTER TABLE q_contacts ADD COLUMN last_login TEXT;
INSERT INTO q_contacts(id,userid,is_primary,active,email) VALUES(101,1,1,1,'legacy@example.invalid'),(102,2,1,1,'private@example.invalid');");
$auth=(new ReflectionClass(Authentication_model::class))->newInstanceWithoutConstructor();
$auth->db=new PortalLoginDb($pdo);
$auth->load=new class {function helper($name){}};
$auth->input=new class {function ip_address(){return '127.0.0.1';}};
$auth->session=new class {public array $data=[];function set_userdata($data){$this->data=$data;}};
$auth->user_autologin=new class {function get($id,$hash){return (object)['id'=>$id,'staff'=>0];}};
foreach([101=>1,102=>2] as $contact=>$client){
 $GLOBALS['cookieContact']=$contact;$GLOBALS['deletedCookies']=0;$GLOBALS['renewedCookies']=0;$auth->session->data=[];
 check($auth->autologin()===true && $auth->session->data['client_user_id']==$client && $auth->session->data['contact_user_id']==$contact,'validated cookie retains its own active legacy/private identity');
 check($GLOBALS['renewedCookies']===1 && $GLOBALS['deletedCookies']===0,'valid identity renews only its cookie');
}
function app_hasher(){return new class {function CheckPassword($password,$hash){return $password==='synthetic-password' && $hash==='private-hash';}};}
foreach([101=>['legacy@example.invalid',1],102=>['private@example.invalid',2]] as $contact=>$values){
 $auth->session->data=[];
 check($auth->login($values[0],'synthetic-password',false,false)===true && $auth->session->data['client_user_id']==$values[1], 'password login establishes only matching active contact/customer identity');
 $auth->session->data=[];
 check($auth->login($values[0],'wrong-password',false,false)===false && $auth->session->data===[], 'wrong password cannot establish portal identity');
}
$pdo->exec('UPDATE q_clients SET active=0 WHERE userid=2');$auth->session->data=[];
check($auth->login('private@example.invalid','synthetic-password',false,false)===false && $auth->session->data===[], 'inactive customer denied despite active contact/password');
$pdo->exec('UPDATE q_clients SET active=1 WHERE userid=2');
$originalHooks=$GLOBALS['hooks'];
$GLOBALS['hooks']=new class($pdo) {function __construct(private PDO $pdo){} function do_action($name,...$args){if($name==='before_client_login')$this->pdo->exec('UPDATE q_contacts SET userid=1 WHERE id=102');}};
$auth->session->data=[];
check($auth->login('private@example.invalid','synthetic-password',false,false)===false && $auth->session->data===[], 'contact moved by a login hook cannot create stale customer session');
$GLOBALS['hooks']=$originalHooks;$pdo->exec('UPDATE q_contacts SET userid=2 WHERE id=102');
foreach(['UPDATE q_contacts SET active=0 WHERE id=102','UPDATE q_contacts SET active=1 WHERE id=102; UPDATE q_clients SET active=0 WHERE userid=2','UPDATE q_clients SET active=1 WHERE userid=2; DELETE FROM q_contacts WHERE id=102'] as $mutation){
 $pdo->exec($mutation);$auth->session->data=[];$GLOBALS['renewedCookies']=0;$GLOBALS['deletedCookies']=0;
 check($auth->autologin()===false && $auth->session->data===[],'revoked or missing cookie identity cannot create session');
 check($GLOBALS['renewedCookies']===0 && $GLOBALS['deletedCookies']===1,'rejected identity expires cookie without renewal');
}
echo "Portal autologin: $n synthetic checks passed\n";

$pdo->exec("INSERT INTO q_contacts(id,userid,is_primary,active,email) VALUES(102,2,1,1,'duplicate@example.invalid'); UPDATE q_contacts SET email='duplicate@example.invalid' WHERE id=101");
$auth->session->data=[];
check($auth->login('duplicate@example.invalid','synthetic-password',false,false)===false && $auth->session->data===[],'global login refuses ambiguous email instead of choosing an owner');
echo "Global duplicate-email login boundary passed.\n";
