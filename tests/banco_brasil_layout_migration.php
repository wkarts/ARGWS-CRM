<?php
declare(strict_types=1);
require __DIR__.'/banco_brasil_module_version.php'; // Native 101 regression remains executable unchanged.
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/LayoutSchema.php';
class BbLayoutMigrationDb extends BbMigrationDb
{
    public $catalog=[];public $badLayout='';public $failAlter=false;public $fail102Tracking=false;
    public function __construct(){ $this->version='1.0.1';$this->catalog=\BancoBrasil\SchemaValidator::requirements(); }
    public function update($t,$r){if($this->fail102Tracking&&$r['installed_version']==='1.0.2')return false;return parent::update($t,$r);}
    public function query($sql,$bind=[]){
        if(str_starts_with($sql,'CREATE')){
            $this->statements[]=$sql;if($this->failDdl)return false;
            if(str_contains($sql,'bb_layout_bindings'))$this->catalog['bb_layout_bindings']=\BancoBrasil\LayoutSchema::requirements()['bb_layout_bindings'];return true;
        }
        if(str_starts_with($sql,'ALTER')){
            $this->statements[]=$sql;if($this->failAlter)return false;
            preg_match('/`test_(bb_[a-z_]+)` ADD COLUMN `([a-z_]+)`/',$sql,$m);
            $this->catalog[$m[1]]['columns'][$m[2]]=\BancoBrasil\LayoutSchema::requirements()[$m[1]]['columns'][$m[2]];return true;
        }
        $table=substr($bind[0],5);$spec=$this->catalog[$table]??null;$rows=[];
        if(count($bind)===2){$rows=isset($spec['columns'][$bind[1]])?[['COLUMN_NAME'=>$bind[1]]]:[];}
        elseif(str_contains($sql,'information_schema.TABLES')){
            if($spec)$rows=[['ENGINE'=>$table==='bb_layout_bindings'&&$this->badLayout==='engine'?'MyISAM':'InnoDB','TABLE_COLLATION'=>'utf8mb4_bin']];
        }elseif(str_contains($sql,'information_schema.COLUMNS')){
            foreach($spec['columns']??[] as $name=>$type){$text=preg_match('/^(?:var)?char\(|^text$/',rtrim($type,'?'));$rows[]=['COLUMN_NAME'=>$name,'COLUMN_TYPE'=>rtrim($type,'?'),'IS_NULLABLE'=>str_ends_with($type,'?')?'YES':'NO','EXTRA'=>$name==='id'?'auto_increment':'','COLUMN_DEFAULT'=>null,'CHARACTER_SET_NAME'=>$text?'utf8mb4':null,'COLLATION_NAME'=>$text?'utf8mb4_bin':null];}
            if($table==='bb_layout_bindings'){
                if($this->badLayout==='missing')array_pop($rows);
                if($this->badLayout==='width')$rows[4]['COLUMN_TYPE']='varchar(64)';
                if($this->badLayout==='null')$rows[4]['IS_NULLABLE']='YES';
            }
            if($table==='bb_return_files'&&$this->badLayout==='nonnull')foreach($rows as &$row)if($row['COLUMN_NAME']==='layout_binding_id')$row['IS_NULLABLE']='NO';
        }else{
            foreach(array_merge([['id']],$spec['unique']??[]) as $key=>$cols)foreach($cols as $i=>$col)$rows[]=['INDEX_NAME'=>$key===0?'PRIMARY':'unique_'.$key,'NON_UNIQUE'=>0,'SEQ_IN_INDEX'=>$i+1,'COLUMN_NAME'=>$col,'SUB_PART'=>null];
            if($table==='bb_layout_bindings'){
                if($this->badLayout==='unique')$rows=array_slice($rows,0,1);
                if($this->badLayout==='prefix')$rows[2]['SUB_PART']=8;
            }
        }
        return new class($rows){function __construct(private $r){}function row_array(){return $this->r[0]??[];}function result_array(){return $this->r;}};
    }
}
$n=0;$CI->db=new BbLayoutMigrationDb();$CI->app_modules=new class{function get($v){return ['headers'=>['version'=>'1.0.2']];}};
$original101=file_get_contents(APP_MODULES_PATH.'banco_brasil/migrations/101_version_101.php');
check(hash('sha256',$original101)==='5ccb8760ab18b3beae7d6fe7d1b7864e4ce7cd3f925ac24c7e582a27def7e306','published migration101 unchanged');
check((string)(new App_module_migration('banco_brasil'))->to_latest()==='102','native101 to102');
check($CI->db->version==='1.0.2','native tracking102');
check(count($CI->db->catalog)===8,'eight tables');
check($CI->db->catalog['bb_return_files']['columns']['layout_binding_id']==='bigint unsigned?','legacy nullable provenance');
check((new App_module_migration('banco_brasil'))->to_latest()===true,'current no-op');
$statements=count($CI->db->statements);require APP_MODULES_PATH.'banco_brasil/install.php';
check($CI->db->version==='1.0.2','reactivation tracks target');
check(count($CI->db->statements)===$statements+8,'reactivation no repeated ALTER');
$CI->db=new BbLayoutMigrationDb();$CI->db->version='1.0.2';require APP_MODULES_PATH.'banco_brasil/install.php';
check($CI->db->version==='1.0.2'&&count($CI->db->catalog)===8,'fresh activation executes101 then102');
foreach(['engine','missing','width','null','unique','prefix','nonnull'] as $bad){
    $CI->db=new BbLayoutMigrationDb();$CI->db->badLayout=$bad;
    try{(new App_module_migration('banco_brasil'))->to_latest();throw new LogicException('schema accepted');}
    catch(RuntimeException $e){check($e->getMessage()==='bb_schema_incompatible','bad102 schema refused '.$bad);check($CI->db->version==='1.0.1','version remains101 '.$bad);}
}
$CI->db=new BbLayoutMigrationDb();$CI->db->failAlter=true;
try{(new App_module_migration('banco_brasil'))->to_latest();throw new LogicException('alter accepted');}catch(RuntimeException $e){check($CI->db->version==='1.0.1','partialDDL no advance');}
$CI->db->failAlter=false;check((string)(new App_module_migration('banco_brasil'))->to_latest()==='102','additiveDDL retry');
check(!preg_match('/DROP|TRUNCATE|DELETE FROM|UPDATE /i',implode("\n",$CI->db->statements)),'no backfill or destructive DDL');
$CI->db=new BbLayoutMigrationDb();$CI->db->fail102Tracking=true;
try{require APP_MODULES_PATH.'banco_brasil/install.php';throw new LogicException('tracking accepted');}catch(RuntimeException $e){check($e->getMessage()==='bb_migration_version_unconfirmed','failed102 tracking visible');}
echo "OK {$n} Banco do Brasil layout migration checks\n";
