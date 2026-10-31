<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Core 474 evolves the existing task records. It does not adopt, copy or infer historical task attributes. */
final class NativeCompanyTaskFormSchema
{
    public const TABLES=['company_tasks','company_standalone_tasks'];
    public const ADDITIONS=['native_fields_json'=>'longtext','native_fields_revision'=>'int unsigned'];
    private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    public static function columns($db,string$p,string$table):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||!in_array($table,self::TABLES,true))throw new InvalidArgumentException('Invalid task schema');
        $out=[];
        if(self::sqlite($db))foreach(IssuerStorage::query($db,'PRAGMA table_info(`'.$p.$table.'`)')->result_array()as$r)$out[$r['name']]=['COLUMN_TYPE'=>strtolower($r['type']),'IS_NULLABLE'=>(int)$r['notnull']?'NO':'YES','COLUMN_DEFAULT'=>$r['dflt_value'],'COLLATION_NAME'=>null,'EXTRA'=>''];
        else foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array()as$r)$out[$r['COLUMN_NAME']]=$r;
        return$out;
    }
    /** Historical readers may tolerate a partially applied additive migration, but only exact new column metadata. */
    public static function assertAdditions(array$columns,bool$sqlite=false,bool$complete=false):void
    {
        foreach(self::ADDITIONS as$name=>$type){if(!isset($columns[$name])){if($complete)self::fail();continue;}$r=$columns[$name];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($r['COLUMN_TYPE']??''))!==$type||($r['IS_NULLABLE']??null)!=='YES'||!IssuerStorage::sqlNullDefault($r)||($r['EXTRA']??null)!==''||(!$sqlite&&($r['COLLATION_NAME']??null)!==($type==='longtext'?'utf8mb4_bin':null)))self::fail();}
    }
    public static function assertValid($db,string$p):void
    {
        foreach(self::TABLES as$table){$cols=self::columns($db,$p,$table);self::assertAdditions($cols,self::sqlite($db),true);foreach(['assignee_id'=>'int','due_date'=>'date']as$name=>$type){$r=$cols[$name]??[];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($r['COLUMN_TYPE']??''))!==$type||($r['IS_NULLABLE']??null)!=='YES'||!IssuerStorage::sqlNullDefault($r)||($r['EXTRA']??null)!=='')self::fail();}}
    }
    private static function fingerprint($db,string$p,string$table):string
    {
        $hash=hash_init('sha256');$after=0;do{$rows=IssuerStorage::query($db,'SELECT * FROM '.$p.$table.' WHERE id>? ORDER BY id LIMIT 1000',[$after])->result_array();foreach($rows as$r){$after=IssuerPolicy::id($r['id']);foreach(self::ADDITIONS as$name=>$type)if(array_key_exists($name,$r)){if($r[$name]!==null)self::fail();unset($r[$name]);}$r=array_map(static fn($v)=>$v===null?null:(string)$v,$r);hash_update($hash,json_encode($r,JSON_THROW_ON_ERROR)."\n");}}while(count($rows)===1000);return hash_final($hash);
    }
    public static function ensure($db,string$p):void
    {
        IssuerTransaction::assertIndependent($db);require_once __DIR__.'/CompanyProjectsSchema.php';require_once __DIR__.'/CompanyStandaloneTasksSchema.php';CompanyProjectsSchema::assertValid($db,$p);CompanyStandaloneTasksSchema::assertValid($db,$p);
        if(self::sqlite($db)){self::assertValid($db,$p);return;} // SQLite fixtures exercise final shape; MySQL ALTER proof remains mandatory.
        foreach(self::TABLES as$table){$cols=self::columns($db,$p,$table);self::assertAdditions($cols,false);$complete=isset($cols['native_fields_json'],$cols['native_fields_revision'])&&($cols['assignee_id']['IS_NULLABLE']??null)==='YES'&&($cols['due_date']['IS_NULLABLE']??null)==='YES';if($complete)continue;$before=self::fingerprint($db,$p,$table);
            foreach(['assignee_id'=>'int','due_date'=>'date']as$name=>$type)if(($cols[$name]['IS_NULLABLE']??null)==='NO')IssuerStorage::query($db,'ALTER TABLE `'.$p.$table.'` MODIFY `'.$name.'` '.$type.' NULL');
            foreach(self::ADDITIONS as$name=>$type)if(!isset($cols[$name]))IssuerStorage::query($db,'ALTER TABLE `'.$p.$table.'` ADD `'.$name.'` '.$type.($type==='longtext'?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').' NULL');
            if(!hash_equals($before,self::fingerprint($db,$p,$table)))throw new RuntimeException('native_task_form_history_changed');
        }self::assertValid($db,$p);CompanyProjectsSchema::assertValid($db,$p);CompanyStandaloneTasksSchema::assertValid($db,$p);
    }
    private static function fail():never{throw new RuntimeException('native_task_form_schema_invalid');}
}
