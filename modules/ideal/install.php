<?php

defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();
$target = (string) $CI->app_modules->get('ideal')['headers']['version'];
$row = $CI->db->where('module_name', 'ideal')->get(db_prefix() . 'modules')->row();
$current = $row ? (string) $row->installed_version : '';
if (!$row || !preg_match('/^[0-9]\.[0-9]\.[0-9]$/D', $current)
    || version_compare($current, '1.0.0', '<') || version_compare($current, $target, '>') || version_compare($target, '1.0.1', '<')) {
    throw new RuntimeException('ideal_activation_version_invalid');
}
// The core stores the header before activation. Replay the additive migration
// from this module's real 1.0.0 baseline only for that prewritten target.
// Earlier installed versions retain their own native upgrade path. No schema work here.
if ($current === $target && !$CI->db->where('module_name', 'ideal')->update(db_prefix() . 'modules', ['installed_version' => '1.0.0'])) {
    throw new RuntimeException('ideal_activation_version_write_failed');
}
$migration = new App_module_migration('ideal');
if ($migration->to_latest() === false) {
    throw new RuntimeException('ideal_migration_failed');
}
$row = $CI->db->where('module_name', 'ideal')->get(db_prefix() . 'modules')->row();
if (!$row || $row->installed_version !== $target) {
    throw new RuntimeException('ideal_migration_version_unconfirmed');
}
