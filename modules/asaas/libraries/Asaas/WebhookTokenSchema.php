<?php

declare(strict_types=1);
namespace Asaas;
require_once (defined('APPPATH') ? APPPATH : dirname(__DIR__,4).'/application/').'services/issuers/IssuerStorage.php';

/** Migration 154 adds optional ciphertext. No token, hash or receiver is backfilled or rotated. */
final class WebhookTokenSchema
{
    public static function ensure($db, string $prefix, bool $create = true): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) throw new \InvalidArgumentException('webhook_token_prefix_invalid');
        if ($create && property_exists($db, 'data_cache')) unset($db->data_cache['table_names']);
        foreach (['asaas_webhook_integrations', 'asaas_company_accounts'] as $suffix) {
            $table = $prefix.$suffix;
            if (!$db->table_exists($table)) throw new \RuntimeException('webhook_token_schema_required');
            if ($create && !\IssuerStorage::fieldExists($db, 'token_cipher', $table)) {
                $sqlite = self::sqlite($db);
                \IssuerStorage::query($db, 'ALTER TABLE `'.$table.'` ADD `token_cipher` TEXT'.($sqlite ? '' : ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin').' NULL');
            }
            self::assertReady($db, $table);
        }
    }

    public static function assertReady($db, string $table): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $table) || !\IssuerStorage::fieldExists($db, 'token_cipher', $table)) {
            throw new \RuntimeException('webhook_token_schema_required');
        }
        if (self::sqlite($db)) {
            $rows = \IssuerStorage::query($db, 'PRAGMA table_info(`'.$table.'`)')->result_array();
            $row = array_values(array_filter($rows, static fn($column)=>$column['name'] === 'token_cipher'))[0] ?? [];
            if (strtolower($row['type'] ?? '') !== 'text' || (int)($row['notnull'] ?? 1) !== 0 || ($row['dflt_value'] ?? null) !== null) {
                throw new \RuntimeException('webhook_token_schema_invalid');
            }
            return;
        }
        $row = \IssuerStorage::query($db, 'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table,'token_cipher'])->row_array();
        if (strtolower($row['COLUMN_TYPE'] ?? '') !== 'text' || ($row['IS_NULLABLE'] ?? '') !== 'YES'
            || !\IssuerStorage::sqlNullDefault($row) || ($row['COLLATION_NAME'] ?? '') !== 'utf8mb4_bin') {
            throw new \RuntimeException('webhook_token_schema_invalid');
        }
    }

    private static function sqlite($db): bool
    {
        $connection = $db->pdo ?? $db->conn_id ?? null;
        return $connection instanceof \PDO && $connection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
