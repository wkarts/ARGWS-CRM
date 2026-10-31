<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffRoles.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyDataTableWire.php';
require_once __DIR__.'/NativeCompanyFormatting.php';
require_once __DIR__.'/StaffAvatarPresentation.php';
/** Data for the original Staff table. Inactive identities retain their authorized membership lineage. */
final class NativeCompanyStaffTable
{
    public function __construct(private $db,private string $p){}
    public function read(int $i,int $a):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$guard=function()use($acl,$i,$a){if($acl->primaryId()===$i||!$acl->can($i,$a,'staff','view'))throw new DomainException('issuer_staff_denied');};$guard();
        $q='SELECT s.staffid FROM '.$this->p.'staff s WHERE EXISTS(SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.staff_id=s.staffid AND m.issuer_id=? AND m.can_view=1) OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_installation_identity x WHERE x.id=1 AND x.master_staff_id=s.staffid) ORDER BY s.staffid';$ids=IssuerStorage::query($this->db,$q,[$i])->result_array();
        $rolesService=new NativeCompanyStaffRoles($this->db,$this->p);$roles=$rolesService->templatesForStaffRead($i,$a);$fieldsService=new NativeCompanyCustomFields($this->db,$this->p);$fields=$fieldsService->definitions($i,$a,'staff',['show_on_table'=>1]);$staff=new CompanyStaff($this->db,$this->p);$rows=[];$proof=[];
        foreach($ids as$id){$id=IssuerPolicy::id($id['staffid']);$access=$staff->get($i,$a,$id);$sql='SELECT staffid,firstname,lastname,email,active,profile_image,last_login FROM '.$this->p.'staff WHERE staffid=?';$row=IssuerStorage::query($this->db,$sql,[$id])->row_array();if(!$row)throw new DomainException('issuer_staff_denied');
            $role=$roles['assignments'][$id]??null;if($role!==null&&!isset($roles['roles'][$role]))throw new DomainException('native_staff_role_stale');$row['role_name']=$role===null?'':$roles['roles'][$role]->name;
            $row['native_revision']=$access['revision'];$row['native_can_status']=$acl->can($i,$a,'staff','edit')&&$id!==$a&&!$access['master']&&$access['profile_editable'];$row['native_status_reason']=$row['native_can_status']?'':($id===$a||$access['master']?'issuer_master_protected':(!$access['profile_editable']?'issuer_staff_shared_identity':'access_denied'));
            $values=IssuerStorage::query($this->db,'SELECT field_source_kind,field_id,value,revision FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND source_kind=? AND source_id=? ORDER BY field_source_kind,field_id',[$i,'staff',$id])->result_array();$mapped=[];foreach($values as$v){$wire=NativeCompanyResourceId::encode($v['field_source_kind'],IssuerPolicy::id($v['field_id']));$mapped[(string)$wire]=$v['value'];}
            foreach($fields as$n=>$field)$row['cvalue_'.$n]=$mapped[(string)$field['id']]??'';
            $rows[]=$row;$proof[]=[$id,$access,$sql,array_intersect_key($row,array_flip(['staffid','firstname','lastname','email','active','profile_image','last_login'])),$values];
        }
        $guard();if($ids!==IssuerStorage::query($this->db,$q,[$i])->result_array()||$roles['revision']!==$rolesService->templatesForStaffRead($i,$a)['revision']||$fields!==$fieldsService->definitions($i,$a,'staff',['show_on_table'=>1]))throw new DomainException('issuer_staff_stale');
        foreach($proof as[$id,$access,$sql,$raw,$values])if($staff->get($i,$a,$id)!==$access||IssuerStorage::query($this->db,$sql,[$id])->row_array()!==$raw||IssuerStorage::query($this->db,'SELECT field_source_kind,field_id,value,revision FROM '.$this->p.'company_custom_field_values WHERE issuer_id=? AND source_kind=? AND source_id=? ORDER BY field_source_kind,field_id',[$i,'staff',$id])->result_array()!==$values)throw new DomainException('issuer_staff_stale');$guard();return ['rows'=>$rows,'fields'=>$fields,'can_create'=>$acl->can($i,$a,'staff','create')];
    }
    public static function endpoint($ci):array
    {
        if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('issuer_staff_denied');CompanyRequestScope::assertNativeMutation($ci);$ctx=CompanyRequestScope::context();if((int)get_staff_user_id()!==$ctx->staffId())throw new DomainException('issuer_staff_denied');$reader=new self($ci->db,db_prefix());$data=$reader->read($ctx->issuerId(),$ctx->staffId());
        $fields=['firstname','email','role_name','last_login','active'];foreach($data['fields']as$n=>$field)$fields[]='cvalue_'.$n;$input=$ci->input->post(null,false);if(!is_array($input))throw new DomainException('native_company_table_request_invalid');if(isset($ci->security))unset($input[$ci->security->get_csrf_token_name()]);$wire=NativeCompanyDataTableWire::request($input,$fields);$rows=$data['rows'];$total=count($rows);
        if($wire['search']!=='')$rows=array_values(array_filter($rows,fn($r)=>mb_stripos(implode(' ',array_map(fn($f)=>(string)($r[$f]??''),$fields)).' '.$r['lastname'],$wire['search'])!==false));$filtered=count($rows);
        usort($rows,function($x,$y)use($wire){foreach($wire['orders']as$order){$c=strnatcasecmp((string)($x[$order['field']]??''),(string)($y[$order['field']]??''));if($c!==0)return$order['direction']==='desc'?-$c:$c;}return(int)$x['staffid']<=>(int)$y['staffid'];});if($wire['length']!==-1)$rows=array_slice($rows,$wire['start'],$wire['length']);
        $native_staff_table=true;$native_staff_rows=$rows;$native_staff_fields=$data['fields'];$native_staff_output=['draw'=>$wire['draw'],'iTotalRecords'=>$total,'iTotalDisplayRecords'=>$filtered,'aaData'=>[]];$native_staff_ci=$ci;$store=new CompanySettingsStorage($ci->db,db_prefix());$format=[];foreach(['dateformat','time_format']as$key)$format[$key]=$store->exists($ctx->issuerId(),$key)?$store->get($ctx->issuerId(),$key):CompanySettingsCatalog::defaultValue('localization',$key);$native_staff_formatter=new NativeCompanyFormatting(CompanySettingsFormats::dates()[$format['dateformat']],$format['time_format']);
        require APPPATH.'views/admin/tables/staff.php';
        foreach($format as$key=>$value)if(($store->exists($ctx->issuerId(),$key)?$store->get($ctx->issuerId(),$key):CompanySettingsCatalog::defaultValue('localization',$key))!==$value)throw new DomainException('issuer_staff_stale');if($reader->read($ctx->issuerId(),$ctx->staffId())!==$data)throw new DomainException('issuer_staff_stale');return$output;
    }
}
