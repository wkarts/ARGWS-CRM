<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/CompanyApiService.php';

/** Dedicated bearer API; cookies, selected company and legacy API tokens confer no access. */
class Company_api extends CI_Controller
{
    private bool $ready=false;
    public function __construct(){parent::__construct();$this->ready=class_exists('CompanyApiScope',false)&&CompanyApiScope::active();}
    private function execute(string $action,$id=''):void
    {
        $this->output->set_content_type('application/json','UTF-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');
        try{if(!$this->ready)throw new RuntimeException('issuer_api_unavailable');$method=$this->input->method(true);if(!in_array($method,$action==='invoices'&&$id===''?['GET','HEAD','POST']:['GET','HEAD'],true)){$this->output->set_header('Allow: '.($action==='invoices'&&$id===''?'GET, HEAD, POST':'GET, HEAD'))->set_status_header(405)->set_output('{"error":"method_not_allowed"}');return;}
            $authorization=$this->input->get_request_header('Authorization',false);if(!is_string($authorization)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$authorization,$m))throw new DomainException('company_api_unauthenticated');$service=new CompanyApiService($this->db,db_prefix());$secret=$m[1];$status=200;
            if($action==='invoices'&&$method==='POST'){$type=$this->input->get_request_header('Content-Type',false);if(!is_string($type)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type))throw new DomainException('company_api_invalid_input');$body=file_get_contents('php://input',false,null,0,262145);if(!is_string($body)||strlen($body)>262144)throw new DomainException('company_api_invalid_input');try{$input=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('company_api_invalid_input');}if(!is_array($input)||array_is_list($input))throw new DomainException('company_api_invalid_input');$key=$this->input->get_request_header('Idempotency-Key',false);if(!is_string($key))throw new DomainException('company_api_invalid_input');$result=$service->createInvoice($secret,$input,$key);$status=$result['replayed']?200:201;}
            elseif($action==='invoices')$result=$id===''?$service->invoices($secret,$this->input->get('after',false)??0):$service->invoice($secret,$id);
            elseif($action==='invoice_pdf'){$pdf=$service->invoicePdf($secret,$id);$this->output->set_header('Content-Disposition: attachment; filename="fatura.pdf"')->set_content_type('application/pdf')->set_output($method==='HEAD'?'':$pdf);return;}
            else{$csv=$this->input->get('format',false)==='csv';$result=$service->financial($secret,$csv);if($csv){$this->output->set_header('Content-Disposition: attachment; filename="posicao-financeira.csv"')->set_content_type('text/csv','UTF-8')->set_output($method==='HEAD'?'':$result);return;}}
            $this->output->set_status_header($status)->set_output($method==='HEAD'?'':json_encode(['api_version'=>1,'data'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException $e){$reason=$e->getMessage();$status=match($reason){'company_api_unauthenticated'=>401,'company_api_access_denied','company_api_scope_denied','company_invoice_access_denied','company_invoice_recipient_denied','issuer_document_scope_mismatch'=>403,'company_invoice_request_conflict'=>409,default=>422};if($status===401)$this->output->set_header('WWW-Authenticate: Bearer');$code=match($status){401=>'unauthenticated',403=>'forbidden',409=>'idempotency_conflict',default=>'validation_or_balance_review'};$this->output->set_status_header($status)->set_output(json_encode(['error'=>$code]));}
        catch(Throwable $e){log_message('error','Company API request failed; no authorization header, body or financial data logged.');$this->output->set_status_header(503)->set_output('{"error":"temporarily_unavailable"}');}
    }
    public function invoices($id=''){ $this->execute('invoices',$id); }
    public function invoice_pdf($id=''){ $this->execute('invoice_pdf',$id); }
    public function financial(){ $this->execute('financial'); }
}
