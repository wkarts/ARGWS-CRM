<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$CI->db->table_exists(db_prefix() . 'ib_templates')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() .'ib_templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `note` TEXT NULL,
  `content` LONGTEXT NULL,
  `active` INT(1) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `created_by` INT NULL,
  PRIMARY KEY (`id`));');
}

if (!$CI->db->field_exists('file_name' ,db_prefix() . 'ib_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ib_templates`
    ADD COLUMN `file_name` VARCHAR(255)');
}

if (!$CI->db->field_exists('default_template' ,db_prefix() . 'ib_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ib_templates`
    ADD COLUMN `default_template` INT(1)');
}

if (!$CI->db->table_exists(db_prefix() . 'ib_customfield')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() .'ib_customfield` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `label` TEXT NULL,
  `formula` TEXT NULL,
  `slug` TEXT NULL,
  `type` VARCHAR(55) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `created_by` INT NULL,
  PRIMARY KEY (`id`));');
}

if (!$CI->db->table_exists(db_prefix() . 'ib_customfield_value')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() .'ib_customfield_value` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `customfield_id` INT(11) NOT NULL,
  `template_id` INT(11) NULL,
  `value` TEXT NULL,
  PRIMARY KEY (`id`));');
}
if (!$CI->db->field_exists('setting' ,db_prefix() . 'ib_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ib_templates`
    ADD COLUMN `setting` text');
}


if (!$CI->db->table_exists(db_prefix() . 'ib_invoices_built')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() .'ib_invoices_built` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) NULL,
  `template_id` INT(11) NULL,
  `hash` VARCHAR(32) NULL,
  `status` VARCHAR(55) NULL,
  `created_by` INT(11) NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`));');
  }

  if (!$CI->db->field_exists('description' ,db_prefix() . 'ib_invoices_built')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ib_invoices_built`
    ADD COLUMN `description` text');
}

if (!$CI->db->field_exists('value' ,db_prefix() . 'ib_customfield')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ib_customfield`
    ADD COLUMN `value` text');
}

create_email_template('Invoice Builder', '<span style=\"font-size: 12pt;\"> Hello !. </span><br /><br /><span style=\"font-size: 12pt;\"> We would like to share with you a link of Invoice information with the number {invoice_number} </span><br /><br /><span style=\"font-size: 12pt;\"><br />Please click on the link to view information: {public_link}
  </span><br /><br />{additional_content}', 'invoice_builder', 'Invoice (Sent to contact)', 'invoice-to-contact');

if (!$CI->db->field_exists('capture_image' ,db_prefix() . 'ib_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ib_templates`
    ADD COLUMN `capture_image` longtext');
}
if (!$CI->db->field_exists('capture_html' ,db_prefix() . 'ib_templates')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ib_templates`
    ADD COLUMN `capture_html` longtext');
}

if (!$CI->db->field_exists('updated_data' ,db_prefix() . 'ib_invoices_built')) {
    $CI->db->query('ALTER TABLE `' . db_prefix() . 'ib_invoices_built`
    ADD COLUMN `updated_data` text NULL');
}


insert_default_template();


add_option('show_invoice_builder_menu_instead_of_curremt_invoice_menu', 0);