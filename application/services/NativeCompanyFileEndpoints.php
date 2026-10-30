<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceFiles.php';
/** Same Misc routes; explicit persisted source, never a client-supplied storage path. */
final class NativeCompanyFileEndpoints
{
    public static function uploadInvoice($ci):array
    {
        CompanyRequestScope::assertNativeMutation($ci);$c=CompanyRequestScope::context();
        if($ci->input->post('type')!=='invoice')throw new DomainException('native_sales_file_source_required');
        $id=IssuerPolicy::id($ci->input->post('rel_id'));$key=$ci->input->post('request_key');
        if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_invoice_file_invalid');
        $f=$_FILES['file']??null;if(!is_array($f)||!is_string($f['tmp_name']??null)||!is_string($f['name']??null)||($f['error']??null)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))throw new DomainException('native_invoice_file_invalid');
        $size=filesize($f['tmp_name']);if($size===false||$size<8||$size>NativeCompanyFileContent::maximum())throw new DomainException('company_invoice_attachment_invalid');$bytes=file_get_contents($f['tmp_name']);if(!is_string($bytes)||strlen($bytes)!==$size)throw new DomainException('native_invoice_file_invalid');
        $service=new NativeCompanyInvoiceFiles($ci->db,db_prefix());$r=$service->upload($c->issuerId(),$c->staffId(),$id,$key,$f['name'],$bytes);return ['success'=>true]+NativeCompanyInvoiceFiles::nativeMetadata($r);
    }
    public static function externalInvoice($ci):array
    {
        CompanyRequestScope::assertNativeMutation($ci);$c=CompanyRequestScope::context();if($ci->input->post('type')!=='invoice')throw new DomainException('native_sales_file_source_required');
        $id=IssuerPolicy::id($ci->input->post('rel_id'));$files=$ci->input->post('files');$provider=$ci->input->post('external');$key=$ci->input->post('request_key');
        if(!is_array($files)||count($files)!==1||!is_array($files[0]??null)||!is_string($provider)||!is_string($key))throw new DomainException('native_file_external_invalid');$file=$files[0];if(!is_string($file['name']??null)||!is_string($file['link']??null))throw new DomainException('native_file_external_invalid');
        $r=(new NativeCompanyInvoiceFiles($ci->db,db_prefix()))->addExternal($c->issuerId(),$c->staffId(),$id,$key,$file['name'],$provider,$file['link']);return ['success'=>true]+NativeCompanyInvoiceFiles::nativeMetadata($r);
    }
    public static function send($ci):array
    {
        CompanyRequestScope::assertNativeMutation($ci);$c=CompanyRequestScope::context();$source=$ci->input->post('native_file_source');$key=$ci->input->post('request_key');$email=$ci->input->post('send_file_email');$subject=$ci->input->post('send_file_subject');$message=$ci->input->post('send_file_message');
        foreach([$source,$key,$email,$subject,$message]as$v)if(!is_string($v))throw new DomainException('native_file_message_invalid');
        $id=IssuerPolicy::id($ci->input->post('native_file_id'));$revision=IssuerPolicy::id($ci->input->post('native_file_revision'));
        require_once __DIR__.'/NativeCompanyFileMessage.php';$queued=(new NativeCompanyFileMessage($ci->db,db_prefix()))->queue($c->issuerId(),$c->staffId(),$source,$id,$revision,$key,$email,$subject,$message,fn($v)=>$ci->encryption->decrypt($v));
        return ['success'=>true,'queued'=>true,'queue_id'=>$queued];
    }
    public static function visibility($ci,$id):array
    {
        CompanyRequestScope::assertNativeMutation($ci);$c=CompanyRequestScope::context();$id=IssuerPolicy::id($id);$revision=IssuerPolicy::id($ci->input->post('native_file_revision'));$visible=$ci->input->post('visible');if(!in_array($visible,['0','1'],true))throw new DomainException('native_invoice_file_invalid');$source=$ci->input->post('native_file_source');
        if($source==='invoice')$next=(new NativeCompanyInvoiceFiles($ci->db,db_prefix()))->visibility($c->issuerId(),$c->staffId(),$id,$revision,$visible==='1');
        elseif($source==='customer'){
            require_once __DIR__.'/NativeCompanyCustomerFiles.php';$contacts=$ci->input->post('contacts');if($contacts===null)$contacts=[];if(!is_array($contacts))throw new DomainException('native_customer_file_invalid');
            $next=(new NativeCompanyCustomerFiles($ci->db,db_prefix()))->share($c->issuerId(),$c->staffId(),$id,$revision,$visible==='1',$contacts);
        }else throw new DomainException('native_sales_file_source_required');
        return ['visible'=>(int)$visible,'revision'=>$next];
    }
}
