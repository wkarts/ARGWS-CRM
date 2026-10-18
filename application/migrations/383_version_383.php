<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_383 extends CI_Migration
{
    public function up(): void
    {
        $prefix = db_prefix();
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `{p}recurring_rules` (
 `id` int NOT NULL AUTO_INCREMENT,
 `emitter_key` varchar(64) NOT NULL,
 `source_invoice_id` int DEFAULT NULL,
 `create_request_hash` char(64) DEFAULT NULL,
 `create_payload_hash` char(64) DEFAULT NULL,
 `clientid` int NOT NULL,
 `addedfrom` int NOT NULL,
 `sale_agent` int NOT NULL DEFAULT 0,
 `title` varchar(191) NOT NULL,
 `state` varchar(16) NOT NULL DEFAULT 'paused',
 `calendar_group` varchar(64) NOT NULL DEFAULT '',
 `integration_group` varchar(32) NOT NULL DEFAULT 'manual',
 `anchor_date` date NOT NULL,
 `anchor_due` date DEFAULT NULL,
 `interval_count` int NOT NULL,
 `interval_unit` varchar(8) NOT NULL,
 `due_mode` varchar(16) NOT NULL DEFAULT 'days',
 `next_issue_date` date NOT NULL,
 `cycles_limit` int NOT NULL DEFAULT 0,
 `cycles_emitted` int NOT NULL DEFAULT 0,
 `template_json` mediumtext NOT NULL,
 `version` int NOT NULL DEFAULT 1,
 `created_at` datetime NOT NULL,
 `updated_at` datetime NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `recurring_source_invoice` (`source_invoice_id`), UNIQUE KEY `recurring_create_request` (`create_request_hash`),
 KEY `recurring_due` (`state`,`next_issue_date`), KEY `recurring_scope` (`emitter_key`,`addedfrom`,`sale_agent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `{p}recurring_rule_batches` (
 `token_hash` char(64) NOT NULL,
 `staff_id` int NOT NULL,
 `source_type` varchar(16) NOT NULL,
 `action` varchar(16) NOT NULL,
 `payload_json` mediumtext NOT NULL,
 `result_json` mediumtext DEFAULT NULL,
 `created_at` datetime NOT NULL,
 `expires_at` datetime NOT NULL,
 `applied_at` datetime DEFAULT NULL,
 PRIMARY KEY (`token_hash`), KEY `recurring_batch_actor` (`staff_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `{p}recurring_rule_audit` (
 `id` bigint NOT NULL AUTO_INCREMENT,
 `rule_id` int DEFAULT NULL,
 `source_invoice_id` int DEFAULT NULL,
 `batch_hash` char(64) DEFAULT NULL,
 `staff_id` int NOT NULL,
 `action` varchar(32) NOT NULL,
 `before_json` text NOT NULL,
 `after_json` text NOT NULL,
 `created_at` datetime NOT NULL,
 PRIMARY KEY (`id`), KEY `recurring_audit_rule` (`rule_id`,`id`), KEY `recurring_audit_batch` (`batch_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `{p}recurring_rule_runs` (
 `id` bigint NOT NULL AUTO_INCREMENT,
 `rule_id` int NOT NULL,
 `cycle_date` date NOT NULL,
 `invoice_id` int NOT NULL,
 `created_at` datetime NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `recurring_cycle` (`rule_id`,`cycle_date`), UNIQUE KEY `recurring_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        foreach (explode(';', str_replace('{p}', $prefix, $sql)) as $statement) {
            if (trim($statement) !== '' && !$this->db->query($statement)) {
                throw new RuntimeException('Não foi possível criar as tabelas de recorrências.');
            }
        }
    }

    public function down(): void
    {
        // Preserve schedules, audit, idempotency tokens and generated invoice links.
        // Never reactivate migrated legacy matrices automatically during rollback.
    }
}
