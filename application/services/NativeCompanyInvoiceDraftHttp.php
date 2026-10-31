<?php
declare(strict_types=1);

/** Draft lifecycle on the canonical Invoices::drafts route. */
final class NativeCompanyInvoiceDraftHttp
{
    public function __construct(private $ci){}
    public function index():void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Contexto indisponível.',503);return;}
        require_once __DIR__.'/CompanyInvoiceDrafts.php';require_once __DIR__.'/CompanyClients.php';require_once __DIR__.'/CompanyInvoiceCopies.php';
        $ctx=CompanyRequestScope::context();$i=$ctx->issuerId();$a=$ctx->staffId();$acl=new IssuerResourceAccess($this->ci->db,db_prefix());
        foreach([['invoices','view'],['customers','view']]as[$feature,$cap])if(!$acl->can($i,$a,$feature,$cap)){show_error('Sem acesso aos rascunhos desta empresa.',403);return;}
        $this->ci->output->set_header('Cache-Control: no-store')->set_header('Pragma: no-cache')->set_header('Referrer-Policy: no-referrer');
        $s=new CompanyInvoiceDrafts($this->ci->db,db_prefix());$csrf=$this->ci->session->userdata('company_invoice_drafts_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->ci->session->set_userdata('company_invoice_drafts_csrf',$csrf);}
        $draft=null;$copy=null;$error=null;$attempt=null;$page=['items'=>[],'next'=>null];$clients=[];$currencies=[];
        try{
            $method=$this->ci->input->method(true);
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($this->ci);$token=$this->ci->input->post('company_invoice_drafts_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Reabra este formulário antes de continuar.',403);return;}
                $action=$this->ci->input->post('action',false);$key=$this->ci->input->post('request_key',false);$attempt=CompanyInvoiceDraftsInput::key($key);
                if(in_array($action,['create','change'],true)){$payload=$this->ci->input->post('payload',false);if(!is_array($payload))throw new DomainException('company_invoice_draft_invalid');if($action==='create')$id=$s->create($i,$a,$payload,$key);else{$id=IssuerPolicy::id($this->ci->input->post('draft_id',false));$s->change($i,$a,$id,$this->ci->input->post('revision',false),$payload,$key);}}
                elseif($action==='copy_invoice'){$source=IssuerPolicy::id($this->ci->input->post('source_invoice_id',false));$review=$this->ci->input->post('review',false);if(!is_array($review))throw new DomainException('company_invoice_draft_invalid');$id=$s->copyInvoice($i,$a,$source,$review,$key);}
                elseif(in_array($action,['cancel','issue'],true)){$id=IssuerPolicy::id($this->ci->input->post('draft_id',false));$revision=CompanyInvoiceDraftsInput::revision($this->ci->input->post('revision',false));if($action==='issue'){if($this->ci->input->post('confirm_issue',false)!=='1')throw new DomainException('company_invoice_draft_invalid');$s->issue($i,$a,$id,$revision,$key);}else$s->change($i,$a,$id,$revision,null,$key);}
                else{show_404();return;}CompanyRequestScope::invalidateCaches($this->ci);redirect(CompanyRequestScope::adminUrl($this->ci,'invoices/drafts?draft='.(int)$id),'location',303);return;
            }
            if(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $id=$this->ci->input->get('draft',false);$source=$this->ci->input->get('copy_invoice',false);$receiptKey=$this->ci->input->get('attempt',false);if(count(array_filter([$id,$source,$receiptKey],fn($v)=>$v!==null))>1){show_404();return;}
            if($receiptKey!==null)$draft=$s->receipt($i,$a,CompanyInvoiceDraftsInput::key($receiptKey));elseif($id!==null)$draft=$s->document($i,$a,IssuerPolicy::id($id));elseif($source!==null)$copy=(new CompanyInvoiceCopies($this->ci->db,db_prefix()))->preview($i,$a,IssuerPolicy::id($source));
            $page=$s->page($i,$a,$this->ci->input->get('after',false)??0);$clients=(new CompanyClients($this->ci->db,db_prefix()))->page($i,$a)['items'];$currencies=(new CompanyInvoices($this->ci->db,db_prefix()))->currencies($i,$a);
            if($method==='HEAD'){$this->ci->output->set_output('');return;}
        }catch(DomainException|InvalidArgumentException $e){$reason=$e->getMessage();if(in_array($reason,['company_invoice_draft_unavailable','company_invoice_copy_unavailable'],true)){show_404();return;}if(str_ends_with($reason,'denied')||str_ends_with($reason,'access_denied')){show_error('Sem permissão atual para esta operação.',403);return;}$this->ci->output->set_status_header(str_contains($reason,'conflict')||str_contains($reason,'stale')||str_contains($reason,'origin_changed')?409:422);$error=str_contains($reason,'origin_changed')?'A identidade ou as configurações mudaram. Reabra e salve a revisão atual antes de emitir.':'Confira os campos, a revisão e o recibo desta tentativa antes de continuar.';}
        catch(Throwable $e){log_message('error','Private invoice draft unconfirmed; no document content logged.');$this->ci->output->set_status_header(503);$error='Não foi possível confirmar esta tentativa. Reabra a lista e confira o documento antes de iniciar outra solicitação.';}
        $this->ci->load->view('admin/invoices/native_drafts',['issuer'=>$i,'draft'=>$draft,'copy'=>$copy,'page'=>$page,'clients'=>$clients,'currencies'=>$currencies,'error'=>$error,'attempt'=>$attempt,'request_key'=>bin2hex(random_bytes(32)),'csrf'=>$csrf,'can_create'=>$acl->can($i,$a,'invoices','create'),'can_edit'=>$acl->can($i,$a,'invoices','edit'),'can_cancel'=>$acl->can($i,$a,'invoices','delete')]);
    }
}
