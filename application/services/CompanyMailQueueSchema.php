<?php

declare(strict_types=1);
require_once __DIR__ . '/issuers/IssuerStorage.php';

/** Nullable ownership preserves historical queue rows as installation-origin mail. */
final class CompanyMailQueueSchema
{
    public static function ensure($db, string $prefix): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        $table = $prefix . 'mail_queue';
        if (!$db->table_exists($table)) { return; } // Minimal core fixtures; real installer creates the queue first.
        if (!IssuerStorage::fieldExists($db, 'issuer_id', $table)) {
            IssuerStorage::query($db, "ALTER TABLE `{$table}` ADD `issuer_id` INT UNSIGNED NULL DEFAULT NULL");
        }
        $columns = IssuerStorage::query($db, 'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$table,'issuer_id'])->result_array();
        $column = $columns[0] ?? [];
        if (count($columns)!==1 || preg_replace('/^int\([0-9]+\)/','int',strtolower((string)($column['COLUMN_TYPE']??'')))!=='int unsigned'
            || ($column['IS_NULLABLE']??null)!=='YES' || ($column['COLUMN_DEFAULT']??null)!==null) {
            throw new RuntimeException('company_mail_queue_schema_invalid');
        }
    }
}
