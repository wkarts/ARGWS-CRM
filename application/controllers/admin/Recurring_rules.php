<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Recurring_rules extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (staff_cant('view', 'invoices') && staff_cant('view_own', 'invoices')
            && get_option('allow_staff_view_invoices_assigned') !== '1') {
            access_denied('invoices');
        }
        $this->protect_request();
        $this->load->model('recurring_rules_model');
    }

    public function index()
    {
        $source = $this->input->get('source') === 'legacy' ? 'legacy' : 'rules';
        $state = $this->input->get('state');
        $state = is_string($state) ? $state : '';
        $group = $this->input->get('calendar_group');
        $group = is_string($group) ? substr($group, 0, 64) : '';
        $page = max(1, min(100000, (int) $this->input->get('page')));
        $data = $this->recurring_rules_model->listing($source, $state, $group, $page);
        $data += ['title' => 'Recorrências', 'source' => $source, 'state' => $state, 'calendar_group' => $group, 'page' => $page];
        $this->load->view('admin/recurring_rules/list', $data);
    }

    public function create()
    {
        if (staff_cant('create', 'invoices')) {
            access_denied('invoices');
        }
        $data = ['title' => 'Nova recorrência', 'error' => null, 'input' => []];
        if ($this->input->method() === 'post') {
            $data['input'] = (array) $this->input->post();
            try {
                $id = $this->recurring_rules_model->create_from_catalogue($data['input']);
                set_alert('success', 'Recorrência criada e pausada. Confira o modelo e faça a prévia para ativar.');
                redirect(admin_url('recurring_rules/view/' . $id));
            } catch (InvalidArgumentException | RuntimeException $e) {
                $data['error'] = $e->getMessage();
            }
        }
        $this->load->view('admin/recurring_rules/form', $data);
    }

    public function view($id = 0)
    {
        try {
            $data = $this->recurring_rules_model->details((int) $id);
        } catch (RuntimeException $e) {
            show_404();
            return;
        }
        $data['title'] = 'Recorrência #' . (int) $id;
        $this->load->view('admin/recurring_rules/view', $data);
    }

    public function preview()
    {
        $this->post_only();
        if (staff_cant('edit', 'invoices')) {
            access_denied('invoices');
        }
        try {
            $data = $this->recurring_rules_model->preview($this->post_text('source'),
                (array) $this->input->post('ids'), $this->post_text('action'), $this->post_text('due_mode'));
            $data['title'] = 'Prévia dos ciclos futuros';
            $this->load->view('admin/recurring_rules/preview', $data);
        } catch (InvalidArgumentException | RuntimeException $e) {
            set_alert('warning', $e->getMessage());
            redirect(admin_url('recurring_rules'));
        }
    }

    public function apply()
    {
        $this->post_only();
        if (staff_cant('edit', 'invoices')) {
            access_denied('invoices');
        }
        try {
            $result = $this->recurring_rules_model->apply($this->post_text('token'), $this->input->post('confirm_future') === '1');
            $this->load->view('admin/recurring_rules/result', ['title' => 'Alteração de recorrências', 'result' => $result]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            set_alert('warning', $e->getMessage());
            redirect(admin_url('recurring_rules'));
        }
    }

    /** Independent of the application's optional global CSRF setting. */
    private function protect_request(): void
    {
        $key = 'recurring_rules_csrf';
        $stored = $this->session->userdata($key);
        if (!is_array($stored) || ($stored['staff_id'] ?? null) !== (int) get_staff_user_id()
            || !is_string($stored['token'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $stored['token'])) {
            $stored = ['staff_id' => (int) get_staff_user_id(), 'token' => bin2hex(random_bytes(32))];
            $this->session->set_userdata($key, $stored);
        }
        if ($this->input->method() === 'post'
            && !hash_equals($stored['token'], $this->post_text($key))) {
            show_error('Formulário expirado ou inválido. Reabra a página e tente novamente.', 403);
            exit;
        }
        // Stable within the actor's session: multiple tabs and valid retries remain usable.
        $this->load->vars([$key => $stored['token']]);
    }

    private function post_text(string $key): string
    {
        $value = $this->input->post($key);
        return is_string($value) ? $value : '';
    }

    private function post_only(): void
    {
        if ($this->input->method() !== 'post') {
            show_error('Use o formulário para esta operação.', 405);
            exit;
        }
    }
}
