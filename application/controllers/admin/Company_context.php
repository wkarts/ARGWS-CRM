<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Isolated company selection. No module, SMTP, bank or financial initialization. */
class Company_context extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!CompanyRequestScope::sealedFor($this)) { show_error('Contexto de empresa indisponível.', 503); exit; }
    }

    public function index(): void
    {
        if (!in_array($this->input->method(true), ['GET', 'HEAD'], true)) { show_error('Use GET para selecionar uma empresa.', 405); return; }
        $nativeUpgradeSuccess = false;
        if ($this->session->userdata('admin_database_upgrade_success') !== null
            && $this->input->method(true) === 'GET' && !$this->input->is_ajax_request()) {
            require_once APPPATH . 'services/AdminDatabaseUpgrade.php';
            try {
                $version = IssuerStorage::query($this->db, 'SELECT version FROM `'.db_prefix().'migrations` LIMIT 1')->row();
                if (!$version || !preg_match('/^[0-9]+$/D', (string) $version->version)) {
                    throw new RuntimeException('native_upgrade_version_unavailable');
                }
            } catch (Throwable $error) {
                show_error('Não foi possível confirmar a atualização do núcleo. Tente novamente.', 503);
                return;
            }
            $nativeUpgradeSuccess = AdminDatabaseUpgrade::consumeContextSuccess($this, (string) $version->version);
        }
        $this->load->view('admin/companies/context', ['native_database_upgrade_success' => $nativeUpgradeSuccess]);
    }

    public function select(): void
    {
        if ($this->input->method(true) !== 'POST') { show_error('A troca de empresa exige POST.', 405); return; }
        try {
            $primary = CompanyRequestScope::context()->select(
                $this->input->post('company_id', false),
                $this->input->post('_company_switch_csrf', false),
                CompanyRequestScope::submittedToken()
            );
            CompanyRequestScope::invalidateCaches($this);
            // A fixed safe GET destination; no caller-supplied return URL or original POST is reused.
            $url = CompanyRequestScope::adminUrl($this, $primary ? '' : 'company_context');
            CompanyRequestScope::finish();
            redirect($url, 'location', 303);
        } catch (DomainException $error) {
            show_error('Não foi possível trocar a empresa. Recarregue a página e confirme seu acesso.', 403);
        }
    }
}
