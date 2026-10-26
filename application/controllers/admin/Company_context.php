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
        // Compatibility entrypoint only. The active selector is in the original
        // admin shell, and every company returns to the same native dashboard.
        redirect(CompanyRequestScope::adminUrl($this), 'location', 303);
    }

    public function select(): void
    {
        if ($this->input->method(true) !== 'POST') { show_error('A troca de empresa exige POST.', 405); return; }
        try {
            CompanyRequestScope::context()->select(
                $this->input->post('company_id', false),
                $this->input->post('_company_switch_csrf', false),
                CompanyRequestScope::submittedToken()
            );
            CompanyRequestScope::invalidateCaches($this);
            // A fixed safe GET destination; no caller-supplied return URL or original POST is reused.
            CompanyRequestScope::finish();
            redirect(CompanyRequestScope::adminUrl($this), 'location', 303);
        } catch (DomainException $error) {
            show_error('Não foi possível trocar a empresa. Recarregue a página e confirme seu acesso.', 403);
        }
    }
}
