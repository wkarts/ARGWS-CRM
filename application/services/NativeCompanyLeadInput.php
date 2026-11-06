<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/issuers/IssuerPolicy.php';
require_once __DIR__.'/CompanyLeadMetadata.php';
require_once __DIR__.'/NativeCompanyFormatting.php';

/** The original modal's names map to the existing, closed CompanyLeads input contract. */
final class NativeCompanyLeadInput
{
    public const WIRE='original_lead_v1';
    public static function source($value,bool $optional=false):?int
    {
        if($optional&&($value===null||$value===''))return null;$ref=NativeCompanyResourceId::decode('lead',$value);if($ref['source_kind']!=='company_lead')throw new DomainException('company_lead_access_denied');return$ref['source_id'];
    }
    public static function decode(array $data,$path=''):array
    {
        $nativeFields=['native_lead_fields','native_lead_fields_revision','native_lead_dateformat','native_lead_timeformat','title','website','address','city','state','country','zip','default_language','lead_value','tags','custom_fields','lastcontact','contacted_today','custom_contact_date'];
        $allowed=array_merge(['native_lead_form','lead_id','revision','request_key','action','name','company','email','phonenumber','vat','description','status','source','assigned','notify_assignee','reminder_local','reminder_timezone','body','file_key'],$nativeFields);
        if(array_diff(array_keys($data),$allowed)||($data['native_lead_form']??null)!==self::WIRE||!is_string($data['request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$data['request_key'])||!is_string($data['action']??null))throw new DomainException('company_lead_invalid_input');
        $id=self::source($data['lead_id']??null,true);$route=self::source($path,true);if($route!==null&&$id!==$route)throw new DomainException('company_lead_access_denied');$action=$data['action'];
        if(!in_array($action,['create','update','move','note','archive','restore','convert','file_upload','file_hide'],true)||($action==='create'?($id!==null||$route!==null):$id===null))throw new DomainException('company_lead_invalid_input');
        $revision=$action==='create'?null:IssuerPolicy::id($data['revision']??null);$input=[];
        $base=['native_lead_form','lead_id','revision','request_key','action'];$fields=match($action){'create','update'=>['name','company','email','phonenumber','vat','description','status','source','assigned','notify_assignee','reminder_local','reminder_timezone'],'move'=>['status'],'note'=>['body'],'file_hide'=>['file_key'],default=>[]};
        $metadata=in_array($action,['create','update'],true)&&array_key_exists('native_lead_fields',$data);
        if($metadata){if(($data['native_lead_fields']??null)!=='1')throw new DomainException('company_lead_invalid_input');$fields=array_merge($fields,$nativeFields);}
        if(array_diff(array_keys($data),array_merge($base,$fields)))throw new DomainException('company_lead_invalid_input');
        if(in_array($action,['create','update'],true)){
            foreach(['name'=>'name','company'=>'company','email'=>'email','phonenumber'=>'phone','vat'=>'vat','description'=>'description','status'=>'stage_id','source'=>'source_id','assigned'=>'assignee_id','notify_assignee'=>'notify_assignee','reminder_local'=>'reminder_local','reminder_timezone'=>'reminder_timezone']as$old=>$current){$value=$data[$old]??(in_array($old,['assigned','notify_assignee'],true)?'0':'');if(!is_string($value)&&!is_int($value))throw new DomainException('company_lead_invalid_input');$input[$current]=$value;}
            if($input['reminder_local']==='')$input['reminder_timezone']='';
            if($metadata)$input['native_fields']=self::metadata($data,$action);
        }elseif($action==='move')$input=['stage_id'=>IssuerPolicy::id($data['status']??null)];
        elseif($action==='note'){if(!is_string($data['body']??null))throw new DomainException('company_lead_invalid_input');$input=['body'=>$data['body']];}
        elseif($action==='file_hide'){if(!is_string($data['file_key']??null))throw new DomainException('company_lead_invalid_input');$input=['file_key'=>$data['file_key']];}
        return['action'=>$action,'id'=>$id,'revision'=>$revision,'request_key'=>$data['request_key'],'input'=>$input];
    }
    private static function metadata(array $data,string $action):array
    {
        $formats=['dateformat'=>$data['native_lead_dateformat']??null,'time_format'=>$data['native_lead_timeformat']??null];CompanySettingsFormats::validate($formats);
        $formatting=new NativeCompanyFormatting(CompanySettingsFormats::dates()[$formats['dateformat']],$formats['time_format']);
        if($action==='update'&&(array_key_exists('contacted_today',$data)||array_key_exists('custom_contact_date',$data)))throw new DomainException('company_lead_metadata_invalid');
        if($action==='create'&&array_key_exists('lastcontact',$data))throw new DomainException('company_lead_metadata_invalid');
        $fields=$data['custom_fields']??[];if(!is_array($fields)||array_diff(array_keys($fields),['leads'])||(isset($fields['leads'])&&!is_array($fields['leads'])))throw new DomainException('company_lead_metadata_invalid');
        $input=['version'=>CompanyLeadMetadata::VERSION,'form_revision'=>$data['native_lead_fields_revision']??null,'formats'=>$formats];
        foreach(CompanyLeadMetadata::TEXT as$name=>$unused)$input[$name]=$data[$name]??'';
        foreach(['country','lead_value']as$name)$input[$name]=$data[$name]??'';
        $input['lastcontact']=$formatting->toSql($data[$action==='create'?'custom_contact_date':'lastcontact']??null,true);
        $input['contacted_today']=$action==='create'?($data['contacted_today']??'0'):'0';$input['tags']=$data['tags']??[];$input['custom_fields']=$fields['leads']??[];
        return CompanyLeadMetadata::normalize($input);
    }
}
