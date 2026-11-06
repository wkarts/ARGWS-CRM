<?php
declare(strict_types=1);

/** Recurring invoice schedules on the canonical Invoices::recurring route. */
final class NativeCompanyInvoiceRecurringHttp
{
    public function __construct(private $ci){}
    public function index($routeId=null,bool $fragment=false):void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Contexto indisponível.',503);return;}require_once __DIR__.'/CompanyInvoiceRecurring.php';require_once __DIR__.'/CompanyClients.php';
        $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();$acl=new IssuerResourceAccess($this->ci->db,db_prefix());if(!$acl->can($issuer,$actor,'invoices','view')){show_error('Agenda indisponível.',403);return;}
        $this->ci->output->set_header('Cache-Control: no-store');$s=new CompanyInvoiceRecurring($this->ci->db,db_prefix());$csrf=$this->ci->session->userdata('company_invoice_schedule_csrf');if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->ci->session->set_userdata('company_invoice_schedule_csrf',$csrf);}
        $error=null;$detail=null;$directory=[];$clients=[];$currencies=[];$contacts=[];$principals=[];$modes_ready=false;$can_manage=$acl->can($issuer,$actor,'invoices','create')&&$acl->can($issuer,$actor,'invoices','edit')&&$acl->can($issuer,$actor,'customers','view');$decrypt=function($value){$this->ci->load->library('encryption');return$this->ci->encryption->decrypt($value);};
        try{
            $method=$this->ci->input->method(true);
            if($fragment&&!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($this->ci);$token=$this->ci->input->post('company_invoice_schedule_csrf',false);if(!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}if(!$can_manage)throw new DomainException('company_invoice_recurring_denied');$action=$this->ci->input->post('action',false);$id=$this->ci->input->post('schedule_id',false);$key=$this->ci->input->post('request_key',false);if(!is_string($key))throw new DomainException('company_invoice_recurring_invalid');
                if($action==='create'){$invoice=$this->ci->input->post('invoice',false);$send=$this->ci->input->post('send',false)==='1';$config=['unit'=>$this->ci->input->post('unit',false),'interval'=>$this->ci->input->post('interval',false),'custom'=>$this->ci->input->post('custom',false)==='1','cycles'=>$this->ci->input->post('cycles',false),'send'=>$send,'contact_id'=>$send?$this->ci->input->post('contact_id',false):0,'base_url'=>$send?rtrim((string)$this->ci->config->base_url(),'/'):''];if($this->ci->input->post('mode',false)!==null){$config['mode']=$this->ci->input->post('mode',false);$config['principal_invoice_id']=$this->ci->input->post('principal_invoice_id',false)??'0';}if(!is_array($invoice))throw new DomainException('company_invoice_recurring_invalid');$id=$s->create($issuer,$actor,$invoice,$config,$key,$send?$decrypt:null);}
                elseif(in_array($action,['pause','resume','cancel'],true))$s->control($issuer,$actor,$id,$this->ci->input->post('revision',false),$action,$key);
                elseif($action==='run'){$r=$s->document($issuer,$actor,$id);if((int)$r['actor_id']!==$actor)throw new DomainException('company_invoice_recurring_denied');$s->run($issuer,$actor,$id,gmdate('Y-m-d'),$decrypt);}else throw new DomainException('company_invoice_recurring_invalid');
                CompanyRequestScope::invalidateCaches($this->ci);redirect(CompanyRequestScope::adminUrl($this->ci,'invoices/recurring/'.(int)$id),'location',303);return;
            }
            if(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}$id=$routeId?:$this->ci->input->get('schedule',false);if($id!==null&&$id!=='')$detail=$s->document($issuer,$actor,$id);
            elseif($fragment){show_error('Série indisponível.',404);return;}
            elseif($this->ci->input->get('new',false)!=='1'){
                require_once __DIR__.'/NativeCompanyInvoiceRecurringDirectory.php';
                $catalogue=(new NativeCompanyInvoiceRecurringDirectory($this->ci->db,db_prefix()))->catalogue($issuer,$actor);
                if($method==='HEAD'){$this->ci->output->set_output('');return;}
                $this->ci->load->view('admin/invoices/recurring/list',$catalogue+['native_recurring'=>true,'invoiceid'=>'','title'=>_l('invoices_list_recurring'),'can_manage'=>$can_manage,'can_create_invoice'=>$acl->can($issuer,$actor,'invoices','create')]);return;
            }
            $directory=$fragment?[]:$s->page($issuer,$actor);$modes_ready=(new CompanyInvoiceRecurringModes($this->ci->db,db_prefix()))->enabled();if($can_manage&&!$fragment){if($modes_ready)$principals=(new CompanyInvoices($this->ci->db,db_prefix()))->page($issuer,$actor)['items'];$clients=(new CompanyClients($this->ci->db,db_prefix()))->page($issuer,$actor,0,100)['items'];$currencies=(new CompanyInvoices($this->ci->db,db_prefix()))->currencies($issuer,$actor);$contacts=$s->contactOptions($issuer,$actor);}if($method==='HEAD'){$this->ci->output->set_output('');return;}
        }catch(DomainException$e){if(str_contains($e->getMessage(),'denied')){show_error('Agenda indisponível nesta empresa.',403);return;}$this->ci->output->set_status_header(str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict')?409:422);$error='Confira a configuração e o estado. Origem ou transporte alterados exigem nova série revisada; o histórico permanece preservado.';}
        catch(Throwable$e){log_message('error','Invoice schedule operation unconfirmed; document and transport values omitted.');$this->ci->output->set_status_header(503);$error='Etapa não confirmada. Uma fatura ou fila pode já estar registrada. Reabra a série e retome a mesma ocorrência.';}
        $this->ci->load->view('admin/invoices/native_recurring',['native_recurring_fragment'=>$fragment,'issuer'=>$issuer,'actor'=>$actor,'detail'=>$detail,'directory'=>$directory,'clients'=>$clients,'currencies'=>$currencies,'contacts'=>$contacts,'principals'=>$principals,'modes_ready'=>$modes_ready,'error'=>$error,'can_manage'=>$can_manage,'company_invoice_schedule_csrf'=>$csrf]);
    }
    public function preview($schedule):void { $this->index($schedule,true); }
    public function table():void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)||!CompanyRequestScope::nativeFor($this->ci)){show_error('Contexto indisponível.',503);return;}
        $this->ci->output->set_header('Cache-Control: no-store');
        try{
            CompanyRequestScope::assertNativeMutation($this->ci);
            if($this->ci->input->get('recurring',false)!=='1')throw new DomainException('native_company_table_request_invalid');
            $context=CompanyRequestScope::context();$issuer=$context->issuerId();$actor=$context->staffId();
            $input=$this->ci->input->post(null,false)?:[];if(!is_array($input))throw new DomainException('native_company_table_request_invalid');
            unset($input['_company_context'],$input['_native_company_csrf']);if(isset($this->ci->security))unset($input[$this->ci->security->get_csrf_token_name()]);
            require_once __DIR__.'/NativeCompanyInvoiceRecurringDirectory.php';
            $page=(new NativeCompanyInvoiceRecurringDirectory($this->ci->db,db_prefix()))->table($issuer,$actor,$input);
            $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$rows=[];
            $states=['active'=>'Ativa','paused'=>'Pausada','paused_processing'=>'Pausada com ocorrência pendente','processing'=>'Etapa pendente','completed'=>'Ciclos concluídos','cancelled'=>'Cancelada'];
            foreach($page['rows']as$row){
                $id=$row['id'];$url=CompanyRequestScope::adminUrl($this->ci,'invoices/recurring/'.$id);
                $number='<a href="'.$esc($url).'" onclick="init_recurring_schedule('.$id.'); return false;">Série #'.$id.'</a><br><span class="text-muted">'.$esc($states[$row['state']]??$row['state']).'</span><div class="row-options"><a href="'.$esc($url).'">'._l('view').'</a></div>';
                $entry=[$number,app_format_money($row['total'],$row['currency']),$esc($row['year']),$esc($row['customer']),$esc($row['frequency']),$row['cycles_remaining']===null?_l('cycles_infinity'):(string)$row['cycles_remaining'],$row['last_date']===''?'':_d($row['last_date']),$row['next_date']===''?'':_d($row['next_date'])];
                $entry['DT_RowClass']='has-row-options';$rows[]=$entry;
            }
            if(isset($input['order'],$input['last_order_identifier'])&&get_option('save_last_order_for_tables')==='1'){
                require_once __DIR__.'/NativeCompanyTableOrder.php';(new NativeCompanyTableOrder($this->ci->db,db_prefix(),$issuer,$actor))->save('recurring-invoices',$input['order'],8);
            }
            unset($page['rows']);$page['aaData']=$rows;
            $this->ci->output->set_content_type('application/json')->set_output(json_encode($page,JSON_THROW_ON_ERROR));
        }catch(DomainException $error){$message=$error->getMessage();$status=str_contains($message,'denied')||str_contains($message,'csrf')||str_contains($message,'access')?403:(str_contains($message,'stale')||str_contains($message,'changed')?409:422);show_error('Lista de recorrências indisponível ou solicitação inválida nesta empresa.',$status);}
        catch(Throwable $error){log_message('error','Recurring directory read unconfirmed; document and transport values omitted.');show_error('Não foi possível confirmar a lista de recorrências.',503);}
    }
}
