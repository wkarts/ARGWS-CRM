<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Legacy installation responsibility is resolved inside the authenticated CRM. */
class Company_master extends AdminController
{
    public function index()
    {
        require_once APPPATH.'services/issuers/IssuerMasterResolution.php';
        $actor = get_staff_user_id();
        $access = new IssuerResourceAccess($this->db, db_prefix());
        $primary = $access->primaryId();
        if ($primary === null || !$access->administrative($primary,$actor)) { access_denied('issuers'); return; }
        $this->output->set_header('Cache-Control: no-store');
        $service = new IssuerMasterResolution($this->db,db_prefix());
        $error = null; $success = false;
        $token = $this->session->userdata('company_master_csrf');
        if (!is_string($token) || strlen($token)!==64) {
            $token = bin2hex(random_bytes(32)); $this->session->set_userdata('company_master_csrf',$token);
        }
        if ($this->input->method(true) === 'POST') {
            $submitted = $this->input->post('company_master_csrf',false);
            if (!is_string($submitted) || !hash_equals($token,$submitted)) { show_error('Recarregue o formulário antes de confirmar.',403); return; }
            try {
                $service->complete($actor,$this->input->post('authorization_code',false),$this->input->post('current_password',false),
                    static fn($password,$hash) => $hash !== '' && app_hasher()->CheckPassword($password,$hash));
                $success = true;
                CompanyRequestScope::invalidateCaches($this);
            } catch (DomainException $exception) {
                $error = 'Confirmação recusada. Verifique a autorização, a validade e a senha. Uma identidade já resolvida não pode ser substituída.';
            } catch (Throwable $exception) {
                log_message('error','Company master confirmation failed; no submitted values logged.');
                $error = 'Não foi possível confirmar. Tente novamente; a operação só é concluída com auditoria e transação confirmadas.';
            }
        } elseif (!in_array($this->input->method(true),['GET','HEAD'],true)) { show_error('Método não permitido.',405); return; }
        $this->load->view('admin/issuers/master_resolution',[
            'title'=>'Responsável da instalação','unresolved'=>$service->unresolved(),
            'company_master_csrf'=>$token,'error'=>$error,'success'=>$success,'actor_id'=>$actor,
        ]);
    }
}
