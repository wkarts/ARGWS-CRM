<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_381 extends CI_Migration
{
    public function up(): void
    {
        // Existing contracts keep their original elapsed-day terms.
        if (!$this->db->field_exists('recurring_due_mode', db_prefix() . 'invoices')) {
            $this->db->query('ALTER TABLE `' . db_prefix() . 'invoices` ADD `recurring_due_mode` VARCHAR(16) NOT NULL DEFAULT \'days\'');
        }
    }

    public function down(): void
    {
        // Deliberately keep the additive column and users' choices on code rollback.
        // Older code ignores it; up() remains safe when this migration is reapplied.
    }
}
