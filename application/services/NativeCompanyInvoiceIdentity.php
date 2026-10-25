<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Resolve the persisted invoice lineage before choosing an adapter; never a fallback after an owned miss. */
final class NativeCompanyInvoiceIdentity
{
    public function __construct(private $db, private string $prefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
    }

    public function resolve($issuer, $actor, $invoice, string $capability = 'view'): array
    {
        $issuer = IssuerPolicy::id($issuer); $actor = IssuerPolicy::id($actor); $invoice = IssuerPolicy::id($invoice);
        if (!in_array($capability, ['view','create','edit','delete'], true)) { throw new DomainException('company_invoice_access_denied'); }
        $acl = new IssuerResourceAccess($this->db, $this->prefix);
        if (!$acl->can($issuer, $actor, 'invoices', 'view') || !$acl->can($issuer, $actor, 'invoices', $capability)) {
            throw new DomainException('company_invoice_access_denied');
        }
        $read = fn()=>IssuerStorage::query($this->db, 'SELECT id,issuer_id FROM '.$this->prefix.'invoices WHERE id=?', [$invoice])->result_array();
        $rows = $read();
        if (count($rows) !== 1 || !array_key_exists('issuer_id', $rows[0])) { throw new DomainException('company_invoice_access_denied'); }
        $row = $rows[0]; $primary = $acl->primaryId();
        if ($row['issuer_id'] === null) {
            if ($primary !== $issuer) { throw new DomainException('company_invoice_access_denied'); }
            $lineage = 'native_legacy_null';
        } else {
            if (IssuerPolicy::id($row['issuer_id']) !== $issuer) { throw new DomainException('company_invoice_access_denied'); }
            $lineage = 'named_company';
        }
        if (!$acl->can($issuer, $actor, 'invoices', 'view') || !$acl->can($issuer, $actor, 'invoices', $capability)
            || $acl->primaryId() !== $primary || $read() !== $rows) {
            throw new DomainException('company_invoice_access_denied');
        }
        // Not a durable grant: a mutating writer must recheck row lineage under its transaction.
        return ['invoice_id'=>$invoice, 'issuer_id'=>$issuer, 'stored_issuer_id'=>$row['issuer_id'] === null ? null : $issuer, 'lineage'=>$lineage];
    }
}
