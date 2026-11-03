<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Add native milestone colors without changing or backfilling legacy milestones. */
class Migration_Version_482 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyMilestoneVisualSchema.php';
        CompanyMilestoneVisualSchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        throw new RuntimeException('native_milestone_visual_downgrade_requires_review');
    }
}
