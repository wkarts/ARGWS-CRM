<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Historical command receipts retain an ID, never an FK that obstructs native staff deletion. */
final class CompanyStaffSchema
{
    public static function statements(string $prefix): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        return ["CREATE TABLE IF NOT EXISTS `{$prefix}company_staff_commands` (
            `issuer_id` int unsigned NOT NULL, `actor_id` int NOT NULL,
            `request_key` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            `operation` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            `input_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            `staff_id` int NOT NULL,
            `result_hash` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`issuer_id`,`actor_id`,`request_key`),
            FOREIGN KEY (`issuer_id`) REFERENCES `{$prefix}issuers_registry` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"];
    }

    public static function ensure($db, string $prefix): void
    {
        IssuerTransaction::assertIndependent($db);
        foreach (self::statements($prefix) as $sql) { IssuerStorage::query($db, $sql); }
        if (property_exists($db, 'data_cache')) { unset($db->data_cache['table_names']); }
        self::assertValid($db, $prefix);
    }

    public static function assertValid($db, string $prefix): void
    {
        self::statements($prefix);
        $table = $prefix.'company_staff_commands';
        $columns = IssuerStorage::query($db, 'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$table])->result_array();
        $expected = ['issuer_id'=>'int unsigned','actor_id'=>'int','request_key'=>'varchar(64)','operation'=>'varchar(16)','input_hash'=>'varchar(64)','staff_id'=>'int','result_hash'=>'varchar(64)','created_at'=>'datetime'];
        if (array_column($columns, 'COLUMN_NAME') !== array_keys($expected)) { throw new RuntimeException('issuer_staff_schema_invalid'); }
        foreach ($columns as $column) {
            $name = $column['COLUMN_NAME'];
            $type = preg_replace('/^int\([0-9]+\)/', 'int', strtolower($column['COLUMN_TYPE']));
            if (($expected[$name] ?? null) !== $type || $column['IS_NULLABLE'] !== 'NO'
                || !IssuerStorage::sqlNullDefault($column) || $column['EXTRA'] !== ''
                || $column['COLLATION_NAME'] !== (str_starts_with($type, 'varchar') ? 'ascii_bin' : null)) {
                throw new RuntimeException('issuer_staff_schema_invalid');
            }
        }
        $index = IssuerStorage::query($db, 'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX', [$table])->result_array();
        $engine = IssuerStorage::query($db, 'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table])->result_array();
        $keys = IssuerStorage::query($db, 'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION', [$table])->result_array();
        $rules = IssuerStorage::query($db, 'SELECT CONSTRAINT_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table])->result_array();
        $schema = IssuerStorage::query($db, 'SELECT DATABASE() current_schema')->result_array()[0]['current_schema'];
        if (array_column($index, 'COLUMN_NAME') !== ['issuer_id','actor_id','request_key']
            || array_filter($index, static fn($row) => $row['INDEX_NAME'] !== 'PRIMARY' || (int)$row['NON_UNIQUE'] !== 0 || $row['SUB_PART'] !== null)
            || strtoupper($engine[0]['ENGINE'] ?? '') !== 'INNODB'
            || ($engine[0]['TABLE_COLLATION'] ?? null) !== 'utf8mb4_bin'
            || count($rules) !== 1 || count($keys) !== 1
            || !in_array($rules[0]['DELETE_RULE'] ?? '', ['RESTRICT','NO ACTION'], true)
            || !in_array($rules[0]['UPDATE_RULE'] ?? '', ['RESTRICT','NO ACTION'], true)
            || $keys[0] !== ['CONSTRAINT_NAME'=>$rules[0]['CONSTRAINT_NAME'],'COLUMN_NAME'=>'issuer_id','REFERENCED_TABLE_SCHEMA'=>$schema,'REFERENCED_TABLE_NAME'=>$prefix.'issuers_registry','REFERENCED_COLUMN_NAME'=>'id']) {
            throw new RuntimeException('issuer_staff_schema_invalid');
        }
    }
}
