<?php
/** Real framework runner with PDO fixtures; SQLite translates only MySQL DDL for local checks. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__).'/system/');
define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
require __DIR__.'/../application/libraries/App_module_migration.php';
function db_prefix() { return $GLOBALS['prefix']; }
function &get_instance() { return $GLOBALS['CI']; }
class IssuerMigrationResult {
 public function __construct(private array $rows) {}
 public function row() { return isset($this->rows[0])?(object)$this->rows[0]:null; }
 public function result_array() { return $this->rows; }
}
class IssuerMigrationDb {
 public array $queries=[];public ?string $failOn=null;public bool $failVersionWrite=false;private array $where=[];
 public function __construct(public PDO $pdo) { $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
 public function query($sql,$params=[]) {
  $this->queries[]=$sql;
  if($this->failOn!==null && str_contains($sql,$this->failOn)) return false;
  if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
   $sql=preg_replace('/(?:int|bigint) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY/','INTEGER PRIMARY KEY AUTOINCREMENT',$sql);
   $sql=str_replace('INSERT IGNORE','INSERT OR IGNORE',$sql);
   $sql=preg_replace('/ ENGINE=InnoDB.*$/s','',$sql);
   $sql=preg_replace('/UNIQUE KEY `[^`]+` \(/','UNIQUE (',$sql);
   $sql=preg_replace('/,?\s*KEY `[^`]+` \([^)]*\)/','',$sql);
  }
  $stmt=$this->pdo->prepare($sql);$stmt->execute($params);
  return new IssuerMigrationResult($stmt->columnCount()?$stmt->fetchAll(PDO::FETCH_ASSOC):[]);
 }
 public function table_exists($table) {
  if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') return (bool)$this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$table])->row();
  return (bool)$this->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row();
 }
 public function field_exists($field,$table) {
  if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') return in_array($field,array_column($this->query('PRAGMA table_info('.$table.')')->result_array(),'name'),true);
  return (bool)$this->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$field])->row();
 }
 public function where($key,$value) { $this->where[$key]=$value;return $this; }
 public function select($columns) { return $this; }
 public function get($table) {
  $where=$this->where;$this->where=[];
  return $this->query('SELECT * FROM '.$table.' WHERE '.implode(' AND ',array_map(fn($key)=>$key.' = ?',array_keys($where))),array_values($where));
 }
 public function update($table,$data) {
  $where=$this->where;$this->where=[];
  if($this->failVersionWrite && ($data['installed_version']??null)===($GLOBALS['target']??'1.0.2')) return false;
  return $this->query('UPDATE '.$table.' SET '.implode(',',array_map(fn($key)=>$key.' = ?',array_keys($data))).' WHERE '.implode(' AND ',array_map(fn($key)=>$key.' = ?',array_keys($where))),array_merge(array_values($data),array_values($where)))!==false;
 }
}
$prefix='module_'.bin2hex(random_bytes(4)).'_';
$pdo=new PDO(getenv('ISSUERS_TEST_DSN')?:'sqlite::memory:',getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null);
if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') $pdo->exec('PRAGMA foreign_keys=ON');
$db=new IssuerMigrationDb($pdo);
$CI=(object)['db'=>$db,'load'=>new class { public function dbforge() {} },'lang'=>new class { public function load($name) {} public function line($name) { return $name; } },'app_modules'=>new class { public function get($name) { preg_match('/^Version:\s*(.+)$/m',file_get_contents(APP_MODULES_PATH.$name.'/'.$name.'.php'),$m);return ['headers'=>['version'=>trim($m[1])]]; } }];
$target=$CI->app_modules->get('issuers')['headers']['version'];
$targetNumber=str_replace('.','',$target);
$db->query("CREATE TABLE {$prefix}modules(module_name VARCHAR(50) PRIMARY KEY,installed_version VARCHAR(20),active INT)");
$db->query("INSERT INTO {$prefix}modules VALUES('issuers','{$target}',0)");
$db->query("CREATE TABLE {$prefix}invoices(id INT,total INT)");$db->query("INSERT INTO {$prefix}invoices VALUES(1,500)");
$checks=0;function check($yes,$name) { global $checks;$checks++;if(!$yes)throw new RuntimeException($name); }
function installedVersion() { return $GLOBALS['db']->where('module_name','issuers')->get(db_prefix().'modules')->row()->installed_version; }
function setInstalledVersion($version) { $GLOBALS['db']->where('module_name','issuers')->update(db_prefix().'modules',['installed_version'=>$version]); }
require APP_MODULES_PATH.'issuers/install.php';
check(installedVersion()===$target,'fresh activation records native migration');
$tables=['lock','registry','staff','staff_defaults','clients','client_links','audit'];
foreach($tables as $table) check($db->query('SELECT COUNT(*) AS n FROM '.$prefix.'issuers_'.$table)!==false,'created table '.$table);
check((int)$db->query("SELECT COUNT(*) AS n FROM {$prefix}issuers_lock")->row()->n===1,'singleton lock seed');
$db->query("INSERT INTO {$prefix}issuers_registry(legal_name,tax_id,settings_json,created_by,created_at) VALUES('Synthetic','12345678000195','{}',7,'2026-10-18 00:00:00')");
$db->query("INSERT INTO {$prefix}issuers_audit(actor_id,event,issuer_id,created_at) VALUES(7,'synthetic',1,'2026-10-18 00:00:00')");
$before=$db->query("SELECT * FROM {$prefix}issuers_registry")->result_array();
require APP_MODULES_PATH.'issuers/install.php';
check(installedVersion()===$target,'reactivation records native migration');
check($db->query("SELECT * FROM {$prefix}issuers_registry")->result_array()===$before,'reactivation preserves issuer identity/config');
check((int)$db->query("SELECT COUNT(*) AS n FROM {$prefix}issuers_audit")->row()->n===1,'reactivation preserves audit');
setInstalledVersion('1.0.0');$runner=new App_module_migration('issuers');
check((string)$runner->to_latest()===$targetNumber,'native upgrade from 1.0.0 executes complete sequence');check(installedVersion()===$target,'native upgrade tracks version');
$count=count($db->queries);check($runner->to_latest()===true,'latest repeat is no-op');check(count($db->queries)===$count+1,'no DDL during no-op');
$db->failOn='CREATE TABLE IF NOT EXISTS `'.$prefix.'issuers_registry';
$failed=false;try { require APP_MODULES_PATH.'issuers/install.php'; } catch(RuntimeException $e) { $failed=$e->getMessage()==='issuer_migration_failed'; }
check($failed && installedVersion()==='1.0.0','DDL failure leaves pending baseline');$db->failOn=null;
require APP_MODULES_PATH.'issuers/install.php';check(installedVersion()===$target,'retry after partial DDL is safe');
$db->failVersionWrite=true;$failed=false;try { require APP_MODULES_PATH.'issuers/install.php'; } catch(RuntimeException $e) { $failed=$e->getMessage()==='issuer_migration_version_unconfirmed'; }
check($failed && installedVersion()!==$target,'activation detects native tracking write failure');$db->failVersionWrite=false;
require APP_MODULES_PATH.'issuers/install.php';
setInstalledVersion('9.9.9');$failed=false;try { require APP_MODULES_PATH.'issuers/install.php'; } catch(RuntimeException $e) { $failed=$e->getMessage()==='issuer_activation_version_invalid'; }
check($failed && installedVersion()==='9.9.9','older code cannot downgrade installed module');setInstalledVersion($target);
$failed=false;try { (new Migration_Version_101())->down(); } catch(RuntimeException $e) { $failed=$e->getMessage()==='issuer_destructive_downgrade_not_supported'; }
check($failed,'destructive down rejected');
check($db->query("SELECT * FROM {$prefix}issuers_registry")->result_array()===$before,'all replays/failures preserve registry');
check((int)$db->query("SELECT total FROM {$prefix}invoices")->row()->total===500,'financial rows untouched');
check((int)$db->where('module_name','issuers')->get($prefix.'modules')->row()->active===0,'test installation does not activate module');
if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
 foreach($tables as $table) check(strtoupper($db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$prefix.'issuers_'.$table])->row()->ENGINE)==='INNODB','real InnoDB table '.$table);
 $foreign=$db->query('SELECT COUNT(*) AS n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME LIKE ?',[$prefix.'issuers_%'])->row()->n;
 check((int)$foreign===5,'real foreign-key relationships preserved');
}
echo 'Issuer native migration: '.$checks.' checks passed (real App_module_migration runner, '.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME).($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'; translated DDL, not MySQL proof':'').").\n";
