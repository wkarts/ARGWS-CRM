<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerPolicy.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
/** Parser for the existing init_invoices_total POST contract. Transport tokens are removed by its controller. */
final class NativeCompanyInvoiceTotals
{
    public static function request(array $input):array
    {
        if(array_diff(array_keys($input),['currency','years','init_total','display_type','customer_id','project_id'])
            ||(isset($input['init_total'])&&!in_array($input['init_total'],[true,1,'1','true'],true))
            ||(isset($input['display_type'])&&!in_array($input['display_type'],['','badge'],true)))throw new DomainException('native_company_invoice_query_invalid');
        $years=$input['years']??[];
        if(!is_array($years)||count($years)>100)throw new DomainException('native_company_invoice_query_invalid');
        foreach($years as$year)if((!is_int($year)&&!is_string($year))||!preg_match('/^[1-9][0-9]{3}$/D',(string)$year))throw new DomainException('native_company_invoice_query_invalid');
        $currency=$input['currency']??null;
        if($currency==='')$currency=null;
        if($currency!==null)$currency=IssuerPolicy::id($currency);
        $scope=[];
        foreach(['customer_id','project_id']as$key)if(isset($input[$key])&&$input[$key]!==''){
            // Decode validates lossless transport only; actual authorization happens in summary's resolver.
            NativeCompanyResourceId::decode($key==='customer_id'?'client':'project',$input[$key]);$scope[$key]=$input[$key];
        }
        if(count($scope)>1)throw new DomainException('native_company_invoice_query_invalid');
        return ['currency'=>$currency,'years'=>array_values($years),'scope'=>$scope];
    }
}
