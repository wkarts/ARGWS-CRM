<?php

declare(strict_types=1);
require_once __DIR__ . '/IssuerPolicy.php';
require_once __DIR__ . '/IssuerTransaction.php';

/** Server-side installation evidence only. No controller reads an ID from HTTP/options. */
final class IssuerInstallationIdentity
{
    public static function bindInstallationMaster($db, string $prefix, $staffId): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix');
        }
        $staffId = IssuerPolicy::id($staffId);
        $p = $prefix . 'issuers_';
        $hasDebug = property_exists($db, 'db_debug');
        $debug = $hasDebug ? $db->db_debug : null;
        $hasSavedQueries = property_exists($db, 'save_queries');
        $savedQueries = $hasSavedQueries ? $db->save_queries : null;
        if ($hasDebug) { $db->db_debug = false; }
        if ($hasSavedQueries) { $db->save_queries = false; }
        $owned = false;
        $rows = static function (string $sql, array $params = []) use ($db): array {
            $result = $db->query($sql, $params);
            if ($result === false) { throw new RuntimeException('issuer_identity_storage_failed'); }
            return $result->result_array();
        };
        $write = static function (string $sql, array $params) use ($db): void {
            if ($db->query($sql, $params) === false) { throw new RuntimeException('issuer_identity_storage_failed'); }
        };
        try {
            if (property_exists($db, '_trans_depth')
                && (new ReflectionProperty($db, '_trans_depth'))->getValue($db) !== 0) {
                throw new DomainException('issuer_identity_outer_transaction');
            }
            $connection = $db->conn_id ?? $db->pdo ?? null;
            if ($connection instanceof PDO && $connection->inTransaction()) {
                throw new DomainException('issuer_identity_outer_transaction');
            }
            $driver = $connection instanceof PDO ? $connection->getAttribute(PDO::ATTR_DRIVER_NAME)
                : (method_exists($db, 'platform') ? $db->platform() : ($db->driver ?? $db->dbdriver ?? ''));
            if ($driver !== 'sqlite' && $driver !== 'sqlite3') {
                $mode = $rows('SELECT @@session.autocommit AS autocommit, @@session.completion_type AS completion_type')[0] ?? [];
                if ((int) ($mode['autocommit'] ?? 0) !== 1 || !in_array((string) ($mode['completion_type'] ?? ''), ['0', 'NO_CHAIN'], true)) {
                    throw new DomainException('issuer_identity_transaction_mode');
                }
                $tables = [$prefix . 'staff', $p . 'lock', $p . 'installation_identity', $p . 'audit'];
                $engines = $rows('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?,?)', $tables);
                if (count($engines) !== count($tables)) { throw new RuntimeException('issuer_identity_storage_failed'); }
                foreach ($engines as $engine) {
                    if (strtoupper((string) $engine['ENGINE']) !== 'INNODB') {
                        throw new DomainException('issuer_identity_transactional_storage_required');
                    }
                }
                // Unlike trans_depth, this also refuses a transaction started
                // through raw SQL before we claim ownership of the connection.
                $write('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', []);
            }
            if (!$db->trans_begin()) { throw new RuntimeException('issuer_identity_storage_failed'); }
            $owned = true;
            if (!$rows("SELECT id FROM {$p}lock WHERE id=1 FOR UPDATE")) {
                throw new RuntimeException('issuer_identity_lock_missing');
            }
            $staff = $rows("SELECT staffid,active,admin FROM {$prefix}staff WHERE staffid=? FOR UPDATE", [$staffId])[0] ?? null;
            if (!$staff || (int) $staff['active'] !== 1 || (int) $staff['admin'] !== 1) {
                throw new DomainException('issuer_identity_admin_required');
            }
            $identity = $rows("SELECT master_staff_id,primary_issuer_id FROM {$p}installation_identity WHERE id=1 FOR UPDATE")[0] ?? null;
            if ($identity && $identity['master_staff_id'] !== null && (int) $identity['master_staff_id'] !== $staffId) {
                throw new DomainException('issuer_identity_already_resolved');
            }
            if (!$identity || $identity['master_staff_id'] === null) {
                $now = date('Y-m-d H:i:s');
                if (!$identity) {
                    $write("INSERT INTO {$p}installation_identity (id,master_staff_id,primary_issuer_id,source,resolved_at) VALUES (1,?,NULL,'installer',?)", [$staffId, $now]);
                } else {
                    // A separately confirmed principal company is never replaced.
                    $write("UPDATE {$p}installation_identity SET master_staff_id=?,source='installer',resolved_at=? WHERE id=1 AND master_staff_id IS NULL", [$staffId, $now]);
                }
                $confirmed = $rows("SELECT master_staff_id FROM {$p}installation_identity WHERE id=1")[0] ?? null;
                if (!$confirmed || (int) $confirmed['master_staff_id'] !== $staffId) {
                    throw new RuntimeException('issuer_identity_write_unconfirmed');
                }
                $write("INSERT INTO {$p}audit (actor_id,event,issuer_id,subject_id,created_at) VALUES (?,'installation_master_bound',NULL,?,?)", [$staffId, $staffId, $now]);
            }
            if ($db->trans_status() === false || !$db->trans_commit()) {
                throw new RuntimeException('issuer_identity_storage_failed');
            }
            $owned = false;
            IssuerTransaction::assertIndependent($db);
        } catch (Throwable $error) {
            if ($owned) {
                try { $db->trans_rollback(); } catch (Throwable $rollbackError) { /* sanitized below */ }
            }
            if ($error instanceof DomainException) { throw $error; }
            // Do not propagate database messages/SQL or credential-bearing traces.
            throw new RuntimeException('issuer_identity_storage_failed');
        } finally {
            if ($hasDebug) { $db->db_debug = $debug; }
            if ($hasSavedQueries) { $db->save_queries = $savedQueries; }
        }
    }
}
