<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_108 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $p = db_prefix();
        $conversations = $p . 'connect_api_chat_conversations';
        $messages = $p . 'connect_api_chat_messages';
        $assignments = $p . 'connect_api_chat_assignments';
        $tickets = $p . 'connect_api_chat_tickets';

        if (!$CI->db->table_exists($tickets)) {
            $CI->db->query("CREATE TABLE `{$tickets}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `instance_name` VARCHAR(191) NOT NULL,
                `conversation_id` BIGINT UNSIGNED NOT NULL,
                `ticket_number` INT UNSIGNED NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'open',
                `opened_via` VARCHAR(32) NOT NULL DEFAULT 'migration',
                `opened_by_staff_id` INT NULL,
                `opened_at` DATETIME NOT NULL,
                `closed_at` DATETIME NULL,
                `closed_by_staff_id` INT NULL,
                `is_new` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_connect_chat_ticket_number` (`conversation_id`,`ticket_number`),
                KEY `idx_connect_chat_ticket_status` (`instance_name`,`status`,`opened_at`),
                KEY `idx_connect_chat_ticket_conversation` (`conversation_id`,`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        if ($CI->db->table_exists($conversations)) {
            if (!$CI->db->field_exists('current_ticket_id', $conversations)) {
                $CI->db->query("ALTER TABLE `{$conversations}` ADD `current_ticket_id` BIGINT UNSIGNED NULL AFTER `status`");
            }
            if (!$CI->db->field_exists('ticket_counter', $conversations)) {
                $CI->db->query("ALTER TABLE `{$conversations}` ADD `ticket_counter` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `current_ticket_id`");
            }
            if (!$CI->db->field_exists('new_ticket_flag', $conversations)) {
                $CI->db->query("ALTER TABLE `{$conversations}` ADD `new_ticket_flag` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ticket_counter`");
            }
            $idx = $CI->db->query("SHOW INDEX FROM `{$conversations}` WHERE Key_name = 'idx_connect_chat_current_ticket'")->row_array();
            if (!$idx) {
                $CI->db->query("ALTER TABLE `{$conversations}` ADD KEY `idx_connect_chat_current_ticket` (`current_ticket_id`)");
            }
        }

        if ($CI->db->table_exists($messages) && !$CI->db->field_exists('ticket_id', $messages)) {
            $CI->db->query("ALTER TABLE `{$messages}` ADD `ticket_id` BIGINT UNSIGNED NULL AFTER `conversation_id`, ADD KEY `idx_connect_chat_message_ticket` (`ticket_id`,`id`)");
        }
        if ($CI->db->table_exists($assignments) && !$CI->db->field_exists('ticket_id', $assignments)) {
            $CI->db->query("ALTER TABLE `{$assignments}` ADD `ticket_id` BIGINT UNSIGNED NULL AFTER `conversation_id`, ADD KEY `idx_connect_chat_assignment_ticket` (`ticket_id`,`created_at`)");
        }

        // Converte o estado legado em Ticket #1 sem destruir o histórico existente.
        if ($CI->db->table_exists($conversations) && $CI->db->table_exists($tickets)) {
            $rows = $CI->db->get($conversations)->result_array();
            foreach ($rows as $c) {
                $cid = (int)$c['id'];
                if (!$cid) continue;
                $ticket = $CI->db->where('conversation_id', $cid)->order_by('ticket_number', 'ASC')->limit(1)->get($tickets)->row_array();
                if (!$ticket) {
                    $openedAt = !empty($c['created_at']) ? $c['created_at'] : date('Y-m-d H:i:s');
                    $status = (($c['status'] ?? 'open') === 'closed') ? 'closed' : 'open';
                    $CI->db->insert($tickets, [
                        'instance_name' => (string)$c['instance_name'],
                        'conversation_id' => $cid,
                        'ticket_number' => 1,
                        'status' => $status,
                        'opened_via' => 'migration',
                        'opened_by_staff_id' => $c['created_by_staff_id'] ?: null,
                        'opened_at' => $openedAt,
                        'closed_at' => $status === 'closed' ? ($c['closed_at'] ?: $c['updated_at']) : null,
                        'closed_by_staff_id' => $status === 'closed' ? ($c['closed_by_staff_id'] ?: null) : null,
                        'is_new' => 0,
                        'created_at' => $openedAt,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $ticketId = (int)$CI->db->insert_id();
                } else {
                    $ticketId = (int)$ticket['id'];
                }
                $CI->db->where('id', $cid)->update($conversations, ['current_ticket_id'=>$ticketId,'ticket_counter'=>max(1,(int)($c['ticket_counter']??0))]);
                if ($CI->db->field_exists('ticket_id', $messages)) $CI->db->where('conversation_id',$cid)->where('ticket_id IS NULL',null,false)->update($messages,['ticket_id'=>$ticketId]);
                if ($CI->db->field_exists('ticket_id', $assignments)) $CI->db->where('conversation_id',$cid)->where('ticket_id IS NULL',null,false)->update($assignments,['ticket_id'=>$ticketId]);
            }
        }
    }
}
