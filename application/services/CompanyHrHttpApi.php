<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyHrApi.php';

/** Mounted only in the finite native bearer controller after exact scope preparation. */
trait CompanyHrHttpApi
{
    private function hrResource(string $endpoint,$id=''):void
    {
        $method=$this->input->method(true);$head=$method==='HEAD';$this->output->set_content_type('application/json','UTF-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff')->set_header('Referrer-Policy: no-referrer');
        try{
            if(!$this->ready)throw new RuntimeException('issuer_operations_api_unavailable');$allowed=match($endpoint){'hr_reports','hr_documents'=>['GET','HEAD'],'hr_imports'=>['POST'],default=>['GET','HEAD','POST']};if(!in_array($method,$allowed,true)){$this->output->set_header('Allow: '.implode(', ',$allowed))->set_status_header(405)->set_output($head?'':'{"error":"method_not_allowed"}');return;}
            $header=$this->input->get_request_header('Authorization',false);if(!is_string($header)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$header,$m))throw new DomainException('company_api_unauthenticated');$api=new CompanyHrApi($this->db,db_prefix());$id=$id===''?null:$id;
            if($method==='POST'){
                $type=$this->input->get_request_header('Content-Type',false);if(!is_string($type)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type))throw new DomainException('company_api_invalid_input');$limit=$endpoint==='hr_files'?2800000:($endpoint==='hr_imports'?350000:262144);$bytes=file_get_contents('php://input',false,null,0,$limit+1);if(!is_string($bytes)||strlen($bytes)>$limit)throw new DomainException('company_api_invalid_input');try{$body=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('company_api_invalid_input');}if(!is_array($body)||array_is_list($body))throw new DomainException('company_api_invalid_input');$key=$this->input->get_request_header('Idempotency-Key',false);if(!is_string($key))throw new DomainException('company_api_invalid_input');$data=$api->write($m[1],$endpoint,$id,$body,$key);
            }else{
                $query=$this->input->get(null,false)??[];if(!is_array($query))throw new DomainException('company_api_invalid_input');
                if($endpoint==='hr_files'&&$id!==null){if(array_diff(array_keys($query),['purpose','object_id'])||!is_string($query['purpose']??null))throw new DomainException('company_api_invalid_input');$blob=$api->file($m[1],$query['purpose'],$query['object_id']??null,$id);$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'")->set_header('Content-Disposition: attachment; filename="documento.'.($blob['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($blob['filename']))->set_header('Content-Length: '.strlen($blob['bytes']))->set_content_type($blob['mime'])->set_status_header(200)->set_output($head?'':$blob['bytes']);return;}
                if($endpoint==='hr_reports'&&($query['format']??null)==='csv'){if($id!==null||array_diff(array_keys($query),['format','name'])||!is_string($query['name']??null))throw new DomainException('company_api_invalid_input');$csv=$api->export($m[1],$query['name']);$this->output->set_header('Content-Disposition: attachment; filename="rh.csv"')->set_status_header(200)->set_content_type('text/csv','UTF-8')->set_output($head?'':$csv);return;}
                $data=$api->read($m[1],$endpoint,$id,$query);
                if($endpoint==='hr_documents'){require_once APPPATH.'services/CompanyHrPdf.php';$pdf=CompanyHrPdf::render($data['document']);$confirmed=$api->read($m[1],$endpoint,$id,$query);if($confirmed!==$data)throw new DomainException('issuer_hr_stale');$this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'")->set_header('Content-Disposition: attachment; filename="contrato-rh.pdf"')->set_status_header(200)->set_content_type('application/pdf')->set_output($head?'':$pdf);return;}
            }
            $this->output->set_status_header(200)->set_output($head?'':json_encode(['api_version'=>1,'data'=>$data],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException $e){$reason=$e->getMessage();$status=$reason==='company_api_unauthenticated'?401:(str_ends_with($reason,'denied')?403:(str_ends_with($reason,'stale')||str_ends_with($reason,'conflict')?409:422));if($status===401)$this->output->set_header('WWW-Authenticate: Bearer');$this->output->set_status_header($status)->set_output($head?'':json_encode(['error'=>match($status){401=>'unauthenticated',403=>'forbidden',409=>'revision_or_idempotency_conflict',default=>'validation_or_state_review'}]));}
        catch(Throwable $e){log_message('error','Company HR API unconfirmed; no credentials, personal data, attachments or request bodies logged.');$this->output->set_status_header(503)->set_output($head?'':'{"error":"temporarily_unavailable"}');}
    }
    public function hr_structure($id=''){$this->hrResource('hr_structure',$id);}
    public function hr_employees($id=''){$this->hrResource('hr_employees',$id);}
    public function hr_profiles($id=''){$this->hrResource('hr_profiles',$id);}
    public function hr_records($id=''){$this->hrResource('hr_records',$id);}
    public function hr_contracts($id=''){$this->hrResource('hr_contracts',$id);}
    public function hr_courses($id=''){$this->hrResource('hr_courses',$id);}
    public function hr_assignments($id=''){$this->hrResource('hr_assignments',$id);}
    public function hr_accesses($id=''){$this->hrResource('hr_accesses',$id);}
    public function hr_files($id=''){$this->hrResource('hr_files',$id);}
    public function hr_reports($id=''){$this->hrResource('hr_reports',$id);}
    public function hr_imports($id=''){$this->hrResource('hr_imports',$id);}
    public function hr_articles($id=''){$this->hrResource('hr_articles',$id);}
    public function hr_documents($id=''){$this->hrResource('hr_documents',$id);}
}
