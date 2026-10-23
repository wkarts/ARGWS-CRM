<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Payment_origins extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!is_admin()) {
            access_denied('Asaas: origem e pendências');
        }
        $this->load->library('asaas/asaas_gateway');
    }

    public function index()
    {
        $store = $this->asaas_gateway->getAdapter()->originStore();
        $token = $this->session->userdata('asaas_origin_csrf');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata('asaas_origin_csrf', $token);
        }
        $data = ['csrf_token' => $token, 'title' => 'Asaas: origem e pendências', 'ready' => $store->ready(), 'origins' => [], 'legacy' => []];
        if ($data['ready']) {
            $data['origins'] = $this->db->order_by('updated_at', 'DESC')->limit(250)
                ->get(db_prefix() . 'asaas_payment_origins')->result_array();
            $data['legacy'] = $this->db->select('m.invoice_id, m.asaas_payment_id, m.status')
                ->from(db_prefix() . 'asaas_payments_map m')
                ->join(db_prefix() . 'asaas_payment_origins o', 'o.invoice_id = m.invoice_id', 'left')
                ->where('o.id IS NULL', null, false)->order_by('m.invoice_id', 'DESC')->limit(250)->get()->result_array();
        }
        $data['embedded_panel'] = $this->input->get('panel') === '1';
        $this->output->set_header('Cache-Control: no-store, private, max-age=0');
        $this->load->view('asaas/payment_origins', $data);
    }

    public function reconcile()
    {
        if (strtoupper($this->input->method()) !== 'POST') {
            show_404();
            return;
        }
        $storedToken = $this->session->userdata('asaas_origin_csrf');
        $submittedToken = $this->input->post('origin_csrf');
        if (!is_string($storedToken) || strlen($storedToken) !== 64 || !is_string($submittedToken)
            || !hash_equals($storedToken, $submittedToken)) {
            show_error('Sessão inválida. Atualize a página e tente novamente.', 403);
            return;
        }
        try {
            $this->asaas_gateway->getAdapter()->reconcileOrigin(
                (int) $this->input->post('invoice_id'),
                trim((string) $this->input->post('remote_id')),
                (string) $this->input->post('environment'),
                trim((string) $this->input->post('account_id'))
            );
            set_alert('success', 'Origem conciliada com a conta informada. Nenhuma cobrança foi criada, cancelada ou estornada.');
        } catch (Throwable $error) {
            // Do not expose provider messages, credentials or customer payloads.
            set_alert('warning', 'Associação não confirmada. Confira conta, ambiente, ID remoto, referência, valor e vencimento. O vínculo existente foi preservado.');
        }
        redirect(admin_url('asaas/payment_origins' . ($this->input->get('panel') === '1' ? '?panel=1' : '')));
    }
}
