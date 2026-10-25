<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
final class NativeCompanyStaffRateSchema
{
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        return['company_staff_rates'=>"CREATE TABLE IF NOT EXISTS `{$p}company_staff_rates`(issuer_id INT UNSIGNED NOT NULL,staff_id INT NOT NULL,hourly_rate DECIMAL(15,2) NULL,revision INT UNSIGNED NOT NULL,actor_id INT NOT NULL,updated_at DATETIME NOT NULL,PRIMARY KEY(issuer_id,staff_id),FOREIGN KEY(issuer_id) REFERENCES `{$p}issuers_registry`(id)) ENGINE=InnoDB"];
    }
    public static function ensure($db,string $p):void
    {
        IssuerTransaction::assertIndependent($db);self::definitions($p);$pdo=$db->pdo??$db->conn_id??null;
        if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$p.'company_staff_rates(issuer_id INTEGER NOT NULL,staff_id INTEGER NOT NULL,hourly_rate DECIMAL(15,2) NULL,revision INTEGER NOT NULL,actor_id INTEGER NOT NULL,updated_at TEXT NOT NULL,PRIMARY KEY(issuer_id,staff_id),FOREIGN KEY(issuer_id) REFERENCES '.$p.'issuers_registry(id))');else foreach(self::definitions($p)as$sql)IssuerStorage::query($db,$sql);self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        self::definitions($p);$pdo=$db->pdo??$db->conn_id??null;$t=$p.'company_staff_rates';$fail=static fn()=>throw new RuntimeException('native_staff_rate_schema_invalid');
        $fields=['issuer_id','staff_id','hourly_rate','revision','actor_id','updated_at'];
        if($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$rows=IssuerStorage::query($db,'PRAGMA table_info('.$t.')')->result_array();if(array_column($rows,'name')!==$fields)$fail();$types=['INTEGER','INTEGER','DECIMAL(15,2)','INTEGER','INTEGER','TEXT'];foreach($rows as$n=>$r)if($r['type']!==$types[$n]||(int)$r['notnull']!==($n===2?0:1)||$r['dflt_value']!==null||(int)$r['pk']!==($n<2?$n+1:0))$fail();$fk=IssuerStorage::query($db,'PRAGMA foreign_key_list('.$t.')')->result_array();if(count($fk)!==1||$fk[0]['table']!==$p.'issuers_registry'||$fk[0]['from']!=='issuer_id'||$fk[0]['to']!=='id'||$fk[0]['on_delete']!=='NO ACTION')$fail();return;}
        $rows=IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$t])->result_array();if(array_column($rows,'COLUMN_NAME')!==$fields)$fail();$types=['int unsigned','int','decimal(15,2)','int unsigned','int','datetime'];foreach($rows as$n=>$r)if(preg_replace('/^int\([0-9]+\)/','int',strtolower($r['COLUMN_TYPE']))!==$types[$n]||$r['IS_NULLABLE']!==($n===2?'YES':'NO')||!IssuerStorage::sqlNullDefault($r)||$r['EXTRA']!=='')$fail();$engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')$fail();$index=IssuerStorage::query($db,'SHOW INDEX FROM '.$t)->result_array();if(count($index)!==2||array_column($index,'Column_name')!==['issuer_id','staff_id'])$fail();foreach($index as$r)if($r['Key_name']!=='PRIMARY'||(int)$r['Non_unique']!==0||$r['Sub_part']!==null)$fail();$fk=IssuerStorage::query($db,'SELECT COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$t])->result_array();$schema=IssuerStorage::query($db,'SELECT DATABASE() AS name')->row_array();if(count($fk)!==1||$fk[0]['COLUMN_NAME']!=='issuer_id'||$fk[0]['REFERENCED_TABLE_SCHEMA']!==$schema['name']||$fk[0]['REFERENCED_TABLE_NAME']!==$p.'issuers_registry'||$fk[0]['REFERENCED_COLUMN_NAME']!=='id')$fail();$rules=IssuerStorage::query($db,'SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->result_array();if(count($rules)!==1||!in_array($rules[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($rules[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true))$fail();
    }
}
