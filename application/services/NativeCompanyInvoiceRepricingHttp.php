<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceRepricing.php';

/** Uses the existing recurring-invoices route and sealed native company context. */
final class NativeCompanyInvoiceRepricingHttp
{
    public function __construct(private $ci) {}
    public function index():void
    {
        if(!CompanyRequestScope::sealedFor($this->ci)){show_error('Contexto indisponível.',503);return;}
        $ctx=CompanyRequestScope::context();$issuer=$ctx->issuerId();$actor=$ctx->staffId();
        $acl=new IssuerResourceAccess($this->ci->db,db_prefix());
        if(!$acl->can($issuer,$actor,'invoices','view')){show_error('Reajustes indisponíveis.',403);return;}
        $can_manage=$acl->can($issuer,$actor,'invoices','create')&&$acl->can($issuer,$actor,'invoices','edit')&&$acl->can($issuer,$actor,'customers','view');
        $this->ci->output->set_header('Cache-Control: no-store');
        $csrf=$this->ci->session->userdata('company_invoice_reprice_csrf');
        if(!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)){$csrf=bin2hex(random_bytes(32));$this->ci->session->set_userdata('company_invoice_reprice_csrf',$csrf);}
        $service=new CompanyInvoiceRepricing($this->ci->db,db_prefix());$directory=[];$history=[];$proposal=null;$error=null;
        $url=CompanyRequestScope::adminUrl($this->ci,'invoices/recurring?reprice=1');
        try{
            $method=$this->ci->input->method(true);
            if($method==='POST'){
                CompanyRequestScope::assertNativeMutation($this->ci);
                $token=$this->ci->input->post('company_invoice_reprice_csrf',false);
                if(!$can_manage||!is_string($token)||!hash_equals($csrf,$token)){show_error('Recarregue antes de salvar.',403);return;}
                $action=$this->ci->input->post('action',false);$key=$this->ci->input->post('request_key',false);
                if($action==='review_link'){
                    foreach(['item_id','payload_hash','link_revision']as$field)if(!is_string($this->ci->input->post($field,false)))throw new DomainException('invoice_reprice_invalid');
                    $service->reviewLink($issuer,$actor,$this->ci->input->post('schedule_id',false),$this->ci->input->post('line_index',false),$this->ci->input->post('item_id',false),$this->ci->input->post('payload_hash',false),$this->ci->input->post('link_revision',false));
                }else{
                    if(!is_string($key))throw new DomainException('invoice_reprice_invalid');
                    if($action==='preview'){
                        $json=$this->ci->input->post('proposal_input',false);
                        if(!is_string($json)||strlen($json)>1048576)throw new DomainException('invoice_reprice_invalid');
                        try{$input=json_decode($json,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new DomainException('invoice_reprice_invalid');}
                        if(!is_array($input))throw new DomainException('invoice_reprice_invalid');$service->preview($issuer,$actor,$input,$key);
                    }elseif(in_array($action,['confirm','cancel'],true)){
                        $revision=$this->ci->input->post('revision',false);
                        if(!is_string($revision)||$action==='confirm'&&$this->ci->input->post('reviewed',false)!=='1')throw new DomainException('invoice_reprice_review_required');
                        $service->$action($issuer,$actor,$key,$revision);
                    }else throw new DomainException('invoice_reprice_invalid');
                    $url.='&proposal='.rawurlencode($key);
                }
                CompanyRequestScope::invalidateCaches($this->ci);redirect($url,'location',303);return;
            }
            if(!in_array($method,['GET','HEAD'],true)){show_error('Método não permitido.',405);return;}
            $search=$this->ci->input->get('catalog_search',false);
            if($search!==null){
                if(!$can_manage||!is_string($search)||strlen($search)>200)throw new DomainException('invoice_reprice_denied');
                $items=(new NativeCompanyCatalogUi($this->ci->db,db_prefix(),$issuer,$actor))->searchForInvoiceEditor($search);
                $public=array_map(static fn($r)=>['id'=>(string)$r['id'],'description'=>(string)$r['description'],'rate'=>(string)$r['rate']],$items);
                $this->ci->output->set_content_type('application/json')->set_output(json_encode($public,JSON_THROW_ON_ERROR));return;
            }
            $key=$this->ci->input->get('proposal',false);
            if($key!==null){if(!is_string($key))throw new DomainException('invoice_reprice_invalid');$proposal=$service->proposal($issuer,$actor,$key);}
            $directory=$service->directory($issuer,$actor);$history=$service->proposals($issuer,$actor);
            if($method==='HEAD'){$this->ci->output->set_output('');return;}
        }catch(DomainException $e){
            $denied=str_contains($e->getMessage(),'denied');$stale=str_contains($e->getMessage(),'stale')||str_contains($e->getMessage(),'conflict');
            $this->ci->output->set_status_header($denied?403:($stale?409:422));
            $error=$stale?'A base ou a revisão mudou. Reabra as recorrências e faça uma nova simulação. Nenhum reajuste parcial foi aplicado.':'Não foi possível confirmar a solicitação. Confira os vínculos revisados, as linhas selecionadas, os preços, as quantidades e o horário.';
        }catch(Throwable $e){log_message('error','Recurring repricing unconfirmed; financial values omitted.');$this->ci->output->set_status_header(503);$error='Operação não confirmada. Consulte o histórico antes de repetir a solicitação.';}
        $this->ci->load->view('admin/invoices/native_repricing',compact('directory','history','proposal','error','can_manage','csrf','actor','url'));
    }
}
