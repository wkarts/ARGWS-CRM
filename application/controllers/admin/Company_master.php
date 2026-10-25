<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Legacy installation responsibility is resolved inside the authenticated CRM. */
class Company_master extends AdminController
{
    public function index()
    {
        require_once APPPATH.'services/issuers/IssuerResourceAccess.php';
        $actor = get_staff_user_id();
        $access = new IssuerResourceAccess($this->db, db_prefix());
        $primary = $access->primaryId();
        if ($primary === null || !$access->administrative($primary,$actor)) { access_denied('issuers'); return; }
        $this->output->set_header('Cache-Control: no-store');
        if (!in_array($this->input->method(true), ['GET','HEAD'], true)) {
            show_error('Este assistente foi retirado. Nenhuma responsabilidade foi alterada.', 410);
            return;
        }
        $this->load->view('admin/issuers/master_resolution', [
            'title'=>'Responsável da instalação',
            'unresolved'=>!$access->isMaster($actor),
        ]);
    }
}
