<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Company_contracts extends AdminController
{
    public function index()
    {
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
        require_once APPPATH.'services/CompanyContracts.php';$ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());if(!$acl->can($issuer,$actor,'contracts','view')){show_error('Sem permissão para contratos desta empresa.',403);return;}
        $this->output->set_header('Cache-Control: no-store');$csrf=$this->session->userdata('company_contracts_csrf');if(!is_string($csrf)||strlen($csrf)!==64){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_contracts_csrf',$csrf);}
        $s=new CompanyContracts($this->db,db_prefix());$error=null;$contract=null;$directory=['items'=>[],'next'=>null];$clients=[];
        try{
            if($this->input->method(true)==='POST'){
                $token=$this->input->post('company_contracts_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}$action=$this->input->post('action',false);$key=$this->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_operation_invalid_input');$id=$this->input->post('contract_id',false);$rev=$this->input->post('revision',false);
                if(in_array($action,['create','update'],true)){$input=$this->input->post('contract',false);if(!is_array($input))throw new DomainException('company_operation_invalid_input');$id=$action==='create'?$s->create($issuer,$actor,$input,$key):$s->update($issuer,$actor,$id,$rev,$input,$key);}
                elseif(is_string($action))$id=$s->transition($issuer,$actor,$id,$rev,$action,$key);else throw new DomainException('company_operation_invalid_input');CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_contracts?contract='.(int)$id),'location',303);return;
            }elseif(!in_array($this->input->method(true),['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $id=$this->input->get('contract',false);if($id!==null){$contract=$s->get($issuer,$actor,$id);if($this->input->get('pdf',false)==='1'){require_once APPPATH.'services/CompanyContractsPdf.php';$bytes=CompanyContractsPdf::render($contract);$s->confirmDownload($issuer,$actor,$id,$contract['revision']);$this->output->set_header('X-Content-Type-Options: nosniff');$this->output->set_header('Content-Disposition: attachment; filename="contrato-'.(int)$contract['number'].'.pdf"');$this->output->set_content_type('application/pdf');$this->output->set_output($bytes);return;}}
            $directory=$s->page($issuer,$actor,$this->input->get('after',false)??0,(string)($this->input->get('search',false)??''));if($acl->can($issuer,$actor,'customers','view'))$clients=(new CompanyClients($this->db,db_prefix()))->page($issuer,$actor,0,100,(string)($this->input->get('client_search',false)??''))['items'];
        }catch(DomainException $e){$this->output->set_status_header(409);$error=match($e->getMessage()){'company_operation_stale'=>'O contrato mudou. Reabra antes de salvar.','company_contract_immutable'=>'O estado do contrato impede esta edição. Use uma cópia para preparar novo rascunho.','company_contract_identity_required'=>'Complete a identidade da empresa antes de ativar o contrato.','company_contract_pdf_denied'=>'PDF disponível somente após ativação e revalidação do contrato.',default=>'Dados, referências ou permissões inválidos. Nenhuma alteração foi confirmada.'};}catch(Throwable $e){log_message('error','Company contract operation unconfirmed; no contents logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar a operação. Reabra antes de tentar novamente.';}
        $this->load->view('admin/companies/contracts',['issuer'=>$issuer,'actor'=>$actor,'contract'=>$contract,'directory'=>$directory,'clients'=>$clients,'error'=>$error,'company_contracts_csrf'=>$csrf,'request_key'=>bin2hex(random_bytes(32)),'can_create'=>$acl->can($issuer,$actor,'contracts','create'),'can_edit'=>$acl->can($issuer,$actor,'contracts','edit')]);
    }
}
