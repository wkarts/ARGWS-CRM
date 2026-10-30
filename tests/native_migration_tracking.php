<?php
/** Actual native App_Migration and App with real SQLite rows; only framework I/O is adapted. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__).'/system/');
require BASEPATH.'libraries/Migration.php';
require __DIR__.'/../application/libraries/App_Migration.php';
final class IssuerCore
{
 public static int $reconcileCalls=0;
 public static function reconcile($db,string $prefix): void { ++self::$reconcileCalls; }
 public static function ready($db,string $prefix): bool { return true; }
}
require __DIR__.'/../application/libraries/App.php';
function config_item($key){return 'App_';}
function &get_instance(){return $GLOBALS['ci'];}
function db_prefix(){return 'test_';}
function log_message($level,$message){}
function delete_option($name){}
function add_option($name,$value){}
function hooks(){return new class {function do_action($name,...$args){$GLOBALS['events'][]=$name;}};}
class TrackingDb {
 public PDO $pdo;public bool $failMarker=false;public int $writes=0;
 function __construct(){$this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->pdo->exec('CREATE TABLE test_migrations(version INTEGER);INSERT INTO test_migrations VALUES(393);CREATE TABLE replay(id INTEGER PRIMARY KEY)');}
 function limit($n){return $this;}function select($s){return $this;}
 function get($table){$r=$this->pdo->query('SELECT * FROM '.$table)->fetch(PDO::FETCH_OBJ);return new class($r){function __construct(private $r){}function row(){return $this->r;}};}
 function update($table,$data){++$this->writes;if($this->failMarker)return false;$q=$this->pdo->prepare('UPDATE '.$table.' SET version=?');return $q->execute([$data['version']]);}
 function query($sql){return $this->pdo->exec($sql);}
}
$checks=0;function check($ok,$message){global $checks;++$checks;if(!$ok)throw new RuntimeException($message);}
$path=sys_get_temp_dir().'/native-migration-'.bin2hex(random_bytes(5));mkdir($path);
file_put_contents($path.'/394_version_394.php',"<?php class Migration_Version_394 extends CI_Migration { public function up(){++\$GLOBALS['upCalls'];\$this->db->query('INSERT OR IGNORE INTO replay(id) VALUES(1)');} public function down(){throw new RuntimeException('downgrade must not run');} }");
try {
 $db=new TrackingDb();$GLOBALS['events']=[];$GLOBALS['upCalls']=0;
 $ci=(object)['db'=>$db];$GLOBALS['ci']=$ci;
 $ci->config=new class {function item($key){return $key==='migration_version'?394:null;}};
 $ci->load=new class {function config($name){}function library($name,$options){}};
 $reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();
 foreach(['_migration_type'=>'sequential','_migration_path'=>$path.'/','_migration_version'=>394,'_migration_table'=>'test_migrations','_migration_regex'=>'/^\d{3}_(\w+)$/'] as$key=>$value)$reflection->getProperty($key)->setValue($migration,$value);
 $ci->migration=$migration;
 $reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);$run=$reflection->getMethod('upgrade_database_silent');
 $db->failMarker=true;$result=$run->invoke($app);
 check($result['success']===false,'failed UPDATE cannot claim native update success');check($app->get_current_db_version()===393,'failed tracking preserves stored version');check(!in_array('database_updated',$GLOBALS['events'],true),'failed tracking suppresses completion hook');check($GLOBALS['upCalls']===1,'actual native runner executed the pending migration');check(IssuerCore::$reconcileCalls===0,'company reconciliation waits for confirmed marker');
 $db->failMarker=false;$result=$run->invoke($app);
 check($result['success']===true,'retry confirms success after actual marker write');check($app->get_current_db_version()===394,'new marker persisted');check((int)$db->pdo->query('SELECT COUNT(*) FROM replay')->fetchColumn()===1,'idempotent migration replay keeps one row');check(IssuerCore::$reconcileCalls===1,'company core reconciles after confirmed marker');
 $calls=$GLOBALS['upCalls'];$result=$run->invoke($app);check($result['success']===true && $GLOBALS['upCalls']===$calls,'already-current schema does not rerun migration');check(IssuerCore::$reconcileCalls===2,'current schema readiness still reconciles company core');
 $db->pdo->exec('UPDATE test_migrations SET version=395');$writes=$db->writes;$result=$run->invoke($app);check($result['success']===false && $db->writes===$writes,'newer stored schema cannot trigger down migration');
 echo "Native migration tracking: {$checks} checks passed (actual App_Migration/App, real SQLite rows).\n";
}finally{unlink($path.'/394_version_394.php');rmdir($path);}
