<?php
declare(strict_types=1);
namespace BancoBrasil;
final class CheckoutSchema
{
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $table=$p.'bb_checkout_publications';
        $meta=$db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
        if($create&&$meta&&$meta->row_array()===[]&&!$db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            origin_id BIGINT UNSIGNED NOT NULL,issuer_id BIGINT UNSIGNED NOT NULL,
            instrument_revision CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            published_by INT UNSIGNED NOT NULL,enabled TINYINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,
            PRIMARY KEY(origin_id),KEY issuer_enabled(issuer_id,enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"))throw new \RuntimeException('bb_checkout_schema_failed');
        if($create&&$meta&&$meta->row_array()===[])$meta=$db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
        if(!$meta||strtoupper($meta->row_array()['ENGINE']??'')!=='INNODB')throw new \RuntimeException('bb_checkout_schema_invalid');
        $result=$db->query('SHOW FULL COLUMNS FROM `'.$table.'`');if(!$result)throw new \RuntimeException('bb_checkout_schema_invalid');
        $cols=[];foreach($result->result_array() as $row)$cols[$row['Field']]=$row;
        foreach(['origin_id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','instrument_revision'=>'char(64)','published_by'=>'int unsigned','enabled'=>'tinyint unsigned','created_at'=>'datetime','updated_at'=>'datetime'] as $field=>$type){
            $row=$cols[$field]??[];if(preg_replace('/^(bigint|int|tinyint)\\([0-9]+\\)/','$1',strtolower($row['Type']??''))!==$type||($row['Null']??'')!=='NO'||($row['Extra']??'')!=='')throw new \RuntimeException('bb_checkout_schema_invalid');
        }
        if(($cols['instrument_revision']['Collation']??'')!=='ascii_bin')throw new \RuntimeException('bb_checkout_schema_invalid');
        $keys=[];$result=$db->query('SHOW INDEX FROM `'.$table.'`');if(!$result)throw new \RuntimeException('bb_checkout_schema_invalid');
        foreach($result->result_array() as $row){if($row['Sub_part']!==null)throw new \RuntimeException('bb_checkout_schema_invalid');$keys[$row['Key_name']][(int)$row['Seq_in_index']]=[$row['Column_name'],(int)$row['Non_unique']];}
        foreach($keys as &$key)ksort($key);unset($key);
        if(array_values($keys['PRIMARY']??[])!==[['origin_id',0]]||array_values($keys['issuer_enabled']??[])!==[['issuer_id',1],['enabled',1]])throw new \RuntimeException('bb_checkout_schema_invalid');
    }
}
