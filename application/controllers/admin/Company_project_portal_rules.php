<?php

defined('BASEPATH') or exit('No direct script access allowed');
/** Dedicated sealed editor. Root request-scope adapter registers this route with projects capability. */
class Company_project_portal_rules extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
        require_once APPPATH.'services/CompanyProjectPortalRules.php';require_once APPPATH.'services/CompanyPortalTaskWriteRules.php';require_once APPPATH.'services/CompanyPortalTaskAuthorship.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());if(!$acl->can($issuer,$actor,'projects','view')){show_error('Sem permissão para projetos desta empresa.',403);return;}
        $this->output->set_header('Cache-Control: no-store');$this->output->set_header('X-Content-Type-Options: nosniff');$csrf=$this->session->userdata('company_project_rules_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_project_rules_csrf',$csrf);}
        $service=new CompanyProjectPortalRules($this->db,db_prefix());$state=null;$write_state=null;$task_authorship=null;$error=null;$project=null;$task=null;
        try{
            if($this->input->method(true)==='POST'){
                $sent=$this->input->post('company_project_rules_csrf',false);if(!is_string($sent)||!hash_equals($csrf,$sent)){show_error('Recarregue antes de salvar.',403);return;}
                $project=IssuerPolicy::id($this->input->post('project_id',false));$action=$this->input->post('action',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_project_portal_invalid');
                if(in_array($action,['save_rules','hide_tasks'],true)){$flags=[];foreach(CompanyProjectPortalRulesSchema::FLAGS as $flag)$flags[$flag]=$action==='hide_tasks'?'0':($this->input->post($flag,false)??'0');$service->save($issuer,$actor,$project,$this->input->post('project_revision',false),$this->input->post('rules_revision',false),$flags,$key);}
                elseif($action==='save_write_rules'){$writeFlags=[];foreach(CompanyPortalTaskWriteSchema::FLAGS as $flag)$writeFlags[$flag]=$this->input->post($flag,false)??'0';(new CompanyPortalTaskWriteRules($this->db,db_prefix()))->save($issuer,$actor,$project,$this->input->post('project_revision',false),$this->input->post('write_revision',false),$writeFlags,$key);}
                elseif($action==='publish'){$task=IssuerPolicy::id($this->input->post('task_id',false));$kind=$this->input->post('kind',false);$child=$this->input->post('child_key',false);$visible=$this->input->post('visible',false)??'0';if(!is_string($kind)||!in_array($visible,['0','1'],true))throw new DomainException('company_project_portal_invalid');$service->publish($issuer,$actor,$project,$this->input->post('project_revision',false),$task,$this->input->post('task_revision',false),$kind,$child,$visible==='1',$key);}
                else throw new DomainException('company_project_portal_invalid');
                CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_project_portal_rules?project='.$project.($task!==null?'&task='.$task:'')),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $project=IssuerPolicy::id($this->input->get('project',false));$task=$this->input->get('task',false);$state=$service->get($issuer,$actor,$project,$task,$this->input->get('after',false)??0);if($state['can_edit'])$write_state=(new CompanyPortalTaskWriteRules($this->db,db_prefix()))->get($issuer,$actor,$project);if($state['task'])$task_authorship=(new CompanyPortalTaskAuthorship($this->db,db_prefix()))->get($issuer,$actor,$project,$state['task']['id']);
        }catch(DomainException $e){$this->output->set_status_header(409);$error=match($e->getMessage()){'company_project_portal_stale'=>'O projeto, a tarefa ou as regras mudaram. Reabra para revisar antes de publicar.','company_project_portal_request_conflict'=>'Esta tentativa já foi usada com outros dados. Reabra o formulário.',default=>'Referências, publicação ou permissões indisponíveis. Nenhuma alteração foi confirmada.'};}
        catch(Throwable $e){log_message('error','Project portal rules unconfirmed; contents omitted.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra antes de tentar novamente.';}
        $this->load->view('admin/companies/project_portal_rules',['issuer'=>$issuer,'actor'=>$actor,'state'=>$state,'write_state'=>$write_state,'task_authorship'=>$task_authorship,'error'=>$error,'company_project_rules_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
    }
}
