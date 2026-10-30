<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyExpenseApi.php';

/** Exact JSON/binary endpoints; envelope limits apply before base64 decoding or domain hydration. */
final class CompanyExpenseApiHttp
{
    public static function limit(string $action):int{return$action==='expense_files'?3000000:262144;}
    public static function parse(string $action,string $type,string $raw):array
    {
        if(!in_array($action,['expenses','expense_categories','expense_files'],true)||strlen($raw)>self::limit($action)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type))throw new DomainException('company_api_invalid_input');try{$object=json_decode($raw,false,32,JSON_THROW_ON_ERROR);if(!$object instanceof stdClass)throw new RuntimeException();$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $invalid){throw new DomainException('company_api_invalid_input');}return$body;
    }
    public static function execute($ci,bool $ready,string $action,$id='',$file=''):void
    {
        $verb=$ci->input->method(true);$head=$verb==='HEAD';$ci->output->set_content_type('application/json','UTF-8')->set_header('Cache-Control: no-store')->set_header('X-Content-Type-Options: nosniff');
        try{
            if(!$ready)throw new RuntimeException('issuer_expense_api_unavailable');$download=$action==='expense_files'&&$file!=='';$allowed=$download?['GET','HEAD']:['GET','HEAD','POST'];if(!in_array($verb,$allowed,true)){$ci->output->set_header('Allow: '.implode(', ',$allowed))->set_status_header(405)->set_output($head?'':'{"error":"method_not_allowed"}');return;}
            $auth=$ci->input->get_request_header('Authorization',false);if(!is_string($auth)||!preg_match('/^Bearer (crmco_[a-f0-9]{32}_[a-f0-9]{64})$/D',$auth,$m))throw new DomainException('company_api_unauthenticated');$api=new CompanyExpenseApi($ci->db,db_prefix());$secret=$m[1];
            if($download){$blob=$api->file($secret,$id,$file);$ci->output->set_header("Content-Security-Policy: sandbox; default-src 'none'")->set_header('Content-Disposition: attachment; filename="comprovante.'.($blob['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($blob['filename']))->set_header('Content-Length: '.strlen($blob['content']))->set_status_header(200)->set_content_type($blob['mime'])->set_output($head?'':$blob['content']);return;}
            if($verb==='POST'){$type=$ci->input->get_request_header('Content-Type',false);if(!is_string($type))throw new DomainException('company_api_invalid_input');$raw=file_get_contents('php://input',false,null,0,self::limit($action)+1);if(!is_string($raw))throw new DomainException('company_api_invalid_input');$body=self::parse($action,$type,$raw);$key=$ci->input->get_request_header('Idempotency-Key',false);if(!is_string($key))throw new DomainException('company_api_invalid_input');$data=match($action){'expenses'=>$api->write($secret,$id===''?null:$id,$body,$key),'expense_categories'=>$api->category($secret,$id===''?null:$id,$body,$key),'expense_files'=>$api->fileWrite($secret,$id,$body,$key),default=>throw new DomainException('company_api_invalid_input')};}
            elseif($action==='expenses'){$search=$ci->input->get('search',false)??'';if(!is_string($search))throw new DomainException('company_api_invalid_input');$data=$api->read($secret,$id===''?null:$id,$ci->input->get('after',false)??0,$search);}
            elseif($action==='expense_categories')$data=$api->categories($secret,$id===''?null:$id);else$data=$api->files($secret,$id);$ci->output->set_status_header(200)->set_output($head?'':json_encode(['api_version'=>1,'data'=>$data],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }catch(DomainException|InvalidArgumentException $e){$r=$e->getMessage();$status=$r==='company_api_unauthenticated'?401:(str_ends_with($r,'denied')?403:(str_contains($r,'conflict')||str_ends_with($r,'stale')||str_ends_with($r,'immutable')?409:422));if($status===401)$ci->output->set_header('WWW-Authenticate: Bearer');$ci->output->set_status_header($status)->set_output($head?'':json_encode(['error'=>match($status){401=>'unauthenticated',403=>'forbidden',409=>'revision_or_financial_review',default=>'validation_or_state_review'}]));}
        catch(Throwable $e){log_message('error','Company expense API unconfirmed; credentials filenames bodies and cost details omitted.');$ci->output->set_status_header(503)->set_output($head?'':'{"error":"temporarily_unavailable"}');}
    }
}
