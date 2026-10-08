<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Version_102 extends App_module_migration
{
	public function up()
	{  
		$CI = &get_instance();
		$CI->db->query('UPDATE `' . db_prefix() . 'si_custom_theme_list` SET default_style=\'[{"id":"admin-menu","color":"#ffffff"},{"id":"admin-menu-submenu-open","color":"#e4fbf4"},{"id":"admin-menu-links","color":"#252525"},{"id":"user-welcome-bg-color","color":"#ffffff"},{"id":"user-welcome-text-color","color":"#37bc9b"},{"id":"admin-menu-active-item","color":"#37bc9b"},{"id":"admin-menu-active-item-color","color":"#ffffff"},{"id":"admin-menu-active-subitem","color":"#bef1e2"},{"id":"admin-menu-submenu-links","color":"#2b957a"},{"id":"top-header","color":"#58ceb1"},{"id":"top-header-links","color":"#ffffff"},{"id":"customer-login-background","color":"#e2eeeb"},{"id":"customers-navigation","color":"#58ceb1"},{"id":"customers-footer-background","color":"#d2f3eb"},{"id":"customers-footer-text","color":"#3c3232"},{"id":"btn-info","color":"#37bc9b"},{"id":"tabs-links-active-hover","color":"#37bc9b"},{"id":"tabs-active-border","color":"#37bc9b"},{"id":"modal-heading","color":"#58ceb1"},{"id":"admin-login-background","color":"#85c3b3"},{"id":"admin-page-background","color":"#eef6f3"},{"id":"admin-panel-color","color":"#37bc9b"}]\' where id=9'); 
	}
}