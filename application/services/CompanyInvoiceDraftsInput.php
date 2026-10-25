<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceCopiesInput.php';

/** The complete invoice-line contract, normalized without reserving an invoice number. */
final class CompanyInvoiceDraftsInput
{
    public const MAX_JSON=8388608;
    public static function key($value):string{return CompanyInvoiceCopiesInput::key($value);}
    public static function json(array $value):string{$json=json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(strlen($json)>self::MAX_JSON)throw new DomainException('company_invoice_draft_limit');return $json;}
    public static function hash(array $value):string{return hash('sha256',self::json($value));}
    public static function decode(string $json):array{if(strlen($json)>self::MAX_JSON)throw new RuntimeException('issuer_invoice_draft_unconfirmed');$a=json_decode($json,true,32,JSON_THROW_ON_ERROR);if(!is_array($a)||self::json($a)!==$json)throw new RuntimeException('issuer_invoice_draft_unconfirmed');return$a;}
    public static function normalize(array $input):array
    {
        if(array_diff(array_keys($input),['client_id','currency','date','due_date','items','note','discount_mode','discount_value','discount_type','adjustment','native_fields','native_project','native_preferences'])||!is_array($input['items']??null))throw new DomainException('company_invoice_draft_invalid');
        $c=CompanyInvoiceCopiesInput::canonical($input+['note'=>'']);if($c['due_date']!==null&&$c['due_date']<$c['date'])throw new DomainException('company_invoice_draft_invalid');
        return ['client_id'=>$c['client_id'],'date'=>$c['date'],'due_date'=>$c['due_date'],'currency'=>$c['currency'],'items'=>$c['items'],'note'=>$c['note'],
            'discount_mode'=>$c['discount_mode'],'discount_value'=>$c['discount_value'],'discount_type'=>$c['discount_type'],'adjustment'=>$c['adjustment']]+(array_key_exists('native_fields',$c)?['native_fields'=>$c['native_fields']]:[])+(array_key_exists('native_project',$c)?['native_project'=>$c['native_project']]:[])+(array_key_exists('native_preferences',$c)?['native_preferences'=>$c['native_preferences']]:[]);
    }
    public static function revision($value):int{if(!is_int($value)&&(!is_string($value)||!preg_match('/^[1-9][0-9]{0,8}$/D',$value)))throw new DomainException('company_invoice_draft_invalid');$n=(int)$value;if($n<1||$n>=2147483647)throw new DomainException('company_invoice_draft_invalid');return$n;}
}
