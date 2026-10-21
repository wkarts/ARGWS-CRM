<?php
declare(strict_types=1);
/** Synthetic real-MySQL/MariaDB fixture; no production bootstrap or SQLite emulation. */
defined('BASEPATH')||define('BASEPATH',dirname(__DIR__,2).'/system/');
defined('APPPATH')||define('APPPATH',dirname(__DIR__,2).'/application/');
require_once APPPATH.'services/CompanyStaff.php';
require_once APPPATH.'services/issuers/IssuerSchema.php';
require_once APPPATH.'services/issuers/IssuerAccessSchema.php';

class StaffTestResult
{
    public function __construct(private array $rows){}
    public function result_array():array{return $this->rows;}public function row_array():array{return $this->rows[0]??[];}public function row(){return isset($this->rows[0])?(object)$this->rows[0]:null;}
}
class StaffTestDb
{
    public bool $db_debug=false;public bool $save_queries=false;public array $data_cache=[];public ?string $failOn=null;public ?string $ignoreOn=null;public $observer=null;public array $writes=[];
    private array $where=[];private string $select='*';private ?string $from=null;private int $affected=0;
    public function __construct(public PDO $pdo,public string $prefix){$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}
    public function query(string $sql,array $params=[])
    {
        if($this->failOn!==null&&str_contains($sql,$this->failOn)){throw new RuntimeException('injected_staff_failure');}
        if(preg_match('/^(INSERT|UPDATE|DELETE)\b/',$sql)){$this->writes[]=$sql;}
        if($this->ignoreOn!==null&&str_contains($sql,$this->ignoreOn)){$this->affected=0;return new StaffTestResult([]);}
        $s=$this->pdo->prepare($sql);$s->execute($params);$this->affected=$s->rowCount();$result=new StaffTestResult($s->columnCount()?$s->fetchAll(PDO::FETCH_ASSOC):[]);
        if($this->observer!==null){($this->observer)($sql,$params,$this);}return $result;
    }
    public function field_exists($field,$table):bool{return (bool)$this->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$field])->result_array();}
    public function table_exists($table):bool{return (bool)$this->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();}
    public function trans_begin():bool{return $this->pdo->beginTransaction();}public function trans_commit():bool{return $this->pdo->commit();}public function trans_rollback():bool{return !$this->pdo->inTransaction()||$this->pdo->rollBack();}public function trans_status():bool{return true;}public function insert_id():int{return (int)$this->pdo->lastInsertId();}public function affected_rows():int{return $this->affected;}
    private function table(string $table):string{return str_starts_with($table,$this->prefix)?$table:$this->prefix.$table;}
    public function where($column,$value=null):self{if(is_array($column)){$this->where=array_merge($this->where,$column);}else{$this->where[$column]=$value;}return $this;}
    public function select($columns):self{$this->select=$columns;return $this;}public function from($table):self{$this->from=$table;return $this;}
    private function take():array{$w=$this->where;$this->where=[];return [$w?' WHERE '.implode(' AND ',array_map(static fn($f)=>$f.'=?',array_keys($w))):'',array_values($w)];}
    public function get($table=null){[$w,$p]=$this->take();$t=$table??$this->from;$this->from=null;$select=$this->select;$this->select='*';return $this->query('SELECT '.$select.' FROM '.$this->table($t).$w,$p);}
    public function insert($table,$data):bool{$this->query('INSERT INTO '.$this->table($table).' ('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));return true;}
    public function update($table,$data):bool{[$w,$p]=$this->take();if(!$data){$this->affected=0;return true;}$this->query('UPDATE '.$this->table($table).' SET '.implode(',',array_map(static fn($f)=>$f.'=?',array_keys($data))).$w,array_merge(array_values($data),$p));return true;}
    public function delete($table):bool{[$w,$p]=$this->take();$this->query('DELETE FROM '.$this->table($table).$w,$p);return true;}
}
function staffPdo():PDO{$dsn=getenv('ISSUERS_TEST_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:')){throw new RuntimeException('Real MySQL/MariaDB required');}return new PDO($dsn,getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD'));}
$GLOBALS['staffChecks']=0;$GLOBALS['staffFixtures']=[];
function staffCheck(bool $value,string $label):void{++$GLOBALS['staffChecks'];if(!$value){throw new RuntimeException('FAIL: '.$label);}}
function staffThrows(callable $operation,string $code,string $label):void{try{$operation();}catch(Throwable $e){staffCheck($e->getMessage()===$code,$label.' actual '.$e->getMessage());return;}throw new RuntimeException('FAIL: missing '.$label);}
function staffKey():string{return bin2hex(random_bytes(32));}
function staffSnapshot(StaffTestDb $db):array
{
    $out=[];foreach(['staff','staff_departments','staff_permissions','customfieldsvalues','dismissed_announcements','issuers_installation_identity','issuers_staff','issuers_staff_defaults','issuers_resource_permissions','issuers_audit','company_staff_commands']as$table){$rows=$db->query('SELECT * FROM '.$db->prefix.$table)->result_array();foreach($rows as&$row){ksort($row);}unset($row);usort($rows,static fn($a,$b)=>json_encode($a)<=>json_encode($b));$out[$table]=$rows;}return $out;
}
function staffFixture():StaffTestDb
{
    $prefix='staff453_'.bin2hex(random_bytes(5)).'_';$db=new StaffTestDb(staffPdo(),$prefix);$GLOBALS['staffFixtures'][]=$db;
    $schema=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
    foreach(['staff'=>'staffid','staff_departments'=>'staffdepartmentid','staff_permissions'=>null,'customfields'=>'id','customfieldsvalues'=>'id','announcements'=>'announcementid','dismissed_announcements'=>'dismissedannouncementid','departments'=>'departmentid','roles'=>'roleid']as$table=>$key){
        if(!preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \([\s\S]+?;/', $schema,$m)){throw new RuntimeException('Missing native table '.$table);}
        $db->query(str_replace('`tbl'.$table.'`','`'.$prefix.$table.'`',$m[0]));if($key!==null){$db->query('ALTER TABLE '.$prefix.$table.' ADD PRIMARY KEY('.$key.'), MODIFY '.$key.' int NOT NULL AUTO_INCREMENT');}
    }
    foreach(IssuerSchema::statements($prefix)as$sql){$db->query($sql);}IssuerSchema::ensureIntegrationPermissions($db,$prefix);IssuerAccessSchema::ensure($db,$prefix);CompanyStaffSchema::ensure($db,$prefix);CompanyStaffSchema::ensure($db,$prefix);
    require_once APPPATH.'third_party/phpass.php';$hash=(new PasswordHash(8,true))->HashPassword('synthetic-staff-password');
    foreach([17=>1,21=>0,22=>0,23=>1]as$id=>$admin){$db->query('INSERT INTO '.$prefix.'staff(staffid,firstname,lastname,email,password,datecreated,admin,active,role) VALUES(?,?,?,?,?,NOW(),?,1,0)',[$id,'Synthetic','Staff'.$id,'staff'.$id.'@example.invalid',$hash,$admin]);}
    foreach([1=>'Principal',2=>'Empresa A',3=>'Empresa B']as$id=>$name){$db->query('INSERT INTO '.$prefix.'issuers_registry(id,legal_name,tax_id,active,settings_json,created_by,created_at) VALUES(?,?,NULL,1,\'{}\',17,NOW())',[$id,$name]);}
    $db->query('INSERT INTO '.$prefix.'issuers_installation_identity(id,master_staff_id,primary_issuer_id,source,resolved_at) VALUES(1,17,1,\'synthetic\',NOW())');
    foreach([[17,1],[21,2],[22,3],[23,1]]as[$staff,$issuer]){$db->query('INSERT INTO '.$prefix.'issuers_staff(issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations) VALUES(?,?,1,1,1,1)',[$issuer,$staff]);$db->query('INSERT INTO '.$prefix.'issuers_staff_defaults(staff_id,issuer_id) VALUES(?,?)',[$staff,$issuer]);}
    foreach(['view','create','edit','delete']as$cap){$db->query('INSERT INTO '.$prefix.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(2,21,\'staff\',?)',[$cap]);}
    $db->query('INSERT INTO '.$prefix.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(2,21,\'tasks\',\'view\'),(1,23,\'core_legacy_administration\',\'access\')');
    $db->query('INSERT INTO '.$prefix.'departments(departmentid,name) VALUES(1,\'Synthetic department\'),(2,\'Other department\')');
    $db->query('INSERT INTO '.$prefix.'announcements(announcementid,name,message,showtostaff,showtousers,showname,dateadded,userid) VALUES(1,\'Synthetic\',\'Local only\',1,0,0,NOW(),\'17\')');
    $db->query('INSERT INTO '.$prefix.'customfields(id,fieldto,name,slug,type,options,display_inline,field_order,active,show_on_pdf,show_on_ticket_form,only_admin,required,show_on_table,bs_column,disalow_client_to_edit,show_on_client_portal) VALUES(1,\'staff\',\'Synthetic field\',\'synthetic\',\'input\',\'\',0,0,1,0,0,0,0,0,12,0,0)');
    return $db;
}
register_shutdown_function(static function(){foreach($GLOBALS['staffFixtures']as$db){try{if($db->pdo->inTransaction()){$db->pdo->rollBack();}$db->failOn=null;$db->ignoreOn=null;$db->observer=null;foreach(['company_staff_commands','issuers_resource_permissions','issuers_installation_identity','issuers_client_links','issuers_clients','issuers_staff_defaults','issuers_staff','issuers_audit','issuers_registry','issuers_lock','staff_departments','staff_permissions','customfieldsvalues','dismissed_announcements','customfields','announcements','departments','roles','staff']as$table){$db->pdo->exec('DROP TABLE IF EXISTS '.$db->prefix.$table);}}catch(Throwable $e){fwrite(STDERR,'Synthetic staff cleanup failed: '.$e->getMessage().PHP_EOL);}}});
