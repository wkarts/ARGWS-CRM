<?php
namespace app\services;
defined('BASEPATH') or exit('No direct script access allowed');

/** Shared by the legacy scheduler, independent rules and all schedule mutations. */
final class RecurringInvoiceLockUnavailable extends \RuntimeException {}

final class RecurringInvoiceLock
{
    public static function run($db, callable $operation)
    {
        // Server-wide namespace intentionally also serializes installations with the
        // same prefix. No credential or user-provided identifier enters the key.
        $name = 'recurrence:' . substr(hash('sha256', db_prefix()), 0, 48);
        $result = $db->query('SELECT GET_LOCK(?, 0) AS acquired', [$name]);
        if (!$result || (int) $result->row()->acquired !== 1) {
            throw new RecurringInvoiceLockUnavailable('Outra operação de recorrência está em andamento. Tente novamente.');
        }
        try {
            return $operation();
        } finally {
            $db->query('SELECT RELEASE_LOCK(?) AS released', [$name]);
        }
    }
}
