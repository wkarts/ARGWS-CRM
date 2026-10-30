<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/SchemaValidator.php';

/** Additive retention only. Never reconstruct historical bytes from parsed events. */
final class ReturnArchiveSchema
{
    public static function requirements():array
    {
        return ['bb_return_originals'=>['columns'=>[
            'id'=>'bigint unsigned','return_file_id'=>'bigint unsigned',
            'issuer_id'=>'bigint unsigned','connection_id'=>'bigint unsigned',
            'scope_key'=>'char(64)','content_hash'=>'char(64)','byte_count'=>'int unsigned',
            'content'=>'longblob','created_by'=>'bigint unsigned','created_at'=>'datetime',
        ],'unique'=>[['return_file_id']]]];
    }
    public static function upgrade($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $pdo=$db->conn_id??$db->pdo??null;
        if((property_exists($db,'_trans_depth')&&(new \ReflectionProperty($db,'_trans_depth'))->getValue($db)!==0)
            ||($pdo instanceof \PDO&&$pdo->inTransaction()))throw new \RuntimeException('bb_return_archive_outer_transaction_not_allowed');
        $mode=$db->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');
        $state=$mode?$mode->row_array():[];
        if((string)($state['autocommit']??'')!=='1'||!in_array((string)($state['completion_type']??''),['0','NO_CHAIN'],true))throw new \RuntimeException('bb_return_archive_transaction_configuration_invalid');
        // Replay validates the existing private ledger without taking a DDL
        // metadata lock or risking an implicit commit after an earlier retry.
        $existing=$db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'bb_return_originals']);
        if(!$existing)throw new \RuntimeException('bb_return_archive_migration_failed');
        if($existing->result_array()!==[]){SchemaValidator::assertCompatible($db,$p,self::requirements());return;}
        if(!$db->query("CREATE TABLE IF NOT EXISTS `{$p}bb_return_originals` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            return_file_id BIGINT UNSIGNED NOT NULL,
            issuer_id BIGINT UNSIGNED NOT NULL,connection_id BIGINT UNSIGNED NOT NULL,
            scope_key CHAR(64) NOT NULL,content_hash CHAR(64) NOT NULL,
            byte_count INT UNSIGNED NOT NULL,content LONGBLOB NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,
            PRIMARY KEY(id),UNIQUE KEY bb_return_original_file(return_file_id),
            KEY bb_return_original_issuer(issuer_id,return_file_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"))throw new \RuntimeException('bb_return_archive_migration_failed');
        SchemaValidator::assertCompatible($db,$p,self::requirements());
    }
}
