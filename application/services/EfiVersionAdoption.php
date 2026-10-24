<?php

declare(strict_types=1);

namespace app\services;

defined('BASEPATH') or exit('No direct script access allowed');

/** Explicit adoption of a release, never an inferred execution of legacy 514. */
final class EfiVersionAdoption
{
    public const MODULE = 'gerencianet_gateway';
    public const PLAN = 'efi-native-515-v1';
    public const TARGET = '5.1.5';
    public const SOURCE_SHA256 = '5688625c931504c9b21c33efdb72caa62369e7230542ddc856ee340829a2033c';
    public const SOURCES = ['5.1.4 sdk f', '5.1.4 sdk final'];

    public static function eligible(string $module, string $source, string $target): bool
    {
        return $module === self::MODULE && $target === self::TARGET
            && in_array($source, self::SOURCES, true);
    }

    /** Caller must authenticate admin, validate CSRF and hold the module upgrade lock.
     * No hooks, install.php, provider clients, ACL or activation writes are permitted here.
     */
    public static function adopt($db, string $prefix, int $actor, string $target, ?string $expectedSource = null): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix) || $actor < 1 || $target !== self::TARGET) {
            throw new \RuntimeException('module_migration_adoption_request_invalid');
        }
        $modules = $prefix . 'modules';
        $audit = $prefix . 'module_version_adoptions';
        // An atomic audit+marker is mandatory; never pretend MyISAM can roll back.
        $engines = self::query($db, 'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)', [$modules, $audit])->result_array();
        if (count($engines) !== 2) { throw new \RuntimeException('module_migration_adoption_schema_missing'); }
        foreach ($engines as $engine) {
            if (strtoupper((string) $engine['ENGINE']) !== 'INNODB') {
                throw new \RuntimeException('module_migration_adoption_transaction_required');
            }
        }
        // Require a transaction we actually own; a nested CI commit is not durable proof.
        if (!property_exists($db, '_trans_depth') || (new \ReflectionProperty($db, '_trans_depth'))->getValue($db) !== 0
            || (($db->conn_id ?? null) instanceof \PDO && $db->conn_id->inTransaction())) {
            throw new \RuntimeException('module_migration_adoption_outer_transaction');
        }
        $state = self::query($db, 'SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type', [])->row_array();
        if ((string) ($state['autocommit'] ?? '') !== '1' || !in_array((string) ($state['completion_type'] ?? ''), ['0','NO_CHAIN'], true)) {
            throw new \RuntimeException('module_migration_adoption_autocommit_required');
        }
        // Reject an undetected raw transaction without implicitly committing it.
        self::query($db, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', []);
        if ($db->trans_begin() === false) { throw new \RuntimeException('module_migration_adoption_begin_failed'); }
        try {
            $rows = self::query($db, "SELECT id,installed_version,active FROM `{$modules}` WHERE module_name=? FOR UPDATE", [self::MODULE])->result_array();
            $history = self::query($db, "SELECT * FROM `{$audit}` WHERE module_name=? AND plan_id=? FOR UPDATE", [self::MODULE, self::PLAN])->result_array();
            if (count($rows) !== 1 || count($history) > 1) { throw new \RuntimeException('module_migration_adoption_conflict'); }
            $row = $rows[0];
            if ((string) $row['installed_version'] === self::TARGET) {
                // A header-only marker without its audit receipt is not a successful replay.
                if (count($history) !== 1 || !self::validReceipt($history[0])
                    || ($expectedSource !== null && $history[0]['source_version'] !== $expectedSource)) {
                    throw new \RuntimeException('module_migration_adoption_receipt_missing');
                }
            } else {
                if (!self::eligible(self::MODULE, (string) $row['installed_version'], $target) || $history
                    || ($expectedSource !== null && $row['installed_version'] !== $expectedSource)) {
                    throw new \RuntimeException('module_migration_adoption_conflict');
                }
                require_once APP_MODULES_PATH . self::MODULE . '/migrations/515_version_515.php';
                (new \Migration_Version_515())->up();
                self::query($db, "INSERT INTO `{$audit}` (module_name,plan_id,source_version,target_version,source_header_sha256,actor_id,created_at) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP())", [self::MODULE, self::PLAN, $row['installed_version'], self::TARGET, self::SOURCE_SHA256, $actor]);
                self::query($db, "UPDATE `{$modules}` SET installed_version=? WHERE id=? AND installed_version=? AND active=?", [self::TARGET, $row['id'], $row['installed_version'], $row['active']]);
                if ((int) $db->affected_rows() !== 1) { throw new \RuntimeException('module_migration_adoption_marker_failed'); }
                $saved = self::query($db, "SELECT installed_version,active FROM `{$modules}` WHERE id=?", [$row['id']])->row_array();
                $receipt = self::query($db, "SELECT * FROM `{$audit}` WHERE module_name=? AND plan_id=?", [self::MODULE, self::PLAN])->result_array();
                if (!$saved || $saved['installed_version'] !== self::TARGET || (int) $saved['active'] !== (int) $row['active']
                    || count($receipt) !== 1 || !self::validReceipt($receipt[0]) || $receipt[0]['source_version'] !== $row['installed_version']) {
                    throw new \RuntimeException('module_migration_adoption_unconfirmed');
                }
            }
            if ($db->trans_status() === false || $db->trans_commit() === false) {
                throw new \RuntimeException('module_migration_adoption_commit_failed');
            }
        } catch (\Throwable $error) {
            $db->trans_rollback();
            throw $error;
        }
    }

    private static function validReceipt(array $row): bool
    {
        return ($row['module_name'] ?? null) === self::MODULE && ($row['plan_id'] ?? null) === self::PLAN
            && in_array($row['source_version'] ?? null, self::SOURCES, true)
            && ($row['target_version'] ?? null) === self::TARGET
            && ($row['source_header_sha256'] ?? null) === self::SOURCE_SHA256
            && (int) ($row['actor_id'] ?? 0) > 0 && !empty($row['created_at']);
    }

    private static function query($db, string $sql, array $binds)
    {
        $result = $db->query($sql, $binds);
        if ($result === false) { throw new \RuntimeException('module_migration_adoption_storage_failed'); }
        return $result;
    }
}
