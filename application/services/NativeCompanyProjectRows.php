<?php
declare(strict_types=1);
require_once __DIR__.'/NativeProjectRowPresentation.php';
require_once __DIR__.'/StaffAvatarPresentation.php';
require_once __DIR__.'/TagPresentation.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';
/** Authorized inputs for the single original project table row component. */
final class NativeCompanyProjectRows
{
    public function __construct(private$db,private string$p){}
    private function query(string$sql,array$args):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function render(int$issuer,int$actor,array$page,$client=''):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'projects','view'))throw new DomainException('native_project_access_denied');
        $permissions=[];foreach(['create','edit','delete']as$cap)$permissions[$cap]=$acl->can($issuer,$actor,'projects',$cap);
        $store=new CompanySettingsStorage($this->db,$this->p);$format=$store->exists($issuer,'dateformat')?$store->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
        $dateFormat=CompanySettingsFormats::dates()[$format]??throw new DomainException('native_project_date_format_invalid');
        $date=static function($value)use($dateFormat){if($value===null||$value==='')return'';$d=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$value,new DateTimeZone('UTC'));if(!$d||$d->format('Y-m-d')!==$value)throw new DomainException('native_project_date_invalid');return$d->format($dateFormat);};
        $statuses=[];foreach($page['statuses']as$status)$statuses[(string)$status['id']]=$status;$out=[];$profiles=[];
        foreach($page['rows']as$row){
            NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$row['id'],'view');
            $memberIds=array_map('intval',array_column($row['member_rows'],'staffid'));
            $avatar=function($id,$classes,$type,$attrs)use($memberIds,&$profiles){
                $staff=IssuerPolicy::id($id);if(!in_array($staff,$memberIds,true))throw new DomainException('native_project_member_denied');
                $sql='SELECT profile_image FROM '.$this->p.'staff WHERE staffid=?';$data=$this->query($sql,[$staff]);if(count($data)!==1)throw new DomainException('native_project_member_denied');$profiles[$staff]=$data;
                return StaffAvatarPresentation::render((object)$data[0],(string)$staff,$classes,$type,$attrs,'base_url',static function($path){$root=realpath(FCPATH.'uploads/staff_profile_images');$file=realpath(FCPATH.$path);return$root!==false&&$file!==false&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file);});
            };
            $custom=[];foreach($page['definitions']as$d){$key='custom_'.$d['id'];$value=$row['custom_values'][(string)$d['id']]??'';
                if($d['type']==='date_picker'&&$value!=='')$value=$date($value);
                // Values remain text, never executable field markup or global helper resolution.
                $row[$key]=htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$custom[]=$key;
            }
            $out[]=NativeProjectRowPresentation::render($row,$client,$permissions,['url'=>'admin_url','translate'=>'_l','escape'=>'e','tags'=>[TagPresentation::class,'render'],'date'=>$date,'avatar'=>$avatar,'status'=>static fn($id)=>$statuses[(string)$id]??throw new DomainException('native_work_status_unknown'),'brightness'=>'adjust_hex_brightness'],$custom);
        }
        foreach($profiles as$id=>$snapshot)if($this->query('SELECT profile_image FROM '.$this->p.'staff WHERE staffid=?',[$id])!==$snapshot)throw new DomainException('native_project_snapshot_changed');
        foreach($permissions as$cap=>$allowed)if($acl->can($issuer,$actor,'projects',$cap)!==$allowed)throw new DomainException('native_project_snapshot_changed');
        if(!$acl->can($issuer,$actor,'projects','view')||($store->exists($issuer,'dateformat')?$store->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat'))!==$format)throw new DomainException('native_project_snapshot_changed');
        return['draw'=>$page['draw'],'iTotalRecords'=>$page['recordsTotal'],'iTotalDisplayRecords'=>$page['recordsFiltered'],'aaData'=>$out];
    }
}
