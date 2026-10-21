<?php

defined('BASEPATH') or exit('No direct script access allowed');
/** Finite sealed review/new-numbered-issuance route. Native CSRF remains enforced by CI. */
class Company_invoice_copies extends AdminController
{
    public function index()
    {
        if(func_num_args()!==0){show_404();return;}
        if(!CompanyRequestScope::sealedFor($this)){show_error('Contexto indisponível.',503);return;}
        require_once APPPATH.'services/CompanyInvoiceCopies.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->db,db_prefix());foreach([['invoices','view'],['invoices','create'],['customers','view']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap)){show_error('Sem permissão para revisar uma nova emissão nesta empresa.',403);return;}
        $this->output->set_header('Cache-Control: no-store');$s=new CompanyInvoiceCopies($this->db,db_prefix());$csrf=$this->session->userdata('company_invoice_copies_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->session->set_userdata('company_invoice_copies_csrf',$csrf);}$source=null;$preview=null;$receipt=null;$error=null;$attempt=null;
        try{
            $verb=$this->input->method(true);
            if($verb==='POST'){
                $token=$this->input->post('company_invoice_copies_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Reabra a revisão antes de emitir.',403);return;}
                if($this->input->post('action',false)!=='issue_reviewed_copy'){show_404();return;}$source=IssuerPolicy::id($this->input->post('source_invoice_id',false));$review=$this->input->post('review',false);$key=$this->input->post('request_key',false);if(!is_array($review)||!is_string($key))throw new DomainException('company_invoice_copy_invalid');$attempt=CompanyInvoiceCopiesInput::key($key);$result=$s->create($issuer,$actor,$source,$review,$attempt);CompanyRequestScope::invalidateCaches($this);redirect(CompanyRequestScope::adminUrl($this,'company_invoice_copies?receipt='.$result['request_key']),'location',303);return;
            }
            if(!in_array($verb,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $candidate=$this->input->get('source',false);$key=$this->input->get('receipt',false);if(($candidate!==null&&$key!==null)||($candidate===null&&$key===null)||($candidate!==null&&!is_scalar($candidate))||($key!==null&&!is_string($key))){show_404();return;}
            if($key!==null)$receipt=$s->receipt($issuer,$actor,$key);else{$source=IssuerPolicy::id($candidate);$preview=$s->preview($issuer,$actor,$source);}
        }catch(DomainException $e){
            if(in_array($e->getMessage(),['company_invoice_copy_unavailable','company_invoice_copy_recipient_unavailable'],true)){show_404();return;}
            if(in_array($e->getMessage(),['company_invoice_copy_access_denied','company_invoice_access_denied','issuer_access_denied'],true)){show_error('Revisão indisponível nesta empresa.',403);return;}
            $this->output->set_status_header(str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:422);$error=match($e->getMessage()){'company_invoice_copy_stale'=>'A origem mudou. Reabra a revisão antes de uma nova emissão.','company_invoice_copy_conflict'=>'Esta chave pertence a uma tentativa diferente. Confira o recibo original.','company_invoice_copy_source_review','company_invoice_invalid_input'=>'A origem excede o perfil de linhas permitido ou possui valores divergentes. Confira descrições complementares de até20.000 bytes, unidades de até40 caracteres, opcionais, tributos e totais.','issuer_document_invalid_profile','issuer_document_profile_required'=>'Complete a identidade e o endereço atuais da empresa antes de emitir.',default=>'Confira as datas, a observação, a confirmação dos valores e as permissões antes de tentar novamente.'};
        }catch(Throwable $e){log_message('error','Reviewed invoice copy unconfirmed; no financial values logged.');$this->output->set_status_header(503);$error='Não foi possível confirmar esta tentativa. Confira o recibo desta chave antes de iniciar outra emissão.';}
        $this->load->view('admin/companies/invoice_copies',['issuer'=>$issuer,'source'=>$source,'preview'=>$preview,'receipt'=>$receipt,'error'=>$error,'attempt'=>$attempt,'request_key'=>bin2hex(random_bytes(32)),'company_invoice_copies_csrf'=>$csrf]);
    }
}
