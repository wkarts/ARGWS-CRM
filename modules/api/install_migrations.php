<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$target = (string) $CI->app_modules->get('api')['headers']['version'];
$row = $CI->db->where('module_name', 'api')->get(db_prefix() . 'modules')->row();
if (!$row || $target !== '2.0.2'
    || !in_array((string) $row->installed_version, ['2.0.1', '2.0.2'], true)) {
    throw new RuntimeException('api_activation_version_invalid');
}
if (!$CI->db->table_exists(db_prefix() . 'user_api')
    || !$CI->db->field_exists('permission_enable', db_prefix() . 'user_api')
    || !$CI->db->table_exists(db_prefix() . 'user_api_permissions')) {
    throw new RuntimeException('api_legacy_install_unconfirmed');
}
// The existing API installer has already established the 2.0.1 schema.
// Do not replay historical 201 DDL or invent files for the 104-199 gap.
// App_modules prewrites the header on first activation. Rewind only this new
// NOOP so the native executor must execute it instead of trusting that marker.
if (!$CI->db->where('module_name', 'api')->update(db_prefix() . 'modules', ['installed_version' => '2.0.1'])) {
    throw new RuntimeException('api_activation_version_write_failed');
}
$row = $CI->db->where('module_name', 'api')->get(db_prefix() . 'modules')->row();
if (!$row || (string) $row->installed_version !== '2.0.1') {
    throw new RuntimeException('api_activation_baseline_unconfirmed');
}
$migration = new App_module_migration('api');
if ($migration->to_latest() === false) {
    throw new RuntimeException('api_migration_failed');
}
$row = $CI->db->where('module_name', 'api')->get(db_prefix() . 'modules')->row();
if (!$row || (string) $row->installed_version !== $target) {
    throw new RuntimeException('api_migration_version_unconfirmed');
}
