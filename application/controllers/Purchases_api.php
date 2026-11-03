<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Bearer API prepared before legacy modules, user sessions and ambient company hydration. */
class Purchases_api extends CI_Controller
{
    public function index($resource='',$id='')
    {
        $verb=$this->input->method(true); $head=$verb==='HEAD';
        $this->output->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff')->set_content_type('application/json','UTF-8');
        try {
            if (!class_exists('CompanyPurchasesScope',false)||!CompanyPurchasesScope::activeApi()) { throw new RuntimeException('issuer_purchase_api_unavailable'); }
            $allowed=in_array($resource,['reports','export','order_pdf','file_download'],true)?['GET','HEAD']:['GET','HEAD','POST'];
            if (!in_array($verb,$allowed,true)) { $this->output->set_header('Allow: '.implode(', ',$allowed))->set_status_header(405)->set_output($head?'':'{"error":"method_not_allowed"}'); return; }
            $header=$this->input->get_request_header('Authorization',false);
            if (!is_string($header)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$header,$m)) { throw new DomainException('company_api_unauthenticated'); }
            require_once APPPATH.'services/CompanyPurchasesApi.php'; $api=new CompanyPurchasesApi($this->db,db_prefix()); $id=$id===''?null:$id;
            if ($verb==='POST') {
                $type=$this->input->get_request_header('Content-Type',false);
                if (!is_string($type)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type)) { throw new DomainException('company_api_invalid_input'); }
                $limit=$resource==='files'?2800000:65536; $raw=file_get_contents('php://input',false,null,0,$limit+1);
                if (!is_string($raw)||strlen($raw)>$limit) { throw new DomainException('company_api_invalid_input'); }
                try { $object=json_decode($raw,false,32,JSON_THROW_ON_ERROR); $body=json_decode($raw,true,32,JSON_THROW_ON_ERROR); }
                catch (Throwable $e) { throw new DomainException('company_api_invalid_input'); }
                if (!$object instanceof stdClass||!is_array($body)) { throw new DomainException('company_api_invalid_input'); }
                $key=$this->input->get_request_header('Idempotency-Key',false);
                if (!is_string($key)) { throw new DomainException('company_api_invalid_input'); }
                $data=$api->write($m[1],$resource,$id,$body,$key);
            } else {
                $query=$this->input->get(null,false)??[];
                if (!is_array($query)) { throw new DomainException('company_api_invalid_input'); }
                if ($resource==='file_download') {
                    if ($query!==[]) { throw new DomainException('company_api_invalid_input'); }
                    $file=$api->file($m[1],$id);
                    $this->output->set_header("Content-Security-Policy: sandbox; default-src 'none'")->set_header('Content-Disposition: attachment; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']))->set_header('Content-Length: '.strlen($file['content']))->set_content_type($file['mime'])->set_status_header(200)->set_output($head?'':$file['content']); return;
                }
                $data=$api->read($m[1],$resource,$id,$query);
                if (in_array($resource,['order_pdf','export'],true)) { $pdf=$resource==='order_pdf'; $this->output->set_header('Content-Disposition: attachment; filename="'.($pdf?'pedido.pdf':'contas-a-pagar.csv').'"')->set_content_type($pdf?'application/pdf':'text/csv')->set_status_header(200)->set_output($head?'':$data); return; }
            }
            $this->output->set_status_header(200)->set_output($head?'':json_encode(['api_version'=>1,'data'=>$data],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        } catch (DomainException|InvalidArgumentException $e) {
            $reason=$e->getMessage(); $status=$reason==='company_api_unauthenticated'?401:(str_ends_with($reason,'denied')?403:((str_contains($reason,'conflict')||str_contains($reason,'stale'))?409:422));
            if ($status===401) { $this->output->set_header('WWW-Authenticate: Bearer'); }
            $this->output->set_status_header($status)->set_output($head?'':json_encode(['error'=>match($status) { 401=>'unauthenticated',403=>'forbidden',409=>'revision_or_idempotency_conflict',default=>'validation_or_state_review' }]));
        } catch (Throwable $e) {
            log_message('error','Company purchasing API unconfirmed; credentials, private documents and payload omitted.');
            $this->output->set_status_header(503)->set_output($head?'':'{"error":"temporarily_unavailable"}');
        }
    }
}
