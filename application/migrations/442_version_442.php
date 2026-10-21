<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_442 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH.'services/CompanyExpenseCopiesSchema.php';
        CompanyExpenseCopiesSchema::ensure($this->db, db_prefix());
        require_once APPPATH.'services/CompanyAiSchema.php';
        CompanyAiSchema::ensure($this->db, db_prefix());
    }

    public function down(): void
    {
        // Preserve reviewed expense-copy provenance and private AI request evidence.
    }
}
