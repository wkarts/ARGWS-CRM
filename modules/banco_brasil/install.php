<?php
defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();
$target = (string) $CI->app_modules->get('banco_brasil')['headers']['version'];
$row = $CI->db->where('module_name','banco_brasil')->get(db_prefix().'modules')->row();
if (!$row || version_compare((string)$row->installed_version,$target,'>') || version_compare($target,'1.0.1','<')) {
    throw new RuntimeException('bb_activation_version_invalid');
}
// Native activation writes the header first; replay idempotent additive DDL from baseline.
if (!$CI->db->where('module_name','banco_brasil')->update(db_prefix().'modules',['installed_version'=>'1.0.0'])) {
    throw new RuntimeException('bb_activation_version_write_failed');
}
$migration = new App_module_migration('banco_brasil');
if ($migration->to_latest() === false) throw new RuntimeException('bb_migration_failed');
$row = $CI->db->where('module_name','banco_brasil')->get(db_prefix().'modules')->row();
if (!$row || $row->installed_version !== $target) throw new RuntimeException('bb_migration_version_unconfirmed');
