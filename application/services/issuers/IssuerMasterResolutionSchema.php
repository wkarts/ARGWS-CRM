<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerStorage.php';

final class IssuerMasterResolutionSchema
{
    public static function ensure($db, string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        IssuerStorage::query($db, "CREATE TABLE IF NOT EXISTS `{$prefix}issuers_master_authorizations` (
            `token_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            `staff_id` int NOT NULL, `expires_at` bigint NOT NULL, `consumed_at` datetime DEFAULT NULL,
            `reason` varchar(500) NOT NULL, `attempts` int NOT NULL DEFAULT 0
        ) ENGINE=InnoDB");
        // Confirm exact keys/types/engine; IF NOT EXISTS must not bless incompatible storage.
        $columns = IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$prefix.'issuers_master_authorizations'])->result_array();
        $actual = []; foreach ($columns as $column) { $actual[$column['COLUMN_NAME']] = $column; }
        foreach (['token_hash'=>['varchar(64)','NO'],'staff_id'=>['int','NO'],'expires_at'=>['bigint','NO'],'consumed_at'=>['datetime','YES'],'reason'=>['varchar(500)','NO'],'attempts'=>['int','NO']] as $name=>[$type,$nullable]) {
            $found = $actual[$name] ?? [];
            if (preg_replace('/^(int|bigint)\([0-9]+\)/','$1',strtolower($found['COLUMN_TYPE']??'')) !== $type || ($found['IS_NULLABLE']??null) !== $nullable) { throw new RuntimeException('issuer_master_schema_invalid'); }
        }
        $table = $prefix.'issuers_master_authorizations';
        $pk = IssuerStorage::query($db,"SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",[$table])->result_array();
        $engine = IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();
        if (array_column($pk,'COLUMN_NAME') !== ['token_hash'] || ($actual['token_hash']['COLLATION_NAME']??null) !== 'ascii_bin' || strtoupper($engine[0]['ENGINE']??'') !== 'INNODB') { throw new RuntimeException('issuer_master_schema_invalid'); }
    }
}
