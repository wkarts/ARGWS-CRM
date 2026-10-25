<?php
declare(strict_types=1);
/** Native taxes/item_tax use DECIMAL(15,2). Extra serialized zeros do not change precision. */
final class NativeCompanyTaxRate
{
    public static function exact($value):string
    {
        if((!is_string($value)&&!is_int($value))||!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,4}))?$/D',(string)$value,$m))throw new DomainException('native_tax_precision_review_required');
        $fraction=$m[2]??'';if(trim(substr($fraction,2),'0')!=='')throw new DomainException('native_tax_precision_review_required');
        return$m[1].'.'.str_pad(substr($fraction,0,2),2,'0');
    }
}
