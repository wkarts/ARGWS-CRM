<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyAssets.php';
/** Shared pure file validation and quota rules; never grants access to a resource. */
final class CompanyOperationFileContent
{
    public static function normalize(string $name,string $bytes):array
    {
        if(strlen($name)>191||$name===''||trim($name)!==$name||str_starts_with($name,'.')||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name)||strlen($bytes)<8||strlen($bytes)>2097152)throw new DomainException('company_operation_file_invalid');$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if($extension==='pdf'&&$mime==='application/pdf'&&preg_match('/^%PDF-[12]\.[0-9][\r\n]/',$bytes))return [$name,'application/pdf',$bytes];if(!in_array($extension,['png','jpg','jpeg'],true)||!in_array($mime,['image/png','image/jpeg'],true))throw new DomainException('company_operation_file_invalid');try{$inline=CompanyAssets::normalize($bytes);}catch(DomainException $e){throw new DomainException('company_operation_file_invalid');}$name=pathinfo($name,PATHINFO_FILENAME).'.png';if(strlen($name)>191)throw new DomainException('company_operation_file_invalid');return [$name,'image/png',base64_decode(substr($inline,strlen('data:image/png;base64,')),true)];
    }
    public static function quota(int $count,int $bytes,int $incoming):void{if($count>=20||$bytes+$incoming>20971520)throw new DomainException('company_operation_file_limit');}
}
