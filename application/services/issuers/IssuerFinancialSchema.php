<?php

declare(strict_types=1);
require_once __DIR__ . '/IssuerStorage.php';
require_once __DIR__ . '/IssuerSchema.php';

/** Additive ownership/document metadata; never reassigns existing invoices or opens rollout. */
final class IssuerFinancialSchema
{
    private static function rows($db, string $sql, array $bindings = []): array
    {
        return IssuerStorage::query($db, $sql, $bindings)->result_array();
    }

    private static function indexes($db, string $table): array
    {
        $indexes = [];
        foreach (self::rows($db, 'SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX', [$table]) as $row) {
            $indexes[$row['INDEX_NAME']]['unique'] = (int) $row['NON_UNIQUE'] === 0;
            $indexes[$row['INDEX_NAME']]['columns'][(int) $row['SEQ_IN_INDEX']] = $row['SUB_PART'] === null ? $row['COLUMN_NAME'] : null;
        }
        foreach ($indexes as &$index) { ksort($index['columns']); $index['columns'] = array_values($index['columns']); }
        return $indexes;
    }

    public static function ensure($db, string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $invoices = $prefix . 'invoices';
        if (!IssuerStorage::fieldExists($db, 'issuer_id', $invoices)) {
            IssuerStorage::query($db, "ALTER TABLE `{$invoices}` ADD `issuer_id` INT UNSIGNED NULL DEFAULT NULL");
        }
        $indexes = self::indexes($db, $invoices);
        if (!isset($indexes['issuer_scope'])) {
            IssuerStorage::query($db, "ALTER TABLE `{$invoices}` ADD INDEX `issuer_scope` (`issuer_id`,`id`)");
        }
        foreach (IssuerSchema::documentStatements($prefix) as $sql) { IssuerStorage::query($db, $sql); }
        self::assertValid($db, $prefix);
    }

    /** Read-only verification of the required types, keys and local foreign keys. */
    public static function assertValid($db, string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $p = $prefix . 'issuers_';
        $definitions = [
            $prefix . 'invoices' => ['issuer_id' => ['int unsigned', 'YES']],
            $p . 'sequences' => ['issuer_id'=>['int unsigned','NO'], 'series'=>['varchar(32)','NO'], 'next_number'=>['bigint unsigned','NO']],
            $p . 'invoice_documents' => ['invoice_id'=>['int','NO'], 'issuer_id'=>['int unsigned','NO'], 'series'=>['varchar(32)','NO'], 'number'=>['bigint unsigned','NO'], 'prefix'=>['varchar(50)','NO'], 'formatted_number'=>['varchar(191)','NO'], 'legal_identity_json'=>['longtext','NO'], 'recipient_identity_json'=>['longtext','NO'], 'created_at'=>['datetime','NO']],
        ];
        foreach ($definitions as $table => $fields) {
            $found = [];
            foreach (self::rows($db, 'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]) as $row) { $found[$row['COLUMN_NAME']] = $row; }
            foreach ($fields as $field => [$type, $nullable]) {
                $column = $found[$field] ?? [];
                $actual = preg_replace('/^(int|bigint)\([0-9]+\)/', '$1', strtolower((string) ($column['COLUMN_TYPE'] ?? '')));
                if ($actual !== $type || ($column['IS_NULLABLE'] ?? null) !== $nullable) { throw new RuntimeException('issuer_financial_schema_invalid'); }
            }
            $engine = self::rows($db, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
            if (count($engine) !== 1 || strtoupper((string) $engine[0]['ENGINE']) !== 'INNODB') { throw new RuntimeException('issuer_financial_schema_invalid'); }
        }
        // MySQL returns PHP NULL; MariaDB reports the unquoted SQL expression NULL.
        // The quoted literal 'NULL' and numeric defaults remain incompatible.
        $ownerColumn = self::rows($db, 'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$prefix . 'invoices', 'issuer_id']);
        if (count($ownerColumn) !== 1 || !IssuerStorage::sqlNullDefault($ownerColumn[0])) { throw new RuntimeException('issuer_financial_schema_invalid'); }
        foreach ([[$prefix.'invoices','issuer_scope',false,['issuer_id','id']], [$p.'sequences','PRIMARY',true,['issuer_id','series']], [$p.'invoice_documents','PRIMARY',true,['invoice_id']], [$p.'invoice_documents','issuer_series_number',true,['issuer_id','series','number']]] as [$table,$key,$unique,$columns]) {
            $index = self::indexes($db, $table)[$key] ?? null;
            if ($index !== ['unique'=>$unique,'columns'=>$columns]) { throw new RuntimeException('issuer_financial_schema_invalid'); }
        }
        foreach ([[$p.'sequences','issuer_id',$p.'registry','id'], [$p.'invoice_documents','issuer_id',$p.'registry','id'], [$p.'invoice_documents','invoice_id',$prefix.'invoices','id']] as [$table,$column,$target,$targetColumn]) {
            $keys = self::rows($db, 'SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION', [$table]);
            $matches = array_filter($keys, static fn($key) => $key['COLUMN_NAME']===$column && $key['REFERENCED_TABLE_NAME']===$target && $key['REFERENCED_COLUMN_NAME']===$targetColumn);
            if (count($matches) !== 1) { throw new RuntimeException('issuer_financial_schema_invalid'); }
        }
    }
}
