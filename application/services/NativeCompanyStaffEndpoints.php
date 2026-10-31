<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffRoles.php';
require_once __DIR__.'/NativeAvailableLanguages.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
/** Adapters of existing Staff actions. Admission is owned by the central route matrix. */
final class NativeCompanyStaffEndpoints
{
    private static function context($ci):array
    {
        if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('issuer_staff_denied');
        $context=CompanyRequestScope::context();return[$context->issuerId(),$context->staffId()];
    }
    private static function personal($ci):array
    {[$i,$a]=self::context($ci);if((int)get_staff_user_id()!==$a)throw new DomainException('issuer_staff_denied');return[$i,$a];}
    public static function hiddenColumns($ci):void
    {
        [$i,$a]=self::personal($ci);CompanyRequestScope::assertNativeMutation($ci);
        $id=$ci->input->post('id');$hidden=$ci->input->post('hidden')??[];
        if($id!=='tickets'||!is_array($hidden))throw new DomainException('native_table_order_invalid');
        require_once __DIR__.'/NativeCompanyTableOrder.php';(new NativeCompanyTableOrder($ci->db,db_prefix(),$i,$a))->saveHidden('tickets',$hidden);
    }
    public static function status($ci,$id,$status):array
    {
        [$i,$a]=self::personal($ci);CompanyRequestScope::assertNativeMutation($ci);
        if(!in_array($status,['0','1',0,1],true))throw new DomainException('issuer_staff_invalid');
        $id=IssuerPolicy::id($id);$revision=$ci->input->post('revision');$key=$ci->input->post('request_key');
        if(!is_string($revision)||!is_string($key))throw new DomainException('issuer_staff_invalid');
        require_once __DIR__.'/CompanyStaff.php';$writer=new CompanyStaff($ci->db,db_prefix());$writer->setNativeStatus($i,$a,$id,$revision,(int)$status,$key);$after=$writer->get($i,$a,$id);if((int)$after['active']!==(int)$status)throw new DomainException('issuer_staff_stale');
        return ['success'=>true,'active'=>(int)$after['active'],'revision'=>$after['revision'],'next_request_key'=>bin2hex(random_bytes(32))];
    }
    public static function ownPage($ci):array
    {
        [$i,$a]=self::personal($ci);require_once __DIR__.'/NativeCompanyStaffSelf.php';$self=new NativeCompanyStaffSelf($ci->db,db_prefix());$page=$self->page($i,$a);
        $sql='SELECT d.id,d.name FROM '.db_prefix().'company_department_members m JOIN '.db_prefix().'company_departments d ON d.issuer_id=m.issuer_id AND d.id=m.department_source_id WHERE m.issuer_id=? AND m.staff_id=? AND m.department_source_kind=? ORDER BY d.name,d.id';$args=[$i,$a,'company_ticket_department'];
        $links=IssuerStorage::query($ci->db,'SELECT department_source_kind,department_source_id FROM '.db_prefix().'company_department_members WHERE issuer_id=? AND staff_id=? ORDER BY id',[$i,$a])->result_array();foreach($links as$link)if($link['department_source_kind']!=='company_ticket_department')throw new DomainException('issuer_staff_denied');
        $rows=IssuerStorage::query($ci->db,$sql,$args)->result_array();if(count($rows)!==count($links)||count(array_unique(array_column($rows,'id')))!==count($rows))throw new DomainException('issuer_staff_denied');$departments=array_map(static fn($r)=>['departmentid'=>NativeCompanyResourceId::encode('company_ticket_department',$r['id']),'name'=>$r['name']],$rows);
        if($page['revision']!==$self->page($i,$a)['revision']||$rows!==IssuerStorage::query($ci->db,$sql,$args)->result_array())throw new DomainException('issuer_staff_stale');if($links!==IssuerStorage::query($ci->db,'SELECT department_source_kind,department_source_id FROM '.db_prefix().'company_department_members WHERE issuer_id=? AND staff_id=? ORDER BY id',[$i,$a])->result_array())throw new DomainException('issuer_staff_stale');
        return['member'=>$page['member'],'current_user'=>$page['member'],'native_staff_self_revision'=>$page['revision'],'native_staff_self'=>true,'native_staff_languages'=>NativeAvailableLanguages::all(),'native_staff_email_factor_available'=>$self->emailFactorAvailable(),'departments'=>$departments,'staff_departments'=>$departments];
    }
    public static function ownSave($ci):string
    {
        [$i,$a]=self::personal($ci);CompanyRequestScope::assertNativeMutation($ci);$input=$ci->input->post(null,false);if(!is_array($input))throw new DomainException('issuer_staff_invalid');
        if(isset($ci->security))unset($input[$ci->security->get_csrf_token_name()]);$revision=$input['native_staff_self_revision']??null;unset($input['native_staff_self_revision']);if(!is_string($revision))throw new DomainException('issuer_staff_invalid');
        require_once __DIR__.'/NativeCompanyStaffSelf.php';$avatar=self::uploadedAvatar($ci,$i,$a,$a,true);
        return(new NativeCompanyStaffSelf($ci->db,db_prefix()))->saveProfile($i,$a,$revision,$input,$avatar);
    }
    public static function uploadedAvatar($ci,int $i,int $a,?int $target,bool $personal=false):?NativeCompanyStaffAvatar
    {
        if(!isset($_FILES['profile_image'])||(int)($_FILES['profile_image']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
        require_once __DIR__.'/NativeCompanyStaffAvatar.php';$file=$_FILES['profile_image'];if(!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||!is_int($file['size']??null)||$file['size']<1||$file['size']>2097152)throw new DomainException('native_staff_avatar_invalid');
        // Establish authority before reading any uploaded bytes; the factory checks it again before decode.
        $acl=new IssuerResourceAccess($ci->db,db_prefix());if($personal?($target!==$a||!$acl->member($i,$a)):(!$acl->can($i,$a,'staff','view')||!$acl->can($i,$a,'staff',$target===null?'create':'edit')))throw new DomainException('issuer_staff_denied');
        $bytes=file_get_contents($file['tmp_name'],false,null,0,2097153);if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new DomainException('native_staff_avatar_invalid');return NativeCompanyStaffAvatar::prepare($ci->db,db_prefix(),$i,$a,$target,$bytes,$personal);
    }
    public static function removeAvatar($ci,$id=''):string
    {
        [$i,$a]=self::personal($ci);CompanyRequestScope::assertNativeMutation($ci);$revision=$ci->input->post('revision');if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('issuer_staff_invalid');
        require_once __DIR__.'/NativeCompanyStaffSelf.php';
        if($id==='')return(new NativeCompanyStaffSelf($ci->db,db_prefix()))->saveProfile($i,$a,$revision,[],NativeCompanyStaffAvatar::prepare($ci->db,db_prefix(),$i,$a,$a,null,true));
        require_once __DIR__.'/NativeCompanyStaffFormCommand.php';$key=$ci->input->post('request_key');if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('issuer_staff_invalid');
        return(new NativeCompanyStaffFormCommand($ci->db,db_prefix()))->removeAvatar($i,$a,IssuerPolicy::id($id),$revision,$key);
    }
    public static function googleSetup($ci):array
    {
        [$i,$a]=self::personal($ci);CompanyRequestScope::assertNativeMutation($ci);require_once __DIR__.'/NativeCompanyStaffSelf.php';
        $setup=(new NativeCompanyStaffSelf($ci->db,db_prefix()))->googleSetup($i,$a,(string)$ci->config->item('base_url'));
        $writer=new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),new \BaconQrCode\Renderer\Image\ImagickImageBackEnd()));
        return['qrURL'=>base64_encode($writer->writeString($setup['uri'])),'secret'=>$setup['secret'],'native_staff_two_factor'=>true];
    }
    public static function verifyGoogleSetup($ci):bool
    {
        [$i,$a]=self::personal($ci);CompanyRequestScope::assertNativeMutation($ci);$secret=$ci->input->post('secret');$code=$ci->input->post('code');if(!is_string($secret)||!is_string($code))throw new DomainException('native_staff_two_factor_invalid');
        require_once __DIR__.'/NativeCompanyStaffSelf.php';return(new NativeCompanyStaffSelf($ci->db,db_prefix()))->verifyGoogleSetup($i,$a,$secret,$code);
    }
    public static function ownTwoFactor($ci):string
    {
        [$i,$a]=self::context($ci);CompanyRequestScope::assertNativeMutation($ci);
        if((int)get_staff_user_id()!==$a)throw new DomainException('issuer_staff_denied');
        $input=$ci->input->post(null,false);if(!is_array($input))throw new DomainException('issuer_staff_invalid');if(isset($ci->security))unset($input[$ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($input),['two_factor_auth','two_factor_oldpassword','native_staff_self_revision','secret','google_auth_code']))throw new DomainException('issuer_staff_profile_field_denied');
        foreach(['two_factor_auth','two_factor_oldpassword','native_staff_self_revision']as$key)if(!is_string($input[$key]??null))throw new DomainException('issuer_staff_invalid');
        foreach(['secret','google_auth_code']as$key)if(isset($input[$key])&&!is_string($input[$key]))throw new DomainException('native_staff_two_factor_invalid');
        require_once __DIR__.'/NativeCompanyStaffSelf.php';$ci->load->library('encryption');
        return(new NativeCompanyStaffSelf($ci->db,db_prefix()))->twoFactor($i,$a,$input['native_staff_self_revision'],$input['two_factor_oldpassword'],$input['two_factor_auth'],($input['secret']??'')===''?null:$input['secret'],($input['google_auth_code']??'')===''?null:$input['google_auth_code'],fn($value)=>$ci->encryption->encrypt($value));
    }
    public static function ownPassword($ci):string
    {
        [$i,$a]=self::context($ci);CompanyRequestScope::assertNativeMutation($ci);
        if((int)get_staff_user_id()!==$a)throw new DomainException('issuer_staff_denied');
        $input=$ci->input->post(null,false);if(!is_array($input))throw new DomainException('issuer_staff_invalid');
        if(isset($ci->security))unset($input[$ci->security->get_csrf_token_name()]);
        if(array_diff(array_keys($input),['oldpassword','newpassword','newpasswordr','native_staff_self_revision']))throw new DomainException('issuer_staff_profile_field_denied');
        foreach(['oldpassword','newpassword','newpasswordr','native_staff_self_revision']as$key)if(!is_string($input[$key]??null))throw new DomainException('issuer_staff_invalid');
        require_once __DIR__.'/NativeCompanyStaffSelf.php';
        return(new NativeCompanyStaffSelf($ci->db,db_prefix()))->changePassword($i,$a,$input['native_staff_self_revision'],$input['oldpassword'],$input['newpassword'],$input['newpasswordr']);
    }
    public static function emailAvailable($ci):bool
    {
        [$i,$a]=self::personal($ci);CompanyRequestScope::assertNativeMutation($ci);$id=$ci->input->post('memberid');$id=($id===null||$id==='')?null:IssuerPolicy::id($id);$email=$ci->input->post('email');if(!is_string($email))throw new DomainException('issuer_staff_invalid');
        $acl=new IssuerResourceAccess($ci->db,db_prefix());$check=function()use($ci,$acl,$i,$a,$id):?array{if(!$acl->can($i,$a,'staff','view')||!$acl->can($i,$a,'staff',$id===null?'create':'edit'))throw new DomainException('issuer_staff_denied');return$id===null?null:(new CompanyStaff($ci->db,db_prefix()))->get($i,$a,$id);};$before=$check();
        require_once __DIR__.'/StaffEmailIdentity.php';try{StaffEmailIdentity::assertAvailable($ci->db,db_prefix(),$email,$id);$available=true;}catch(DomainException $error){$available=false;}
        $after=$check();if($before!==$after)throw new DomainException('issuer_staff_stale');return$available;
    }
    public static function roleChanged($ci,$id):array
    {
        [$i,$a]=self::context($ci);$ref=NativeCompanyResourceId::decode('staff_role',$id);
        if($ref['source_kind']!=='company_staff_role')throw new DomainException('native_staff_role_denied');
        $service=new NativeCompanyStaffRoles($ci->db,db_prefix());$roles=$service->templatesForStaff($i,$a);
        $role=$roles['roles'][$ref['source_id']]??null;if(!$role)throw new DomainException('native_staff_role_denied');
        $permissions=(array)$role->permissions;$acl=new IssuerResourceAccess($ci->db,db_prefix());
        // The original selector may only propose capabilities the actor can actually grant.
        foreach($permissions as$feature=>$caps)foreach($caps as$cap)if(!$acl->can($i,$a,$feature,$cap))throw new DomainException('issuer_staff_grant_denied');
        if($roles['revision']!==$service->templatesForStaff($i,$a)['revision'])throw new DomainException('native_staff_role_stale');
        return$permissions;
    }
}
