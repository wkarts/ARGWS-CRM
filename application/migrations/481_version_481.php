<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Preserve timer provenance while allowing the existing standalone source identifier. */
class Migration_Version_481 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/NativeCompanyBlankTimerSchema.php';
        NativeCompanyBlankTimerSchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        // Shrinking the provenance field would truncate existing standalone references.
        throw new RuntimeException('native_blank_timer_downgrade_requires_review');
    }
}
