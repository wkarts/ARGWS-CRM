<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Technical company-switch endpoint. It has no page, menu or alternative workspace. */
class Company_selection extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!CompanyRequestScope::sealedFor($this)) { show_error('Seleção indisponível.', 503); exit; }
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
            // Never replay a previous POST or carry a resource ID into a different company.
            $destination = CompanyRequestScope::adminUrl($this);
            CompanyRequestScope::finish();
            redirect($destination, 'location', 303);
        } catch (DomainException $error) {
            show_error('Não foi possível trocar a empresa. Recarregue a página e confirme seu acesso.', 403);
        }
    }
}
