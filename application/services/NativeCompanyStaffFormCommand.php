<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffFormRead.php';
/** Parser for the existing staff form. Authority and all writes remain in the native transaction. */
final class NativeCompanyStaffFormCommand
{
    public function __construct(private $db,private string $p){}
    public function removeAvatar(int $i,int $a,int $id,string $revision,string $key):string
    {
        $page=(new NativeCompanyStaffFormRead($this->db,$this->p))->page($i,$a,$id);if(!hash_equals($page['native_staff_revision'],$revision))throw new DomainException('issuer_staff_stale');
        $role=$page['native_staff_selected_role'];$roleId=$role===''?null:NativeCompanyResourceId::decode('staff_role',$role)['source_id'];
        $values=(new NativeCompanyCustomFields($this->db,$this->p))->storedInput($i,$a,'staff',$id,'edit');
        $avatar=NativeCompanyStaffAvatar::prepare($this->db,$this->p,$i,$a,$id,null);
        $form=new NativeCompanyStaffForm($this->db,$this->p,$i,$a,array_column($page['staff_departments'],'departmentid'),$roleId,$page['native_staff_roles_revision'],$values,$page['native_staff_form_revision'],null,null,$avatar);
        $writer=new CompanyStaff($this->db,$this->p);$writer->updateNative($i,$a,$id,$revision,[],$page['native_staff_permissions'],false,$key,$form);return$writer->get($i,$a,$id)['revision'];
    }
    public function save(int $i,int $a,?int $id,array $input,string $base,string $secret,callable $decrypt,?NativeCompanyStaffAvatar $avatar=null):int
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$cap=$id===null?'create':'edit';
        if($acl->primaryId()===$i||!$acl->can($i,$a,'staff','view')||!$acl->can($i,$a,'staff',$cap))throw new DomainException('issuer_staff_denied');
        $profileFields=['firstname','lastname','email','phonenumber','password','facebook','linkedin','skype','default_language','email_signature','direction','is_not_staff'];
        $metadata=['native_staff_revision','native_staff_form_revision','native_staff_roles_revision','native_staff_rate_revision','request_key','departments','role','permissions','custom_fields','hourly_rate','send_welcome_email','make_default','isedit','memberid','fakeusernameremembered','fakepasswordremembered'];
        if(array_diff(array_keys($input),array_merge($profileFields,$metadata)))throw new DomainException('issuer_staff_profile_field_denied');
        foreach(['request_key','native_staff_roles_revision']as$key)if(!is_string($input[$key]??null)||!preg_match('/^[a-f0-9]{64}$/D',$input[$key]))throw new DomainException('issuer_staff_invalid');
        foreach(['native_staff_revision','native_staff_form_revision']as$key){$v=$input[$key]??'';if(!is_string($v)||($id===null?$v!=='':!preg_match('/^[a-f0-9]{64}$/D',$v)))throw new DomainException('issuer_staff_invalid');}
        if(isset($input['memberid'])&&(string)$input['memberid']!==(string)$id)throw new DomainException('issuer_staff_denied');
        foreach(['make_default','send_welcome_email','is_not_staff']as$key)if(isset($input[$key])&&!in_array($input[$key],['0','1','on'],true))throw new DomainException('issuer_staff_invalid');
        $rateRevision=$input['native_staff_rate_revision']??null;if(!is_string($rateRevision)||!preg_match('/^(0|[1-9][0-9]{0,9})$/D',$rateRevision)||(int)$rateRevision>2147483646)throw new DomainException('native_staff_rate_invalid');
        if(!array_key_exists('hourly_rate',$input))throw new DomainException('native_staff_rate_invalid');$rate=NativeCompanyStaffRates::normalize($input['hourly_rate']);
        foreach(['departments','permissions','custom_fields']as$key)if(isset($input[$key])&&!is_array($input[$key]))throw new DomainException('issuer_staff_invalid');
        $departments=$input['departments']??[];if(!array_is_list($departments))throw new DomainException('issuer_staff_invalid');
        $role=$input['role']??'';if(!is_string($role))throw new DomainException('native_staff_role_denied');$source=null;if($role!==''){$ref=NativeCompanyResourceId::decode('staff_role',$role);if($ref['source_kind']!=='company_staff_role')throw new DomainException('native_staff_role_denied');$source=$ref['source_id'];}
        $custom=$input['custom_fields']??[];if(array_diff(array_keys($custom),['staff'])||!is_array($custom['staff']??[]))throw new DomainException('native_custom_field_invalid');
        $profile=array_intersect_key($input,array_flip($profileFields));foreach($profile as$v)if(!is_string($v))throw new DomainException('issuer_staff_invalid');
        if($id!==null){$current=(new NativeCompanyStaffRead($this->db,$this->p))->member($i,$a,$id);if(!$current['access']['profile_editable']){foreach($profile as$key=>$value){if($key==='password'&&$value==='')continue;if(!property_exists($current['member'],$key)||(string)$current['member']->$key!==$value)throw new DomainException('issuer_staff_shared_identity');}$profile=[];}elseif(!isset($profile['is_not_staff'])){$profile['is_not_staff']='0';}}
        elseif(!isset($profile['is_not_staff']))$profile['is_not_staff']='0';
        $send=isset($input['send_welcome_email'])&&$input['send_welcome_email']!=='0';if($send&&$id!==null)throw new DomainException('native_staff_invite_denied');
        $welcome=$send?new NativeCompanyStaffWelcome($this->db,$this->p,$i,$a,$input['request_key'],$base,$secret,$decrypt):null;
        $form=new NativeCompanyStaffForm($this->db,$this->p,$i,$a,$departments,$source,$input['native_staff_roles_revision'],$custom['staff']??[],$id===null?null:$input['native_staff_form_revision'],$welcome,['value'=>$rate,'revision'=>(int)$rateRevision],$avatar);
        $writer=new CompanyStaff($this->db,$this->p);$default=isset($input['make_default'])&&$input['make_default']!=='0';
        return$id===null?$writer->createNative($i,$a,$profile,$input['permissions']??[],$default,$input['request_key'],$form):$writer->updateNative($i,$a,$id,$input['native_staff_revision'],$profile,$input['permissions']??[],$default,$input['request_key'],$form);
    }
}
