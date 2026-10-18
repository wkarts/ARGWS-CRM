<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH . 'services/issuers/IssuerCore.php';

function issuer_core_ready(): bool
{
    $CI = &get_instance();
    return IssuerCore::ready($CI->db, db_prefix());
}

function issuers_can_access($issuerId, $staffId, string $capability = 'view'): bool
{
    if (!issuer_core_ready()) {
        return false;
    }
    $CI = &get_instance();
    $CI->load->model('issuers_model');
    return $CI->issuers_model->can_access($issuerId, $staffId, $capability);
}

function issuers_default_for_staff($staffId): ?int
{
    if (!issuer_core_ready()) {
        return null;
    }
    $CI = &get_instance();
    $CI->load->model('issuers_model');
    return $CI->issuers_model->default_for_staff($staffId);
}

function issuers_assert_operational_scope($issuerId): void
{
    require_once APPPATH . 'services/issuers/IssuerPolicy.php';
    IssuerPolicy::assertOperationalScope($issuerId);
}

require_once __DIR__ . '/staff_access_helper.php';

/** Explicit resource permission; no global administrator or settings fallback. */
function issuer_resource_can($issuerId, $staffId, string $feature, string $capability): bool
{
    if (!issuer_core_ready()) { return false; }
    require_once APPPATH . 'services/issuers/IssuerResourceAccess.php';
    $CI = &get_instance();
    try { return (new IssuerResourceAccess($CI->db, db_prefix()))->can($issuerId, $staffId, $feature, $capability); }
    catch (Throwable $error) { log_message('error', 'Issuer resource ACL unavailable; access denied.'); return false; }
}

/** Explicit document capability; the financial rollout guard remains independent of grants. */
function issuers_invoice_documents(): InvoiceIssuerDocuments
{
    require_once APPPATH . 'services/issuers/InvoiceIssuerDocuments.php';
    $CI = &get_instance();
    return new InvoiceIssuerDocuments($CI->db, db_prefix(), get_staff_user_id(), static function ($issuer, $staff, $capability): bool {
        issuers_assert_operational_scope($issuer);
        $map = ['create_invoices' => 'create', 'render_invoices' => 'view'];
        return isset($map[$capability]) && issuer_resource_can($issuer, $staff, 'invoices', $map[$capability]);
    });
}
