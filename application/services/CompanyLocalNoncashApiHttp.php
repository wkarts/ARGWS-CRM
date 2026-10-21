<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyLocalNoncashApi.php';
/** No session fallback. The dedicated pre-hydration CompanyApiScope must admit this exact route. */
final class CompanyLocalNoncashApiHttp
{
    public static function execute($ci,bool$ready,$invoice):void
    {
        $ci->output->set_content_type('application/json','UTF-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');
        try{if(!$ready)throw new RuntimeException('issuer_api_unavailable');$verb=$ci->input->method(true);if(!in_array($verb,['GET','HEAD','POST'],true)){$ci->output->set_header('Allow: GET, HEAD, POST')->set_status_header(405)->set_output('{"error":"method_not_allowed"}');return;}$auth=$ci->input->get_request_header('Authorization',false);if(!is_string($auth)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$auth,$m))throw new DomainException('company_api_unauthenticated');$service=new CompanyLocalNoncashApi($ci->db,db_prefix());
            if($verb==='POST'){$type=$ci->input->get_request_header('Content-Type',false);if(!is_string($type)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type))throw new DomainException('company_api_invalid_input');$raw=file_get_contents('php://input',false,null,0,262145);if(!is_string($raw)||strlen($raw)>262144)throw new DomainException('company_api_invalid_input');try{$object=json_decode($raw,false,32,JSON_THROW_ON_ERROR);if(!$object instanceof stdClass)throw new RuntimeException();$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable$e){throw new DomainException('company_api_invalid_input');}$key=$ci->input->get_request_header('Idempotency-Key',false);if(!is_string($key))throw new DomainException('company_api_invalid_input');$result=$service->write($m[1],$invoice,$input,$key);$status=$result['replayed']?200:201;}else{$result=$service->read($m[1],$invoice);$status=200;}$ci->output->set_status_header($status)->set_output($verb==='HEAD'?'':json_encode(['api_version'=>1,'data'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException|InvalidArgumentException$e){$reason=$e->getMessage();$status=$reason==='company_api_unauthenticated'?401:(str_ends_with($reason,'denied')||in_array($reason,['company_api_scope_denied','issuer_document_scope_mismatch'],true)?403:(str_contains($reason,'conflict')||str_contains($reason,'stale')?409:422));if($status===401)$ci->output->set_header('WWW-Authenticate: Bearer');$ci->output->set_status_header($status)->set_output(json_encode(['error'=>match($status){401=>'unauthenticated',403=>'forbidden',409=>'conflict_or_review',default=>'validation_or_balance_review'}]));}
        catch(Throwable$e){log_message('error','LOCAL financial API unconfirmed; credential, evidence and input omitted.');$ci->output->set_status_header(503)->set_output('{"error":"temporarily_unavailable"}');}
    }
}
