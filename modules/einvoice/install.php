<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$target = (string) $CI->app_modules->get('einvoice')['headers']['version'];
$row = $CI->db->where('module_name', 'einvoice')->get(db_prefix() . 'modules')->row();
if (!$row || $target !== '1.0.1'
    || !in_array((string) $row->installed_version, ['1.0.0', '1.0.1'], true)) {
    throw new RuntimeException('einvoice_activation_version_invalid');
}
// The historical 1.0.0 release had no migrations. The only replayed step
// is the new code-only NOOP; preserve the existing activation side effects.
// App_modules prewrites the header on first activation. Rewind only this new
// NOOP so the native executor must execute it instead of trusting that marker.
if (!$CI->db->where('module_name', 'einvoice')->update(db_prefix() . 'modules', ['installed_version' => '1.0.0'])) {
    throw new RuntimeException('einvoice_activation_version_write_failed');
}
$row = $CI->db->where('module_name', 'einvoice')->get(db_prefix() . 'modules')->row();
if (!$row || (string) $row->installed_version !== '1.0.0') {
    throw new RuntimeException('einvoice_activation_baseline_unconfirmed');
}
$migration = new App_module_migration('einvoice');
if ($migration->to_latest() === false) {
    throw new RuntimeException('einvoice_migration_failed');
}
$row = $CI->db->where('module_name', 'einvoice')->get(db_prefix() . 'modules')->row();
if (!$row || (string) $row->installed_version !== $target) {
    throw new RuntimeException('einvoice_migration_version_unconfirmed');
}
