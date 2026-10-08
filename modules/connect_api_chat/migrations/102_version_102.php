<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();

        $contacts = $p . 'connect_api_chat_contacts';
        if ($CI->db->table_exists($contacts)) {
            if (!$CI->db->field_exists('remote_jid_alt', $contacts)) {
                $CI->db->query("ALTER TABLE `{$contacts}` ADD `remote_jid_alt` VARCHAR(191) NULL AFTER `remote_jid`");
            }
            if (!$CI->db->field_exists('identity_key', $contacts)) {
                $CI->db->query("ALTER TABLE `{$contacts}` ADD `identity_key` VARCHAR(191) NULL AFTER `remote_jid_alt`");
            }
            $this->ensureIndex($CI, $contacts, 'idx_connect_chat_contact_identity', ['instance_name','identity_key']);
            $this->ensureIndex($CI, $contacts, 'idx_connect_chat_contact_alt', ['instance_name','remote_jid_alt']);
        }

        $conversations = $p . 'connect_api_chat_conversations';
        if ($CI->db->table_exists($conversations)) {
            if (!$CI->db->field_exists('remote_jid_alt', $conversations)) {
                $CI->db->query("ALTER TABLE `{$conversations}` ADD `remote_jid_alt` VARCHAR(191) NULL AFTER `remote_jid`");
            }
            if (!$CI->db->field_exists('identity_key', $conversations)) {
                $CI->db->query("ALTER TABLE `{$conversations}` ADD `identity_key` VARCHAR(191) NULL AFTER `remote_jid_alt`");
            }
            $this->ensureIndex($CI, $conversations, 'idx_connect_chat_conversation_identity', ['instance_name','identity_key']);
            $this->ensureIndex($CI, $conversations, 'idx_connect_chat_conversation_alt', ['instance_name','remote_jid_alt']);
        }

        $messages = $p . 'connect_api_chat_messages';
        if ($CI->db->table_exists($messages) && !$CI->db->field_exists('remote_jid_alt', $messages)) {
            $CI->db->query("ALTER TABLE `{$messages}` ADD `remote_jid_alt` VARCHAR(191) NULL AFTER `remote_jid`");
        }
    }

    private function ensureIndex($CI, $table, $name, array $columns)
    {
        $exists = $CI->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name=" . $CI->db->escape($name))->row_array();
        if ($exists) return;
        $cols = implode('`,`', array_map('trim', $columns));
        $CI->db->query("ALTER TABLE `{$table}` ADD KEY `{$name}` (`{$cols}`)");
    }
}
