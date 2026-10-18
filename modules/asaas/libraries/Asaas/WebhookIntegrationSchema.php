<?php
declare(strict_types=1);
namespace Asaas;
final class WebhookIntegrationSchema
{
    public static function install($db): void
    {
        $table = db_prefix() . 'asaas_webhook_integrations';
        if (!$db->table_exists($table) && !$db->query("CREATE TABLE `{$table}` (
            `id` INT NOT NULL AUTO_INCREMENT, `public_id` CHAR(32) NOT NULL,
            `label` VARCHAR(100) NOT NULL, `issuer_id` INT NULL,
            `provider` VARCHAR(32) NOT NULL, `account_id` VARCHAR(128) NOT NULL,
            `environment` VARCHAR(16) NOT NULL, `token_hash` CHAR(64) NOT NULL,
            `enabled` TINYINT NOT NULL DEFAULT 1, `created_by` INT NULL,
            `created_at` DATETIME NOT NULL, `rotated_at` DATETIME NULL, `rotated_by` INT NULL,
            `disabled_at` DATETIME NULL, `disabled_by` INT NULL,
            `last_valid_at` DATETIME NULL, `last_rejected_at` DATETIME NULL,
            `rejection_count` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), UNIQUE KEY `asaas_webhook_public_uq` (`public_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")) {
            throw new \RuntimeException('webhook_integration_schema_failed');
        }
        $audit=db_prefix().'asaas_webhook_admin_audit';
        if (!$db->table_exists($audit) && !$db->query("CREATE TABLE `{$audit}` (
            `id` BIGINT NOT NULL AUTO_INCREMENT, `integration_id` INT NOT NULL,
            `event_row_id` INT NULL, `actor_id` INT NOT NULL, `action` VARCHAR(32) NOT NULL,
            `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`),
            KEY `asaas_webhook_admin_audit_idx` (`integration_id`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")) throw new \RuntimeException('webhook_admin_audit_schema_failed');
        $outbox=db_prefix().'asaas_settlement_outbox';
        if (!$db->table_exists($outbox) && !$db->query("CREATE TABLE `{$outbox}` (
            `id` BIGINT NOT NULL AUTO_INCREMENT, `invoice_id` INT NOT NULL, `origin_id` INT NOT NULL,
            `receipt_id` INT NOT NULL, `event_row_id` INT NOT NULL, `event_id` VARCHAR(128) NOT NULL,
            `provider` VARCHAR(32) NOT NULL, `account_id` VARCHAR(128) NOT NULL, `environment` VARCHAR(16) NOT NULL,
            `remote_id` VARCHAR(64) NOT NULL, `verified_status` VARCHAR(32) NOT NULL, `payment_date` DATE NOT NULL, `billing_type` VARCHAR(20) NOT NULL,
            `state` VARCHAR(32) NOT NULL,
            `created_at` DATETIME NOT NULL, `processed_at` DATETIME NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `asaas_settlement_invoice_uq` (`invoice_id`),
            UNIQUE KEY `asaas_settlement_origin_uq` (`origin_id`), UNIQUE KEY `asaas_settlement_event_uq` (`event_row_id`),
            KEY `asaas_settlement_pending_idx` (`state`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")) throw new \RuntimeException('settlement_outbox_schema_failed');
        $events = db_prefix() . 'asaas_webhook_events';
        $columns = [
            'integration_id' => 'INT NULL', 'scope_key' => "VARCHAR(64) NOT NULL DEFAULT 'legacy'",
            'payload_hash' => 'CHAR(64) NULL', 'attempt_count' => 'INT NOT NULL DEFAULT 0',
            'next_attempt_at' => 'DATETIME NULL', 'verified_status' => 'VARCHAR(32) NULL',
            'reviewed_by' => 'INT NULL', 'reviewed_at' => 'DATETIME NULL',
        ];
        foreach ($columns as $name => $definition) {
            if (!$db->field_exists($name, $events) && !$db->query("ALTER TABLE `{$events}` ADD `{$name}` {$definition}")) {
                throw new \RuntimeException('webhook_event_schema_failed');
            }
        }
        // Provider identifiers are opaque and case-sensitive. Default database
        // collations must not collapse distinct event IDs into one receipt.
        $collation=$db->query('SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$events,'event_id'])->row_array();
        if (($collation['COLLATION_NAME']??'')!=='utf8mb4_bin'
            && !$db->query("ALTER TABLE `{$events}` MODIFY `event_id` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL")) {
            throw new \RuntimeException('webhook_identifier_collation_failed');
        }
        $indexes = $db->query("SHOW INDEX FROM `{$events}`")->result_array();
        $names = array_column($indexes, 'Key_name');
        // Scope idempotency to the authenticated integration, never the URL token.
        if (!in_array('asaas_webhook_scope_event_uq', $names, true)
            && !$db->query("ALTER TABLE `{$events}` ADD UNIQUE KEY `asaas_webhook_scope_event_uq` (`scope_key`,`event_id`)")) {
            throw new \RuntimeException('webhook_event_index_failed');
        }
        if (in_array('event_id', $names, true) && !$db->query("ALTER TABLE `{$events}` DROP INDEX `event_id`")) {
            throw new \RuntimeException('webhook_legacy_index_failed');
        }
        if (!in_array('asaas_webhook_pending_idx', $names, true)
            && !$db->query("ALTER TABLE `{$events}` ADD KEY `asaas_webhook_pending_idx` (`process_status`,`next_attempt_at`,`id`)")) {
            throw new \RuntimeException('webhook_pending_index_failed');
        }
        // Existing raw payloads are not rewritten or assigned an inferred origin.
    }
}
