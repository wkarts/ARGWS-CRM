<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_103 extends App_module_migration
{
	public function up()
	{  
		$CI   = & get_instance();
		if(!$CI->db->table_exists(db_prefix() . 'si_custom_theme_staff')) {
			$CI->db->query("CREATE TABLE `" . db_prefix() . "si_custom_theme_staff` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`theme_id` int(11) NOT NULL DEFAULT '1',
			`staff_id` int(11) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
		}
		if(!$CI->db->table_exists(db_prefix() . 'si_custom_theme_client')) {
			$CI->db->query("CREATE TABLE `" . db_prefix() . "si_custom_theme_client` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`theme_id` int(11) NOT NULL DEFAULT '1',
			`contact_id` int(11) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
		}	
		add_option(SI_CUSTOM_THEME_MODULE_NAME.'_customer_login_header', 1); 
		add_option(SI_CUSTOM_THEME_MODULE_NAME.'_customer_login_footer', 1);
		add_option(SI_CUSTOM_THEME_MODULE_NAME.'_enable_staff_theme', 1);
		add_option(SI_CUSTOM_THEME_MODULE_NAME.'_enable_client_theme', 1); 
	}
}