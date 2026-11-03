<?php
/** Pure metadata contract; MySQL integration remains a separate required gate. */
declare(strict_types=1);
require_once __DIR__.'/../../application/services/NativeCompanyDepartmentSchema.php';
function departmentCheck(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
final class DepartmentSchemaRows{private array $rows;public function __construct(array $rows){$this->rows=$rows;}public function result_array():array{return $this->rows;}public function row_array():array{return $this->rows[0]??[];}}
final class DepartmentSchemaDb
{
    public array $tables=[];public array $writes=[];public bool $db_debug=true;public bool $save_queries=true;
    public function __construct(){foreach(NativeCompanyDepartmentSchema::definitions('tbl') as $name=>$parts)$this->tables['tbl'.$name]=self::metadata($parts);}
    private static function metadata(array $parts):array
    {
        $m=['columns'=>[],'indexes'=>[],'foreign'=>[],'rules'=>[],'engine'=>[['ENGINE'=>'InnoDB']]];
        foreach($parts as $part){
            if(preg_match('/^`([^`]+)` (varchar\([0-9]+\)|bigint unsigned|int unsigned|int|text|datetime)(.*)$/D',$part,$v)){
                preg_match('/COLLATE ([a-z0-9_]+)/',$v[3],$c);
                $m['columns'][]=['COLUMN_NAME'=>$v[1],'COLUMN_TYPE'=>$v[2],'IS_NULLABLE'=>str_contains($v[3],'NOT NULL')?'NO':'YES','COLUMN_DEFAULT'=>null,'COLLATION_NAME'=>$c[1]??null,'EXTRA'=>str_contains($v[3],'AUTO_INCREMENT')?'auto_increment':''];
                if(str_contains($v[3],'PRIMARY KEY'))$m['indexes'][]=['INDEX_NAME'=>'PRIMARY','COLUMN_NAME'=>$v[1],'NON_UNIQUE'=>0,'SUB_PART'=>null];
            }elseif(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$part,$v)){
                foreach(explode(',',str_replace('`','',$v[3])) as $col)$m['indexes'][]=['INDEX_NAME'=>$v[2],'COLUMN_NAME'=>$col,'NON_UNIQUE'=>$v[1]===''?1:0,'SUB_PART'=>null];
            }elseif(preg_match('/^FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$part,$v)){
                $n='fk'.count($m['rules']);$cols=explode(',',str_replace('`','',$v[1]));$refs=explode(',',str_replace('`','',$v[3]));
                foreach($cols as $i=>$col)$m['foreign'][]=['CONSTRAINT_NAME'=>$n,'COLUMN_NAME'=>$col,'REFERENCED_TABLE_NAME'=>$v[2],'REFERENCED_COLUMN_NAME'=>$refs[$i]];
                $m['rules'][]=['UPDATE_RULE'=>'RESTRICT','DELETE_RULE'=>'RESTRICT'];
            }
        }return $m;
    }
    public function query(string $sql,array $params=[]):DepartmentSchemaRows
    {
        if(str_starts_with($sql,'CREATE TABLE')){ $this->writes[]=$sql; preg_match('/CREATE TABLE IF NOT EXISTS `([^`]+)`/',$sql,$m);$parts=NativeCompanyDepartmentSchema::definitions('tbl')[substr($m[1],3)];$this->tables[$m[1]]=self::metadata($parts);return new DepartmentSchemaRows([]);}
        $t=$this->tables[$params[0]]??null;
        if(str_contains($sql,'SELECT TABLE_NAME FROM'))return new DepartmentSchemaRows($t?[['TABLE_NAME'=>$params[0]]]:[]);
        foreach(['COLUMNS'=>'columns','STATISTICS'=>'indexes','KEY_COLUMN_USAGE'=>'foreign','REFERENTIAL_CONSTRAINTS'=>'rules','TABLES'=>'engine'] as $needle=>$key)if(str_contains($sql,'information_schema.'.$needle))return new DepartmentSchemaRows($t[$key]??[]);
        throw new RuntimeException('Unexpected metadata query');
    }
}
$db=new DepartmentSchemaDb();NativeCompanyDepartmentSchema::ensure($db,'tbl');departmentCheck(!$db->writes,'replay never mutates existing tables');
unset($db->tables['tblcompany_department_members']);NativeCompanyDepartmentSchema::ensure($db,'tbl');departmentCheck(count($db->writes)===1,'only missing table created');
$mutations=[
    static function(&$t){$t['engine'][0]['ENGINE']='MyISAM';},
    static function(&$t){$t['columns'][0]['COLUMN_DEFAULT']='0';},
    static function(&$t){$t['columns'][0]['EXTRA']='auto_increment invisible';},
    static function(&$t){$t['columns'][0]['COLUMN_TYPE']='bigint unsigned';},
    static function(&$t){array_pop($t['columns']);},
    static function(&$t){$t['columns'][1]['IS_NULLABLE']='YES';},
    static function(&$t){$t['indexes'][1]['NON_UNIQUE']=1;},
    static function(&$t){$t['indexes'][0]['SUB_PART']=1;},
    static function(&$t){$t['foreign'][0]['REFERENCED_TABLE_NAME']='other_issuers_registry';},
    static function(&$t){$t['rules'][0]['DELETE_RULE']='CASCADE';},
];
foreach($mutations as $i=>$mutate){$db=new DepartmentSchemaDb();$mutate($db->tables['tblcompany_departments']);unset($db->tables['tblcompany_department_members']);$failed=false;try{NativeCompanyDepartmentSchema::ensure($db,'tbl');}catch(RuntimeException $e){$failed=$e->getMessage()==='issuer_native_department_schema_invalid';}departmentCheck($failed&&!$db->writes,'invalid existing schema must fail before DDL '.$i);}
echo "Native department schema: replay, missing-only creation and ten metadata negatives passed\n";
