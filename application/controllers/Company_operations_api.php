<?php

defined('BASEPATH') or exit('No direct script access allowed');require_once APPPATH.'services/CompanyOperationsApi.php';
require_once APPPATH.'services/CompanyLeadsHttpApi.php';
class Company_operations_api extends CI_Controller
{
    use CompanyLeadsHttpApi;
    private bool $ready=false;
    public function __construct(){parent::__construct();$this->ready=class_exists('CompanyOperationsApiScope',false)&&CompanyOperationsApiScope::active();}
    private function execute(string $domain,$id=''):void
    {
        $this->output->set_content_type('application/json','UTF-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');
        try{if(!$this->ready)throw new RuntimeException('issuer_operations_api_unavailable');$method=$this->input->method(true);if(!in_array($method,['GET','HEAD','POST'],true)){$this->output->set_header('Allow: GET, HEAD, POST')->set_status_header(405)->set_output('{"error":"method_not_allowed"}');return;}$header=$this->input->get_request_header('Authorization',false);if(!is_string($header)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$header,$m))throw new DomainException('company_api_unauthenticated');$service=new CompanyOperationsApi($this->db,db_prefix());
            if($method==='POST'){$type=$this->input->get_request_header('Content-Type',false);if(!is_string($type)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type))throw new DomainException('company_api_invalid_input');$bytes=file_get_contents('php://input',false,null,0,262145);if(!is_string($bytes)||strlen($bytes)>262144)throw new DomainException('company_api_invalid_input');try{$body=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('company_api_invalid_input');}if(!is_array($body)||array_is_list($body))throw new DomainException('company_api_invalid_input');$key=$this->input->get_request_header('Idempotency-Key',false);if(!is_string($key))throw new DomainException('company_api_invalid_input');$result=$service->write($m[1],$domain,$id===''?null:$id,$body,$key);}
            else{$search=$this->input->get('search',false)??'';if(!is_string($search))throw new DomainException('company_api_invalid_input');$result=$service->read($m[1],$domain,$id===''?null:$id,$this->input->get('after',false)??0,$this->input->get('project_id',false),$search);}$this->output->set_status_header(200)->set_output($method==='HEAD'?'':json_encode(['api_version'=>1,'data'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException $e){$reason=$e->getMessage();$status=$reason==='company_api_unauthenticated'?401:(str_ends_with($reason,'access_denied')||$reason==='company_project_copy_denied'||str_ends_with($reason,'scope_denied')||str_ends_with($reason,'reference_denied')?403:(str_ends_with($reason,'stale')||$reason==='company_project_copy_conflict'||str_ends_with($reason,'request_conflict')?409:422));if($status===401)$this->output->set_header('WWW-Authenticate: Bearer');$this->output->set_status_header($status)->set_output(json_encode(['error'=>match($status){401=>'unauthenticated',403=>'forbidden',409=>'revision_or_idempotency_conflict',default=>'validation_or_state_review'}]));}
        catch(Throwable $e){log_message('error','Company operations API unavailable; no authorization header body or resource data logged.');$this->output->set_status_header(503)->set_output('{"error":"temporarily_unavailable"}');}
    }
    public function projects($id=''){$this->execute('projects',$id);}public function tasks($id=''){$this->execute('tasks',$id);}public function standalone_tasks($id=''){$this->execute('standalone_tasks',$id);}public function contracts($id=''){$this->execute('contracts',$id);}public function tickets($id=''){$this->execute('tickets',$id);}
    public function task_schedules($id=''){$this->execute('task_schedules',$id);}
    public function standalone_schedules($id=''){$this->execute('standalone_schedules',$id);}
    /** Dedicated finite routes; byte delivery occurs only after the canonical guarded reader commits. */
    private function subresource(string $endpoint,$id,$file=''):void
    {
        $method=$this->input->method(true);$head=$method==='HEAD';$this->output->set_content_type('application/json','UTF-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');
        try{
            if(!$this->ready)throw new RuntimeException('issuer_operations_api_unavailable');require_once APPPATH.'services/CompanyOperationsSubresourceApi.php';$download=$file!=='';$allowed=$download?['GET','HEAD']:['GET','HEAD','POST'];if(!in_array($method,$allowed,true)){$this->output->set_header('Allow: '.implode(', ',$allowed))->set_status_header(405)->set_output($head?'':'{"error":"method_not_allowed"}');return;}
            $header=$this->input->get_request_header('Authorization',false);if(!is_string($header)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$header,$match))throw new DomainException('company_api_unauthenticated');$api=new CompanyOperationsSubresourceApi($this->db,db_prefix());
            if($download){$blob=$api->file($match[1],$endpoint,$id,$file);$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");$this->output->set_header('Content-Disposition: attachment; filename="anexo.'.($blob['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($blob['filename']));$this->output->set_header('Content-Length: '.strlen($blob['content']));$this->output->set_status_header(200)->set_content_type($blob['mime'])->set_output($head?'':$blob['content']);return;}
            if($method==='POST'){require_once APPPATH.'services/CompanyOperationsSubresourceHttp.php';$type=$this->input->get_request_header('Content-Type',false);if(!is_string($type))throw new DomainException('company_api_invalid_input');$limit=CompanyOperationsSubresourceHttp::limit($endpoint);$bytes=file_get_contents('php://input',false,null,0,$limit+1);if(!is_string($bytes))throw new DomainException('company_api_invalid_input');$body=CompanyOperationsSubresourceHttp::parse($endpoint,$type,$bytes);$key=$this->input->get_request_header('Idempotency-Key',false);if(!is_string($key))throw new DomainException('company_api_invalid_input');$value=$api->write($match[1],$endpoint,$id,$body,$key);}
            else $value=$api->read($match[1],$endpoint,$id);$this->output->set_status_header(200)->set_output($head?'':json_encode(['api_version'=>1,'data'=>$value],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException $e){$reason=$e->getMessage();$status=$reason==='company_api_unauthenticated'?401:(str_ends_with($reason,'access_denied')||$reason==='company_project_copy_denied'||str_ends_with($reason,'scope_denied')||str_ends_with($reason,'reference_denied')?403:(str_ends_with($reason,'stale')||$reason==='company_project_copy_conflict'||str_ends_with($reason,'request_conflict')?409:422));if($status===401)$this->output->set_header('WWW-Authenticate: Bearer');$this->output->set_status_header($status)->set_output($head?'':json_encode(['error'=>match($status){401=>'unauthenticated',403=>'forbidden',409=>'revision_or_idempotency_conflict',default=>'validation_or_state_review'}]));}
        catch(Throwable $error){log_message('error','Company operational subresource API unavailable; no headers bodies filenames or private data logged.');$this->output->set_status_header(503)->set_output($head?'':'{"error":"temporarily_unavailable"}');}
    }
    public function project_files($id='',$file=''){$this->subresource('project_files',$id,$file);}
    public function task_files($id='',$file=''){$this->subresource('task_files',$id,$file);}
    public function contract_files($id='',$file=''){$this->subresource('contract_files',$id,$file);}
    public function ticket_files($id='',$file=''){$this->subresource('ticket_files',$id,$file);}
    public function project_copies($id=''){$this->subresource('project_copies',$id);}
    public function project_milestones($id=''){$this->subresource('project_milestones',$id);}
    public function task_dependencies($id=''){$this->subresource('task_dependencies',$id);}
    public function project_follow($id=''){$this->subresource('project_follow',$id);}
    public function task_follow($id=''){$this->subresource('task_follow',$id);}

}
