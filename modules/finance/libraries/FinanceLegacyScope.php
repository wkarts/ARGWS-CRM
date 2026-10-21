<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'services/InvoiceIssuerScope.php';
require_once APPPATH . 'services/ClientIssuerScope.php';
require_once APPPATH . 'services/CustomerRelationBoundary.php';

/** Explicit native/legacy ownership; never grants access to a selected company. */
final class FinanceLegacyScope
{
    private static function identifier(string $value): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value)) throw new InvalidArgumentException('finance_scope_identifier');
    }

    private static function foundationExists($db, string $prefix): bool
    {
        foreach (['issuers_registry', 'issuers_clients', 'issuers_client_links'] as $table) {
            if ($db->table_exists($prefix . $table)) return true;
        }
        return false;
    }

    public static function invoiceSql($db, string $prefix, string $alias): string
    {
        self::identifier($prefix); self::identifier($alias);
        if ($db->field_exists('issuer_id', $prefix . 'invoices')) return $alias . '.issuer_id IS NULL';
        // A damaged/partial migration is not evidence that every invoice is legacy.
        return self::foundationExists($db, $prefix) ? '1=0' : '1=1';
    }

    public static function expenseSql($db, string $prefix, string $alias): string
    {
        self::identifier($prefix); self::identifier($alias);
        $sql = \app\services\CustomerRelationBoundary::sql($db, $prefix, 'expenses', $alias);
        if (!$db->field_exists('invoiceid', $prefix . 'expenses')) {
            return self::foundationExists($db, $prefix) ? '1=0' : $sql;
        }
        $reference = $alias . '.invoiceid';
        $unlinked = '(' . $reference . ' IS NULL OR ' . $reference . '=0)';
        if (!$db->table_exists($prefix . 'invoices')) return '(' . $sql . ' AND ' . $unlinked . ')';
        $invoice = self::invoiceSql($db, $prefix, 'finance_expense_origin');
        return '(' . $sql . ' AND (' . $unlinked . ' OR EXISTS (SELECT 1 FROM ' . $prefix
            . 'invoices AS finance_expense_origin WHERE finance_expense_origin.id=' . $reference
            . ' AND ' . $invoice . ')))';
    }

    public static function invoices($db, string $prefix, ?string $alias = null): void
    {
        $db->where(self::invoiceSql($db, $prefix, $alias ?? $prefix . 'invoices'), null, false);
    }

    public static function expenses($db, string $prefix, ?string $alias = null): void
    {
        $db->where(self::expenseSql($db, $prefix, $alias ?? $prefix . 'expenses'), null, false);
    }
}
