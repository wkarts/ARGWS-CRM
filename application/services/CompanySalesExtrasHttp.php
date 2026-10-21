<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySalesFiles.php';require_once __DIR__.'/CompanySalesDelivery.php';

/** Request adapter called only after the sealed sales controller validates context and CSRF. */
final class CompanySalesExtrasHttp
{
    public static function post($ci,int $issuer,int $actor,$document,$action):bool
    {
        if(!in_array($action,['sales_file_upload','sales_file_hide','sales_notify'],true))return false;
        if($action==='sales_notify'){$contact=$ci->input->post('contact_id',false);$revision=$ci->input->post('revision',false);$key=$ci->input->post('request_key',false);if(!is_string($revision)||!is_string($key))throw new DomainException('company_sales_delivery_invalid');$ci->load->library('encryption');(new CompanySalesDelivery($ci->db,db_prefix()))->queue($issuer,$actor,$document,$contact,$revision,$key,$ci->config->base_url(),fn($value)=>$ci->encryption->decrypt($value));return true;}
        $files=new CompanySalesFiles($ci->db,db_prefix());if($action==='sales_file_hide'){$key=$ci->input->post('file_key',false);$hash=$ci->input->post('content_hash',false);if(!is_string($key)||!is_string($hash))throw new DomainException('company_sales_file_invalid');$files->hide($issuer,$actor,$document,$key,$hash);return true;}
        $upload=$_FILES['sales_file']??null;$key=$ci->input->post('request_key',false);$visible=$ci->input->post('portal_visible',false)??'0';if(!is_string($key)||!in_array($visible,['0','1'],true)||!is_array($upload)||($upload['error']??null)!==UPLOAD_ERR_OK||!is_int($upload['size']??null)||$upload['size']>2097152||!is_string($upload['name']??null)||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name']))throw new DomainException('company_sales_file_invalid');$bytes=file_get_contents($upload['tmp_name'],false,null,0,2097153);if(!is_string($bytes))throw new DomainException('company_sales_file_invalid');$files->upload($issuer,$actor,$document,$key,$upload['name'],$bytes,$visible==='1');return true;
    }
    public static function download($ci,int $issuer,int $actor,$document):bool
    {
        $key=$ci->input->get('file',false);if($key===null)return false;if(!is_string($key))throw new DomainException('company_sales_file_denied');$file=(new CompanySalesFiles($ci->db,db_prefix()))->download($issuer,$actor,$document,$key);self::output($ci,$file,$ci->input->get('preview',false)==='1');return true;
    }
    public static function output($ci,array $file,bool $preview=false):void
    {
        if($preview&&$file['mime']!=='image/png')throw new DomainException('company_sales_file_invalid');$ci->output->set_header('Cache-Control: no-store');$ci->output->set_header('X-Content-Type-Options: nosniff');$ci->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");$ci->output->set_header('Content-Disposition: '.($preview?'inline':'attachment').'; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$ci->output->set_content_type($file['mime'])->set_output($file['content']);
    }
}
