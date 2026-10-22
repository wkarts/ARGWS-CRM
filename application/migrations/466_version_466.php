<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_466 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH . 'services/StaffEmailIdentitySchema.php';
        StaffEmailIdentitySchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve the uniqueness constraint and all staff identities on application rollback.
        // Removing this protection requires a separately reviewed maintenance plan.
    }
}
