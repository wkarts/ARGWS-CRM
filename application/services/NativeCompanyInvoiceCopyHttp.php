<?php
declare(strict_types=1);

/** Reviewed copy/new issuance on the canonical Invoices::copy route. */
final class NativeCompanyInvoiceCopyHttp
{
    public function __construct(private $ci){}
    public function index($routeSource=null):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Contexto indisponível.',503);return;}
        require_once __DIR__.'/CompanyInvoiceCopies.php';$context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();$acl=new IssuerResourceAccess($this->ci->db,db_prefix());
        foreach([['invoices','view'],['invoices','create'],['customers','view']]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap)){show_error('Sem permissão para revisar uma nova emissão nesta empresa.',403);return;}
        $this->ci->output->set_header('Cache-Control: no-store');$s=new CompanyInvoiceCopies($this->ci->db,db_prefix());$csrf=$this->ci->session->userdata('company_invoice_copies_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->ci->session->set_userdata('company_invoice_copies_csrf',$csrf);}$source=null;$preview=null;$receipt=null;$error=null;$attempt=null;
        try{
            $verb=$this->ci->input->method(true);
            if($verb==='POST'){
                CompanyRequestScope::assertNativeMutation($this->ci);$token=$this->ci->input->post('company_invoice_copies_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Reabra a revisão antes de emitir.',403);return;}
                if($this->ci->input->post('action',false)!=='issue_reviewed_copy'){show_404();return;}$source=IssuerPolicy::id($this->ci->input->post('source_invoice_id',false));$review=$this->ci->input->post('review',false);$key=$this->ci->input->post('request_key',false);if(!is_array($review)||!is_string($key))throw new DomainException('company_invoice_copy_invalid');$attempt=CompanyInvoiceCopiesInput::key($key);$result=$s->create($issuer,$actor,$source,$review,$attempt);CompanyRequestScope::invalidateCaches($this->ci);redirect(CompanyRequestScope::adminUrl($this->ci,'invoices/copy?receipt='.$result['request_key']),'location',303);return;
            }
            if(!in_array($verb,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $candidate=$routeSource?:$this->ci->input->get('source',false);$key=$this->ci->input->get('receipt',false);if(($candidate!==null&&$candidate!==''&&$key!==null)||(($candidate===null||$candidate==='')&&$key===null)||($candidate!==null&&!is_scalar($candidate))||($key!==null&&!is_string($key))){show_404();return;}
            if($key!==null)$receipt=$s->receipt($issuer,$actor,$key);else{$source=IssuerPolicy::id($candidate);$preview=$s->preview($issuer,$actor,$source);}
            if($verb==='HEAD'){$this->ci->output->set_output('');return;}
        }catch(DomainException $e){if(in_array($e->getMessage(),['company_invoice_copy_unavailable','company_invoice_copy_recipient_unavailable'],true)){show_404();return;}if(in_array($e->getMessage(),['company_invoice_copy_access_denied','company_invoice_access_denied','issuer_access_denied'],true)){show_error('Revisão indisponível nesta empresa.',403);return;}$this->ci->output->set_status_header(str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:422);$error=match($e->getMessage()){'company_invoice_copy_stale'=>'A origem mudou. Reabra a revisão antes de uma nova emissão.','company_invoice_copy_conflict'=>'Esta chave pertence a uma tentativa diferente. Confira o recibo original.','company_invoice_copy_source_review','company_invoice_invalid_input'=>'A origem excede o perfil de linhas permitido ou possui valores divergentes.','issuer_document_invalid_profile','issuer_document_profile_required'=>'Complete a identidade e o endereço atuais da empresa antes de emitir.',default=>'Confira as datas, a observação, a confirmação dos valores e as permissões antes de tentar novamente.'};}
        catch(Throwable $e){log_message('error','Reviewed invoice copy unconfirmed; no financial values logged.');$this->ci->output->set_status_header(503);$error='Não foi possível confirmar esta tentativa. Confira o recibo desta chave antes de iniciar outra emissão.';}
        $this->ci->load->view('admin/invoices/native_copy',['issuer'=>$issuer,'source'=>$source,'preview'=>$preview,'receipt'=>$receipt,'error'=>$error,'attempt'=>$attempt,'request_key'=>bin2hex(random_bytes(32)),'company_invoice_copies_csrf'=>$csrf]);
    }
}
