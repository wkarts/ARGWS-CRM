<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/issuers/IssuerPolicy.php';

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
        $allowed=['native_lead_form','lead_id','revision','request_key','action','name','company','email','phonenumber','vat','description','status','source','assigned','notify_assignee','reminder_local','reminder_timezone','body','file_key'];
        if(array_diff(array_keys($data),$allowed)||($data['native_lead_form']??null)!==self::WIRE||!is_string($data['request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$data['request_key'])||!is_string($data['action']??null))throw new DomainException('company_lead_invalid_input');
        $id=self::source($data['lead_id']??null,true);$route=self::source($path,true);if($route!==null&&$id!==$route)throw new DomainException('company_lead_access_denied');$action=$data['action'];
        if(!in_array($action,['create','update','move','note','archive','restore','convert','file_upload','file_hide'],true)||($action==='create'?($id!==null||$route!==null):$id===null))throw new DomainException('company_lead_invalid_input');
        $revision=$action==='create'?null:IssuerPolicy::id($data['revision']??null);$input=[];
        $base=['native_lead_form','lead_id','revision','request_key','action'];$fields=match($action){'create','update'=>['name','company','email','phonenumber','vat','description','status','source','assigned','notify_assignee','reminder_local','reminder_timezone'],'move'=>['status'],'note'=>['body'],'file_hide'=>['file_key'],default=>[]};
        if(array_diff(array_keys($data),array_merge($base,$fields)))throw new DomainException('company_lead_invalid_input');
        if(in_array($action,['create','update'],true)){
            foreach(['name'=>'name','company'=>'company','email'=>'email','phonenumber'=>'phone','vat'=>'vat','description'=>'description','status'=>'stage_id','source'=>'source_id','assigned'=>'assignee_id','notify_assignee'=>'notify_assignee','reminder_local'=>'reminder_local','reminder_timezone'=>'reminder_timezone']as$old=>$current){$value=$data[$old]??(in_array($old,['assigned','notify_assignee'],true)?'0':'');if(!is_string($value)&&!is_int($value))throw new DomainException('company_lead_invalid_input');$input[$current]=$value;}
            if($input['reminder_local']==='')$input['reminder_timezone']='';
        }elseif($action==='move')$input=['stage_id'=>IssuerPolicy::id($data['status']??null)];
        elseif($action==='note'){if(!is_string($data['body']??null))throw new DomainException('company_lead_invalid_input');$input=['body'=>$data['body']];}
        elseif($action==='file_hide'){if(!is_string($data['file_key']??null))throw new DomainException('company_lead_invalid_input');$input=['file_key'=>$data['file_key']];}
        return['action'=>$action,'id'=>$id,'revision'=>$revision,'request_key'=>$data['request_key'],'input'=>$input];
    }
}
