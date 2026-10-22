<?php
declare(strict_types=1);
/** Actual App_Migration/App + repository migration 466 on a disposable MySQL/MariaDB database.
 * Framework loading and post-migration IssuerCore reconciliation are adapted boundaries.
 * This is a fresh-native-schema/upgrade proof, not a full web-installer end-to-end test.
 */
require __DIR__.'/companies/staff_fixture.php';
require BASEPATH.'libraries/Migration.php';
require APPPATH.'libraries/App_Migration.php';
final class IssuerCore
{
    public static int $calls=0;
    public static function reconcile($db,string $prefix): void { ++self::$calls; }
    public static function ready($db,string $prefix): bool { return true; }
}
require APPPATH.'libraries/App.php';
function config_item($key){return $key==='subclass_prefix'?'App_':null;}
function &get_instance(){return $GLOBALS['coreIdentityCi'];}
function db_prefix(){return $GLOBALS['coreIdentityPrefix'];}
function log_message($level,$message){$GLOBALS['coreIdentityLogs'][]=$message;}
function delete_option($name){}
function add_option($name,$value){}
function hooks(){return new class{function do_action($name,...$args){$GLOBALS['coreIdentityEvents'][]=$name;}};}
final class CoreIdentityTrackingDb extends StaffTestDb
{
    public string $markerMode='normal';
    public int $markerWrites=0;
    public array $sql=[];
    public function limit($n): self{return $this;}
    public function update($table,$data): bool
    {
        if ($table===$this->prefix.'migrations') {
            ++$this->markerWrites;
            if($this->markerMode==='fail')return false;
            if($this->markerMode==='ignore')return true;
        }
        return parent::update($table,$data);
    }
    public function query(string $sql,array $params=[])
    {
        $this->sql[]=$sql;
        return parent::query($sql,$params);
    }
}
function coreIdentitySnapshot(StaffTestDb $db): array
{
    $state=staffSnapshot($db);
    foreach($state['staff'] as &$row){unset($row[StaffEmailIdentity::COLUMN]);}unset($row);
    return $state;
}
function coreIdentityRunner(CoreIdentityTrackingDb $db): array
{
    $GLOBALS['coreIdentityPrefix']=$db->prefix;
    $ci=(object)['db'=>$db];$GLOBALS['coreIdentityCi']=$ci;
    $ci->config=new class{function item($key){return $key==='migration_version'?466:null;}};
    $ci->load=new class{function config($name){}function library($name,$options){}};
    $reflection=new ReflectionClass(App_Migration::class);$migration=$reflection->newInstanceWithoutConstructor();
    foreach(['_migration_type'=>'sequential','_migration_path'=>APPPATH.'migrations/','_migration_version'=>466,'_migration_table'=>$db->prefix.'migrations','_migration_regex'=>'/^\d{3}_(\w+)$/'] as $key=>$value){$reflection->getProperty($key)->setValue($migration,$value);}
    $ci->migration=$migration;
    $reflection=new ReflectionClass(App::class);$app=$reflection->newInstanceWithoutConstructor();$reflection->getProperty('ci')->setValue($app,$ci);
    return [$app,$reflection->getMethod('upgrade_database_silent')];
}
function coreIdentityBase(CoreIdentityTrackingDb $db): void
{
    $table=$db->prefix.'staff';
    if($db->field_exists(StaffEmailIdentity::COLUMN,$table)){$db->query('ALTER TABLE `'.$table.'` DROP INDEX `'.StaffEmailIdentity::INDEX.'`, DROP COLUMN `'.StaffEmailIdentity::COLUMN.'`');}
    $db->query('UPDATE '.$db->prefix.'migrations SET version=465');
    $db->markerMode='normal';$db->markerWrites=0;$db->sql=[];$GLOBALS['coreIdentityEvents']=[];IssuerCore::$calls=0;
}
if(!is_file(APPPATH.'migrations/466_version_466.php'))throw new RuntimeException('Repository core migration 466 required; no substitute fixture allowed');
$fixture=staffFixture();$db=new CoreIdentityTrackingDb($fixture->pdo,$fixture->prefix);$p=$db->prefix;
$db->query('CREATE TABLE '.$p.'migrations(version BIGINT NOT NULL) ENGINE=InnoDB');$db->query('INSERT INTO '.$p.'migrations(version) VALUES(465)');
$GLOBALS['coreIdentityEvents']=[];$GLOBALS['coreIdentityLogs']=[];
[$app,$run]=coreIdentityRunner($db);$start=$GLOBALS['staffChecks'];
try{
    // Fresh native schema from install/database.sql with synthetic bootstrap identities/links.
    coreIdentityBase($db);$before=coreIdentitySnapshot($db);
    $result=$run->invoke($app);
    staffCheck($result['success']===true&&$app->get_current_db_version()==466,'actual migration466 records confirmed marker on fresh native schema');
    staffCheck($db->field_exists(StaffEmailIdentity::COLUMN,$p.'staff'),'actual466 creates generated identity column');
    $index=$db->query('SELECT NON_UNIQUE,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$p.'staff',StaffEmailIdentity::INDEX])->result_array();
    staffCheck(count($index)===1&&(int)$index[0]['NON_UNIQUE']===0&&$index[0]['COLUMN_NAME']===StaffEmailIdentity::COLUMN&&$index[0]['SUB_PART']===null,'actual466 persists full unique canonical index');
    staffCheck(coreIdentitySnapshot($db)===$before,'native466 preserves bootstrap staff, master and all memberships');
    staffCheck($db->markerWrites===1&&IssuerCore::$calls===1&&in_array('database_updated',$GLOBALS['coreIdentityEvents'],true),'completion follows confirmed marker');
    $queries=count($db->sql);$writes=$db->markerWrites;$result=$run->invoke($app);
    staffCheck($result['success']===true&&$db->markerWrites===$writes,'native replay never rewrites marker');
    staffCheck(!array_filter(array_slice($db->sql,$queries),static fn($sql)=>str_starts_with($sql,'ALTER TABLE')),'native replay does not repeat DDL');

    // Preflight refuses legacy duplicates before DDL and before version tracking.
    coreIdentityBase($db);$original=$db->query('SELECT email FROM '.$p.'staff WHERE staffid=22')->row()->email;
    $duplicate=$db->query('SELECT email FROM '.$p.'staff WHERE staffid=21')->row()->email;
    $db->query('UPDATE '.$p.'staff SET email=? WHERE staffid=22',[' '.strtoupper($duplicate).' ']);$before=coreIdentitySnapshot($db);$db->sql=[];
    $result=$run->invoke($app);
    staffCheck($result['success']===false&&$app->get_current_db_version()==465&&$db->markerWrites===0,'preflight failure never advances native marker');
    staffCheck(!$db->field_exists(StaffEmailIdentity::COLUMN,$p.'staff')&&coreIdentitySnapshot($db)===$before,'preflight failure changes neither schema nor identities/links');
    staffCheck(IssuerCore::$calls===0&&!in_array('database_updated',$GLOBALS['coreIdentityEvents'],true),'preflight failure never signals completion');
    staffCheck(!str_contains(json_encode($GLOBALS['coreIdentityLogs']),$duplicate),'failure logs contain no staff email');
    // Synthetic fixture repair is test setup, never performed by migration.
    $db->query('UPDATE '.$p.'staff SET email=? WHERE staffid=22',[$original]);

    coreIdentityBase($db);$db->query('UPDATE '.$p.'staff SET email=? WHERE staffid=22',["invalid\t@example.invalid"]);$before=coreIdentitySnapshot($db);
    $result=$run->invoke($app);
    staffCheck($result['success']===false&&$app->get_current_db_version()==465&&$db->markerWrites===0,'invalid legacy email blocks actual466 before tracking');
    staffCheck(!$db->field_exists(StaffEmailIdentity::COLUMN,$p.'staff')&&coreIdentitySnapshot($db)===$before,'invalid legacy email is not rewritten by upgrade');
    $db->query('UPDATE '.$p.'staff SET email=? WHERE staffid=22',[$original]);

    // DDL interruption: column may remain, but marker must not advance and replay must finish.
    coreIdentityBase($db);$before=coreIdentitySnapshot($db);$db->failOn='ADD UNIQUE INDEX';
    $result=$run->invoke($app);$db->failOn=null;
    staffCheck($result['success']===false&&$app->get_current_db_version()==465&&$db->markerWrites===0,'DDL interruption leaves old native marker');
    staffCheck($db->field_exists(StaffEmailIdentity::COLUMN,$p.'staff')&&coreIdentitySnapshot($db)===$before,'partial additive DDL preserves all original rows');
    staffCheck($run->invoke($app)['success']===true&&$app->get_current_db_version()==466,'native retry resumes partial466 and confirms marker');

    foreach(['fail','ignore'] as $mode){
        coreIdentityBase($db);$before=coreIdentitySnapshot($db);$db->markerMode=$mode;
        $result=$run->invoke($app);
        staffCheck($result['success']===false&&$app->get_current_db_version()==465,'marker '.$mode.' cannot claim completed native466');
        staffCheck($db->markerWrites===1&&IssuerCore::$calls===0&&!in_array('database_updated',$GLOBALS['coreIdentityEvents'],true),'marker '.$mode.' suppresses downstream completion');
        staffCheck($db->field_exists(StaffEmailIdentity::COLUMN,$p.'staff')&&coreIdentitySnapshot($db)===$before,'marker '.$mode.' preserves additive schema and original identities');
        $db->markerMode='normal';
        staffCheck($run->invoke($app)['success']===true&&$app->get_current_db_version()==466,'marker '.$mode.' retry verifies schema and persists466');
        staffCheck(coreIdentitySnapshot($db)===$before,'marker retry preserves staff/master/links');
    }
    $db->query('UPDATE '.$p.'migrations SET version=467');$writes=$db->markerWrites;
    staffCheck($run->invoke($app)['success']===false&&$app->get_current_db_version()==467&&$db->markerWrites===$writes,'App refuses downgrade of newer marker without removing constraint');
    echo 'Core466 native migration tracking: '.($GLOBALS['staffChecks']-$start).' checks passed (actual App/App_Migration/466, real MySQL/MariaDB; reconciliation boundary adapted).'.PHP_EOL;
}finally{
    $db->failOn=null;if($db->pdo->inTransaction())$db->pdo->rollBack();$db->query('DROP TABLE IF EXISTS '.$p.'migrations');
}
