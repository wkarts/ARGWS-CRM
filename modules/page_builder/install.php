<?php defined('BASEPATH') or exit('No direct script access allowed');

add_option('page_builder_options', '');

$CI = &get_instance();

// Create table for storing page information
$table = db_prefix() . PAGE_BUILDER_MODULE_NAME . '_pages';
if (!$CI->db->table_exists($table)) {
    $CI->db->query(
        "CREATE TABLE IF NOT EXISTS `" . $table . "` (
            `id` int NOT NULL AUTO_INCREMENT,
            `hash` varchar(255) DEFAULT NULL,
            `file` text DEFAULT NULL,
            `metadata` longtext COMMENT 'Extra data',
            PRIMARY KEY (`id`),
            UNIQUE KEY `hash` (`hash`)
          ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";"
    );
}

$settings = page_builder_get_settings();
foreach ($settings as $key => $value) {
    if (strlen($key) == 32 && (!is_object($value) || is_array($value))) {
        if ($CI->db->insert($table, ['file' => '', 'hash' => $key, 'metadata' => json_encode($value)]))
            unset($settings[$key]);
    }
}
update_option('page_builder_options', json_encode($settings));

// Migrate from saas
page_builder_migrate_from_saas();