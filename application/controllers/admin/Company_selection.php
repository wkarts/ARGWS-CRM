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
        $stage = 'context';
        try {
            $context = CompanyRequestScope::context();
            if (!($context instanceof CompanyContext)) { throw new LogicException('company_context_unavailable'); }
            $stage = 'authorization';
            $context->select(
                $this->input->post('company_id', false),
                $this->input->post('_company_switch_csrf', false),
                CompanyRequestScope::submittedToken()
            );
            $stage = 'cache_invalidation';
            CompanyRequestScope::invalidateCaches($this);
            // Never replay a previous POST or carry a resource ID into a different company.
            $destination = CompanyRequestScope::adminUrl($this);
            $stage = 'request_release';
            CompanyRequestScope::finish();
            $stage = 'redirect';
            redirect($destination, 'location', 303);
        } catch (DomainException $error) {
            show_error('Não foi possível trocar a empresa. Recarregue a página e confirme seu acesso.', 403);
        } catch (Throwable $error) {
            $errorClass = get_class($error);
            if (!preg_match('/^[A-Za-z0-9_\\\\]{1,128}$/D', $errorClass)) { $errorClass = 'Throwable'; }
            $sourceFile = basename((string) $error->getFile());
            if (!preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $sourceFile)) { $sourceFile = 'unknown'; }
            $safeMessage = 'Native company selection failed [' . $stage . ']: ' . $errorClass
                . ' @ ' . $sourceFile . ':' . (int) $error->getLine() . '.';
            error_log($safeMessage);
            log_message('error', $safeMessage);
            show_error('Não foi possível trocar a empresa. Recarregue a página e tente novamente.', 500);
        }
    }
}
