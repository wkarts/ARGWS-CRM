<?php

defined('BASEPATH') or exit('No direct script access allowed');
/** Native fixed project billing has one sealed route and no legacy invoice/project adapter. */
class Company_project_fixed_billing extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
        require_once APPPATH.'services/CompanyProjectFixedBilling.php';require_once APPPATH.'services/CompanyProjects.php';
        $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());$this->output->set_header('Cache-Control: no-store');
        foreach([['tasks','view'],['tasks','edit'],['projects','view'],['projects','edit'],['customers','view'],['invoices','view'],['invoices','create']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap)){show_error('Sem permissão para faturar o projeto nesta empresa.',403);return;}
        $csrf=$this->session->userdata('company_project_fixed_billing_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_project_fixed_billing_csrf',$csrf);}
        $service=new CompanyProjectFixedBilling($this->db,db_prefix());$project=null;$billing=null;$currencies=[];$projects=[];$error=null;
        try{
            if($this->input->method(true)==='POST'){
                $posted=$this->input->post('company_project_fixed_billing_csrf',false);if(!is_string($posted)||!hash_equals($csrf,$posted)){show_error('Recarregue antes de salvar.',403);return;}
                $action=$this->input->post('action',false);$id=$this->input->post('project_id',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_project_fixed_billing_invalid');
                if($action==='prepare'){$input=$this->input->post('billing',false);if(!is_array($input)||$this->input->post('billable',false)!=='1')throw new DomainException('company_project_fixed_billing_invalid');if(isset($input['taxes'])&&is_array($input['taxes']))$input['taxes']=array_values(array_filter($input['taxes'],fn($t)=>!(is_array($t)&&($t['name']??null)===''&&($t['rate']??null)==='0.00')));$service->prepare($issuer,$actor,$id,$this->input->post('revision',false),$input,$key);}
                elseif($action==='convert')$service->convert($issuer,$actor,$id,$this->input->post('origin_id',false));elseif($action==='disable')$service->disable($issuer,$actor,$id,$this->input->post('origin_id',false));else throw new DomainException('company_project_fixed_billing_invalid');
                CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_project_fixed_billing?project='.(int)$id),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $id=$this->input->get('project',false);if($id!==null){$project=(new CompanyProjects($this->db,db_prefix()))->get($issuer,$actor,$id);$billing=$service->status($issuer,$actor,$id);$currencies=(new CompanyInvoices($this->db,db_prefix()))->currencies($issuer,$actor);}else $projects=(new CompanyProjects($this->db,db_prefix()))->page($issuer,$actor)['items'];
        }catch(DomainException $e){$reason=$e->getMessage();if(in_array($reason,['company_task_billing_denied','company_project_fixed_billing_denied','company_project_access_denied','company_invoice_access_denied'],true)){show_error('Origem ou permissão indisponível nesta empresa.',403);return;}$this->output->set_status_header(in_array($reason,['company_project_fixed_billing_stale','company_project_fixed_billing_conflict','company_project_fixed_billing_immutable','company_project_fixed_billing_overlap','company_sales_origin_changed'],true)?409:422);$error=match($reason){'company_project_fixed_billing_stale'=>'O projeto ou suas tarefas mudaram. Reabra e revise a declaração.','company_project_fixed_billing_immutable'=>'A conversão reservada ou concluída preserva a tarefa e seus timers.','company_project_fixed_billing_not_ready'=>'Conclua o projeto e todas as tarefas antes de faturar.','company_project_fixed_billing_reference_denied'=>'O projeto e o cliente próprio devem pertencer à empresa atual.','issuer_document_invalid_profile','issuer_document_profile_required'=>'Complete o endereço, cidade, estado, CEP e país nas configurações desta empresa antes de preparar a fatura.','company_sales_origin_changed'=>'A identidade, o cliente ou a configuração mudou. Revise a origem antes de emitir.',default=>'Confira valores com ponto decimal, moeda, datas, preço fixo e tributos. A tentativa não foi confirmada.'};}
        catch(Throwable $e){log_message('error','Company fixed project billing unconfirmed; no contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra para retomar o mesmo vínculo antes de tentar novamente.';}
        $this->load->view('admin/companies/project_fixed_billing',['issuer'=>$issuer,'project'=>$project,'billing'=>$billing,'projects'=>$projects,'currencies'=>$currencies,'error'=>$error,'company_project_fixed_billing_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32))]);
    }
}
