<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/SchemaValidator.php';

/** Additive export envelopes. Existing preparation, origin and source-batch identities remain immutable. */
final class Cnab400GroupSchema
{
    public static function requirements():array
    {
        return [
            'bb_cnab_groups'=>['columns'=>[
                'id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','compatibility_hash'=>'char(64)',
                'state'=>'varchar(24)','content'=>'mediumblob','content_hash'=>'char(64)',
                'created_by'=>'bigint unsigned','created_at'=>'datetime','frozen_at'=>'datetime?',
            ],'unique'=>[]],
            'bb_cnab_group_members'=>['columns'=>[
                'id'=>'bigint unsigned','preparation_id'=>'bigint unsigned','group_id'=>'bigint unsigned','source_hash'=>'char(64)',
            ],'unique'=>[['preparation_id']]],
        ];
    }
    public static function assertValid($db,string $p):void
    {
        SchemaValidator::assertCompatible($db,$p,self::requirements());
        foreach(['preparation_id'=>['bb_cnab_preparations','id'],'group_id'=>['bb_cnab_groups','id']] as $field=>[$table,$column]){
            $r=$db->query('SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.'bb_cnab_group_members',$field]);
            $rows=$r?$r->result_array():[];
            if(count($rows)!==1||$rows[0]['REFERENCED_TABLE_NAME']!==$p.$table||$rows[0]['REFERENCED_COLUMN_NAME']!==$column)throw new \RuntimeException('bb_schema_incompatible');
        }
    }
    public static function upgrade($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $pdo=$db->conn_id??$db->pdo??null;
        if((property_exists($db,'_trans_depth')&&(new \ReflectionProperty($db,'_trans_depth'))->getValue($db)!==0)||($pdo instanceof \PDO&&$pdo->inTransaction()))throw new \RuntimeException('bb400_group_outer_transaction_not_allowed');
        $r=$db->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');$mode=$r?$r->row_array():[];
        if((string)($mode['autocommit']??'')!=='1'||!in_array((string)($mode['completion_type']??''),['0','NO_CHAIN'],true))throw new \RuntimeException('bb400_group_transaction_configuration_invalid');
        $r=$db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)',[$p.'bb_cnab_groups',$p.'bb_cnab_group_members']);
        if(!$r)throw new \RuntimeException('bb400_group_schema_failed');
        if(count($r->result_array())===2){self::assertValid($db,$p);return;}
        foreach([
            "CREATE TABLE IF NOT EXISTS `{$p}bb_cnab_groups` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,issuer_id BIGINT UNSIGNED NOT NULL,compatibility_hash CHAR(64) NOT NULL,state VARCHAR(24) NOT NULL,content MEDIUMBLOB NOT NULL,content_hash CHAR(64) NOT NULL,created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,frozen_at DATETIME DEFAULT NULL,PRIMARY KEY(id),KEY compatible_open(issuer_id,compatibility_hash,state,id))",
            "CREATE TABLE IF NOT EXISTS `{$p}bb_cnab_group_members` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,preparation_id BIGINT UNSIGNED NOT NULL,group_id BIGINT UNSIGNED NOT NULL,source_hash CHAR(64) NOT NULL,PRIMARY KEY(id),UNIQUE KEY one_export(preparation_id),KEY group_members(group_id,preparation_id),FOREIGN KEY(preparation_id) REFERENCES {$p}bb_cnab_preparations(id),FOREIGN KEY(group_id) REFERENCES {$p}bb_cnab_groups(id))",
        ]as$sql)if(!$db->query($sql.' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'))throw new \RuntimeException('bb400_group_schema_failed');
        self::assertValid($db,$p);
    }
}
