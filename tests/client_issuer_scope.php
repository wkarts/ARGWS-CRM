<?php
define('BASEPATH',__DIR__);
require __DIR__.'/../application/services/ClientIssuerScope.php';
require __DIR__.'/../application/services/InvoiceIssuerScope.php';
use app\services\ClientIssuerScope;
class ClientScopeResult {function __construct(private array $rows){} function row(){return $this->rows?(object)$this->rows[0]:null;} function result_array(){return $this->rows;}}
class ClientScopeDb {
 function __construct(public PDO $pdo){} function table_exists($name){$s=$this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$name]);return (bool)$s->fetch();}
 function field_exists($field,$table){return in_array($field,array_column($this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),'name'));}
 function query($sql,$args=[]){$s=$this->pdo->prepare($sql);$s->execute($args);return new ClientScopeResult($s->fetchAll(PDO::FETCH_ASSOC));}
}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db=new ClientScopeDb($pdo);
$pdo->exec("CREATE TABLE t_clients(userid INT PRIMARY KEY,company TEXT,vat TEXT); INSERT INTO t_clients VALUES(1,'Legacy',''),(2,'Private X','x'),(3,'Private Y','y'),(4,'Shared X to Y','s')");
$n=0;function check($v,$m){global $n;$n++;if(!$v)throw new RuntimeException($m);}function ids($rows){return array_column($rows,'userid');}
check(ClientIssuerScope::legacySql($db,'t_','t_clients')==='1=1','before installation unchanged');
$pdo->exec('CREATE TABLE t_issuers_clients(client_id INT PRIMARY KEY,owner_issuer_id INT);CREATE TABLE t_issuers_client_links(client_id INT,issuer_id INT);INSERT INTO t_issuers_clients VALUES(2,11),(3,22),(4,11); INSERT INTO t_issuers_client_links VALUES(4,22)');
check(ids($db->query('SELECT * FROM t_clients WHERE '.ClientIssuerScope::legacySql($db,'t_','t_clients'))->result_array())===[1],'legacy never includes owned/shared clients');
$allow=fn($issuer,$actor,$cap)=>$actor===7&&in_array($issuer,[11,22],true)&&$cap==='view';
check(ids(ClientIssuerScope::forIssuer($db,'t_',11,7,$allow))===[2,4],'X gets owned only');
check(ids(ClientIssuerScope::forIssuer($db,'t_',22,7,$allow))===[3,4],'Y gets owned and linked only');
check(ClientIssuerScope::forIssuer($db,'t_',22,7,$allow,2)===[],'guess private ID denied');
$pdo->exec("ALTER TABLE t_clients ADD COLUMN future_private_field TEXT; UPDATE t_clients SET future_private_field='never-share'");
check(array_keys(ClientIssuerScope::forIssuer($db,'t_',11,7,$allow,2)[0])===['userid','company','vat','owner_issuer_id'],'scoped identity projection never exports future columns');
check(ClientIssuerScope::forIssuer($db,'t_',11,7,$allow,1)===[],'new company never inherits legacy');
$pdo->exec('DELETE FROM t_issuers_client_links');check(ids(ClientIssuerScope::forIssuer($db,'t_',22,7,$allow))===[3],'revocation immediate');
check(ClientIssuerScope::isLegacyClient($db,'t_',1),'legacy ID visible');check(!ClientIssuerScope::isLegacyClient($db,'t_',2),'owned ID hidden');check(!ClientIssuerScope::isLegacyClient($db,'t_','1 OR 1'),'invalid ID denied');
foreach([[11,8,$allow],[22,7,fn()=>false]] as [$issuer,$actor,$fn]){try{ClientIssuerScope::forIssuer($db,'t_',$issuer,$actor,$fn);throw new RuntimeException('must deny');}catch(DomainException $e){check($e->getMessage()==='client_scope_denied','ACL cannot be skipped');}}
$calls=0;try{ClientIssuerScope::forIssuer($db,'t_',11,7,function()use(&$calls){return ++$calls===1;});throw new RuntimeException('must recheck');}catch(DomainException $e){check($calls===2,'live ACL rechecked after query');}
try{ClientIssuerScope::legacySql($db,'t_;DROP','t_clients');throw new RuntimeException('identifier');}catch(InvalidArgumentException $e){check(true,'identifier constrained');}
$pdo->exec("CREATE TABLE t_invoices(id INT PRIMARY KEY,clientid INT,issuer_id INT);INSERT INTO t_invoices VALUES(10,2,NULL),(20,2,11),(30,3,22)");
$identity=ClientIssuerScope::legacyInvoiceIdentity($db,'t_',10);
check($identity->company==='Private X' && $identity->issuer_financial_identity_only===true,'legacy finance retains minimal identity without moving ownership');
check(array_keys((array)$identity)===['userid','company','vat','issuer_financial_identity_only'],'no customer phone/notes/contacts hydrated through invoice');
check(ClientIssuerScope::legacyInvoiceIdentity($db,'t_',20)===null,'positive finance not granted by customer link');
check(ClientIssuerScope::legacyInvoiceIdentity($db,'t_',30)===null,'other issuer finance hidden');
check(!ClientIssuerScope::isLegacyClient($db,'t_',2),'finance lookup never opens directory');
$pdo->exec("CREATE TABLE t_contacts(id INT,userid INT,firstname TEXT,lastname TEXT,is_primary INT); INSERT INTO t_contacts VALUES(10,1,'Legacy','Contact',1),(20,2,'Private','Contact',1)");
check(ClientIssuerScope::isLegacyContact($db,'t_',10),'legacy contact identity lookup preserved');
check(!ClientIssuerScope::isLegacyContact($db,'t_',20),'private contact identity lookup blocked');
check(!ClientIssuerScope::isLegacyContact($db,'t_','10 OR 1=1'),'malformed contact identity blocked');
$pdo->exec("UPDATE t_clients SET company='' WHERE userid=2");
check(ClientIssuerScope::legacyInvoiceIdentity($db,'t_',10)->company==='Private Contact','legacy invoice uses only primary-name fallback');
check(ClientIssuerScope::legacyInvoiceIdentity($db,'t_',0)===null,'invalid invoice cannot hydrate identity');
$pdo->exec('DROP TABLE t_issuers_clients');
check(ClientIssuerScope::legacySql($db,'t_','t_clients')==='1=0','partial schema never reopens private clients');
check(!ClientIssuerScope::isLegacyClient($db,'t_',1),'partial schema fails closed even for existing IDs');
echo "Client issuer scope: $n checks passed (SQLite, synthetic data)\n";
