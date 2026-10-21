<?php
// Synthetic SQLite only: explicit portal identity is not a customer directory or finance grant.
define('BASEPATH', __DIR__);
require __DIR__.'/../application/services/PortalClientIdentity.php';
require __DIR__.'/../application/services/ClientIssuerScope.php';
use app\services\PortalClientIdentity;
use app\services\ClientIssuerScope;
class PortalResult { public function __construct(private array $rows) {} public function row(){return $this->rows?(object)$this->rows[0]:null;} }
class PortalDb {
    public bool $fail=false;
    public $afterFirst=null;
    private int $calls=0;
    public function __construct(public PDO $pdo){}
    public function query($sql,$args=[]){if($this->fail)return false;$s=$this->pdo->prepare($sql);$s->execute($args);$rows=$s->fetchAll(PDO::FETCH_ASSOC);if(++$this->calls===1&&$this->afterFirst)($this->afterFirst)();return new PortalResult($rows);}
    public function table_exists($name){$s=$this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$name]);return (bool)$s->fetch();}
}
class PortalSession {public function __construct(public array $data){} public function userdata($key){return $this->data[$key]??null;} }
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$r=new ReflectionClass(PortalClientIdentity::class);
foreach(['contacts'=>'CONTACT_FIELDS','clients'=>'CLIENT_FIELDS'] as $table=>$constant){$fields=$r->getConstant($constant);$pdo->exec('CREATE TABLE x_'.$table.'('.implode(',',array_map(fn($f)=>$f.' TEXT',$fields)).',secret_future TEXT,password TEXT,new_pass_key TEXT)');}
$pdo->exec("INSERT INTO x_clients(userid,company,active,secret_future) VALUES(1,'Legacy',1,'synthetic'),(2,'PrivateX',1,'synthetic'),(3,'PrivateY',1,'synthetic'); INSERT INTO x_contacts(id,userid,firstname,active,password,new_pass_key) VALUES(10,1,'Legacy',1,'testhash','testreset'),(20,2,'X',1,'testhash','testreset'),(30,3,'Y',1,'testhash','testreset'); CREATE TABLE x_issuers_clients(client_id INT,owner_issuer_id INT); INSERT INTO x_issuers_clients VALUES(2,11),(3,22)");
$db=new PortalDb($pdo);$n=0;
function check($v,$message){global $n;if(!$v)throw new RuntimeException($message);$n++;}
function sessionFor($contact=20,$client=2){return new PortalSession(['client_logged_in'=>true,'contact_user_id'=>$contact,'client_user_id'=>$client]);}
foreach([[10,1,'Legacy'],[20,2,'PrivateX'],[30,3,'PrivateY']] as [$contact,$client,$company]){
    $i=PortalClientIdentity::fromAuthenticatedSession($db,'x_',sessionFor($contact,$client));
    check($i['client']->company===$company,'authenticated own company preserved');
    check((int)$i['contact']->id===$contact,'only own contact');
    foreach(['password','new_pass_key','secret_future'] as $secret){check(!property_exists($i['contact'],$secret),'no contact secret');check(!property_exists($i['client'],$secret),'no client secret');}
}
foreach([[20,3],[30,2],[999,2],[20,999],['20 OR 1=1',2],[20,'2e0'],[true,2],[20,0]] as [$contact,$client])check(PortalClientIdentity::fromAuthenticatedSession($db,'x_',sessionFor($contact,$client))===null,'mismatch or invalid session denied');
foreach([[],['client_logged_in'=>false],['client_logged_in'=>1],['client_logged_in'=>'true'],['staff_logged_in'=>true,'contact_user_id'=>20,'client_user_id'=>2]] as $data)check(PortalClientIdentity::fromAuthenticatedSession($db,'x_',new PortalSession($data))===null,'authentication required');
$pdo->exec('UPDATE x_contacts SET active=0 WHERE id=20');check(PortalClientIdentity::fromAuthenticatedSession($db,'x_',sessionFor())===null,'disabled contact');
$pdo->exec('UPDATE x_contacts SET active=1 WHERE id=20;UPDATE x_clients SET active=0 WHERE userid=2');check(PortalClientIdentity::fromAuthenticatedSession($db,'x_',sessionFor())===null,'disabled client');
$pdo->exec('UPDATE x_clients SET active=1 WHERE userid=2;UPDATE x_contacts SET userid=3 WHERE id=20');check(PortalClientIdentity::fromAuthenticatedSession($db,'x_',sessionFor())===null,'reparented contact invalidates stale session');
$pdo->exec('UPDATE x_contacts SET userid=2 WHERE id=20');
$race=new PortalDb($pdo);$race->afterFirst=fn()=>$pdo->exec('UPDATE x_contacts SET active=0 WHERE id=20');check(PortalClientIdentity::fromAuthenticatedSession($race,'x_',sessionFor())===null,'revocation between identity reads fails closed');$pdo->exec('UPDATE x_contacts SET active=1 WHERE id=20');
check(!ClientIssuerScope::isLegacyClient($db,'x_',2),'portal lookup does not open staff directory');check(ClientIssuerScope::isLegacyClient($db,'x_',1),'legacy directory unchanged');
$db->fail=true;try{PortalClientIdentity::fromAuthenticatedSession($db,'x_',sessionFor());throw new Exception('failure accepted');}catch(RuntimeException $e){check($e->getMessage()==='portal_identity_storage_failed','storage failure explicit');}
try{PortalClientIdentity::fromAuthenticatedSession($db,'x_;DROP',sessionFor());throw new Exception('prefix accepted');}catch(InvalidArgumentException $e){check(true,'prefix rejected');}
echo "OK: $n portal identity SQLite checks.\n";
