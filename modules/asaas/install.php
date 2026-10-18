<?php
defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();
// The framework records the header version before the activation hook.
$target = (string) $CI->app_modules->get('asaas')['headers']['version'];
$version = $CI->db->where('module_name', 'asaas')->get(db_prefix() . 'modules')->row();
if (!$version || version_compare((string) $version->installed_version, $target, '>')) {
    throw new RuntimeException('asaas_activation_version_invalid');
}
if ((string) $version->installed_version === $target
    && !$CI->db->where('module_name', 'asaas')->update(db_prefix() . 'modules', ['installed_version' => '1.4.5'])) {
    throw new RuntimeException('asaas_activation_version_write_failed');
}

if (!$CI->db->field_exists('asaas_customer_id', db_prefix() . 'clients')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'clients` ADD `asaas_customer_id` VARCHAR(25) NULL DEFAULT NULL;');
}

$customersTable = db_prefix() . 'asaas_customers_map';
if (!$CI->db->table_exists($customersTable)) {
    $CI->db->query("CREATE TABLE `{$customersTable}` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `client_id` INT NOT NULL,
        `asaas_customer_id` VARCHAR(64) NOT NULL,
        `created_at` DATETIME NULL,
        `updated_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `argws_asaas_client_id_uq` (`client_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

$paymentsTable = db_prefix() . 'asaas_payments_map';
if (!$CI->db->table_exists($paymentsTable)) {
    $CI->db->query("CREATE TABLE `{$paymentsTable}` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `invoice_id` INT NOT NULL,
        `asaas_payment_id` VARCHAR(64) NOT NULL,
        `billing_type` VARCHAR(32) NULL,
        `status` VARCHAR(32) NULL,
        `value` DECIMAL(15,2) NULL,
        `due_date` DATE NULL,
        `last_sync_at` DATETIME NULL,
        `payload_cache` LONGTEXT NULL,
        `created_at` DATETIME NULL,
        `updated_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `invoice_id` (`invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

$webhookTable = db_prefix() . 'asaas_webhook_events';
if (!$CI->db->table_exists($webhookTable)) {
    $CI->db->query("CREATE TABLE `{$webhookTable}` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `event_id` VARCHAR(128) NOT NULL,
        `event_type` VARCHAR(64) NULL,
        `asaas_payment_id` VARCHAR(64) NULL,
        `invoice_id` INT NULL,
        `received_at` DATETIME NULL,
        `processed_at` DATETIME NULL,
        `payload` LONGTEXT NULL,
        `process_status` VARCHAR(32) NULL,
        `error_message` TEXT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `event_id` (`event_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

$logsTable = db_prefix() . 'asaas_logs';
if (!$CI->db->table_exists($logsTable)) {
    $CI->db->query("CREATE TABLE `{$logsTable}` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `level` VARCHAR(32) NOT NULL,
        `message` TEXT NOT NULL,
        `context_json` LONGTEXT NULL,
        `created_at` DATETIME NULL,
        `correlation_id` VARCHAR(64) NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

$migration = new App_module_migration('asaas');
if ($migration->to_latest() === false) { throw new RuntimeException('asaas_native_migration_failed'); }
$version = $CI->db->where('module_name', 'asaas')->get(db_prefix() . 'modules')->row();
if (!$version || (string) $version->installed_version !== $target) {
    throw new RuntimeException('asaas_migration_version_unconfirmed');
}

