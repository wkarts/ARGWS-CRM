<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffRoles.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/StaffAvatarPresentation.php';
final class NativeCompanyRolesEndpoints
{
    private static function context($ci):array{if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('native_staff_role_context_required');$c=CompanyRequestScope::context();return[$c->issuerId(),$c->staffId()];}
    private static function id($id):int{$decoded=NativeCompanyResourceId::decode('staff_role',$id);if($decoded['source_kind']!=='company_staff_role')throw new DomainException('native_staff_role_denied');return$decoded['source_id'];}
    private static function service($ci):NativeCompanyStaffRoles{return new NativeCompanyStaffRoles($ci->db,db_prefix());}
    public static function page($ci,$id=''):array
    {
        [$i,$a]=self::context($ci);$page=self::service($ci)->page($i,$a);$acl=new IssuerResourceAccess($ci->db,db_prefix());$data=['native_staff_roles'=>true,'native_staff_roles_revision'=>$page['revision'],'native_staff_csrf'=>CompanyRequestScope::nativeCsrfToken($ci),'native_staff_permission_catalog'=>CompanyResourceCatalog::definitions(),'native_staff_permissions'=>[],'native_staff_assignable'=>[],'native_staff_roles_can_create'=>$acl->can($i,$a,'roles','create'),'title'=>_l($id===''?'add_new':'edit',_l('role'))];
        foreach($data['native_staff_permission_catalog']as$f=>$d)foreach($d['capabilities']as$c=>$label)if($acl->can($i,$a,$f,$c))$data['native_staff_assignable'][$f][]=$c;
        if($id!==''){$source=self::id($id);$role=$page['roles'][$source]??null;if(!$role)throw new DomainException('native_staff_role_denied');$data['role']=(object)['roleid'=>NativeCompanyResourceId::encode('company_staff_role',$source),'name'=>$role->name,'permissions'=>(array)$role->permissions];$data['native_staff_permissions']=(array)$role->permissions;$data['role_staff']=[];foreach($page['assignments']as$staff=>$roleId){if($roleId!==$source)continue;if(!$acl->member($i,(int)$staff))throw new DomainException('native_staff_role_stale');$r=IssuerStorage::query($ci->db,'SELECT staffid,firstname,lastname,profile_image FROM '.db_prefix().'staff WHERE staffid=?',[(int)$staff])->row_array();if(!$r||!$acl->member($i,(int)$staff))throw new DomainException('native_staff_role_stale');$data['role_staff'][]=$r;}}
        if(self::service($ci)->page($i,$a)['revision']!==$page['revision'])throw new DomainException('native_staff_role_stale');return$data;
    }
    public static function save($ci,$id=''):string
    {
        CompanyRequestScope::assertNativeMutation($ci);[$i,$a]=self::context($ci);$v=$ci->input->post();if(!is_array($v)||array_diff(array_keys($v),['name','permissions','revision','update_staff_permissions'])||!is_string($v['name']??null)||!is_string($v['revision']??null)||!is_array($v['permissions']??[]))throw new DomainException('native_staff_role_invalid');$update=$v['update_staff_permissions']??null;if($update!==null&&!in_array($update,['on','1',1],true))throw new DomainException('native_staff_role_invalid');$source=self::service($ci)->save($i,$a,$id===''?null:self::id($id),$v['revision'],$v['name'],$v['permissions']??[],$update!==null);return NativeCompanyResourceId::encode('company_staff_role',$source);
    }
    public static function delete($ci,$id):void{CompanyRequestScope::assertNativeMutation($ci);[$i,$a]=self::context($ci);$revision=$ci->input->post('revision');if(!is_string($revision))throw new DomainException('native_staff_role_invalid');self::service($ci)->delete($i,$a,self::id($id),$revision);}
    public static function table($ci):array
    {
        [$i,$a]=self::context($ci);$page=self::service($ci)->page($i,$a);
        $request=$ci->input->post()?:$ci->input->get();
        if(!is_array($request)||!is_array($request['search']??[])||!is_array($request['order']??[]))throw new DomainException('native_staff_role_invalid');
        $draw=filter_var($request['draw']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        $start=filter_var($request['start']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        $length=filter_var($request['length']??25,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100]]);
        $q=$request['search']['value']??'';$order=$request['order']??[];
        if($draw===false||$start===false||$length===false||!is_string($q)||strlen($q)>100||count($order)>1)throw new DomainException('native_staff_role_invalid');
        $direction='asc';if($order){$sort=$order[0]??null;if(!is_array($sort)||!in_array($sort['column']??null,[0,'0'],true)||!in_array($sort['dir']??null,['asc','desc'],true))throw new DomainException('native_staff_role_invalid');$direction=$sort['dir'];}
        $roles=$page['roles'];uksort($roles,function($x,$y)use($roles,$direction){$c=strnatcasecmp($roles[$x]->name,$roles[$y]->name);if($c===0)$c=$x<=>$y;return$direction==='asc'?$c:-$c;});
        $rows=[];$acl=new IssuerResourceAccess($ci->db,db_prefix());$escape=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        foreach($roles as$id=>$r){
            if($q!==''&&mb_stripos($r->name,$q)===false)continue;
            $wire=NativeCompanyResourceId::encode('company_staff_role',(int)$id);$options='';
            if($acl->can($i,$a,'roles','edit'))$options='<a class="btn btn-default btn-icon" href="'.$escape(admin_url('roles/role/'.$wire)).'"><i class="fa-regular fa-pen-to-square"></i></a>';
            if($acl->can($i,$a,'roles','delete'))$options.='<form method="post" action="'.$escape(admin_url('roles/delete/'.$wire)).'" class="tw-inline" onsubmit="return confirm_delete();"><input type="hidden" name="revision" value="'.$escape($page['revision']).'"><input type="hidden" name="_native_company_csrf" value="'.$escape(CompanyRequestScope::nativeCsrfToken($ci)).'"><button type="submit" class="btn btn-danger btn-icon"><i class="fa-regular fa-trash-can"></i></button></form>';
            $rows[]=[$escape($r->name),$options];
        }
        if(self::service($ci)->page($i,$a)['revision']!==$page['revision'])throw new DomainException('native_staff_role_stale');
        return['draw'=>$draw,'iTotalRecords'=>count($page['roles']),'iTotalDisplayRecords'=>count($rows),'aaData'=>array_slice($rows,$start,$length)];
    }
}
