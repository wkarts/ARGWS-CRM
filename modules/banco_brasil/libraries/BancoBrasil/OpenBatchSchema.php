<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/SchemaValidator.php';

/** Commands may share an open CNAB240 batch; published batches are never backfilled. */
final class OpenBatchSchema
{
    public static function requirements():array
    {
        return ['bb_batch_requests'=>['columns'=>[
            'id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','batch_id'=>'bigint unsigned',
            'scope_key'=>'char(64)','request_key'=>'char(64)','payload_hash'=>'char(64)',
            'compatibility_hash'=>'char(64)','created_by'=>'bigint unsigned','created_at'=>'datetime',
        ],'unique'=>[['scope_key','request_key']]]];
    }
    public static function assertValid($db,string $p):void
    {
        SchemaValidator::assertCompatible($db,$p,self::requirements());
        $result=$db->query('SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.'bb_batch_requests','batch_id']);
        $rows=$result?$result->result_array():[];
        if(count($rows)!==1||$rows[0]['REFERENCED_TABLE_NAME']!==$p.'bb_batches'||$rows[0]['REFERENCED_COLUMN_NAME']!=='id')throw new \RuntimeException('bb_schema_incompatible');
    }
    public static function upgrade($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $pdo=$db->conn_id??$db->pdo??null;
        if((property_exists($db,'_trans_depth')&&(new \ReflectionProperty($db,'_trans_depth'))->getValue($db)!==0)
            ||($pdo instanceof \PDO&&$pdo->inTransaction()))throw new \RuntimeException('bb_open_batch_schema_outer_transaction_not_allowed');
        $mode=$db->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');
        $state=$mode?$mode->row_array():[];
        if((string)($state['autocommit']??'')!=='1'||!in_array((string)($state['completion_type']??''),['0','NO_CHAIN'],true))throw new \RuntimeException('bb_open_batch_schema_transaction_configuration_invalid');
        // Replay validates the existing private ledger without taking a DDL
        // metadata lock or risking an implicit commit after an earlier retry.
        $existing=$db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'bb_batch_requests']);
        if(!$existing)throw new \RuntimeException('bb_open_batch_schema_migration_failed');
        if($existing->result_array()!==[]){self::assertValid($db,$p);return;}
        if(!$db->query("CREATE TABLE IF NOT EXISTS `{$p}bb_batch_requests` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            issuer_id BIGINT UNSIGNED NOT NULL,batch_id BIGINT UNSIGNED NOT NULL,
            scope_key CHAR(64) NOT NULL,request_key CHAR(64) NOT NULL,payload_hash CHAR(64) NOT NULL,
            compatibility_hash CHAR(64) NOT NULL,created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,
            PRIMARY KEY(id),UNIQUE KEY bb_batch_command(scope_key,request_key),
            KEY bb_batch_compatible(compatibility_hash,batch_id),
            FOREIGN KEY(batch_id) REFERENCES {$p}bb_batches(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"))throw new \RuntimeException('bb_open_batch_schema_migration_failed');
        self::assertValid($db,$p);
    }
}
