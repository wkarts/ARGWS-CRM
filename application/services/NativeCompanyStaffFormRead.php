<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffRead.php';
require_once __DIR__.'/NativeCompanyStaffForm.php';
require_once __DIR__.'/NativeCompanyStaffPreferences.php';
/** Data for the original staff/member form. No global role or department hydration. */
final class NativeCompanyStaffFormRead
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function authorize(int $i,int $a,bool $create):IssuerResourceAccess
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if($acl->primaryId()===$i||!$acl->can($i,$a,'staff','view')||($create&&!$acl->can($i,$a,'staff','create')))throw new DomainException('issuer_staff_denied');
        return $acl;
    }
    public function page(int $i,int $a,?int $id=null):array
    {
        $acl=$this->authorize($i,$a,$id===null);
        $detail=$id===null?null:(new NativeCompanyStaffRead($this->db,$this->p))->member($i,$a,$id);
        $roleService=new NativeCompanyStaffRoles($this->db,$this->p);$roles=$roleService->templatesForStaffRead($i,$a);
        $sql='SELECT id,name FROM '.$this->p.'company_departments WHERE issuer_id=? ORDER BY name,id LIMIT 1001';
        $departments=IssuerStorage::query($this->db,$sql,[$i])->result_array();
        if(count($departments)>1000)throw new DomainException('issuer_staff_invalid');
        $form=new NativeCompanyStaffForm($this->db,$this->p,$i,$a,[],null,$roles['revision'],[]);
        $state=$id===null?null:$form->state($id);
        $catalog=[];foreach($departments as$row){$catalog[(int)$row['id']]=true;}
        $selected=[];foreach($state['departments']??[] as$row){if($row['department_source_kind']!=='company_ticket_department'||!isset($catalog[(int)$row['department_source_id']]))throw new DomainException('issuer_staff_denied');$selected[]=['departmentid'=>NativeCompanyResourceId::encode('company_ticket_department',(int)$row['department_source_id'])];}
        $default=(new NativeCompanyStaffPreferences($this->db,$this->p))->configured($roles['roles'],$i,$a);$assignment=$state['role']??null;if($assignment!==null&&!isset($roles['roles'][$assignment]))throw new DomainException('native_staff_role_stale');
        $roleRows=[];foreach($roles['roles']as$source=>$role)$roleRows[]=['roleid'=>NativeCompanyResourceId::encode('company_staff_role',(int)$source),'name'=>$role->name];
        $grantable=[];foreach(CompanyResourceCatalog::definitions()as$feature=>$definition)foreach($definition['capabilities']as$cap=>$label)if($acl->can($i,$a,$feature,$cap))$grantable[$feature][]=$cap;
        $fields=(new NativeCompanyCustomFields($this->db,$this->p))->definitions($i,$a,'staff');
        $this->authorize($i,$a,$id===null);
        if($departments!==IssuerStorage::query($this->db,$sql,[$i])->result_array()||$roles['revision']!==$roleService->templatesForStaffRead($i,$a)['revision'])throw new DomainException('issuer_staff_stale');
        if($id!==null){$again=(new NativeCompanyStaffRead($this->db,$this->p))->member($i,$a,$id);if($detail['revision']!==$again['revision']||$state!==$form->state($id))throw new DomainException('issuer_staff_stale');}
        $data=['native_staff_form'=>true,'native_staff_can_edit'=>$acl->can($i,$a,'staff',$id===null?'create':'edit'),'native_staff_can_revoke'=>$id!==null&&$id!==$a&&!($detail['access']['master']??false)&&$acl->can($i,$a,'staff','delete'),'native_staff_revoke_request_key'=>bin2hex(random_bytes(32)),'native_staff_default_current'=>$id===null||($detail['access']['default_current']??false),'native_staff_rate_revision'=>$detail['hourly_rate_source']['revision']??0,'native_staff_revision'=>$detail['access']['revision']??null,'native_staff_form_revision'=>$state===null?null:hash('sha256',json_encode($state,JSON_THROW_ON_ERROR)),'native_staff_roles_revision'=>$roles['revision'],'native_staff_selected_role'=>$assignment===null?($id===null?$default:''):NativeCompanyResourceId::encode('company_staff_role',$assignment),'native_staff_profile_editable'=>$detail['access']['profile_editable']??true,'native_staff_target_master'=>$detail['access']['master']??false,'native_staff_permission_catalog'=>CompanyResourceCatalog::definitions(),'native_staff_permissions'=>$detail['access']['grants']??[],'native_staff_assignable'=>$grantable,'native_staff_custom_fields'=>$fields,'roles'=>$roleRows,'departments'=>array_map(fn($r)=>['departmentid'=>NativeCompanyResourceId::encode('company_ticket_department',(int)$r['id']),'name'=>$r['name']],$departments),'staff_departments'=>$selected];
        if($detail!==null)$data['member']=$detail['member'];
        return $data;
    }
}
