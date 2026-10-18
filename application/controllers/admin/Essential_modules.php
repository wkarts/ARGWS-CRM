<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Separate from AdminController so incomplete setup cannot create a redirect loop. */
class Essential_modules extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_staff_logged_in()) {
            redirect(admin_url('authentication'));
            return;
        }
        if (!$this->authorizedStaff()) {
            show_error('Um administrador ativo precisa concluir a configuração obrigatória de Emitentes.', 403);
            return;
        }
    }

    private function authorizedStaff(): bool
    {
        if (!is_staff_logged_in() || !is_admin()) { return false; }
        $this->load->model('staff_model');
        $staff = $this->staff_model->get(get_staff_user_id());
        return $staff && (int) $staff->active === 1 && (int) $staff->admin === 1;
    }

    public function index()
    {
        $method = $this->input->method(true);
        if (!in_array($method, ['GET', 'POST'], true)) {
            show_error('Método não permitido.', 405);
            return;
        }
        // Defense in depth for direct dispatch; constructor checks the live staff record.
        if (!$this->authorizedStaff()) {
            show_error('Configuração restrita ao administrador.', 403);
            return;
        }
        $token = $this->session->userdata('essential_modules_csrf');
        $error = null;
        if ($method === 'POST') {
            $provided = $this->input->post('essential_modules_csrf');
            if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)
                || !is_string($provided) || !hash_equals($token, $provided)) {
                show_error('Solicitação inválida. Atualize a página e tente novamente.', 403);
                return;
            }
            try {
                if (!$this->app_modules->ensure_essential_modules()) {
                    throw new RuntimeException('issuer_essential_setup_unconfirmed');
                }
                $complete = true;
            } catch (Throwable $exception) {
                log_message('error', 'Falha na configuração essencial de Emitentes: ' . get_class($exception));
                $error = 'A configuração não foi concluída. O painel permanece bloqueado. Revise os logs e tente novamente; os dados existentes são preservados.';
                $this->output->set_status_header(503);
            }
            if (!empty($complete)) {
                $this->session->unset_userdata('essential_modules_csrf');
                redirect(admin_url());
                return;
            }
        } elseif ($this->app_modules->essential_modules_ready()) {
            redirect(admin_url());
            return;
        }
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata('essential_modules_csrf', $token);
        }
        $this->load->view('admin/modules/essential_setup', ['token' => $token, 'error' => $error]);
    }
}
