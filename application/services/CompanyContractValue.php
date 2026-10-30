<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceAmounts.php';
/** Optional stored contract amount. Absence stays unknown; it is never invented as zero. */
final class CompanyContractValue
{
    public static function input(array $input):array
    {
        if(!array_key_exists('contract_value',$input)||$input['contract_value']==='')return [];
        if(!is_string($input['contract_value'])||!preg_match('/^(?:0|[1-9][0-9]{0,10})(?:\.[0-9]{1,2})?$/D',$input['contract_value'])||!is_string($input['currency']??null)||!preg_match('/^[A-Z]{3}$/D',$input['currency']))throw new DomainException('company_contract_value_invalid');
        return ['contract_value'=>CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor($input['contract_value'])),'currency'=>$input['currency']];
    }
    public static function stored(array $snapshot):array
    {
        $keys=array_intersect_key($snapshot,array_flip(['contract_value','currency']));if(!$keys)return [];
        if(count($keys)!==2||self::input($keys)!==$keys)throw new DomainException('company_contract_value_invalid');return$keys;
    }
    public static function verifyCurrency($db,string $p,array $value):void
    {
        if(!$value)return;IssuerTransaction::assertOwned($db,$p,['currencies']);
        if(count(IssuerStorage::query($db,'SELECT id FROM '.$p.'currencies WHERE name=?',[$value['currency']])->result_array())!==1)throw new DomainException('company_contract_value_invalid');
    }
}
