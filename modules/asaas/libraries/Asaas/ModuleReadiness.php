<?php

declare(strict_types=1);
namespace Asaas;
require_once (defined('APPPATH') ? APPPATH : dirname(__DIR__,4).'/application/').'services/issuers/IssuerStorage.php';

/** Native version readback, separate from additive schema presence. Never gates legacy ingress. */
final class ModuleReadiness
{
    public static function ready154($db, string $prefix): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) return false;
        try {
            $row = \IssuerStorage::query($db, 'SELECT installed_version FROM '.$prefix.'modules WHERE module_name=?', ['asaas'])->row_array();
            $version = is_array($row) ? ($row['installed_version'] ?? null) : null;
            return is_string($version) && preg_match('/^[0-9]\.[0-9]\.[0-9]$/D', $version)
                && version_compare($version, '1.5.4', '>=');
        } catch (\Throwable $error) { return false; }
    }

    public static function require154($db, string $prefix): void
    {
        if (!self::ready154($db, $prefix)) throw new \DomainException('asaas_module_154_required');
    }
}
