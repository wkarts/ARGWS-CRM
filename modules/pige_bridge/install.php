<?php

defined('BASEPATH') or exit('No direct script access allowed');
$CI = &get_instance();
$headers=$CI->app_modules->get('pige_bridge')['headers']??[];$target=$headers['version']??null;
if(!is_string($target)||!preg_match('/^[0-9]\.[0-9]\.[0-9]$/D',$target)||version_compare($target,'1.0.2','<'))throw new RuntimeException('pige_bridge_activation_version_invalid');
// App_modules::activate inserts the header version BEFORE this hook. Reset only
// this module's activation baseline so the native runner actually executes 101.
$row = $CI->db->where('module_name', 'pige_bridge')->get(db_prefix() . 'modules')->row();
if (!$row || version_compare((string) $row->installed_version, $target, '>')) {
    throw new RuntimeException('pige_bridge_activation_version_invalid');
}
if (!$CI->db->where('module_name', 'pige_bridge')->update(db_prefix() . 'modules', ['installed_version' => '1.0.0'])) {
    throw new RuntimeException('pige_bridge_activation_version_write_failed');
}
$migration = new App_module_migration('pige_bridge');
if ($migration->to_latest() === false) {
    throw new RuntimeException('pige_bridge_migration_failed');
}
$row = $CI->db->where('module_name', 'pige_bridge')->get(db_prefix() . 'modules')->row();
if (!$row || $row->installed_version !== $target) {
    throw new RuntimeException('pige_bridge_migration_version_unconfirmed');
}
