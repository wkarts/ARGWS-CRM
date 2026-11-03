<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyCatalog.php';
require_once __DIR__.'/CompanyInvoiceLines.php';
require_once __DIR__.'/NativeCompanyTaxRate.php';
/** Exact native expense snapshots (native tax precision: two decimal places). Scalar legacy tax_rate is intentionally absent (NULL). */
final class NativeCompanyExpenseFormValues
{
    public const CONTRACT='native_expense_v1';
    private static function text($value,int$max):string
    {
        if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))throw new DomainException('native_expense_form_invalid');return$value;
    }
    public static function normalize(array$input):array
    {
        if(array_keys($input)!==['contract','name','reference_no','payment_mode','taxes']||$input['contract']!==self::CONTRACT||!is_array($input['taxes'])||!array_is_list($input['taxes'])||count($input['taxes'])>2)throw new DomainException('native_expense_form_invalid');
        $mode=$input['payment_mode'];if($mode!==''){$ref=NativeCompanyResourceId::decode('payment_mode',$mode);if($ref['source_kind']!=='company_payment_mode')throw new DomainException('native_expense_form_invalid');$mode=$ref['canonical_id'];}
        $taxes=[];foreach($input['taxes']as$index=>$tax){if(!is_array($tax)||array_keys($tax)!==['position','id','name','rate']||$tax['position']!==$index+1)throw new DomainException('native_expense_form_invalid');$id=NativeCompanyResourceId::decode('tax',$tax['id']);if($id['source_kind']!=='company_tax')throw new DomainException('native_expense_form_invalid');$fields=NativeCompanyCatalog::simpleFields(['name'=>$tax['name'],'taxrate'=>$tax['rate']],true);$taxes[]=['position'=>$index+1,'id'=>$id['canonical_id'],'name'=>$fields['name'],'rate'=>$fields['taxrate']];}
        return['contract'=>self::CONTRACT,'name'=>self::text($input['name'],191),'reference_no'=>self::text($input['reference_no'],100),'payment_mode'=>$mode,'taxes'=>$taxes];
    }
    public static function amounts(string$amount,array$profile):array
    {
        $profile=self::normalize($profile);if(!preg_match('/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/D',$amount)||CompanyInvoiceAmounts::minor($amount)<1)throw new DomainException('native_expense_form_invalid');
        // The original UI adds each tax fraction before final money formatting. Preserve order/rates,
        // and use the existing exact rational native calculator, not a summed/rounded scalar rate.
        $line=['description'=>'Expense amount calculation','qty'=>'1','rate'=>$amount,'taxes'=>array_map(static fn($tax)=>['name'=>$tax['name'],'rate'=>NativeCompanyTaxRate::exact($tax['rate'])],$profile['taxes'])];
        $money=CompanyInvoiceLines::calculate([$line],['calculation_contract'=>'native_sales_v1']);
        return['amount_cents'=>CompanyInvoiceAmounts::minor($money['subtotal']),'tax_cents'=>CompanyInvoiceAmounts::minor($money['tax']),'tax_rate'=>null,'total_cents'=>CompanyInvoiceAmounts::minor($money['total'])];
    }
}
