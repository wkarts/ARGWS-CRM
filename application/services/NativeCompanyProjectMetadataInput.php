<?php

declare(strict_types=1);

/** Pure explicit native form values. This grants no authority and never determines billing from prior invoices. */
final class NativeCompanyProjectMetadataInput
{
    public const FIELDS=['workflow_status','billing_type','currency_id','project_cost','project_rate_per_hour','estimated_hours','progress_from_tasks','progress'];
    public static function normalize(array$input,string$lifecycle):array
    {
        if(array_diff(array_keys($input),self::FIELDS)||array_diff(self::FIELDS,array_keys($input)))throw new DomainException('native_project_configuration_invalid');
        $out=[];$out['workflow_status']=self::integer($input['workflow_status'],0,5);
        $expected=match($out['workflow_status']){0,1,2,3=>'active',4=>'completed',5=>'cancelled'};
        if($lifecycle!==$expected)throw new DomainException('native_project_workflow_lifecycle_conflict');
        $out['billing_type']=self::integer($input['billing_type'],1,3);
        $out['currency_id']=self::integer($input['currency_id'],1,2147483647);
        $out['project_cost']=self::decimal($input['project_cost'],true);
        $out['project_rate_per_hour']=self::decimal($input['project_rate_per_hour'],false);
        $out['estimated_hours']=self::decimal($input['estimated_hours'],true);
        $out['progress_from_tasks']=self::integer($input['progress_from_tasks'],0,1);
        $out['progress']=self::integer($input['progress'],0,100);
        // Preserve the native form's mutually exclusive fixed/hourly/task-rate configuration.
        if($out['billing_type']===1)$out['project_rate_per_hour']='0.00';
        elseif($out['billing_type']===2)$out['project_cost']='0.00';
        else{$out['project_cost']='0.00';$out['project_rate_per_hour']='0.00';}
        return$out;
    }
    private static function integer($value,int$minimum,int$maximum):int
    {
        if((!is_string($value)&&!is_int($value))||!preg_match('/^(?:0|[1-9][0-9]*)$/D',(string)$value)||strlen((string)$value)>10||(int)$value<$minimum||(int)$value>$maximum)throw new DomainException('native_project_configuration_invalid');return(int)$value;
    }
    private static function decimal($value,bool$nullable):?string
    {
        if($nullable&&($value===null||$value===''))return null;
        if((!is_string($value)&&!is_int($value))||!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D',(string)$value,$match))throw new DomainException('native_project_configuration_invalid');
        return$match[1].'.'.str_pad($match[2]??'',2,'0');
    }
}
