<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyLeadsApi.php';

/** Mounted only in the native finite Company_operations_api controller after scope preparation. */
trait CompanyLeadsHttpApi
{
    private function leadResource(string $endpoint,$id='',$file=''):void
    {
        $method=$this->input->method(true);$head=$method==='HEAD';$this->output->set_content_type('application/json','UTF-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');
        try{
            if(!$this->ready)throw new RuntimeException('issuer_operations_api_unavailable');$allowed=(in_array($endpoint,['lead_exports','lead_assignees'],true)||$file!=='')?['GET','HEAD']:['GET','HEAD','POST'];if(!in_array($method,$allowed,true)){$this->output->set_header('Allow: '.implode(', ',$allowed))->set_status_header(405)->set_output($head?'':'{"error":"method_not_allowed"}');return;}
            $header=$this->input->get_request_header('Authorization',false);if(!is_string($header)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$header,$m))throw new DomainException('company_api_unauthenticated');$api=new CompanyLeadsApi($this->db,db_prefix());$id=$id===''?null:$id;
            if($file!==''){$blob=$api->file($m[1],$id,$file);$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'")->set_header('Content-Disposition: attachment; filename="anexo.'.($blob['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($blob['filename']))->set_header('Content-Length: '.strlen($blob['bytes']))->set_status_header(200)->set_content_type($blob['mime'])->set_output($head?'':$blob['bytes']);return;}
            if($method==='POST'){$type=$this->input->get_request_header('Content-Type',false);if(!is_string($type)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type))throw new DomainException('company_api_invalid_input');$limit=$endpoint==='lead_files'?2800000:($endpoint==='lead_imports'?350000:65536);$bytes=file_get_contents('php://input',false,null,0,$limit+1);if(!is_string($bytes)||strlen($bytes)>$limit)throw new DomainException('company_api_invalid_input');try{$body=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('company_api_invalid_input');}if(!is_array($body)||array_is_list($body))throw new DomainException('company_api_invalid_input');$key=$this->input->get_request_header('Idempotency-Key',false);if(!is_string($key))throw new DomainException('company_api_invalid_input');$data=$api->write($m[1],$endpoint,$id,$body,$key);}
            else{$query=$this->input->get(null,false)??[];if(!is_array($query)||array_diff(array_keys($query),['search','after']))throw new DomainException('company_api_invalid_input');$search=$query['search']??'';if(!is_string($search))throw new DomainException('company_api_invalid_input');if($endpoint==='lead_exports'){$csv=$api->export($m[1],$search);$this->output->set_header('Content-Disposition: attachment; filename="leads.csv"')->set_status_header(200)->set_content_type('text/csv','UTF-8')->set_output($head?'':$csv);return;}$data=$api->read($m[1],$endpoint,$id,$query['after']??0,$search);}
            $this->output->set_status_header(200)->set_output($head?'':json_encode(['api_version'=>1,'data'=>$data],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException $e){$reason=$e->getMessage();$status=$reason==='company_api_unauthenticated'?401:(str_ends_with($reason,'denied')?403:(str_ends_with($reason,'stale')||str_ends_with($reason,'conflict')?409:422));if($status===401)$this->output->set_header('WWW-Authenticate: Bearer');$this->output->set_status_header($status)->set_output($head?'':json_encode(['error'=>match($status){401=>'unauthenticated',403=>'forbidden',409=>'revision_or_idempotency_conflict',default=>'validation_or_state_review'}]));}
        catch(Throwable $e){log_message('error','Company leads API unconfirmed; no credentials payload filenames or private data logged.');$this->output->set_status_header(503)->set_output($head?'':'{"error":"temporarily_unavailable"}');}
    }
    public function leads($id=''){$this->leadResource('leads',$id);}
    public function lead_stages($id=''){$this->leadResource('lead_stages',$id);}
    public function lead_sources($id=''){$this->leadResource('lead_sources',$id);}
    public function lead_assignees(){$this->leadResource('lead_assignees');}
    public function lead_files($id='',$file=''){$this->leadResource('lead_files',$id,$file);}
    public function lead_imports($id=''){$this->leadResource('lead_imports',$id);}
    public function lead_inbox($id=''){$this->leadResource('lead_inbox',$id);}
    public function lead_exports(){$this->leadResource('lead_exports');}
}
