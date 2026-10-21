<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Per-company, per-staff credentials. Plaintext is returned only by the original successful issue. */
class Company_api_tokens extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
        require_once APPPATH.'services/CompanyApiTokens.php';$scope=CompanyRequestScope::context();$issuer=$scope->issuerId();$actor=$scope->staffId();$service=new CompanyApiTokens($this->db,db_prefix());$acl=new IssuerResourceAccess($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache');
        if(!$acl->can($issuer,$actor,'company_api','manage')){show_error('Sem permissão para administrar tokens API desta empresa.',403);return;}$csrf=$this->session->userdata('company_api_tokens_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_api_tokens_csrf',$csrf);}
        $error=null;$created=null;$tokens=[];$scopes=[];$message=null;
        try{if($this->input->method(true)==='POST'){$posted=$this->input->post('company_api_tokens_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue o formulário antes de salvar.',403);return;}$action=$this->input->post('action',false);$id=$this->input->post('token_id',false);if($action==='revoke'){if(!is_string($id))throw new DomainException('company_api_invalid_input');$service->revoke($issuer,$actor,$id);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_api_tokens'),'location',303);return;}
                if(!in_array($action,['issue','rotate'],true))throw new DomainException('company_api_invalid_input');$label=$this->input->post('label',false);$selected=$this->input->post('scopes',false);$days=$this->input->post('days',false);$key=$this->input->post('request_key',false);if(!is_string($label)||!is_array($selected)||!is_string($days)||!preg_match('/^[1-9][0-9]?$/D',$days)||!is_string($key)||($action==='rotate'&&!is_string($id)))throw new DomainException('company_api_invalid_input');$created=$service->issue($issuer,$actor,$label,$selected,(int)$days,$key,$action==='rotate'?$id:null);if($created['secret']===null)$message='O envio já foi processado. O token anterior não será exibido novamente; faça uma rotação se precisar substituí-lo.';CompanyRequestScope::invalidateCaches($this);
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}$tokens=$service->page($issuer,$actor);$scopes=$service->availableScopes($issuer,$actor);
        }catch(DomainException $e){$reason=$e->getMessage();if($reason==='company_api_access_denied'){show_error('Token indisponível ou operação não autorizada nesta empresa.',403);return;}$this->output->set_status_header($reason==='company_api_request_conflict'?409:422);$error=match($reason){'company_api_scope_denied'=>'Uma permissão selecionada não está mais autorizada para você nesta empresa.','company_api_request_conflict'=>'Este envio já foi usado ou o token mudou. Reabra a tela.','company_api_token_limit'=>'Limite de 20 tokens ativos por funcionário e empresa. Revogue um token antes de criar outro.',default=>'Confira nome, permissões explícitas e prazo entre 1 e 90 dias. Nenhum token foi confirmado nesta tentativa.'};}
        catch(Throwable $e){log_message('error','Company API token management failed; no secret or input data logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra a tela antes de tentar novamente.';}
        $this->load->view('admin/companies/api_tokens',['issuer'=>$issuer,'actor'=>$actor,'tokens'=>$tokens,'available_scopes'=>$scopes,'created'=>$created,'message'=>$message,'error'=>$error,'company_api_tokens_csrf'=>$csrf]);
    }
}
