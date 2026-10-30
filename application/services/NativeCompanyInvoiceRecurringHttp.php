<?php
declare(strict_types=1);

/** Recurring invoice schedules on the canonical Invoices::recurring route. */
final class NativeCompanyInvoiceRecurringHttp
{
    public function __construct(private $ci){}
    public function index($routeId=null):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Contexto indisponível.',503);return;}require_once __DIR__.'/CompanyInvoiceRecurring.php';require_once __DIR__.'/CompanyClients.php';
        $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->ci->db,db_prefix());if(!$acl->can($issuer,$actor,'invoices','view')){show_error('Agenda indisponível.',403);return;}
        $this->ci->output->set_header('Cache-Control: no-store');$s=new CompanyInvoiceRecurring($this->ci->db,db_prefix());$csrf=$this->ci->session->userdata('company_invoice_schedule_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->ci->session->set_userdata('company_invoice_schedule_csrf',$csrf);}
        $error=null;$detail=null;$directory=[];$clients=[];$currencies=[];$contacts=[];$principals=[];$modes_ready=false;$can_manage=$acl->can($issuer,$actor,'invoices','create')&&$acl->can($issuer,$actor,'invoices','edit')&&$acl->can($issuer,$actor,'customers','view');$decrypt=function($value){$this->ci->load->library('encryption');return$this->ci->encryption->decrypt($value);};
        try{
            $method=$this->ci->input->method(true);
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($this->ci);$token=$this->ci->input->post('company_invoice_schedule_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}if(!$can_manage)throw new DomainException('company_invoice_recurring_denied');$action=$this->ci->input->post('action',false);$id=$this->ci->input->post('schedule_id',false);$key=$this->ci->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_invoice_recurring_invalid');
                if($action==='create'){$invoice=$this->ci->input->post('invoice',false);$send=$this->ci->input->post('send',false)==='1';$config=['unit'=>$this->ci->input->post('unit',false),'interval'=>$this->ci->input->post('interval',false),'custom'=>$this->ci->input->post('custom',false)==='1','cycles'=>$this->ci->input->post('cycles',false),'send'=>$send,'contact_id'=>$send?$this->ci->input->post('contact_id',false):0,'base_url'=>$send?rtrim((string)$this->ci->config->base_url(),'/'):''];if($this->ci->input->post('mode',false)!==null){$config['mode']=$this->ci->input->post('mode',false);$config['principal_invoice_id']=$this->ci->input->post('principal_invoice_id',false)??'0';}if(!is_array($invoice))throw new DomainException('company_invoice_recurring_invalid');$id=$s->create($issuer,$actor,$invoice,$config,$key,$send?$decrypt:null);}
                elseif(in_array($action,['pause','resume','cancel'],true))$s->control($issuer,$actor,$id,$this->ci->input->post('revision',false),$action,$key);
                elseif($action==='run'){$r=$s->document($issuer,$actor,$id);if((int)$r['actor_id']!==$actor)throw new DomainException('company_invoice_recurring_denied');$s->run($issuer,$actor,$id,gmdate('Y-m-d'),$decrypt);}else throw new DomainException('company_invoice_recurring_invalid');
                CompanyRequestScope::invalidateCaches($this->ci);redirect(CompanyRequestScope::adminUrl($this->ci,'invoices/recurring/'.(int)$id),'location',303);return;
            }
            if(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}$id=$routeId?:$this->ci->input->get('schedule',false);if($id!==null&&$id!=='')$detail=$s->document($issuer,$actor,$id);$directory=$s->page($issuer,$actor);$modes_ready=(new CompanyInvoiceRecurringModes($this->ci->db,db_prefix()))->enabled();if($can_manage){if($modes_ready)$principals=(new CompanyInvoices($this->ci->db,db_prefix()))->page($issuer,$actor)['items'];$clients=(new CompanyClients($this->ci->db,db_prefix()))->page($issuer,$actor,0,100)['items'];$currencies=(new CompanyInvoices($this->ci->db,db_prefix()))->currencies($issuer,$actor);$contacts=$s->contactOptions($issuer,$actor);}if($method==='HEAD'){$this->ci->output->set_output('');return;}
        }catch(DomainException$e){if(str_contains($e->getMessage(),'denied')){show_error('Agenda indisponível nesta empresa.',403);return;}$this->ci->output->set_status_header(str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:422);$error='Confira a configuração e o estado. Origem ou transporte alterados exigem nova série revisada; o histórico permanece preservado.';}
        catch(Throwable$e){log_message('error','Invoice schedule operation unconfirmed; document and transport values omitted.');$this->ci->output->set_status_header(503);$error='Etapa não confirmada. Uma fatura ou fila pode já estar registrada. Reabra a série e retome a mesma ocorrência.';}
        $this->ci->load->view('admin/invoices/native_recurring',['issuer'=>$issuer,'actor'=>$actor,'detail'=>$detail,'directory'=>$directory,'clients'=>$clients,'currencies'=>$currencies,'contacts'=>$contacts,'principals'=>$principals,'modes_ready'=>$modes_ready,'error'=>$error,'can_manage'=>$can_manage,'company_invoice_schedule_csrf'=>$csrf]);
    }
}
