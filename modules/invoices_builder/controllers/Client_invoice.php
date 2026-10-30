<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Client_invoice extends ClientsController
{
     /**
     * construct
     */
    public function __construct() {
        parent::__construct();
        $this->load->model('invoices_builder/invoices_builder_model');
    }

    /**
     * { index }
     *
     * @param      bool  $status  The status
     */
    public function index($status = false) {
        if (!has_contact_permission('invoices')) {
            set_alert('warning', _l('access_denied'));
            redirect(site_url());
        }

        $data['invoices'] = $this->invoices_builder_model->get_built_invoice_for_client(get_client_user_id());

        $data['title']    = _l('clients_my_invoices');
        $this->data($data);
        $this->view('client_portal/invoices');
        $this->layout();
    }
}
