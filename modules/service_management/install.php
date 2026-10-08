<?php
defined('BASEPATH') or exit('No direct script access allowed');


if (!$CI->db->field_exists("commodity_group_code" ,db_prefix() . "items_groups")) { 
	$CI->db->query("ALTER TABLE `" . db_prefix() . "items_groups`
		ADD COLUMN `commodity_group_code` varchar(100) NULL,
		ADD COLUMN `order` int(10) NULL,
		ADD COLUMN `display` int(1)  NULL,
		ADD COLUMN `note` text NULL
		;");
}

if (!$CI->db->table_exists(db_prefix() . "sm_units")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_units` (
		`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		`unit_code` varchar(100) NULL,
		`unit_name` text NULL,
		`unit_value` INT(11) NULL,
		`unit_type` VARCHAR(50) NULL,
		`order` int(10) NULL,
		`display` int(1) NULL COMMENT  'display 1: display (yes)  0: not displayed (no)',
		`note` text NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->table_exists(db_prefix() . "sm_item_status")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_item_status` (
		`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		`status_code` varchar(100) NULL,
		`status_name` text NULL,
		`display` int(1) NULL COMMENT  'display 1: display (yes)  0: not displayed (no)',
		`note` text NULL,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

add_option("service_management_display_on_portal", 1, 1);

if (!$CI->db->field_exists("can_be_product_service" ,db_prefix() . "items")) { 
	$CI->db->query("ALTER TABLE `" . db_prefix() . "items`
		ADD COLUMN `can_be_product_service` VARCHAR(100) NULL
		;");
}

if (!$CI->db->field_exists("allow_extension_service" ,db_prefix() . "items")) { 
	$CI->db->query("ALTER TABLE `" . db_prefix() . "items`
		ADD COLUMN `allow_extension_service` VARCHAR(100) NULL COMMENT 'allow or reject'
		;");
}

if (!$CI->db->field_exists('service_policy' ,db_prefix() . 'items')) { 
  $CI->db->query('ALTER TABLE `' . db_prefix() . "items`
  ADD COLUMN `service_policy` TEXT NULL 
 ;");
}

if (!$CI->db->table_exists(db_prefix() . "sm_items_cycles")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_items_cycles` (
		`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		`item_id` INT(11) NOT NULL,
		`unit_id` INT(11) NULL,
		`unit_value` INT(11) NULL,
		`unit_type` VARCHAR(50) NULL,
		`item_rate` DECIMAL(15,2) NULL DEFAULT '0.00',
		`extend_value` INT(11) NULL DEFAULT '0',
		`promotion_extended_percent` DECIMAL(15,2) NULL DEFAULT '0.00',
		`status_cycles` text NULL COMMENT 'active or inactive',

		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->table_exists(db_prefix() . "sm_orders")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_orders` (
		`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		`order_code` VARCHAR(200) NULL,
		`client_id` INT(11) NULL,
		`created_id` int(11) NULL,
		`created_type` VARCHAR(50) NULL DEFAULT 'staff',
		`datecreated` datetime NULL,
		`client_note` text NULL,
		`admin_note` text NULL,
		`description` text NULL,
		`status` VARCHAR(100) NULL,
		`sub_total` DECIMAL(15,2) NULL DEFAULT '0.00',
		`total_tax` DECIMAL(15,2) NULL DEFAULT '0.00',
		`total` DECIMAL(15,2) NULL DEFAULT '0.00',
		`discount_percent` DECIMAL(15,2) NULL DEFAULT '0.00',
		`discount_type` VARCHAR(20) NULL,
		`discount_total` DECIMAL(15,2) NULL DEFAULT '0.00',
		`billing_street` text DEFAULT NULL,
		`billing_city` text DEFAULT NULL,
		`billing_state` text DEFAULT NULL,
		`billing_zip` text DEFAULT NULL,
		`billing_country` int(11) DEFAULT NULL,
		`shipping_street` text DEFAULT NULL,
		`shipping_city` text DEFAULT NULL,
		`shipping_state` text DEFAULT NULL,
		`shipping_zip` text DEFAULT NULL,
		`shipping_country` int(11) DEFAULT NULL,

		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}


if (!$CI->db->table_exists(db_prefix() . "sm_order_details")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_order_details` (
		`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		`order_id` int(11) NOT NULL,
		`item_id` int(11) NULL,
		`item_name` text NULL,
		`billing_plan_unit_id` int(11) NULL,
		`billing_plan_value` int(11) NULL,
		`billing_plan_type` TEXT NULL,
		`billing_plan_rate` DECIMAL(15,2) NULL DEFAULT '0.00',
		`quantity` DECIMAL(15,2) NULL,
		
		`discount` DECIMAL(15,2) NULL,
		`discount_money` DECIMAL(15,2) NULL,
		`total_after_discount` DECIMAL(15,2),
		`tax_id` TEXT NULL,
		`tax_rate` TEXT NULL,
		`tax_name` TEXT NULL,
		`sub_total` DECIMAL(15,2) NULL DEFAULT '0',
		`total_money` DECIMAL(15,2) NULL DEFAULT '0.00',

		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->table_exists(db_prefix() . "sm_contracts")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_contracts` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`content` longtext,
		`description` text,
		`subject` varchar(191) DEFAULT NULL,
		`client` int(11) NOT NULL,
		`datestart` date DEFAULT NULL,
		`dateend` date DEFAULT NULL,
		`contract_type` int(11) DEFAULT NULL,
		`project_id` int(11) DEFAULT NULL,
		`addedfrom` int(11) NOT NULL,
		`dateadded` datetime NOT NULL,
		`isexpirynotified` int(11) NOT NULL DEFAULT '0',
		`contract_value` decimal(15,2) DEFAULT NULL,
		`trash` tinyint(1) DEFAULT '0',
		`not_visible_to_client` tinyint(1) NOT NULL DEFAULT '0',
		`hash` varchar(32) DEFAULT NULL,
		`signed` tinyint(1) NOT NULL DEFAULT '0',
		`signature` varchar(40) DEFAULT NULL,
		`marked_as_signed` tinyint(1) NOT NULL DEFAULT '0',
		`acceptance_firstname` varchar(50) DEFAULT NULL,
		`acceptance_lastname` varchar(50) DEFAULT NULL,
		`acceptance_email` varchar(100) DEFAULT NULL,
		`acceptance_date` datetime DEFAULT NULL,
		`acceptance_ip` varchar(40) DEFAULT NULL,
		`short_link` varchar(100) DEFAULT NULL,

		PRIMARY KEY (`id`),
		KEY `client` (`client`),
		KEY `contract_type` (`contract_type`)
		
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->table_exists(db_prefix() . "sm_contract_comments")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_contract_comments` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`content` mediumtext,
		`contract_id` int(11) NOT NULL,
		`staffid` int(11) NOT NULL,
		`dateadded` datetime NOT NULL,
		PRIMARY KEY (`id`)
		
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->table_exists(db_prefix() . "sm_contract_renewals")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_contract_renewals` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`contractid` int(11) NOT NULL,
		`old_start_date` date NOT NULL,
		`new_start_date` date NOT NULL,
		`old_end_date` date DEFAULT NULL,
		`new_end_date` date DEFAULT NULL,
		`old_value` decimal(15,2) DEFAULT NULL,
		`new_value` decimal(15,2) DEFAULT NULL,
		`date_renewed` datetime NOT NULL,
		`renewed_by` varchar(100) NOT NULL,
		`renewed_by_staff_id` int(11) NOT NULL DEFAULT '0',
		`is_on_old_expiry_notified` int(11) DEFAULT '0',
		PRIMARY KEY (`id`)
		
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->table_exists(db_prefix() . "sm_contracts_types")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_contracts_types` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`name` mediumtext NOT NULL,
		PRIMARY KEY (`id`)
		
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->field_exists('order_id' ,db_prefix() . 'sm_contracts')) { 
  $CI->db->query('ALTER TABLE `' . db_prefix() . "sm_contracts`
  ADD COLUMN `order_id` INT(11) NULL DEFAULT '0'
 ;");
}
if (!$CI->db->field_exists('invoice_id' ,db_prefix() . 'sm_orders')) { 
  $CI->db->query('ALTER TABLE `' . db_prefix() . "sm_orders`
  ADD COLUMN `invoice_id` INT(11) NULL DEFAULT '0'
 ;");
}

if (!$CI->db->table_exists(db_prefix() . "sm_service_details")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_service_details` (
		`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		`order_id` int(11) NOT NULL,
		`invoice_id` int(11) NULL,
		`client_id` int(11) NULL,
		`item_id` int(11) NULL,
		`item_name` text NULL,
		`billing_plan_unit_id` int(11) NULL,
		`billing_plan_value` int(11) NULL,
		`billing_plan_type` TEXT NULL,
		`billing_plan_rate` DECIMAL(15,2) NULL DEFAULT '0.00',
		`quantity` DECIMAL(15,2) NULL,
		`discount` DECIMAL(15,2) NULL,
		`discount_money` DECIMAL(15,2) NULL,
		`total_after_discount` DECIMAL(15,2),
		`tax_id` TEXT NULL,
		`tax_rate` TEXT NULL,
		`tax_name` TEXT NULL,
		`sub_total` DECIMAL(15,2) NULL DEFAULT '0',
		`total_money` DECIMAL(15,2) NULL DEFAULT '0.00',
		`start_date` DATETIME NULL,
		`expiration_date` DATETIME NULL,
		`status` TEXT NULL,
		`datecreated` DATETIME NULL,

		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->field_exists('client_id' ,db_prefix() . 'sm_service_details')) { 
  $CI->db->query('ALTER TABLE `' . db_prefix() . "sm_service_details`
  ADD COLUMN `client_id` INT(11) NULL
 ;");
}

if (!$CI->db->table_exists(db_prefix() . "sm_service_invoices")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_service_invoices` (
		`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		`old_service_id` INT(11) NOT NULL,
		`renewal_invoice_id` INT(11) NULL,
		`order_id` INT(11) NULL,
		`client_id` INT(11) NULL,
		`datecreated` DATETIME NULL,

		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->table_exists(db_prefix() . "sm_contract_addendums")) {
	$CI->db->query("CREATE TABLE `" . db_prefix() . "sm_contract_addendums` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`contract_id` int(11) NOT NULL,
		`subject` varchar(191) DEFAULT NULL,
		`content` longtext,
		`description` text,
		`datestart` date DEFAULT NULL,
		`addedfrom` int(11) NOT NULL,
		`dateadded` datetime NOT NULL,
		`isexpirynotified` int(11) NOT NULL DEFAULT '0',
		`trash` tinyint(1) DEFAULT '0',
		`not_visible_to_client` tinyint(1) NOT NULL DEFAULT '0',
		`hash` varchar(32) DEFAULT NULL,
		`signed` tinyint(1) NOT NULL DEFAULT '0',
		`signature` varchar(40) DEFAULT NULL,
		`marked_as_signed` tinyint(1) NOT NULL DEFAULT '0',
		`acceptance_firstname` varchar(50) DEFAULT NULL,
		`acceptance_lastname` varchar(50) DEFAULT NULL,
		`acceptance_email` varchar(100) DEFAULT NULL,
		`acceptance_date` datetime DEFAULT NULL,
		`acceptance_ip` varchar(40) DEFAULT NULL,
		`short_link` varchar(100) DEFAULT NULL,

		PRIMARY KEY (`id`)
		
	) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ";");
}

if (!$CI->db->field_exists("stripe_plan_id" ,db_prefix() . "items")) { 
	$CI->db->query("ALTER TABLE `" . db_prefix() . "items`
		ADD COLUMN `stripe_plan_id` TEXT NULL
		;");
}

if (!$CI->db->field_exists("service_type" ,db_prefix() . "items")) { 
	$CI->db->query("ALTER TABLE `" . db_prefix() . "items`
		ADD COLUMN `service_type` VARCHAR(20) NULL DEFAULT 'normal'
		;");
}

if (!$CI->db->field_exists("subscription_id" ,db_prefix() . "sm_orders")) { 
	$CI->db->query("ALTER TABLE `" . db_prefix() . "sm_orders`
		ADD COLUMN `subscription_id` INT(11) NULL
		;");
}

if (!$CI->db->field_exists("product_id" ,db_prefix() . "sm_orders")) { 
	$CI->db->query("ALTER TABLE `" . db_prefix() . "sm_orders`
		ADD COLUMN `product_id` INT(11) NULL
		;");
}

if (!$CI->db->field_exists("subscription_price" ,db_prefix() . "items")) { 
	$CI->db->query("ALTER TABLE `" . db_prefix() . "items`
		ADD COLUMN `subscription_price` DECIMAL(15,2) NULL,
		ADD COLUMN `subscription_period` VARCHAR(255) NULL,
		ADD COLUMN `subscription_count` INT NULL
		;");
}

if (!$CI->db->field_exists('active' ,db_prefix() . 'items')) { 
	$CI->db->query('ALTER TABLE `' . db_prefix() . "items`
		ADD COLUMN `active` INT(11) NULL DEFAULT 1
		;");
}