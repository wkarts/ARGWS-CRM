<?php
declare(strict_types=1);
namespace BancoBrasil;
final class ModuleSchema
{
    public static function statements(string $prefix): array
    {
        if (!preg_match('/^[A-Za-z0-9_]*$/D',$prefix)) throw new \InvalidArgumentException('bb_table_prefix_invalid');
        return [
            "CREATE TABLE IF NOT EXISTS `{$prefix}bb_connections` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                issuer_id BIGINT UNSIGNED NOT NULL, provider VARCHAR(32) NOT NULL DEFAULT 'banco_brasil',
                account_id VARCHAR(80) NOT NULL, environment VARCHAR(16) NOT NULL,
                agreement_number VARCHAR(7) NOT NULL, wallet VARCHAR(2) NOT NULL, variation VARCHAR(3) NOT NULL,
                issuer_tax_id VARCHAR(14) NOT NULL, issuer_name VARCHAR(160) NOT NULL,
                identity_hash CHAR(64) NOT NULL, configuration_hash CHAR(64) NOT NULL,
                lifecycle VARCHAR(24) NOT NULL DEFAULT 'pending_verification',
                created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY(id), UNIQUE KEY bb_connection_identity(identity_hash), KEY bb_connection_issuer(issuer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
            "CREATE TABLE IF NOT EXISTS `{$prefix}bb_origins` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, invoice_id BIGINT UNSIGNED NOT NULL,
                issuer_id BIGINT UNSIGNED NOT NULL, connection_id BIGINT UNSIGNED NOT NULL,
                account_id VARCHAR(80) NOT NULL, environment VARCHAR(16) NOT NULL,
                agreement_number VARCHAR(7) NOT NULL, provider VARCHAR(32) NOT NULL DEFAULT 'banco_brasil',
                channel VARCHAR(16) NOT NULL, remote_id VARCHAR(64) DEFAULT NULL,
                control_reference VARCHAR(25) NOT NULL, scope_key CHAR(64) NOT NULL,
                state VARCHAR(32) NOT NULL, active_slot TINYINT UNSIGNED DEFAULT 1,
                payload_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY(id), UNIQUE KEY bb_active_invoice(invoice_id,active_slot),
                UNIQUE KEY bb_origin_remote(scope_key,channel,remote_id), UNIQUE KEY bb_origin_control(scope_key,control_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
            "CREATE TABLE IF NOT EXISTS `{$prefix}bb_batches` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, issuer_id BIGINT UNSIGNED NOT NULL,
                connection_id BIGINT UNSIGNED NOT NULL, scope_key CHAR(64) NOT NULL,
                idempotency_key VARCHAR(128) NOT NULL, payload_hash CHAR(64) NOT NULL,
                layout_id VARCHAR(96) NOT NULL, layout_source_hash CHAR(64) NOT NULL,
                file_sequence BIGINT UNSIGNED NOT NULL, content_hash CHAR(64) NOT NULL,
                content MEDIUMBLOB NOT NULL, generation_state VARCHAR(24) NOT NULL,
                delivery_state VARCHAR(24) NOT NULL DEFAULT 'not_submitted',
                financial_state VARCHAR(24) NOT NULL DEFAULT 'not_applied',
                created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY(id), UNIQUE KEY bb_batch_key(scope_key,idempotency_key),
                UNIQUE KEY bb_batch_sequence(scope_key,file_sequence)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
            "CREATE TABLE IF NOT EXISTS `{$prefix}bb_return_files` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, issuer_id BIGINT UNSIGNED NOT NULL,
                connection_id BIGINT UNSIGNED NOT NULL, scope_key CHAR(64) NOT NULL,
                content_hash CHAR(64) NOT NULL, layout_id VARCHAR(96) NOT NULL,
                record_count INT UNSIGNED NOT NULL, event_count INT UNSIGNED NOT NULL,
                process_state VARCHAR(24) NOT NULL DEFAULT 'review_required',
                created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY(id), UNIQUE KEY bb_return_file(scope_key,content_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
            "CREATE TABLE IF NOT EXISTS `{$prefix}bb_return_events` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, return_file_id BIGINT UNSIGNED NOT NULL,
                issuer_id BIGINT UNSIGNED NOT NULL, connection_id BIGINT UNSIGNED NOT NULL,
                scope_key CHAR(64) NOT NULL, event_key CHAR(64) NOT NULL, payload_hash CHAR(64) NOT NULL,
                remote_id VARCHAR(64) NOT NULL, control_reference VARCHAR(25) NOT NULL,
                occurrence VARCHAR(2) NOT NULL, observation VARCHAR(48) NOT NULL,
                observation_json TEXT NOT NULL, financial_state VARCHAR(24) NOT NULL DEFAULT 'not_applied',
                created_at DATETIME NOT NULL,
                PRIMARY KEY(id), UNIQUE KEY bb_return_event(scope_key,event_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
            "CREATE TABLE IF NOT EXISTS `{$prefix}bb_return_file_events` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, return_file_id BIGINT UNSIGNED NOT NULL,
                return_event_id BIGINT UNSIGNED NOT NULL, issuer_id BIGINT UNSIGNED NOT NULL,
                connection_id BIGINT UNSIGNED NOT NULL, scope_key CHAR(64) NOT NULL,
                lot_number INT UNSIGNED NOT NULL, record_sequence INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY(id), UNIQUE KEY bb_file_event(return_file_id,return_event_id),
                UNIQUE KEY bb_file_position(return_file_id,lot_number,record_sequence), KEY bb_event_files(return_event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
            "CREATE TABLE IF NOT EXISTS `{$prefix}bb_audit` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, issuer_id BIGINT UNSIGNED NOT NULL,
                connection_id BIGINT UNSIGNED DEFAULT NULL, actor_id BIGINT UNSIGNED NOT NULL,
                action VARCHAR(48) NOT NULL, resource_type VARCHAR(24) NOT NULL, resource_id BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL, PRIMARY KEY(id), KEY bb_audit_scope(issuer_id,connection_id,id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
        ];
    }
}
