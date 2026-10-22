<?php
/**
 * Shared actual App_Migration/App harness for disposable native upgrade tests.
 * The version-456 baseline is a minimal fixture, not a full install.sql replay.
 * Only unrelated IssuerCore reconciliation and framework I/O are adapted.
 */
declare(strict_types=1);
$source=getenv('A1_NATIVE_SOURCE_ROOT')?:dirname(__DIR__,2);
if (!is_dir($source.'/application')||!is_file($source.'/application/migrations/458_version_458.php')) {
    fwrite(STDERR,"The composed 458 migration is required; native upgrade was NOT executed.\n");exit(2);
}
define('BASEPATH',$source.'/system/');define('APPPATH',$source.'/application/');
require __DIR__.'/support.php';
if (!getenv('A1_TEST_DSN') && getenv('A1_NATIVE_SQLITE_SMOKE')!=='1') { fwrite(STDERR,"Disposable MySQL DSN required; native upgrade was NOT executed.\n");exit(2); }
if(getenv('A1_NATIVE_SQLITE_SMOKE')==='1'&&getenv('A1_TEST_DSN'))throw new RuntimeException('SQLite smoke cannot receive a database DSN');
require_once BASEPATH.'database/DB_driver.php';
require_once BASEPATH.'libraries/Migration.php';
require_once APPPATH.'libraries/App_Migration.php';
// Do not replace functional helpers invoked by the tested native migrations.
final class IssuerCore
{
    public static int $reconciled=0;
    public static function reconcile($db,string $prefix): void { ++self::$reconciled; }
    public static function ready($db,string $prefix): bool { return true; }
}
require_once APPPATH.'libraries/App.php';
function &get_instance() { return $GLOBALS['a1_native_ci']; }
function db_prefix() { return 'a1_'; }
function delete_option($name) {}
function add_option($name,$value) {}
function hooks() { return new class { public function do_action($name,...$args) { $GLOBALS['a1_native_events'][]=$name; } }; }
final class A1NativeResult
{
    public function __construct(private array $rows) {}
    public function result_array():array{return $this->rows;}
    public function row_array():array{return $this->rows[0]??[];}
    public function row(){return isset($this->rows[0])?(object)$this->rows[0]:null;}
}
final class A1NativeDb extends CI_DB_driver
{
    private IssuerTestDb $delegate;
    public ?string $failSql=null;
    public ?int $failMarker=null;
    public ?int $pretendMarker=null;
    public ?int $failReadback=null;
    public array $markerWrites=[];
    public ?int $failEmailWriteAt=null;
    public int $emailWrites=0;
    public array $ddl=[];
    public function __construct(IssuerTestDb $db) {$this->delegate=$db;$this->conn_id=$db->pdo;$this->dbdriver='mysql';}
    public function query($sql,$binds=false,$return_object=null)
    {
        if ($this->failSql!==null&&str_contains($sql,$this->failSql)) { throw new RuntimeException('synthetic_ddl_failure'); }
        if (preg_match('/^(?:INSERT INTO|UPDATE) a1_emailtemplates\b/', $sql)) {
            ++$this->emailWrites;
            if ($this->failEmailWriteAt===$this->emailWrites) throw new RuntimeException('synthetic_email_write_failure');
        }
        if(str_starts_with($sql,'CREATE TABLE'))$this->ddl[]=$sql;
        return new A1NativeResult($this->delegate->query($sql,is_array($binds)?$binds:[])->result_array());
    }
    protected function _list_tables($prefix_limit=false) {return $this->conn_id->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?"SELECT name AS table_name FROM sqlite_master WHERE type='table'":'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()';}
    public function field_exists($field_name,$table_name){return $this->delegate->field_exists($field_name,$table_name);}
    public function select($columns='*',$escape=null){return $this;}
    public function limit($value,$offset=0){return $this;}
    public function get($table)
    {
        if($table!=='a1_migrations')throw new RuntimeException('Unexpected query-builder table');
        if($this->failReadback!==null&&in_array($this->failReadback,$this->markerWrites,true)){
            $this->failReadback=null;throw new RuntimeException('core_migration_readback_unavailable');
        }
        return $this->query('SELECT version FROM a1_migrations');
    }
    public function update($table,$values)
    {
        if($table!=='a1_migrations'||array_keys($values)!==['version'])throw new RuntimeException('Unexpected marker write');
        $version=(int)$values['version'];$this->markerWrites[]=$version;
        if($this->failMarker===$version)return false;
        if($this->pretendMarker===$version)return true;
        $this->query('UPDATE a1_migrations SET version=?',[$version]);return true;
    }
    public function insert_id(){return $this->delegate->insert_id();}
    public function trans_begin($test_mode=false){$result=$this->delegate->trans_begin();$this->_trans_depth=1;return $result;}
    public function trans_status(){return $this->delegate->trans_status();}
    public function trans_commit(){$result=$this->delegate->trans_commit();$this->_trans_depth=0;return $result;}
    public function trans_rollback(){$result=$this->delegate->trans_rollback();$this->_trans_depth=0;return $result;}
}
function a1nativeSeed(int $target=458): array
{
    if(getenv('A1_NATIVE_SQLITE_SMOKE')!=='1'){
        $pdo=a1mysql();
        foreach(['company_client_profiles','issuer_certificate_audit','issuer_certificate_credentials','emailtemplates','migrations','clients']as$table)$pdo->exec('DROP TABLE IF EXISTS a1_'.$table);
        unset($pdo);
    }
    $raw=a1db();
    $raw->query('DROP TABLE a1_issuer_certificate_audit');$raw->query('DROP TABLE a1_issuer_certificate_credentials');
    $raw->query('CREATE TABLE a1_clients (userid int NOT NULL PRIMARY KEY,company varchar(191) NOT NULL) ENGINE=InnoDB');
    $raw->query("INSERT INTO a1_clients VALUES(101,'SYNTHETIC CLIENT A'),(201,'SYNTHETIC CLIENT B')");
    $raw->query('CREATE TABLE a1_migrations (version int NOT NULL) ENGINE=InnoDB');$raw->query('INSERT INTO a1_migrations VALUES(456)');
    $autoId=$raw->driver==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'int NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $raw->query('CREATE TABLE a1_emailtemplates (emailtemplateid '.$autoId.',issuer_id int unsigned NULL,`type` varchar(100) NOT NULL,slug varchar(191) NOT NULL,language varchar(40) NOT NULL,name varchar(191) NOT NULL,subject varchar(255) NOT NULL,message longtext NOT NULL,fromname varchar(191) NOT NULL,fromemail varchar(191) NOT NULL,plaintext int NOT NULL,active int NOT NULL,`order` int NOT NULL) ENGINE=InnoDB');
    $defaults=json_decode(file_get_contents(APPPATH.'services/email_templates_ptbr/company_defaults.json'),true,512,JSON_THROW_ON_ERROR);
    $source=array_values(array_filter($defaults,static fn($r)=>$r['slug']==='contact-forgot-password'))[0]??null;
    if($source===null)throw new RuntimeException('Native factory email fixture missing');
    $rows=[['issuer_id'=>null]+$source,['issuer_id'=>10]+array_replace($source,['language'=>'portuguese_br','name'=>'NOME PERSONALIZADO','subject'=>'ASSUNTO PERSONALIZADO {contact_firstname}','message'=>'CORPO PERSONALIZADO {reset_password_url}']),['issuer_id'=>20]+array_replace($source,['language'=>'portuguese_br'])];
    foreach($rows as$row)$raw->query('INSERT INTO a1_emailtemplates (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    $db=new A1NativeDb($raw);$GLOBALS['a1_native_events']=[];IssuerCore::$reconciled=0;
    $ci=(object)['db'=>$db];$GLOBALS['a1_native_ci']=$ci;
    $ci->config=new class($target) {public function __construct(private int $target){} public function item($key){return ['migration_version'=>$this->target,'migration_type'=>'sequential','migration_table'=>'a1_migrations','migration_auto_latest'=>false,'migration_path'=>APPPATH.'migrations/'][$key]??null;}};
    $ci->load=new class {public function config($name){}public function library($name,$config){}};
    $ci->lang=new class {public function line($key){return $key;}};
    $reflection=new ReflectionClass(App_Migration::class);$runner=$reflection->newInstanceWithoutConstructor();
    foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>$target,'_migration_table'=>'a1_migrations','_migration_regex'=>'/^\d{3}_(\w+)$/']as$key=>$value)$reflection->getProperty($key)->setValue($runner,$value);
    $ci->migration=$runner;$reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);
    return [$db,$app,$source];
}
function a1nativeRun(App $app):array{return(new ReflectionMethod(App::class,'upgrade_database_silent'))->invoke($app);}
function a1nativeVersion(A1NativeDb $db):int{return(int)$db->query('SELECT version FROM a1_migrations')->row()->version;}
function a1nativeMails(A1NativeDb $db):array{return$db->query('SELECT * FROM a1_emailtemplates ORDER BY emailtemplateid')->result_array();}
function a1nativeReady(A1NativeDb $db):void
{
    CompanyClientProfileSchema::assertValid($db,'a1_');IssuerCertificateSchema::assertValid($db,'a1_');
    foreach(['company_client_profiles','issuer_certificate_credentials','issuer_certificate_audit']as$table)a1check($db->table_exists('a1_'.$table),'native table cache recognizes '.$table);
}
