<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceAmounts.php';require_once __DIR__.'/CompanyInvoiceLines.php';require_once __DIR__.'/issuers/IssuerPolicy.php';
/** Exact existing canonical amounts; no new monetary algorithm or copied identity/provider authorization. */
final class CompanyInvoiceCopiesInput
{
    public const MAX_JSON=16777215;
    public static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
    public static function key($value):string{if(!is_string($value)||!preg_match('/^[a-f0-9]{64}$/D',$value))throw new DomainException('company_invoice_copy_invalid');return $value;}
    public static function text($value,int $max,bool $required=false):string{if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)||($required&&trim($value)===''))throw new DomainException('company_invoice_copy_invalid');return trim($value);}
    public static function date($value):string{$value=self::text($value,10,true);$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('company_invoice_copy_invalid');return $value;}
    public static function review(array $input):array{if(array_diff(array_keys($input),['source_revision','date','due_date','note','confirm_amounts'])||count($input)!==5||!in_array($input['confirm_amounts']??null,['1',1],true))throw new DomainException('company_invoice_copy_invalid');$date=self::date($input['date']??null);$due=self::date($input['due_date']??null);if($due<$date)throw new DomainException('company_invoice_copy_invalid');return ['source_revision'=>self::key($input['source_revision']??null),'date'=>$date,'due_date'=>$due,'note'=>self::text($input['note']??null,20000),'confirm_amounts'=>1];}
    /** Matches CompanyInvoices::input serialization, verified by the real canonical writer in tests. */
    public static function canonical(array $input):array{$options=array_intersect_key($input,array_flip(['discount_mode','discount_value','discount_type','adjustment']));$amounts=CompanyInvoiceLines::calculate($input['items'],$options);return array_merge($amounts,['client_id'=>IssuerPolicy::id($input['client_id']),'date'=>self::date($input['date']),'due_date'=>self::date($input['due_date']),'currency'=>IssuerPolicy::id($input['currency']),'note'=>self::text($input['note'],20000)]);}
    public static function json(array $input):string{$json=json_encode($input,JSON_THROW_ON_ERROR);if(strlen($json)>self::MAX_JSON)throw new DomainException('company_invoice_copy_limit');return $json;}
    public static function decode(string $json):array{if(strlen($json)>self::MAX_JSON)throw new RuntimeException('issuer_invoice_copy_unconfirmed');try{$input=json_decode($json,true,32,JSON_THROW_ON_ERROR);}catch(JsonException $bad){throw new RuntimeException('issuer_invoice_copy_unconfirmed');}if(!is_array($input)||array_keys($input)!==['client_id','date','due_date','currency','items','note','discount_mode','discount_value','discount_type','adjustment']||self::json($input)!==$json)throw new RuntimeException('issuer_invoice_copy_unconfirmed');self::canonical($input);return $input;}
}
