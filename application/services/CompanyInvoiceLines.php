<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceAmounts.php';
/** Invoice-only native line attributes; shared sales amount contracts remain unchanged. */
final class CompanyInvoiceLines
{
    public const DETAILS_BYTES=20000;
    public const UNIT_BYTES=160;
    private static function literal($value,int $max):string{if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('company_invoice_invalid_input');return $value;}
    private static function flag($value):int{if(!in_array($value,[0,1,'0','1'],true))throw new DomainException('company_invoice_invalid_input');return (int)$value;}
    /** Defaults are omitted from normalized hashes, preserving pre-extension idempotency keys. */
    public static function metadata(array $item,bool $stored=false):array
    {
        $details=$item['long_description']??'';$unit=$item['unit']??'';if(!$stored&&((array_key_exists('long_description',$item)&&$item['long_description']===null)||(array_key_exists('unit',$item)&&$item['unit']===null)))throw new DomainException('company_invoice_invalid_input');$details=self::literal($details,self::DETAILS_BYTES);$unit=self::literal($unit,self::UNIT_BYTES);if(preg_match_all('/./us',$unit)>40)throw new DomainException('company_invoice_invalid_input');if((array_key_exists('is_optional',$item)&&$item['is_optional']===null)||(array_key_exists('is_selected',$item)&&$item['is_selected']===null))throw new DomainException('company_invoice_invalid_input');$optional=self::flag($item['is_optional']??0);$selected=self::flag($item['is_selected']??($optional===1?0:1));if($optional===0&&$selected!==1)throw new DomainException('company_invoice_invalid_input');$extra=[];if($details!=='')$extra['long_description']=$details;if($unit!=='')$extra['unit']=$unit;if($optional!==0||$selected!==1){$extra['is_optional']=$optional;$extra['is_selected']=$selected;}return $extra;
    }
    public static function normalize(array $items):array
    {
        if(count($items)<1||count($items)>100)throw new DomainException('company_invoice_invalid_input');$out=[];
        foreach(array_values($items) as $item){if(!is_array($item)||array_diff(array_keys($item),['description','qty','rate','taxname','taxrate','taxes','long_description','unit','is_optional','is_selected']))throw new DomainException('company_invoice_invalid_input');$extra=self::metadata($item);$money=CompanyInvoiceAmounts::calculate([['description'=>$item['description']??null,'qty'=>$item['qty']??null,'rate'=>$item['rate']??null,'taxes'=>[]]])['items'][0];$taxInput=['description'=>'Invoice tax validation','qty'=>'1.00','rate'=>'1.00']+array_intersect_key($item,array_flip(['taxname','taxrate','taxes']));$money['taxes']=CompanyInvoiceAmounts::calculate([$taxInput])['items'][0]['taxes'];$out[]=$money+$extra;}
        return $out;
    }
    /** Native sales_helper638/1013: optional unselected lines persist but do not enter monetary totals. */
    public static function calculate(array $items,array $options=[]):array
    {
        $normalized=self::normalize($items);$charged=[];foreach($normalized as $line){if(($line['is_optional']??0)===1&&($line['is_selected']??1)===0)continue;$charged[]=array_intersect_key($line,array_flip(['description','qty','rate','taxes']));}if(!$charged)throw new DomainException('company_invoice_invalid_input');$amounts=CompanyInvoiceAmounts::calculate($charged,$options);$amounts['items']=$normalized;return $amounts;
    }
}
