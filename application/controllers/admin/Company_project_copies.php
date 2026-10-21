<?php

defined('BASEPATH') or exit('No direct script access allowed');
/** Finite sealed review/new-private-project copy route. Native CSRF remains enforced by CI. */
class Company_project_copies extends AdminController
{
    public function index()
    {
        if(func_num_args()!==0){show_404();return;}
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
        require_once APPPATH.'services/CompanyProjectCopies.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());foreach([['projects','view'],['projects','create'],['customers','view']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap)){show_error('Sem permissão para copiar projetos nesta empresa.',403);return;}
        $this->output->set_header('Cache-Control: no-store');$s=new CompanyProjectCopies($this->db,db_prefix());$csrf=$this->session->userdata('company_project_copies_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_project_copies_csrf',$csrf);}$source=null;$preview=null;$receipt=null;$error=null;$attempt=null;
        try{
            $verb=$this->input->method(true);
            if($verb==='POST'){
                $token=$this->input->post('company_project_copies_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Reabra a revisão antes de copiar.',403);return;}
                if($this->input->post('action',false)!=='copy_reviewed_project'){show_404();return;}$source=IssuerPolicy::id($this->input->post('source_project_id',false));$review=$this->input->post('review',false);$key=$this->input->post('request_key',false);if(!is_array($review)||!is_string($key))throw new DomainException('company_project_copy_invalid');if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_copy_invalid');$attempt=$key;$result=$s->create($issuer,$actor,$source,$review,$attempt);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_project_copies?receipt='.$result['request_key']),'location',303);return;
            }
            if(!in_array($verb,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $candidate=$this->input->get('source',false);$key=$this->input->get('receipt',false);if(($candidate!==null&&$key!==null)||($candidate===null&&$key===null)||($candidate!==null&&!is_scalar($candidate))||($key!==null&&!is_string($key))){show_404();return;}
            if($key!==null)$receipt=$s->receipt($issuer,$actor,$key);else{$source=IssuerPolicy::id($candidate);$preview=$s->preview($issuer,$actor,$source);}
        }catch(DomainException $e){
            if(in_array($e->getMessage(),['company_project_copy_denied','company_project_access_denied','company_project_reference_denied','issuer_access_denied'],true)){show_error('Cópia indisponível para esta empresa, origem ou responsável atual.',403);return;}
            $this->output->set_status_header(str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:422);$error=match($e->getMessage()){'company_project_copy_stale'=>'O projeto ou seus componentes mudaram. Reabra a revisão.','company_project_copy_conflict'=>'Esta chave já pertence a uma tentativa diferente. Confira o recibo original.','company_project_copy_limit'=>'A origem excede o limite de200tarefas ou100marcos por cópia.',default=>'Confira nome, datas, opções, responsáveis atuais e permissões. Nenhuma cópia foi confirmada.'};
        }catch(Throwable $e){log_message('error','Reviewed project copy unconfirmed; no source contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar esta tentativa. Confira o recibo desta chave antes de iniciar outra cópia.';}
        $this->load->view('admin/companies/project_copies',['issuer'=>$issuer,'source'=>$source,'preview'=>$preview,'receipt'=>$receipt,'error'=>$error,'attempt'=>$attempt,'request_key'=>bin2hex(random_bytes(32)),'company_project_copies_csrf'=>$csrf]);
    }
}
