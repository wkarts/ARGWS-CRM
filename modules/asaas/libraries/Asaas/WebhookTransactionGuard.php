<?php
declare(strict_types=1);
namespace Asaas;

/** Require ownership before a webhook may acknowledge or mutate durable state. */
final class WebhookTransactionGuard
{
    public static function prepare($db): void
    {
        if (!property_exists($db, '_trans_depth')
            || (new \ReflectionProperty($db, '_trans_depth'))->getValue($db) !== 0
            || (($db->conn_id ?? null) instanceof \PDO && $db->conn_id->inTransaction())) {
            throw new \RuntimeException('webhook_outer_transaction_not_allowed');
        }
        $result = $db->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');
        $state = $result ? $result->row_array() : [];
        if ((string) ($state['autocommit'] ?? '') !== '1'
            || !in_array((string) ($state['completion_type'] ?? ''), ['0', 'NO_CHAIN'], true)) {
            throw new \RuntimeException('webhook_autocommit_required');
        }
        // MySQL refuses this inside a raw SQL transaction without committing it.
        if (!$db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) {
            throw new \RuntimeException('webhook_outer_transaction_or_isolation_unavailable');
        }
    }
}
