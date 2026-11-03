<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/SchemaValidator.php';

/** Separate rejected-upload ledger: never shares accepted-file deduplication or financial state. */
final class ReturnRejectionSchema
{
    public static function requirements():array
    {
        return ['bb_return_rejections'=>['columns'=>[
            'id'=>'bigint unsigned','layout_binding_id'=>'bigint unsigned',
            'issuer_id'=>'bigint unsigned','connection_id'=>'bigint unsigned',
            'scope_key'=>'char(64)','content_hash'=>'char(64)','layout_id'=>'varchar(96)',
            'reason_code'=>'varchar(64)','record_index'=>'int unsigned?','byte_count'=>'int unsigned',
            'content'=>'longblob','created_by'=>'bigint unsigned','created_at'=>'datetime',
        ],'unique'=>[['scope_key','layout_binding_id','content_hash','reason_code']]]];
    }
    public static function upgrade($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $pdo=$db->conn_id??$db->pdo??null;
        if((property_exists($db,'_trans_depth')&&(new \ReflectionProperty($db,'_trans_depth'))->getValue($db)!==0)
            ||($pdo instanceof \PDO&&$pdo->inTransaction()))throw new \RuntimeException('bb_return_rejection_schema_outer_transaction_not_allowed');
        $mode=$db->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');
        $state=$mode?$mode->row_array():[];
        if((string)($state['autocommit']??'')!=='1'||!in_array((string)($state['completion_type']??''),['0','NO_CHAIN'],true))throw new \RuntimeException('bb_return_rejection_schema_transaction_configuration_invalid');
        // Replay validates the existing private ledger without taking a DDL
        // metadata lock or risking an implicit commit after an earlier retry.
        $existing=$db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'bb_return_rejections']);
        if(!$existing)throw new \RuntimeException('bb_return_rejection_schema_migration_failed');
        if($existing->result_array()!==[]){SchemaValidator::assertCompatible($db,$p,self::requirements());return;}
        if(!$db->query("CREATE TABLE IF NOT EXISTS `{$p}bb_return_rejections` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            layout_binding_id BIGINT UNSIGNED NOT NULL,
            issuer_id BIGINT UNSIGNED NOT NULL,connection_id BIGINT UNSIGNED NOT NULL,
            scope_key CHAR(64) NOT NULL,content_hash CHAR(64) NOT NULL,
            layout_id VARCHAR(96) NOT NULL,reason_code VARCHAR(64) NOT NULL,record_index INT UNSIGNED DEFAULT NULL,
            byte_count INT UNSIGNED NOT NULL,content LONGBLOB NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,
            PRIMARY KEY(id),UNIQUE KEY bb_return_rejection_identity(scope_key,layout_binding_id,content_hash,reason_code),
            KEY bb_return_rejection_issuer(issuer_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"))throw new \RuntimeException('bb_return_rejection_schema_migration_failed');
        SchemaValidator::assertCompatible($db,$p,self::requirements());
    }
}
