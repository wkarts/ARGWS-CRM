<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Public portal identity/recovery; deliberately avoids the legacy theme, models and module hydration. */
class Company_portal extends CI_Controller
{
    public function index($issuer=null)
    {
        if(!PortalRequestScope::active()){show_404();return;}
        require_once APPPATH.'services/CompanyPortal.php';
        $this->output->set_header('Cache-Control: no-store');
        $this->output->set_header('Referrer-Policy: no-referrer');
        $this->output->set_header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-".($nonce=base64_encode(random_bytes(18)))."'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $this->output->set_header('X-Content-Type-Options: nosniff');
        try{$issuer=IssuerPolicy::id($issuer);$portal=new CompanyPortal($this->db,db_prefix(),(string)$this->config->item('encryption_key'));}
        catch(Throwable $error){show_404();return;}
        $sessionKey='company_portal_identity';$identity=$this->session->userdata($sessionKey);$message=null;$error=null;
        $csrf=$this->session->userdata('company_portal_csrf');
        if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_portal_csrf',$csrf);}
        try{
            $current=is_array($identity)?$portal->sessionIdentity($identity,$issuer):null;
            if(!$current&&is_array($identity)&&($identity['issuer_id']??null)===$issuer)$this->session->unset_userdata($sessionKey);
            if($this->input->method(true)==='POST'){
                $posted=$this->input->post('portal_csrf',false);
                if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue o formulário antes de continuar.',403);return;}
                $action=$this->input->post('action',false);
                if($action==='logout'){
                    $this->session->unset_userdata($sessionKey);$this->session->sess_regenerate(true);
                    redirect($this->config->site_url('company_portal/index/'.$issuer),'location',303);return;
                }
                if(!in_array($action,['login','recovery','reset'],true))throw new DomainException('company_portal_invalid_input');
                $allowed=$portal->allowAttempt($issuer,$this->input->ip_address(),$action);
                $email=$this->input->post('email',false);$password=$this->input->post('password',false);
                if($action==='recovery'){
                    if($allowed&&is_string($email)){
                        $this->load->library('encryption');
                        $portal->requestRecovery($issuer,$email,$this->config->base_url(),fn($value)=>$this->encryption->decrypt($value));
                    }
                    $message='Se houver uma conta ativa nesta empresa e o envio estiver disponível, você receberá um link. Aguarde antes de solicitar novamente.';
                }elseif($action==='login'){
                    require_once APPPATH.'third_party/phpass.php';
                    $hasher=new PasswordHash(PHPASS_HASH_STRENGTH,PHPASS_HASH_PORTABLE);
                    $authenticated=$allowed&&is_string($email)&&is_string($password)?$portal->login($issuer,$email,$password,static fn($value,$hash)=>$hasher->CheckPassword($value,$hash)):null;
                    if($authenticated){
                        $this->session->sess_regenerate(true);$this->session->set_userdata($sessionKey,$authenticated);
                        $this->session->set_userdata('company_portal_csrf',bin2hex(random_bytes(32)));
                        redirect($this->config->site_url('company_portal/index/'.$issuer),'location',303);return;
                    }
                    $this->output->set_status_header(422);$error='Não foi possível entrar. Confira a empresa, o e-mail e a senha, ou aguarde antes de tentar novamente.';
                }else{
                    $token=$this->input->post('token',false);$repeat=$this->input->post('password_repeat',false);
                    require_once APPPATH.'third_party/phpass.php';
                    $hasher=new PasswordHash(PHPASS_HASH_STRENGTH,PHPASS_HASH_PORTABLE);
                    if($allowed&&is_string($password)&&$password===$repeat&&is_string($token)&&$portal->reset($issuer,$token,$password,static fn($value)=>$hasher->HashPassword($value))){
                        $this->session->unset_userdata($sessionKey);$this->session->sess_regenerate(true);$current=null;
                        $message='Senha alterada. Entre novamente nesta empresa.';
                    }else{$this->output->set_status_header(422);$error='Não foi possível alterar a senha. Use um link válido e uma senha de pelo menos 12 caracteres. Se necessário, solicite outro link.';}
                }
                // POST/refresh never echoes a bearer or password into HTML.
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        }catch(Throwable $exception){
            log_message('error','Company portal operation unavailable; no identity, password or token logged.');
            // Public recovery has the same response for an unknown account and unavailable mail config.
            if($this->input->post('action',false)==='recovery')$message='Se houver uma conta ativa nesta empresa e o envio estiver disponível, você receberá um link. Aguarde antes de solicitar novamente.';
            else{$this->output->set_status_header(503);$error='Serviço indisponível. Tente novamente mais tarde.';}
        }
        $this->load->view('company_portal/index',['issuer'=>$issuer,'identity'=>$current??null,'message'=>$message,'error'=>$error,'portal_csrf'=>$csrf,'script_nonce'=>$nonce]);
    }
}
