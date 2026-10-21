<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
final class CompanyCurrencyUnitSchema
{
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.'company_currency_units` (`currency_id` int NOT NULL PRIMARY KEY,`iso_code` varchar(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`basis` varchar(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,`recorded_at` datetime NOT NULL,`source_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL) ENGINE=InnoDB');self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;$table=$p.'company_currency_units';$columns=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
        foreach(['currency_id'=>'int','iso_code'=>'varchar(3)','basis'=>'varchar(48)','recorded_at'=>'datetime','source_hash'=>'varchar(64)'] as $name=>$type){$row=$columns[$name]??[];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($row['COLUMN_TYPE']??''))!==$type||($row['IS_NULLABLE']??null)!=='NO'||!IssuerStorage::sqlNullDefault($row)||(!in_array($name,['currency_id','recorded_at'],true)&&($row['COLLATION_NAME']??null)!=='ascii_bin'))throw new RuntimeException('issuer_currency_schema_invalid');}$engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('issuer_currency_schema_invalid');$index=IssuerStorage::query($db,'SELECT COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',[$table,'PRIMARY'])->result_array();if(count($index)!==1||$index[0]['COLUMN_NAME']!=='currency_id'||(int)$index[0]['NON_UNIQUE']!==0||$index[0]['SUB_PART']!==null)throw new RuntimeException('issuer_currency_schema_invalid');
    }
}
