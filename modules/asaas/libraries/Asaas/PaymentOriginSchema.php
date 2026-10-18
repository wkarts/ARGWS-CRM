<?php

declare(strict_types=1);
namespace Asaas;

final class PaymentOriginSchema
{
    public static function install($db): void
    {
        $origins = db_prefix() . 'asaas_payment_origins';
        if (!$db->table_exists($origins)) {
            if (!$db->query("CREATE TABLE `{$origins}` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `invoice_id` INT NOT NULL,
                `invoice_hash` VARCHAR(64) NOT NULL,
                `invoice_total` DECIMAL(15,2) NOT NULL,
                `invoice_due_date` DATE NULL,
                `issuer_id` INT NULL,
                `provider` VARCHAR(32) NOT NULL,
                `account_id` VARCHAR(128) NOT NULL,
                `environment` VARCHAR(16) NOT NULL,
                `channel` VARCHAR(16) NOT NULL,
                `remote_id` VARCHAR(64) NULL,
                `batch_id` VARCHAR(128) NULL,
                `state` VARCHAR(32) NOT NULL,
                `last_code` VARCHAR(64) NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `asaas_origin_invoice_uq` (`invoice_id`),
                UNIQUE KEY `asaas_origin_remote_uq` (`provider`,`account_id`,`environment`,`remote_id`),
                KEY `asaas_origin_remote_idx` (`remote_id`),
                KEY `asaas_origin_state_idx` (`state`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")) {
                throw new \RuntimeException('payment_origin_schema_failed');
            }
        }
        $events = db_prefix() . 'asaas_payment_origin_events';
        if (!$db->table_exists($events)) {
            if (!$db->query("CREATE TABLE `{$events}` (
                `id` BIGINT NOT NULL AUTO_INCREMENT,
                `invoice_id` INT NOT NULL,
                `actor_id` INT NULL,
                `code` VARCHAR(64) NOT NULL,
                `evidence_hash` CHAR(64) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `asaas_origin_event_invoice_idx` (`invoice_id`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")) {
                throw new \RuntimeException('payment_origin_audit_schema_failed');
            }
        }
        // Deliberately no backfill: current configuration is not historical evidence.
    }
}
