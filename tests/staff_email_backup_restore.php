<?php
declare(strict_types=1);
/** Actual mysqli utility, native mysqli results, and disposable MySQL/MariaDB restore. */
define('BASEPATH', dirname(__DIR__).'/system/');
require BASEPATH.'database/DB_utility.php';
require BASEPATH.'database/drivers/mysqli/mysqli_utility.php';
require __DIR__.'/../application/services/StaffEmailIdentity.php';
if (!extension_loaded('mysqli')) { throw new RuntimeException('mysqli extension required for real backup proof'); }
$dsn=getenv('STAFF_IDENTITY_TEST_DSN');
if (!is_string($dsn)||!str_starts_with($dsn,'mysql:')) { throw new RuntimeException('Explicit disposable MySQL test DSN required'); }
$settings=[];
foreach(explode(';',substr($dsn,6)) as $part){$pair=explode('=',$part,2);if(count($pair)===2){$settings[$pair[0]]=$pair[1];}}
if(empty($settings['host'])||empty($settings['dbname'])){throw new RuntimeException('Disposable host and database required');}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$connection=new mysqli($settings['host'],getenv('STAFF_IDENTITY_TEST_USER')?:'root',getenv('STAFF_IDENTITY_TEST_PASSWORD')?:'',$settings['dbname'],(int)($settings['port']??3306));
$connection->set_charset('utf8mb4');
final class BackupProofResult
{
    public function __construct(public mysqli_result $result_id){}
    public function result_array():array{if($this->result_id->num_rows>0)$this->result_id->data_seek(0);return $this->result_id->fetch_all(MYSQLI_ASSOC);}
    public function num_rows():int{return $this->result_id->num_rows;}
}
final class BackupProofDb
{
    public bool $db_debug=true,$save_queries=true;
    public ?string $metadataFailure=null;
    public function __construct(public mysqli $connection,public string $database){}
    public function query(string $sql)
    {
        if(str_starts_with($sql,'SHOW FULL COLUMNS')&&$this->metadataFailure!==null){
            if($this->metadataFailure==='false')return false;
            if($this->metadataFailure==='throw')throw new RuntimeException('private SQL metadata detail');
            return new class {public function result_array():array{return [['Field'=>'staffid']];}};
        }
        $result=$this->connection->query($sql);
        return $result instanceof mysqli_result?new BackupProofResult($result):$result;
    }
    public function escape_identifiers($name):string{return implode('.',array_map(static fn($part)=>'`'.str_replace('`','``',$part).'`',explode('.',$name)));}
    public function protect_identifiers($name):string{return $this->escape_identifiers($name);}
    public function escape($value):string{return "'".$this->connection->real_escape_string((string)$value)."'";}
}
final class BackupProofUtility extends CI_DB_mysqli_utility
{
    public function __construct($db){$this->db=$db;}
    public function dump(array $tables,bool $insert=true):string
    {return $this->_backup(['tables'=>$tables,'ignore'=>[],'add_drop'=>true,'add_insert'=>$insert,'foreign_key_checks'=>false,'newline'=>"\n"]);}
}
$checks=0;function backupCheck(bool $ok,string $label):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);}
$prefix='backup_sid_'.bin2hex(random_bytes(6)).'_';
$tables=array_map(static fn($suffix)=>$prefix.$suffix,['staff','links','normal','empty','all_generated']);
[$staff,$links,$normal,$empty,$all]=$tables;
$db=new BackupProofDb($connection,$settings['dbname']);$utility=new BackupProofUtility($db);
$snapshot=static function(array $names)use($connection):array{$out=[];foreach($names as $name){$out[$name]=$connection->query('SELECT * FROM `'.$name.'`')->fetch_all(MYSQLI_ASSOC);usort($out[$name],static fn($a,$b)=>serialize($a)<=>serialize($b));}return $out;};
try {
    $connection->query("CREATE TABLE `$staff` (staffid INT NOT NULL PRIMARY KEY,email VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,staff_email_identity VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED,UNIQUE KEY uq_staff_email_identity(staff_email_identity)) ENGINE=InnoDB");
    $connection->query("CREATE TABLE `$links` (issuer_id INT NOT NULL,staff_id INT NOT NULL,PRIMARY KEY(issuer_id,staff_id),FOREIGN KEY(staff_id) REFERENCES `$staff`(staffid)) ENGINE=InnoDB");
    $connection->query("CREATE TABLE `$normal` (id INT NOT NULL PRIMARY KEY,label VARCHAR(100),optional_value INT NULL,enabled TINYINT NOT NULL DEFAULT 1,payload LONGBLOB NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $connection->query("CREATE TABLE `$empty` (id INT NOT NULL,derived_id INT GENERATED ALWAYS AS(id+1) VIRTUAL) ENGINE=InnoDB");
    $connection->query("INSERT INTO `$staff`(staffid,email) VALUES(17,' Owner@Example.Invalid '),(21,'other@example.invalid')");
    $connection->query("INSERT INTO `$links` VALUES(1,17),(2,17),(2,21)");
    $connection->query("INSERT INTO `$normal`(id,label,optional_value,enabled,payload) VALUES(1,'O\'Brien',NULL,0,X'0001FF'),(2,'synthetic',42,1,NULL)");
    $active=array_slice($tables,0,4);
    // MySQL requires a non-generated field; some MariaDB versions allow constants only.
    try {
        $connection->query("CREATE TABLE `$all` (fixed_value INT GENERATED ALWAYS AS(42) VIRTUAL) ENGINE=InnoDB");
        $connection->query("INSERT INTO `$all` () VALUES (),()");$active[]=$all;
    } catch(mysqli_sql_exception $error) {
        if(!in_array((int)$error->getCode(),[1064,1113,3106],true))throw new RuntimeException('Unexpected generated-only fixture failure');
        $connection->query("DROP TABLE IF EXISTS `$all`");
    }
    $before=$snapshot($active);
    $dump=$utility->dump($active);
    backupCheck(str_contains($dump,'GENERATED ALWAYS AS'),'dump retains generated DDL');
    backupCheck(str_contains($dump,'uq_staff_email_identity'),'dump retains unique constraint DDL');
    foreach(explode("\n",$dump)as$line){if(str_starts_with($line,'INSERT INTO `'.$staff.'`'))backupCheck(!str_contains($line,'staff_email_identity'),'staff INSERT excludes generated column');}
    foreach(explode("\n",$dump)as$line){if(str_starts_with($line,'INSERT INTO `'.$normal.'`'))backupCheck(str_contains($line,'`created_at`')&&str_contains($line,'`payload`')&&str_contains($line,'`enabled`'),'ordinary DEFAULT_GENERATED timestamp binary and boolean columns retained');}
    backupCheck(!str_contains($dump,'INSERT INTO `'.$empty.'`'),'empty table emits no data INSERT');
    backupCheck(str_contains($dump,'CREATE TABLE `'.$empty.'`'),'empty table DDL remains');
    backupCheck(str_contains($utility->dump($active,false),'GENERATED ALWAYS AS'),'schema-only dump retains DDL');
    $connection->multi_query($dump);
    do {if($result=$connection->store_result())$result->free();} while($connection->more_results()&&$connection->next_result());
    backupCheck($snapshot($active)===$before,'restore preserves original staff text IDs memberships generated values normal fields and empty tables');
    $rejected=false;
    try{$connection->query("INSERT INTO `$staff`(staffid,email) VALUES(99,'owner@example.invalid')");}catch(mysqli_sql_exception $error){$rejected=(int)$error->getCode()===1062;}
    backupCheck($rejected,'restored unique identity still enforced');
    foreach(['false','throw','malformed']as$failure){
        $db->metadataFailure=$failure;$caught=false;
        try{$utility->dump($active);}catch(RuntimeException $error){$caught=$error->getMessage()==='database_backup_metadata_failed';}
        backupCheck($caught,'metadata '.$failure.' fails closed without partial dump');
        backupCheck($db->db_debug&&$db->save_queries,'metadata '.$failure.' restores driver flags');
    }
    $db->metadataFailure=null;
    backupCheck($snapshot($active)===$before,'failed backups never change stored data');
    echo 'Staff generated-column backup/restore: '.$checks.' checks passed (real mysqli utility and database).'.PHP_EOL;
} finally {
    $connection->query('SET foreign_key_checks=0');
    foreach(array_reverse($tables)as$table){$connection->query('DROP TABLE IF EXISTS `'.$table.'`');}
    $connection->query('SET foreign_key_checks=1');$connection->close();
}
