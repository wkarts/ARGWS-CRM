<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Company_standalone_tasks extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
        require_once APPPATH.'services/CompanyStandaloneTasks.php';
        $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());
        if(!$acl->can($issuer,$actor,'tasks','view')){show_error('Sem permissão para tarefas desta empresa.',403);return;}
        $this->output->set_header('Cache-Control: no-store');$csrf=$this->session->userdata('company_standalone_tasks_csrf');if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_standalone_tasks_csrf',$csrf);}
        $creation_defaults=null;$s=new CompanyStandaloneTasks($this->db,db_prefix());$error=null;$task=null;$directory=['items'=>[],'next'=>null];$staff=[];
        try{
            if($this->input->method(true)==='POST'){
                $token=$this->input->post('company_standalone_tasks_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}
                $action=$this->input->post('action',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_project_invalid_input');
                if(in_array($action,['create','update'],true)){
                    $input=$this->input->post('task',false);if(!is_array($input))throw new DomainException('company_project_invalid_input');$lines=$this->input->post('checklist_lines',false)??'';if(!is_string($lines)||strlen($lines)>6000)throw new DomainException('company_project_invalid_input');$input['checklist']=$input['checklist']??[];if(!is_array($input['checklist']))throw new DomainException('company_project_invalid_input');$input['checklist']=array_values($input['checklist']);foreach(preg_split('/\r\n|\r|\n/',$lines) as $line)if(trim($line)!=='')$input['checklist'][]=['text'=>$line,'done'=>'0'];
                    if($action==='create')$id=$s->create($issuer,$actor,$input,$key);else $id=$s->update($issuer,$actor,$this->input->post('task_id',false),$this->input->post('revision',false),$input,$key);
                }elseif(in_array($action,['comment','start','stop'],true)){$body=$this->input->post('comment',false)??'';if(!is_string($body))throw new DomainException('company_project_invalid_input');$id=$s->activity($issuer,$actor,$this->input->post('task_id',false),$this->input->post('revision',false),$action,$key,$body);}
                else throw new DomainException('company_project_invalid_input');
                CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_standalone_tasks?task='.(int)$id),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $id=$this->input->get('task',false);if($id!==null)$task=$s->get($issuer,$actor,$id);$directory=$s->page($issuer,$actor,$this->input->get('after',false)??0);$staff=$s->staffOptions($issuer,$actor);if($acl->can($issuer,$actor,'tasks','create'))$creation_defaults=$s->creationDefaults($issuer,$actor);
        }catch(DomainException $e){$this->output->set_status_header(409);$error=match($e->getMessage()){'company_project_stale'=>'A tarefa mudou. Reabra antes de salvar.','company_project_closed'=>'A tarefa está concluída. Reabra-a para registrar nova atividade.','company_project_timer_running'=>'Já existe cronômetro em execução. Pare o cronômetro antes de iniciar outro ou concluir.','company_project_timer_missing'=>'Nenhum cronômetro seu está em execução nesta tarefa.','company_project_checklist_open'=>'Conclua todos os itens do checklist antes de finalizar.',default=>'Dados, referências ou permissões inválidos. Nenhuma alteração foi confirmada.'};}
        catch(Throwable $e){log_message('error','Company task operation unconfirmed; no contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra antes de tentar novamente.';}
        $this->load->view('admin/companies/standalone_tasks',['issuer'=>$issuer,'actor'=>$actor,'task'=>$task,'directory'=>$directory,'staff_options'=>$staff,'creation_defaults'=>$creation_defaults,'error'=>$error,'company_standalone_tasks_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'can_create'=>$acl->can($issuer,$actor,'tasks','create'),'can_edit'=>$acl->can($issuer,$actor,'tasks','edit')]);
    }
}
