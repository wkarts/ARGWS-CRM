<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/SchemaValidator.php';

final class LayoutSchema
{
    public static function additions():array
    {
        $columns=[];
        foreach(['bb_origins','bb_batches','bb_return_files','bb_return_events','bb_return_file_events'] as $table){
            $columns[$table]=['layout_binding_id'=>'BIGINT UNSIGNED NULL DEFAULT NULL'];
        }
        $columns['bb_return_files']['provenance_json']='TEXT NULL DEFAULT NULL';
        return $columns;
    }
    public static function create(string $prefix):string
    {
        if(!preg_match('/^[A-Za-z0-9_]*$/D',$prefix))throw new \InvalidArgumentException('bb_prefix_invalid');
        return "CREATE TABLE IF NOT EXISTS `{$prefix}bb_layout_bindings` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, issuer_id BIGINT UNSIGNED NOT NULL,
            connection_id BIGINT UNSIGNED NOT NULL, scope_key CHAR(64) NOT NULL,
            layout_id VARCHAR(96) NOT NULL, profile_hash CHAR(64) NOT NULL,
            connection_hash CHAR(64) NOT NULL, remittance_source_hash CHAR(64) NOT NULL,
            return_source_hash CHAR(64) NOT NULL, date_window VARCHAR(16) NOT NULL,
            declared_currency VARCHAR(3) NOT NULL, verification_state VARCHAR(32) NOT NULL DEFAULT 'declared_unverified',
            created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
            PRIMARY KEY(id), UNIQUE KEY bb_layout_revision(connection_id,layout_id,profile_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin";
    }
    public static function requirements():array
    {
        $all=SchemaValidator::requirements();
        foreach(self::additions() as $table=>$columns){
            foreach($columns as $name=>$type)$all[$table]['columns'][$name]=$name==='provenance_json'?'text?':'bigint unsigned?';
        }
        $all['bb_layout_bindings']=['columns'=>[
            'id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','connection_id'=>'bigint unsigned','scope_key'=>'char(64)',
            'layout_id'=>'varchar(96)','profile_hash'=>'char(64)','connection_hash'=>'char(64)',
            'remittance_source_hash'=>'char(64)','return_source_hash'=>'char(64)','date_window'=>'varchar(16)',
            'declared_currency'=>'varchar(3)','verification_state'=>'varchar(32)','created_by'=>'bigint unsigned','created_at'=>'datetime'],
            'unique'=>[['connection_id','layout_id','profile_hash']]];
        return $all;
    }
    public static function upgrade($db,string $prefix):void
    {
        // Preflight 101 before any additive DDL. Existing rows are never attributed retroactively.
        SchemaValidator::assertCompatible($db,$prefix);
        if(!$db->query(self::create($prefix)))throw new \RuntimeException('bb_layout_migration_failed');
        foreach(self::additions() as $table=>$columns){
            foreach($columns as $name=>$definition){
                $result=$db->query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$prefix.$table,$name]);
                if(!$result)throw new \RuntimeException('bb_layout_migration_failed');
                if(!$result->row_array()&&!$db->query("ALTER TABLE `{$prefix}{$table}` ADD COLUMN `{$name}` {$definition}"))throw new \RuntimeException('bb_layout_migration_failed');
            }
        }
        SchemaValidator::assertCompatible($db,$prefix,self::requirements());
    }
}
