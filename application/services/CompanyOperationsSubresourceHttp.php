<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyOperationsSubresourceApi.php';
/** Route-specific JSON envelope limits. Binary normalization remains in the canonical domain writer. */
final class CompanyOperationsSubresourceHttp
{
 public static function limit(string $endpoint):int{if(isset(CompanyOperationsSubresourceApi::FILES[$endpoint]))return 3000000;if(isset(CompanyOperationsSubresourceApi::EXTRAS[$endpoint]))return 262144;throw new DomainException('company_api_invalid_input');}
 public static function parse(string $endpoint,string $type,string $bytes):array
 {
  if(strlen($bytes)>self::limit($endpoint)||!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD',$type))throw new DomainException('company_api_invalid_input');try{$body=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $invalid){throw new DomainException('company_api_invalid_input');}if(!is_array($body)||array_is_list($body))throw new DomainException('company_api_invalid_input');return $body;
 }
}
