<?php

declare(strict_types=1);
require_once __DIR__.'/IssuerStorage.php';

/** Read-only readiness checks. Only controlled setup/upgrade callers may reconcile. */
final class IssuerCoreSchema
{
    /** Request-local schema metadata only; never permissions, activation or version. */
    private static ?WeakMap $validatedSchemas = null;
    public static function invalidateSchemaCache($db): void
    {
        if (self::$validatedSchemas !== null && isset(self::$validatedSchemas[$db])) {
            unset(self::$validatedSchemas[$db]);
        }
        // Native CI3 caches table/column discovery. Raw additive DDL in 101 does
        // not invalidate it, including recovery after a missing-table check.
        if (property_exists($db, 'data_cache')) {
            unset($db->data_cache['table_names'], $db->data_cache['field_names']);
        }
    }

    public static function assertSchema($db, string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new RuntimeException('issuer_schema_prefix_invalid');
        }
        self::$validatedSchemas ??= new WeakMap();
        $verified = self::$validatedSchemas[$db] ?? [];
        if (!isset($verified[$prefix])) {
            foreach (self::schema() as $table => $fields) {
                $table = $prefix . 'issuers_' . $table;
                if (!$db->table_exists($table)) {
                    throw new RuntimeException('issuer_essential_schema_incomplete');
                }
                $columns = method_exists($db, 'list_fields') ? $db->list_fields($table) : null;
                foreach ($fields as $field) {
                    if (is_array($columns) ? !in_array($field, $columns, true) : !$db->field_exists($field, $table)) {
                        throw new RuntimeException('issuer_essential_schema_incomplete');
                    }
                }
            }
            self::assertConstraints($db, $prefix);
        }
        $row = self::rows($db, 'SELECT id FROM '.$prefix.'issuers_lock WHERE id=1');
        if (!$row) {
            throw new RuntimeException('issuer_essential_lock_missing');
        }
        $verified[$prefix] = true;
        self::$validatedSchemas[$db] = $verified;
    }

    private static function rows($db, string $sql, array $bindings = []): array
    {
        $result = IssuerStorage::query($db,$sql,$bindings);
        if ($result === false) {
            throw new RuntimeException('issuer_essential_schema_unconfirmed');
        }
        return $result->result_array();
    }

    /** Required keys/FKs and transactional engines, not a universal legacy-data audit. */
    private static function assertConstraints($db, string $prefix): void
    {
        $connection = $db->conn_id ?? $db->pdo ?? null;
        $driver = $connection instanceof PDO ? $connection->getAttribute(PDO::ATTR_DRIVER_NAME)
            : ($db->dbdriver ?? null);
        if (!in_array($driver, ['sqlite', 'mysql', 'mysqli'], true)) {
            throw new RuntimeException('issuer_essential_storage_unsupported');
        }
        $p = $prefix . 'issuers_';
        $primary = ['lock'=>['id'], 'registry'=>['id'], 'staff'=>['issuer_id','staff_id'],
            'staff_defaults'=>['staff_id'], 'clients'=>['client_id'],
            'client_links'=>['client_id','issuer_id'], 'audit'=>['id']];
        $foreign = [];
        if ($driver !== 'sqlite') {
            $tables = array_map(static fn($name) => $p . $name, array_keys($primary));
            $marks = implode(',', array_fill(0, count($tables), '?'));
            $engines = self::rows($db, 'SELECT TABLE_NAME AS table_name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $marks . ')', $tables);
            if (count($engines) !== count($tables)) { throw new RuntimeException('issuer_essential_schema_unconfirmed'); }
            foreach ($engines as $engine) {
                if (strtoupper((string) $engine['engine']) !== 'INNODB') { throw new RuntimeException('issuer_essential_innodb_required'); }
            }
            foreach (self::rows($db, 'SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $marks . ') AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL', $tables) as $row) {
                $key = $row['TABLE_NAME'] . ':' . $row['CONSTRAINT_NAME'];
                $foreign[$key]['table'] = $row['TABLE_NAME'];
                $foreign[$key]['reference'] = $row['REFERENCED_TABLE_NAME'];
                $foreign[$key]['columns'][(int) $row['ORDINAL_POSITION']] = $row['COLUMN_NAME'];
                $foreign[$key]['targets'][(int) $row['ORDINAL_POSITION']] = $row['REFERENCED_COLUMN_NAME'];
            }
        }
        foreach ($primary as $name => $expectedPrimary) {
            $table = $p . $name;
            $keys = []; $pk = [];
            if ($driver === 'sqlite') {
                foreach (self::rows($db, 'PRAGMA table_info(`' . $table . '`)') as $row) {
                    if ((int) $row['pk'] > 0) { $pk[(int) $row['pk']] = $row['name']; }
                }
                foreach (self::rows($db, 'PRAGMA index_list(`' . $table . '`)') as $index) {
                    if ((int) $index['unique'] !== 1 || (int) ($index['partial'] ?? 0) !== 0) { continue; }
                    $columns = [];
                    foreach (self::rows($db, 'PRAGMA index_info("' . str_replace('"', '""', $index['name']) . '")') as $row) {
                        $columns[(int) $row['seqno']] = $row['name'];
                    }
                    ksort($columns); $keys[] = array_values($columns);
                }
                foreach (self::rows($db, 'PRAGMA foreign_key_list(`' . $table . '`)') as $row) {
                    $key = $table . ':' . $row['id'];
                    $foreign[$key]['table'] = $table;
                    $foreign[$key]['reference'] = $row['table'];
                    $foreign[$key]['columns'][(int) $row['seq']] = $row['from'];
                    $foreign[$key]['targets'][(int) $row['seq']] = $row['to'];
                }
            } else {
                $indexes = [];
                foreach (self::rows($db, 'SHOW INDEX FROM `' . $table . '`') as $row) {
                    if ((int) $row['Non_unique'] !== 0) { continue; }
                    // A prefix/expression index cannot attest whole-identity uniqueness.
                    $indexes[$row['Key_name']][(int) $row['Seq_in_index']] = $row['Sub_part'] === null ? $row['Column_name'] : null;
                }
                foreach ($indexes as $key => $columns) {
                    ksort($columns); $keys[] = array_values($columns);
                    if ($key === 'PRIMARY') { $pk = $columns; }
                }
            }
            ksort($pk);
            if (array_values($pk) !== $expectedPrimary
                || (in_array($name, ['registry','clients'], true) && !in_array(['tax_id'], $keys, true))) {
                throw new RuntimeException('issuer_essential_keys_invalid');
            }
        }
        $actual = [];
        foreach ($foreign as $constraint) {
            ksort($constraint['columns']); ksort($constraint['targets']);
            $actual[] = [$constraint['table'], array_values($constraint['columns']), $constraint['reference'], array_values($constraint['targets'])];
        }
        foreach ([
            ['staff',['issuer_id'],'registry',['id']],
            ['staff_defaults',['issuer_id','staff_id'],'staff',['issuer_id','staff_id']],
            ['clients',['owner_issuer_id'],'registry',['id']],
            ['client_links',['client_id'],'clients',['client_id']],
            ['client_links',['issuer_id'],'registry',['id']],
        ] as [$table,$columns,$target,$targets]) {
            if (!in_array([$p.$table,$columns,$p.$target,$targets], $actual, true)) {
                throw new RuntimeException('issuer_essential_foreign_keys_invalid');
            }
        }
    }

    private static function schema(): array
    {
        return [
            'lock' => ['id'],
            'registry' => ['id', 'legal_name', 'tax_id', 'active', 'settings_json', 'created_by', 'created_at'],
            'staff' => ['issuer_id', 'staff_id', 'can_view', 'can_manage_clients', 'can_share_clients'],
            'staff_defaults' => ['staff_id', 'issuer_id'],
            'clients' => ['client_id', 'owner_issuer_id', 'tax_id', 'created_by', 'created_at'],
            'client_links' => ['client_id', 'issuer_id', 'linked_by', 'created_at'],
            'audit' => ['id', 'actor_id', 'event', 'issuer_id', 'subject_id', 'created_at'],
        ];
    }
}
