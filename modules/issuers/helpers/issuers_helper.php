<?php

defined('BASEPATH') or exit('No direct script access allowed');

function issuers_can_access($issuerId, $staffId, string $capability = 'view'): bool
{
    if (!defined('ISSUERS_MODULE_NAME')) {
        return false;
    }
    $CI = &get_instance();
    $CI->load->model('issuers/issuers_model');
    return $CI->issuers_model->can_access($issuerId, $staffId, $capability);
}

function issuers_default_for_staff($staffId): ?int
{
    if (!defined('ISSUERS_MODULE_NAME')) {
        return null;
    }
    $CI = &get_instance();
    $CI->load->model('issuers/issuers_model');
    return $CI->issuers_model->default_for_staff($staffId);
}

function issuers_assert_operational_scope($issuerId): void
{
    require_once __DIR__ . '/../services/IssuerPolicy.php';
    IssuerPolicy::assertOperationalScope($issuerId);
}
