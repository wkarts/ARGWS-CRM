<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Resolve ownership from storage, never a current-company selector or caller-supplied object.
 * This helper is core-loaded so disabling the optional module cannot restore global fallbacks.
 */
function invoice_issuer_snapshot($invoice): ?array
{
    $CI = &get_instance();
    $id = is_object($invoice) ? ($invoice->id ?? null) : $invoice;
    if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $id)) {
        throw new DomainException('issuer_document_invalid_invoice');
    }
    // SELECT * also works before the additive issuer_id migration (legacy-only installation).
    $result = $CI->db->query('SELECT * FROM ' . db_prefix() . 'invoices WHERE id=?', [$id]);
    if ($result === false) { throw new RuntimeException('issuer_document_storage_failed'); }
    $record = $result->result_array()[0] ?? null;
    if (!$record) { throw new DomainException('issuer_document_invoice_unavailable'); }
    $issuer = $record['issuer_id'] ?? null;
    if (is_object($invoice) && property_exists($invoice, 'issuer_id')
        && ($invoice->issuer_id === null ? null : (string) $invoice->issuer_id) !== ($issuer === null ? null : (string) $issuer)) {
        throw new DomainException('issuer_document_scope_mismatch');
    }
    if ($issuer === null) { return null; }
    if (!function_exists('issuer_core_ready') || !issuer_core_ready() || !function_exists('issuers_invoice_documents')) {
        throw new DomainException('issuer_document_core_required');
    }
    // Enforced even for already allocated snapshots, cron, public links and administrators.
    issuers_assert_operational_scope($issuer);
    return issuers_invoice_documents()->get($id, $issuer);
}

/** Positive documents are routed exclusively through the dedicated authorized DTO renderer. */
function invoice_issuer_assert_legacy_pdf_scope(?array $snapshot): void
{
    if ($snapshot !== null) { throw new DomainException('issuer_document_legacy_pdf_blocked'); }
}

function invoice_issuer_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Explicit snapshot argument prevents ambient/global invoice context from leaking across PDFs. */
function invoice_pdf_issuer_organization(?array $snapshot): string
{
    if ($snapshot === null) { return format_organization_info(); }
    $identity = $snapshot['identity'];
    $lines = ['<b>' . invoice_issuer_html($identity['legal_name']) . '</b>'];
    foreach (['tax_id', 'address', 'city', 'state', 'postcode', 'country_code', 'phone', 'email'] as $field) {
        if ($identity[$field] !== '') { $lines[] = invoice_issuer_html($identity[$field]); }
    }
    return implode('<br />', $lines);
}

function invoice_pdf_issuer_logo(?array $snapshot): string
{
    if ($snapshot === null) { return pdf_logo_url(); }
    // The stored bytes were revalidated on read. Width is issuer-document-local, never a global option.
    $logo = $snapshot['identity']['logo_inline'];
    return $logo === '' ? '' : '<img width="120px" src="' . invoice_issuer_html($logo) . '">';
}

function invoice_pdf_issuer_terms(?array $snapshot, $legacyTerms): string
{
    return $snapshot === null ? (string) $legacyTerms : nl2br(invoice_issuer_html($snapshot['identity']['terms']));
}
