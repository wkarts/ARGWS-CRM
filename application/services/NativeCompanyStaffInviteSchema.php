<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Creation-only staff invitations. Staff IDs remain historical identities, never renumbered. */
final class NativeCompanyStaffInviteSchema
{
    private const FIELDS=['staff_id'=>'int','issuer_id'=>'int unsigned','actor_id'=>'int','request_key'=>'char(64)','identity_hash'=>'char(64)','token_hash'=>'char(64)','marker'=>'char(32)','queue_id'=>'int','expires_at'=>'datetime','used_at'=>'datetime','created_at'=>'datetime'];
    private static function sqlite($db):bool{$pdo=$db->pdo??$db->conn_id??null;return$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        return['company_native_staff_invites'=>"CREATE TABLE IF NOT EXISTS `{$p}company_native_staff_invites` (
            staff_id INT NOT NULL,issuer_id INT UNSIGNED NOT NULL,actor_id INT NOT NULL,
            request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            identity_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            marker CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            queue_id INT NULL,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL,
            PRIMARY KEY(staff_id),UNIQUE KEY invitation_token(token_hash),UNIQUE KEY invitation_queue(queue_id),
            UNIQUE KEY creation_request(issuer_id,actor_id,request_key),
            FOREIGN KEY(issuer_id) REFERENCES `{$p}issuers_registry`(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"];
    }
    public static function ensure($db,string $p):void
    {
        IssuerTransaction::assertIndependent($db);self::definitions($p);
        if(self::sqlite($db))IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$p.'company_native_staff_invites(staff_id INTEGER NOT NULL PRIMARY KEY,issuer_id INTEGER NOT NULL,actor_id INTEGER NOT NULL,request_key TEXT NOT NULL,identity_hash TEXT NOT NULL,token_hash TEXT NOT NULL,marker TEXT NOT NULL,queue_id INTEGER NULL,expires_at TEXT NOT NULL,used_at TEXT NULL,created_at TEXT NOT NULL,UNIQUE(token_hash),UNIQUE(queue_id),UNIQUE(issuer_id,actor_id,request_key),FOREIGN KEY(issuer_id) REFERENCES '.$p.'issuers_registry(id))');
        else foreach(self::definitions($p)as$sql)IssuerStorage::query($db,$sql);
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        self::definitions($p);$table=$p.'company_native_staff_invites';$fail=static fn()=>throw new RuntimeException('native_staff_invite_schema_invalid');
        if(self::sqlite($db)){
            $rows=IssuerStorage::query($db,'PRAGMA table_info('.$table.')')->result_array();if(array_column($rows,'name')!==array_keys(self::FIELDS))$fail();
            foreach($rows as$r){$type=str_starts_with(self::FIELDS[$r['name']],'int')?'INTEGER':'TEXT';if($r['type']!==$type||(int)$r['notnull']!==(in_array($r['name'],['queue_id','used_at'],true)?0:1)||$r['dflt_value']!==null||(int)$r['pk']!==($r['name']==='staff_id'?1:0))$fail();}
            $indexes=[];foreach(IssuerStorage::query($db,'PRAGMA index_list('.$table.')')->result_array()as$r){if((int)$r['unique']!==1||(int)$r['partial']!==0)$fail();$indexes[]=implode(',',array_column(IssuerStorage::query($db,'PRAGMA index_info('.$r['name'].')')->result_array(),'name'));}sort($indexes);if($indexes!==['issuer_id,actor_id,request_key','queue_id','token_hash'])$fail();
            $fk=IssuerStorage::query($db,'PRAGMA foreign_key_list('.$table.')')->result_array();if(count($fk)!==1||$fk[0]['from']!=='issuer_id'||$fk[0]['to']!=='id'||$fk[0]['table']!==$p.'issuers_registry'||$fk[0]['on_delete']!=='NO ACTION'||$fk[0]['on_update']!=='NO ACTION')$fail();return;
        }
        $rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$table])->result_array();if(array_column($rows,'COLUMN_NAME')!==array_keys(self::FIELDS))$fail();foreach($rows as$r){$name=$r['COLUMN_NAME'];$type=preg_replace('/^int\([0-9]+\)/','int',strtolower($r['COLUMN_TYPE']));if($type!==self::FIELDS[$name]||$r['IS_NULLABLE']!==(in_array($name,['queue_id','used_at'],true)?'YES':'NO')||!IssuerStorage::sqlNullDefault($r)||$r['EXTRA']!==''||$r['COLLATION_NAME']!==(str_starts_with($type,'char(')?'ascii_bin':null))$fail();}
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')$fail();
        $indexes=[];foreach(IssuerStorage::query($db,'SHOW INDEX FROM '.$table)->result_array()as$r){if((int)$r['Non_unique']!==0||$r['Sub_part']!==null)$fail();$indexes[$r['Key_name']][(int)$r['Seq_in_index']]=$r['Column_name'];}foreach($indexes as&$cols){ksort($cols);$cols=array_values($cols);}unset($cols);$expected=['PRIMARY'=>['staff_id'],'invitation_token'=>['token_hash'],'invitation_queue'=>['queue_id'],'creation_request'=>['issuer_id','actor_id','request_key']];ksort($indexes);ksort($expected);if($indexes!==$expected)$fail();
        $fk=IssuerStorage::query($db,'SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$table])->result_array();if($fk!==[['COLUMN_NAME'=>'issuer_id','REFERENCED_TABLE_NAME'=>$p.'issuers_registry','REFERENCED_COLUMN_NAME'=>'id']])$fail();
    }
}
