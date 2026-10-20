<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** A sealed company route; no legacy global Settings controller or provider callback session authority. */
class Company_microsoft_mail_oauth extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
        require_once APPPATH.'services/CompanyMicrosoftMailOAuth.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$service=new CompanyMicrosoftMailOAuth($this->db,db_prefix());
        $this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');
        $url=CompanyRequestScope::adminUrl($this,'company_microsoft_mail_oauth');$method=$this->input->method(true);$error=null;
        if(!(new IssuerResourceAccess($this->db,db_prefix()))->can($issuer,$actor,'settings','view')){show_error('Sem permissão nesta empresa.',403);return;}
        $this->load->library('encryption');$encrypt=fn($v)=>$this->encryption->encrypt($v);$decrypt=fn($v)=>$this->encryption->decrypt($v);
        // Authorization code is protocol query input only: never echo, persist, flash or log it.
        if(in_array($method,['GET','HEAD'],true)&&($this->input->get('state',false)!==null||$this->input->get('code',false)!==null||$this->input->get('error',false)!==null)){
            try{$state=$this->input->get('state',false);$code=$this->input->get('code',false);if($method!=='GET'||!is_string($state)||!is_string($code)||$this->input->get('error',false)!==null)throw new DomainException('company_microsoft_mail_state_invalid');$service->callback($issuer,$actor,$state,$code,$encrypt,$decrypt);$this->session->set_flashdata('company_microsoft_mail_result','connected');}
            catch(Throwable$error){$this->session->set_flashdata('company_microsoft_mail_result','unconfirmed');}
            redirect($url,'location',303);return;
        }
        $csrf=$this->session->userdata('company_microsoft_mail_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_microsoft_mail_csrf',$csrf);}
        $redirect=null;try{if(!defined('COMPANY_MICROSOFT_MAIL_REDIRECT_URI'))throw new DomainException('company_microsoft_mail_https_required');$redirect=CompanyMicrosoftMailOAuth::redirectUri(COMPANY_MICROSOFT_MAIL_REDIRECT_URI);}catch(Throwable$ignored){}
        try{
            if($method==='POST'){
                $posted=$this->input->post('company_microsoft_mail_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue a página antes de salvar.',403);return;}
                $revision=$this->input->post('revision',false);$action=$this->input->post('action',false);if(!is_string($revision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$revision))throw new DomainException('company_microsoft_mail_invalid');
                if($action==='save'){$tenant=$this->input->post('tenant_id',false);$id=$this->input->post('client_id',false);$secret=$this->input->post('client_secret',false);if(!is_string($tenant)||!is_string($id)||!is_string($secret))throw new DomainException('company_microsoft_mail_invalid');$service->save($issuer,$actor,(int)$revision,$tenant,$id,$secret,$encrypt,$decrypt);}
                elseif($action==='document_mode'){require_once APPPATH.'services/CompanyMailTransportPolicy.php';$policyRevision=$this->input->post('transport_revision',false);$mode=$this->input->post('document_mode',false);$providerRevision=$mode==='google'?$this->input->post('google_revision',false):$revision;if(!is_string($policyRevision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$policyRevision)||!is_string($providerRevision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$providerRevision)||!is_string($mode))throw new DomainException('company_mail_transport_invalid');(new CompanyMailTransportPolicy($this->db,db_prefix()))->save($issuer,$actor,(int)$policyRevision,$mode,(int)$providerRevision);}
                elseif($action==='portal_mode'){require_once APPPATH.'services/CompanyPortalMailConfig.php';$portalRevision=$this->input->post('portal_revision',false);$mode=$this->input->post('portal_mode',false);if(!is_string($portalRevision)||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$portalRevision)||!is_string($mode))throw new DomainException('company_portal_mail_invalid');(new CompanyPortalMailConfig($this->db,db_prefix()))->save($issuer,$actor,(int)$portalRevision,$mode,(int)$revision);}
                elseif($action==='revoke')$service->revoke($issuer,$actor,(int)$revision);
                elseif($action==='begin'){if($redirect===null)throw new DomainException('company_microsoft_mail_https_required');$target=$service->begin($issuer,$actor,(int)$revision,$redirect,$encrypt);redirect($target,'location',303);return;}
                else throw new DomainException('company_microsoft_mail_invalid');
                CompanyRequestScope::invalidateCaches($this);redirect($url,'location',303);return;
            }elseif(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
        }catch(DomainException$failure){$status=match($failure->getMessage()){'company_microsoft_mail_access_denied','company_portal_mail_denied','company_mail_transport_denied'=>403,'company_microsoft_mail_stale','company_portal_mail_stale','company_mail_transport_stale'=>409,default=>422};$this->output->set_status_header($status);$error=match($failure->getMessage()){'company_microsoft_mail_access_denied','company_portal_mail_denied','company_mail_transport_denied'=>'Sua permissão mudou. Reabra a empresa antes de continuar.','company_microsoft_mail_stale','company_portal_mail_stale','company_mail_transport_stale'=>'A configuração mudou. Reabra a tela antes de continuar.','company_microsoft_mail_https_required'=>'Defina no servidor o endereço HTTPS confiável de retorno.','company_microsoft_mail_sender_required'=>'Configure primeiro o endereço de envio próprio desta empresa.',default=>'Confira os dados. Nenhuma nova conexão foi confirmada nesta tentativa.'};}
        catch(Throwable$failure){$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra a tela; não repita um código de autorização.';}
        try{$profile=$service->view($issuer,$actor);require_once APPPATH.'services/CompanyMailTransportPolicy.php';$documentMail=(new CompanyMailTransportPolicy($this->db,db_prefix()))->page($issuer,$actor);$portalMail=null;require_once APPPATH.'services/CompanyPortalMailConfig.php';if(CompanyPortalMailSchema::present($this->db,db_prefix()))$portalMail=(new CompanyPortalMailConfig($this->db,db_prefix()))->page($issuer,$actor);}catch(Throwable$failure){show_error('Configuração indisponível nesta empresa. Configure o remetente próprio ou confira a atualização do núcleo.',503);return;}
        $this->load->view('admin/companies/microsoft_mail_oauth',['issuer'=>$issuer,'profile'=>$profile,'error'=>$error,'csrf'=>$csrf,'trusted_redirect'=>$redirect,'can_edit'=>(new IssuerResourceAccess($this->db,db_prefix()))->can($issuer,$actor,'settings','edit'),'document_mail'=>$documentMail,'portal_mail'=>$portalMail,'result'=>$this->session->flashdata('company_microsoft_mail_result')]);
    }
}
