<?php
/**
 * Actual App_Migration/App and shipped 457/458 helpers on a disposable MySQL DB.
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
// This suite must not replace any of the 458 migration's functional helpers.
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
    public array $ddl=[];
    public function __construct(IssuerTestDb $db) {$this->delegate=$db;$this->conn_id=$db->pdo;$this->dbdriver='mysql';}
    public function query($sql,$binds=false,$return_object=null)
    {
        if ($this->failSql!==null&&str_contains($sql,$this->failSql)) { throw new RuntimeException('synthetic_ddl_failure'); }
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
function a1nativeSeed(): array
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
    $ci->config=new class {public function item($key){return ['migration_version'=>458,'migration_type'=>'sequential','migration_table'=>'a1_migrations','migration_auto_latest'=>false,'migration_path'=>APPPATH.'migrations/'][$key]??null;}};
    $ci->load=new class {public function config($name){}public function library($name,$config){}};
    $ci->lang=new class {public function line($key){return $key;}};
    $reflection=new ReflectionClass(App_Migration::class);$runner=$reflection->newInstanceWithoutConstructor();
    foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>458,'_migration_table'=>'a1_migrations','_migration_regex'=>'/^\d{3}_(\w+)$/']as$key=>$value)$reflection->getProperty($key)->setValue($runner,$value);
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
$source458=file_get_contents(APPPATH.'migrations/458_version_458.php');
foreach(['CompanyClientProfileSchema::ensure','IssuerCertificateSchema::ensure']as$call)a1check(str_contains($source458,$call),'458 composes actual '.$call);
[$db,$app,$source]=a1nativeSeed();
a1check(!$db->table_exists('a1_issuer_certificate_credentials'),'456 baseline cache primed before A1 CREATE');
$result=a1nativeRun($app);
a1check($result['success']===true&&a1nativeVersion($db)===458,'actual native upgrade456→457→458 confirms persisted marker');
a1check($db->markerWrites===[457,458],'native runner tracks both contiguous steps');
a1check(count(array_filter($GLOBALS['a1_native_events'],static fn($event)=>$event==='database_updated'))===1&&IssuerCore::$reconciled===1,'confirmed native upgrade fires one completion hook/reconciliation');
a1nativeReady($db);
$mails=a1nativeMails($db);a1check(count($mails)===3,'458 leaves unrelated email variants untouched');
a1check($mails[0]['language']==='english'&&$mails[0]['message']===$source['message'],'English source is preserved');
a1check($mails[1]['subject']==='ASSUNTO PERSONALIZADO {contact_firstname}'&&$mails[1]['message']==='CORPO PERSONALIZADO {reset_password_url}','company A custom subject/body are preserved');
a1check($mails[2]['message']===$source['message']&&$mails[2]['language']==='portuguese_br'&&(int)$mails[2]['issuer_id']===20,'458 does not rewrite another company email content');
$db->query('INSERT INTO a1_company_client_profiles VALUES(?,?,?)',[10,101,'{"custom":"preserve"}']);
$certificate=a1fixture();$manager=new IssuerCertificateManager($db,'a1_',a1encryption());$manager->replace(10,2,0,$certificate['pfx'],$certificate['password']);
$material=$db->query('SELECT * FROM a1_issuer_certificate_credentials')->result_array();$audit=$db->query('SELECT * FROM a1_issuer_certificate_audit')->result_array();
$steps=$db->markerWrites;$ddl=count($db->ddl);a1check(a1nativeRun($app)['success']===true&&$db->markerWrites===$steps&&count($db->ddl)===$ddl,'already-current native runner does not repeat migrations');
// Failed tracking means DDL/data may already exist; replay actual up() is conservative.
$db->query('UPDATE a1_migrations SET version=457');$result=a1nativeRun($app);a1check($result['success']===true&&a1nativeVersion($db)===458,'actual458 replay succeeds on existing tables/data');
a1check(a1nativeMails($db)===$mails,'email replay has no duplicate or content drift');
a1check($db->query('SELECT profile_json FROM a1_company_client_profiles')->row()->profile_json==='{"custom":"preserve"}','profile replay preserves customized JSON');
a1check($db->query('SELECT * FROM a1_issuer_certificate_credentials')->result_array()===$material&&$db->query('SELECT * FROM a1_issuer_certificate_audit')->result_array()===$audit,'migration replay does not rewrite certificates or audit');
foreach(['profile','certificate','marker_false','marker_ack_without_write','readback']as$failure){
    [$db,$app]=a1nativeSeed();$before=a1nativeMails($db);
    if($failure==='profile')$db->failSql='CREATE TABLE IF NOT EXISTS a1_company_client_profiles';
    elseif($failure==='certificate')$db->failSql='CREATE TABLE IF NOT EXISTS `a1_issuer_certificate_audit`';
    elseif($failure==='marker_false')$db->failMarker=458;
    elseif($failure==='marker_ack_without_write')$db->pretendMarker=458;
    else $db->failReadback=458;
    $result=a1nativeRun($app);
    a1check($result['success']===false,'native '.$failure.' cannot claim successful upgrade');
    a1check(a1nativeVersion($db)===($failure==='readback'?458:457),'native '.$failure.' leaves truthful persisted marker');
    a1check(!in_array('database_updated',$GLOBALS['a1_native_events'],true)&&IssuerCore::$reconciled===0,'native '.$failure.' suppresses completion hook/reconciliation');
    $db->failSql=null;$db->failMarker=null;$db->pretendMarker=null;$db->failReadback=null;
    a1check(a1nativeRun($app)['success']===true&&a1nativeVersion($db)===458,'native '.$failure.' safely resumes after repair');a1nativeReady($db);
    a1check(a1nativeMails($db)===$before,'native '.$failure.' retry preserves unrelated email variants');
}
echo 'Native458 '.(getenv('A1_NATIVE_SQLITE_SMOKE')==='1'?'SQLite smoke (NOT MySQL proof)':'MySQL').": actual shipped migrations/helpers and App readback validated; minimal456 fixture, unrelated reconciliation adapted.\n";
