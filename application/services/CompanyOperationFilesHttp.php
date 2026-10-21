<?php

declare(strict_types=1);
/** Native multipart parsing and authorized byte response; no public filesystem resolver. */
final class CompanyOperationFilesHttp
{
    public static function upload():array
    {
        $file=$_FILES['operation_file']??null;
        if(!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK||!is_int($file['size']??null)||$file['size']>2097152||!is_string($file['name']??null)||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new DomainException('company_operation_file_invalid');
        $bytes=file_get_contents($file['tmp_name'],false,null,0,2097153);if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new DomainException('company_operation_file_invalid');return [$file['name'],$bytes];
    }
    public static function respond($controller,array $file):void
    {
        $preview=$controller->input->get('preview',false)==='1'&&$file['mime']==='image/png';$controller->output->set_header('X-Content-Type-Options: nosniff');$controller->output->set_header("Content-Security-Policy: sandbox; default-src 'none'");$controller->output->set_header('Content-Disposition: '.($preview?'inline':'attachment').'; filename="anexo.'.($file['mime']==='image/png'?'png':'pdf').'"; filename*=UTF-8\'\''.rawurlencode($file['filename']));$controller->output->set_content_type($file['mime']);$controller->output->set_output($controller->input->method(true)==='HEAD'?'':$file['content']);
    }
}
