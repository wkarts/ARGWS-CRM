<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Company_staff extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto de empresa indisponível.',503);return;}
        require_once APPPATH.'services/CompanyStaff.php';
        $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$service=new CompanyStaff($this->db,db_prefix());
        $this->output->set_header('Cache-Control: no-store');
        if(!$acl->can($issuer,$actor,'staff','view')){show_error('Sem permissão para consultar a equipe desta empresa.',403);return;}
        $csrf=$this->session->userdata('company_staff_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_staff_csrf',$csrf);}
        $error=null;$detail=null;$search='';$page=['items'=>[],'next'=>null];
        try{
            if($this->input->method(true)==='POST'){
                $posted=$this->input->post('company_staff_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue o formulário antes de salvar.',403);return;}
                $action=$this->input->post('action',false);$key=$this->input->post('request_key',false);$grants=$this->input->post('grants',false)??[];$profile=$this->input->post('profile',false)??[];$default=$this->input->post('make_default',false)==='1';
                if(!is_string($key)||!is_array($grants)||!is_array($profile)){throw new DomainException('issuer_staff_invalid');}
                if($action==='create'){$id=$service->create($issuer,$actor,$profile,$grants,$default,$key);}
                elseif($action==='link'){$email=$this->input->post('existing_email',false);if(!is_string($email)){throw new DomainException('issuer_staff_invalid');}$id=$service->link($issuer,$actor,$email,$grants,$default,$key);}
                elseif(in_array($action,['update','revoke'],true)){$id=$this->input->post('staff_id',false);$revision=$this->input->post('revision',false);if(!is_string($revision)){throw new DomainException('issuer_staff_invalid');}
                    if($action==='update'){$id=$service->update($issuer,$actor,$id,$revision,$profile,$grants,$default,$key);}else{$service->revoke($issuer,$actor,$id,$revision,$key);$id=null;}}
                else{throw new DomainException('issuer_staff_invalid');}
                CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_staff'.($id?'?staff='.(int)$id:'')),'location',303);return;
            }
            if(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $id=$this->input->get('staff',false);if($id!==null){$detail=$service->get($issuer,$actor,$id);}
            $search=$this->input->get('search',false)??'';if(!is_string($search)){throw new DomainException('issuer_staff_invalid');}$page=$service->page($issuer,$actor,$this->input->get('after',false)??0,50,$search);
        }catch(DomainException $e){
            if(in_array($e->getMessage(),['issuer_staff_denied','issuer_staff_grant_denied','issuer_master_protected','issuer_staff_shared_identity'],true)){$this->output->set_status_header(403);}else{$this->output->set_status_header(in_array($e->getMessage(),['issuer_staff_conflict','issuer_staff_stale'],true)?409:422);}
            $error=match($e->getMessage()){
                'issuer_staff_shared_identity'=>'Esta identidade também atende outra empresa ou tem administração global. Somente o master pode alterar seu perfil; você pode gerir o vínculo desta empresa.',
                'issuer_master_protected'=>'A identidade do master e o acesso de quem executa a operação são preservados.',
                'issuer_staff_email_conflict'=>'Este e-mail já está cadastrado. Use a opção de vincular a identidade existente.',
                'issuer_staff_stale'=>'O perfil ou seus acessos mudaram. Reabra o cadastro antes de salvar.',
                'issuer_staff_conflict'=>'Este envio ou vínculo já foi utilizado. Reabra o cadastro para conferir o resultado.',
                'issuer_staff_grant_denied'=>'Você só pode conceder permissões que possui nesta empresa, preservando permissões já existentes.',
                'issuer_staff_denied'=>'Identidade ou ação indisponível nesta empresa.',
                default=>'Confira os dados. A senha inicial deve ter de 12 a 128 caracteres; perfil, vínculo e permissões são gravados juntos.'};
        }catch(Throwable $e){log_message('error','Company staff write not confirmed; no identity values logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra o cadastro para conferir o estado antes de tentar novamente.';}
        $caps=[];foreach(['view','create','edit','delete'] as$cap){$caps[$cap]=$acl->can($issuer,$actor,'staff',$cap);}
        $this->load->view('admin/companies/staff',['issuer'=>$issuer,'actor'=>$actor,'directory'=>$page,'detail'=>$detail,'search'=>$search,'error'=>$error,'capabilities'=>$caps,'resources'=>CompanyResourceCatalog::definitions(),'company_staff_csrf'=>$csrf]);
    }
}
