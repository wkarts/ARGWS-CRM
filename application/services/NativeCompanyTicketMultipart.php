<?php

declare(strict_types=1);
/** Parse only files actually uploaded in the current multipart request. */
final class NativeCompanyTicketMultipart
{
    public static function normalize(array$files):array
    {
        if(!array_is_list($files)||count($files)>20)throw new DomainException('company_operation_file_limit');require_once __DIR__.'/CompanyOperationFileContent.php';$normalized=[];$total=0;
        foreach($files as$file){if(!is_array($file)||array_keys($file)!==['name','bytes']||!is_string($file['name'])||!is_string($file['bytes']))throw new DomainException('company_operation_file_invalid');[$name,$mime,$bytes]=CompanyOperationFileContent::normalize($file['name'],$file['bytes']);CompanyOperationFileContent::quota(count($normalized),$total,strlen($bytes));$total+=strlen($bytes);$normalized[]=['name'=>$name,'mime'=>$mime,'bytes'=>$bytes,'hash'=>hash('sha256',$bytes),'extension'=>strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))];}return$normalized;
    }
    public static function assertPolicy(array$files,array$options):void
    {
        $allowed=array_map(static fn($v)=>ltrim(strtolower(trim($v)),'.'),explode(',',$options['ticket_attachments_file_extensions']));if(count($files)>(int)$options['maximum_allowed_ticket_attachments'])throw new DomainException('company_operation_file_limit');foreach($files as$file)if(!in_array($file['extension'],$allowed,true))throw new DomainException('company_operation_file_invalid');
    }
    public static function attachments():array
    {
        if(array_diff(array_keys($_FILES),['attachments']))throw new DomainException('native_ticket_attachments_invalid');if(!isset($_FILES['attachments']))return[];$upload=$_FILES['attachments'];
        if(!is_array($upload)||!is_array($upload['error']??null)||!is_array($upload['name']??null)||!is_array($upload['tmp_name']??null)||!is_array($upload['size']??null))throw new DomainException('native_ticket_attachments_invalid');
        $files=[];foreach($upload['error']as$n=>$error){if($error===UPLOAD_ERR_NO_FILE)continue;if($error!==UPLOAD_ERR_OK||!is_string($upload['name'][$n]??null)||!is_string($upload['tmp_name'][$n]??null)||!is_int($upload['size'][$n]??null)||$upload['size'][$n]<1||$upload['size'][$n]>2097152||!is_uploaded_file($upload['tmp_name'][$n]))throw new DomainException('native_ticket_attachments_invalid');$bytes=file_get_contents($upload['tmp_name'][$n],false,null,0,2097153);if(!is_string($bytes)||strlen($bytes)!==$upload['size'][$n])throw new DomainException('native_ticket_attachments_invalid');$files[]=['name'=>$upload['name'][$n],'bytes'=>$bytes];}return$files;
    }
}
