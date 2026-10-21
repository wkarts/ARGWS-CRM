<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once APPPATH.'services/CompanyApiService.php';require_once APPPATH.'services/CompanyFinancialApiService.php';

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
    private function executeFinancial(string $action,$id=''):void
    {
        $this->output->set_content_type('application/json','UTF-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');
        try{
            if(!$this->ready)throw new RuntimeException('issuer_api_unavailable');$method=$this->input->method(true);
            $allowed=match($action){'payments'=>['GET','HEAD','POST'],'credits'=>$id===''?['GET','HEAD','POST']:['GET','HEAD'],'credit_pdf'=>['GET','HEAD'],default=>['POST']};
            if(!in_array($method,$allowed,true)){$this->output->set_header('Allow: '.implode(', ',$allowed))->set_status_header(405)->set_output('{"error":"method_not_allowed"}');return;}
            $authorization=$this->input->get_request_header('Authorization',false);if(!is_string($authorization)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$authorization,$m))throw new DomainException('company_api_unauthenticated');$secret=$m[1];$service=new CompanyFinancialApiService($this->db,db_prefix());$status=200;
            if($method==='POST'){
                $type=$this->input->get_request_header('Content-Type',false);if(!is_string($type)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type))throw new DomainException('company_api_invalid_input');$body=file_get_contents('php://input',false,null,0,262145);if(!is_string($body)||strlen($body)>262144)throw new DomainException('company_api_invalid_input');
                try{$object=json_decode($body,false,32,JSON_THROW_ON_ERROR);if(!$object instanceof stdClass)throw new RuntimeException();$input=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('company_api_invalid_input');}
                $key=$this->input->get_request_header('Idempotency-Key',false);if(!is_string($key))throw new DomainException('company_api_invalid_input');
                $result=match($action){'payments'=>$service->recordManual($secret,$id,$input,$key),'credits'=>$service->issueCredit($secret,$input,$key),'credit_void'=>$input===[]?$service->voidCredit($secret,$id,$key):throw new DomainException('company_api_invalid_input'),default=>$service->moveCredit($secret,$action==='payment_reverse'?'manual_reverse':substr($action,7),$id,$input,$key)};$status=$result['replayed']?200:201;
            }elseif($action==='credit_pdf'){$bytes=$service->creditPdf($secret,$id);$this->output->set_status_header(200)->set_header('Content-Disposition: attachment; filename="nota-credito.pdf"')->set_content_type('application/pdf')->set_output($method==='HEAD'?'':$bytes);return;}
            elseif($action==='payments')$result=$service->payments($secret,$id,$this->input->get('after',false)??0);
            else $result=$id===''?$service->credits($secret,$this->input->get('after',false)??0):$service->credit($secret,$id);
            $this->output->set_status_header($status)->set_output($method==='HEAD'?'':json_encode(['api_version'=>1,'data'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException|InvalidArgumentException $e){$reason=$e->getMessage();$status=$reason==='company_api_unauthenticated'?401:(str_ends_with($reason,'access_denied')||$reason==='company_api_scope_denied'||$reason==='issuer_document_scope_mismatch'?403:(str_ends_with($reason,'conflict')||in_array($reason,['company_payment_provider_review_required','company_credit_provider_review_required','company_credit_has_history','company_api_resource_changed'],true)?409:422));if($status===401)$this->output->set_header('WWW-Authenticate: Bearer');$code=match($status){401=>'unauthenticated',403=>'forbidden',409=>'conflict_or_financial_review',default=>'validation_or_balance_review'};$this->output->set_status_header($status)->set_output(json_encode(['error'=>$code]));}
        catch(Throwable $e){log_message('error','Company financial API request failed; credential, body and financial data omitted.');$this->output->set_status_header(503)->set_output('{"error":"temporarily_unavailable"}');}
    }
    private function executeDraft(string $action,$id=''):void
    {
        $this->output->set_content_type('application/json','UTF-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');
        try{
            if(!$this->ready)throw new RuntimeException('issuer_api_unavailable');$verb=$this->input->method(true);$allowed=$action==='drafts'?($id===''?['GET','HEAD','POST']:['GET','HEAD']):['POST'];
            if(!in_array($verb,$allowed,true)){$this->output->set_header('Allow: '.implode(', ',$allowed))->set_status_header(405)->set_output('{"error":"method_not_allowed"}');return;}
            $header=$this->input->get_request_header('Authorization',false);if(!is_string($header)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$header,$m))throw new DomainException('company_api_unauthenticated');require_once APPPATH.'services/CompanyInvoiceDraftsApi.php';$service=new CompanyInvoiceDraftsApi($this->db,db_prefix());
            if($verb==='POST'){
                $type=$this->input->get_request_header('Content-Type',false);if(!is_string($type)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type))throw new DomainException('company_api_invalid_input');$raw=file_get_contents('php://input',false,null,0,262145);if(!is_string($raw)||strlen($raw)>262144)throw new DomainException('company_api_invalid_input');try{$object=json_decode($raw,false,32,JSON_THROW_ON_ERROR);if(!$object instanceof stdClass)throw new RuntimeException();$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $bad){throw new DomainException('company_api_invalid_input');}$key=$this->input->get_request_header('Idempotency-Key',false);if(!is_string($key))throw new DomainException('company_api_invalid_input');$value=$service->write($m[1],$action==='drafts'?'create':substr($action,6),$id,$input,$key);
            }else$value=$service->read($m[1],$id===''?null:$id,$this->input->get('after',false)??0);
            $this->output->set_status_header(200)->set_output($verb==='HEAD'?'':json_encode(['api_version'=>1,'data'=>$value],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException|InvalidArgumentException $e){$reason=$e->getMessage();$status=$reason==='company_api_unauthenticated'?401:(str_ends_with($reason,'denied')||$reason==='company_api_scope_denied'?403:(str_ends_with($reason,'unavailable')?404:(str_contains($reason,'conflict')||str_contains($reason,'stale')||str_contains($reason,'origin_changed')?409:422)));if($status===401)$this->output->set_header('WWW-Authenticate: Bearer');$this->output->set_status_header($status)->set_output(json_encode(['error'=>match($status){401=>'unauthenticated',403=>'forbidden',404=>'not_found',409=>'conflict_or_review',default=>'validation_review'}]));}
        catch(Throwable $e){log_message('error','Company draft API unconfirmed; credentials and document content omitted.');$this->output->set_status_header(503)->set_output('{"error":"temporarily_unavailable"}');}
    }
    public function drafts($id=''){$this->executeDraft('drafts',$id);}
    public function draft_change($id=''){$this->executeDraft('draft_change',$id);}
    public function draft_cancel($id=''){$this->executeDraft('draft_cancel',$id);}
    public function draft_issue($id=''){$this->executeDraft('draft_issue',$id);}
    public function expenses($id=''){require_once APPPATH.'services/CompanyExpenseApiHttp.php';CompanyExpenseApiHttp::execute($this,$this->ready,'expenses',$id);}
    public function expense_categories($id=''){require_once APPPATH.'services/CompanyExpenseApiHttp.php';CompanyExpenseApiHttp::execute($this,$this->ready,'expense_categories',$id);}
    public function expense_files($id='',$file=''){require_once APPPATH.'services/CompanyExpenseApiHttp.php';CompanyExpenseApiHttp::execute($this,$this->ready,'expense_files',$id,$file);}
    public function local_noncash($id=''){require_once APPPATH.'services/CompanyLocalNoncashApiHttp.php';CompanyLocalNoncashApiHttp::execute($this,$this->ready,$id);}
    public function payments($id=''){ $this->executeFinancial('payments',$id); }
    public function payment_reverse($id=''){ $this->executeFinancial('payment_reverse',$id); }
    public function credits($id=''){ $this->executeFinancial('credits',$id); }
    public function credit_pdf($id=''){ $this->executeFinancial('credit_pdf',$id); }
    public function credit_apply($id=''){ $this->executeFinancial('credit_apply',$id); }
    public function credit_unapply($id=''){ $this->executeFinancial('credit_unapply',$id); }
    public function credit_refund($id=''){ $this->executeFinancial('credit_refund',$id); }
    public function credit_refund_reverse($id=''){ $this->executeFinancial('credit_refund_reverse',$id); }
    public function credit_void($id=''){ $this->executeFinancial('credit_void',$id); }
    public function invoices($id=''){ $this->execute('invoices',$id); }
    public function invoice_pdf($id=''){ $this->execute('invoice_pdf',$id); }
    public function financial(){ $this->execute('financial'); }
}
