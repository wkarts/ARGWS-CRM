<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'services/InvoiceIssuerScope.php';
require_once APPPATH . 'services/ClientIssuerScope.php';
require_once APPPATH . 'services/CustomerRelationBoundary.php';

/** Restricts native accounting inputs to their explicit legacy origin, not session selection. */
final class AccountingLegacyScope
{
    private static function identifier(string $value): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value)) throw new InvalidArgumentException('accounting_scope_identifier');
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
        $invoice = self::invoiceSql($db, $prefix, 'accounting_expense_origin');
        return '(' . $sql . ' AND (' . $unlinked . ' OR EXISTS (SELECT 1 FROM ' . $prefix
            . 'invoices AS accounting_expense_origin WHERE accounting_expense_origin.id=' . $reference
            . ' AND ' . $invoice . ')))';
    }

    public static function paymentSql($db, string $prefix, string $alias): string
    {
        self::identifier($prefix); self::identifier($alias);
        if (!$db->table_exists($prefix . 'invoices')) return '1=0';
        return 'EXISTS (SELECT 1 FROM ' . $prefix . 'invoices AS accounting_payment_origin'
            . ' WHERE accounting_payment_origin.id=' . $alias . '.invoiceid AND '
            . self::invoiceSql($db, $prefix, 'accounting_payment_origin') . ')';
    }

    public static function billMappingSql($db, string $prefix, string $alias): string
    {
        self::identifier($prefix); self::identifier($alias);
        if (!$db->table_exists($prefix . 'expenses')) return '1=0';
        return 'EXISTS (SELECT 1 FROM ' . $prefix . 'expenses AS accounting_bill_origin'
            . ' WHERE accounting_bill_origin.id=' . $alias . '.bill_id AND '
            . self::expenseSql($db, $prefix, 'accounting_bill_origin') . ')';
    }

    public static function invoices($db, string $prefix): void { $db->where(self::invoiceSql($db, $prefix, $prefix . 'invoices'), null, false); }
    public static function expenses($db, string $prefix): void { $db->where(self::expenseSql($db, $prefix, $prefix . 'expenses'), null, false); }
    public static function payments($db, string $prefix): void { $db->where(self::paymentSql($db, $prefix, $prefix . 'invoicepaymentrecords'), null, false); }

    public static function permitsExpenseWrite($db, string $prefix, array $data): bool
    {
        self::identifier($prefix);
        if (!\app\services\CustomerRelationBoundary::permitsWrite($db, $prefix, 'expenses', $data)) return false;
        return empty($data['invoiceid']) || self::permitsRecord($db, $prefix, 'invoice', $data['invoiceid']);
    }

    /** A raw existence check precedes hydration, mapping, hooks and writes. */
    public static function permitsRecord($db, string $prefix, string $type, $id): bool
    {
        self::identifier($prefix);
        if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string)$id)) return false;
        $tables = ['invoice'=>'invoices', 'payment'=>'invoicepaymentrecords', 'expense'=>'expenses'];
        if (!isset($tables[$type])) return false;
        $table = $prefix . $tables[$type];
        if (!$db->table_exists($table)) return false;
        $predicate = match ($type) {
            'invoice' => self::invoiceSql($db, $prefix, $table),
            'payment' => self::paymentSql($db, $prefix, $table),
            'expense' => self::expenseSql($db, $prefix, $table),
        };
        $query = $db->query('SELECT id FROM ' . $table . ' WHERE id=? AND (' . $predicate . ')', [$id]);
        return $query !== false && $query->row() !== null;
    }
}
