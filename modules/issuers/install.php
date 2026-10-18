<?php

defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();
$target = (string) $CI->app_modules->get('issuers')['headers']['version'];
$row = $CI->db->where('module_name', 'issuers')->get(db_prefix() . 'modules')->row();
if (!$row || version_compare((string) $row->installed_version, $target, '>') || version_compare($target, '1.0.1', '<')) {
    throw new RuntimeException('issuer_activation_version_invalid');
}
// App_modules::activate stores the header version before invoking this hook.
// Replay additive module migrations from the pre-migration baseline, including
// on reactivation. Native upgrades call App_module_migration directly.
if (!$CI->db->where('module_name', 'issuers')->update(db_prefix() . 'modules', ['installed_version' => '1.0.0'])) {
    throw new RuntimeException('issuer_activation_version_write_failed');
}
$migration = new App_module_migration('issuers');
if ($migration->to_latest() === false) {
    throw new RuntimeException('issuer_migration_failed');
}
$row = $CI->db->where('module_name', 'issuers')->get(db_prefix() . 'modules')->row();
if (!$row || $row->installed_version !== $target) {
    throw new RuntimeException('issuer_migration_version_unconfirmed');
}
