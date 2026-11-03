<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceFormInput.php';
require_once __DIR__.'/NativeCompanyResourceId.php';

/**
 * Parse the original create-estimate wire format without authorizing or writing.
 * Returned references and custom fields MUST be resolved under the company by
 * the eventual writer. This parser is not an HTTP admission or an issuer proof.
 */
final class NativeCompanyEstimateFormInput
{
    private static function checkbox(array $input,string $key):int
    {
        if(!array_key_exists($key,$input))return 0;
        if(!in_array($input[$key],['on','1',1],true))throw new DomainException('native_estimate_form_invalid');
        return 1;
    }
    private static function custom($input,string $kind):array
    {
        if(!is_array($input)||array_diff(array_keys($input),[$kind])||!is_array($input[$kind]??[]))throw new DomainException('native_estimate_form_invalid');
        $fields=$input[$kind]??[];if(count($fields)>256)throw new DomainException('native_estimate_form_invalid');
        try{$json=json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('native_estimate_form_invalid');}
        if(strlen($json)>65536)throw new DomainException('native_estimate_form_invalid');
        return $fields;
    }
    public static function create(array $input,string $dateFormat):array
    {
        $allowed=['_native_estimate_id','_native_estimate_revision','_native_estimate_request_key','_native_estimate_number_revision',
            'clientid','project_id','estimate_request_id','number','date','expirydate','currency','status','reference_no','clientnote','tags','newitems','custom_fields',
            'subtotal','total','discount_percent','discount_total','discount_type','adjustment','save_and_send','save_and_send_later',
            'item_select','description','long_description','quantity','unit','rate','taxname','items','removed_items','show_shipping_on_estimate'];
        $allowed=array_merge($allowed,array_keys(CompanyInvoiceNativeFields::TEXT),array_keys(CompanyInvoiceNativeFields::INTEGER));
        $allowed=array_values(array_diff($allowed,['show_shipping_on_invoice']));
        if(array_diff(array_keys($input),$allowed)||($input['_native_estimate_id']??null)!==''||($input['_native_estimate_revision']??null)!==''
            ||!is_array($input['newitems']??null)||!$input['newitems']||count($input['newitems'])>100)throw new DomainException('native_estimate_form_invalid');
        foreach(['items','removed_items']as$key)if(!in_array($input[$key]??null,[null,'',[]],true))throw new DomainException('native_estimate_form_source_required');
        $actions=[];foreach(['save_and_send','save_and_send_later']as$key)if(array_key_exists($key,$input)){if($input[$key]!=='true')throw new DomainException('native_estimate_form_invalid');$actions[]=$key;}
        if(count($actions)>1)throw new DomainException('native_estimate_form_invalid');$action=$actions[0]??'save';
        $items=[];$lineFields=[];$orders=[];
        foreach($input['newitems']as$row){
            if(!is_array($row)||array_diff(array_keys($row),['order','description','long_description','qty','rate','unit','taxname','custom_fields','is_optional','is_selected'])
                ||!is_string($row['order']??null)||!preg_match('/^[1-9][0-9]{0,2}$/D',$row['order'])||isset($orders[$row['order']]))throw new DomainException('native_estimate_form_invalid');
            $orders[$row['order']]=true;$taxes=$row['taxname']??[];if(!is_array($taxes)||!array_is_list($taxes))throw new DomainException('native_estimate_form_invalid');
            $normalizedTaxes=[];foreach($taxes as$tax){if(!is_string($tax)||substr_count($tax,'|')!==1)throw new DomainException('native_estimate_form_invalid');[$name,$rate]=explode('|',$tax);$normalizedTaxes[]=['name'=>$name,'rate'=>NativeCompanyTaxRate::exact($rate)];}
            $line=array_intersect_key($row,array_flip(['description','long_description','qty','rate','unit','is_optional','is_selected']));$line['taxes']=$normalizedTaxes;
            $items[(int)$row['order']]=$line;$lineFields[(int)$row['order']]=self::custom($row['custom_fields']??[],'items');
        }
        ksort($items);ksort($lineFields);if(array_keys($items)!==range(1,count($items)))throw new DomainException('native_estimate_form_invalid');
        $percent=$input['discount_percent']??'0';$fixed=$input['discount_total']??'0';$mode=CompanyInvoiceAmounts::minor($percent)>0?'percent':'fixed';
        $options=['calculation_contract'=>'native_sales_v1','discount_mode'=>$mode,'discount_value'=>$mode==='percent'?$percent:$fixed,
            'discount_type'=>$input['discount_type']??'','adjustment'=>($input['adjustment']??'')===''?'0':$input['adjustment']];
        $amounts=CompanyInvoiceLines::calculate(array_values($items),$options);
        foreach(['subtotal','total','discount_total']as$key)if(!isset($input[$key])||CompanyInvoiceAmounts::minor($input[$key])!==CompanyInvoiceAmounts::minor($amounts[$key]))throw new DomainException('native_estimate_form_amount_mismatch');
        $native=array_intersect_key($input,array_flip(array_merge(array_keys(CompanyInvoiceNativeFields::TEXT),array_keys(CompanyInvoiceNativeFields::INTEGER))));
        // The two original documents use different names for the same display flag.
        unset($native['show_shipping_on_invoice']);$native['include_shipping']=self::checkbox($input,'include_shipping');$native['show_shipping_on_invoice']=self::checkbox($input,'show_shipping_on_estimate');
        if(($native['sale_agent']??null)==='')$native['sale_agent']='0';$native=CompanyInvoiceNativeFields::input($native);
        $payload=['client_id'=>$input['clientid']??null,'currency'=>$input['currency']??null,
            'date'=>NativeCompanyInvoiceFormInput::date($input['date']??null,$dateFormat),
            'due_date'=>NativeCompanyInvoiceFormInput::date($input['expirydate']??'',$dateFormat,true),
            'items'=>array_values($items),'note'=>$input['clientnote']??'','native_fields'=>$native]+$options;
        $payload=CompanyInvoiceDraftsInput::normalize($payload);
        $status=$input['status']??null;if(!is_string($status)||!in_array($status,['1','2','3','4','5'],true))throw new DomainException('native_estimate_form_invalid');
        $reference=$input['reference_no']??'';if(!is_string($reference)||strlen($reference)>100||preg_match('//u',$reference)!==1||preg_match('/[\x00-\x1f\x7f]/',$reference))throw new DomainException('native_estimate_form_invalid');
        $tags=$input['tags']??'';if(!is_string($tags)||strlen($tags)>10000||preg_match('//u',$tags)!==1||preg_match('/[\x00-\x1f\x7f]/',$tags))throw new DomainException('native_estimate_form_invalid');
        $tags=$tags===''?[]:array_map('trim',explode(',',$tags));if(count($tags)>100||in_array('',$tags,true))throw new DomainException('native_estimate_form_invalid');
        $project=$input['project_id']??'';if($project!=='')$project=NativeCompanyResourceId::decode('project',$project)['canonical_id'];
        $request=$input['estimate_request_id']??'';if($request!=='')$request=IssuerPolicy::id($request);
        $number=$input['number']??null;$revision=$input['_native_estimate_number_revision']??null;
        if(!is_string($number)||!preg_match('/^[0-9]{1,30}$/D',$number)||ltrim($number,'0')===''||!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('native_estimate_form_invalid');
        return ['payload'=>$payload,'metadata'=>['status'=>$status,'reference_no'=>$reference,'project'=>$project,'estimate_request_id'=>$request,
            'estimate_fields'=>self::custom($input['custom_fields']??[],'estimate'),'line_fields'=>array_values($lineFields),'tags'=>$tags,
            'number_selection'=>['next_number'=>ltrim($number,'0'),'revision'=>$revision]],
            'action'=>$action,'request_key'=>CompanyInvoiceDraftsInput::key($input['_native_estimate_request_key']??null)];
    }
}
