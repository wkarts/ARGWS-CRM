<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyProjectsSchema.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Core 474's single-column evolution. Historical table definitions and recorded dates are not rewritten. */
final class NativeCompanyProjectDeadlineSchema
{
    private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    private static function column($db,string$p):array
    {
        CompanyProjectsSchema::definitions($p);
        if(self::sqlite($db)){foreach(IssuerStorage::query($db,'PRAGMA table_info(`'.$p.'company_projects`)')->result_array()as$r)if($r['name']==='due_date')return['Type'=>strtolower($r['type']),'Null'=>(int)$r['notnull']===1?'NO':'YES','Default'=>$r['dflt_value'],'Extra'=>'','Collation'=>null];}
        else foreach(IssuerStorage::query($db,'SHOW FULL COLUMNS FROM `'.$p.'company_projects`')->result_array()as$r)if($r['Field']==='due_date')return$r;
        throw new RuntimeException('native_project_deadline_schema_invalid');
    }
    private static function valid(array$c,bool$nullable):void
    {
        if(strtolower($c['Type']??'')!=='date'||!in_array($c['Null']??null,$nullable?['YES']:['YES','NO'],true)||!IssuerStorage::sqlNullDefault(['COLUMN_DEFAULT'=>$c['Default']??null])||($c['Extra']??null)!==''||($c['Collation']??null)!==null)throw new RuntimeException('native_project_deadline_schema_invalid');
    }
    private static function fingerprint($db,string$p):string
    {
        $hash=hash_init('sha256');$after=0;
        do{$rows=IssuerStorage::query($db,'SELECT * FROM '.$p.'company_projects WHERE id>? ORDER BY id LIMIT 1000',[$after])->result_array();foreach($rows as$r){if(!isset($r['id'])||!ctype_digit((string)$r['id'])||(int)$r['id']<=$after)throw new RuntimeException('native_project_deadline_snapshot_invalid');$after=(int)$r['id'];$r=array_map(static fn($v)=>$v===null?null:(string)$v,$r);hash_update($hash,json_encode($r,JSON_THROW_ON_ERROR)."\n");}}while(count($rows)===1000);
        return hash_final($hash);
    }
    public static function ensure($db,string$p):void
    {
        IssuerTransaction::assertIndependent($db);CompanyProjectsSchema::assertValid($db,$p);$column=self::column($db,$p);self::valid($column,false);
        if($column['Null']==='YES'){self::assertValid($db,$p);return;}
        if(self::sqlite($db))throw new RuntimeException('native_project_deadline_mysql_migration_required');
        $before=self::fingerprint($db,$p);IssuerStorage::query($db,'ALTER TABLE `'.$p.'company_projects` MODIFY `due_date` DATE NULL');
        self::assertValid($db,$p);if(!hash_equals($before,self::fingerprint($db,$p)))throw new RuntimeException('native_project_deadline_data_changed');
    }
    public static function assertValid($db,string$p):void{CompanyProjectsSchema::assertValid($db,$p);self::valid(self::column($db,$p),true);}
}
