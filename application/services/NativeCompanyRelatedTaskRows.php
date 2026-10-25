<?php
declare(strict_types=1);
require_once __DIR__.'/NativeTaskRowPresentation.php';require_once __DIR__.'/TaskMembersPresentation.php';require_once __DIR__.'/StaffAvatarPresentation.php';require_once __DIR__.'/TagPresentation.php';require_once __DIR__.'/NativeCompanyFormatting.php';require_once __DIR__.'/NativeCompanyBlankTimers.php';
/** The same row component with explicit issuer-authorized inputs and revisions. */
final class NativeCompanyRelatedTaskRows
{
    public function __construct(private$db,private string$p){}
    private function rows(string$q,array$a=[]):array{return IssuerStorage::query($this->db,$q,$a)->result_array();}
    public function render(int$i,int$a,array$page):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tasks','view'))throw new DomainException('native_company_task_denied');$edit=$acl->can($i,$a,'tasks','edit');$delete=$acl->can($i,$a,'tasks','delete');
        $store=new CompanySettingsStorage($this->db,$this->p);$format=$store->exists($i,'dateformat')?$store->get($i,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');$datePattern=CompanySettingsFormats::dates()[$format]??throw new DomainException('native_task_date_format_invalid');
        $date=static function($value)use($datePattern){if($value===null||$value==='')return'';NativeCompanyTaskFormValues::date($value);return DateTimeImmutable::createFromFormat('!Y-m-d',$value)->format($datePattern);};
        $statuses=WorkStatusPresentation::tasks('_l');$statusMap=array_column($statuses,null,'id');$priorities=TaskPriorityPresentation::definitions('_l');$priorityMap=array_column($priorities,null,'id');$out=[];$profiles=[];
        $policy=(new NativeCompanyTimerPolicy($this->db,$this->p))->read($i,$a);$hash=NativeCompanyBlankTimers::policyHash($policy);
        foreach($page['rows']as$item){
            $ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'task',$item['id'],'view');if($ref!==$item['reference'])throw new DomainException('native_task_relation_stale');
            $members=[];foreach($item['assignees']as$m)$members[]=['id'=>(string)$m['staffid'],'name'=>trim($m['firstname'].' '.$m['lastname'])];$memberIds=array_column($members,'id');
            $avatar=function($id,$classes,$type,$attrs)use($memberIds,&$profiles){if(!in_array((string)$id,$memberIds,true))throw new DomainException('native_assignment_denied');$rows=$this->rows('SELECT profile_image FROM '.$this->p.'staff WHERE staffid=?',[$id]);if(count($rows)!==1)throw new DomainException('native_assignment_denied');$profiles[(string)$id]=$rows;return StaffAvatarPresentation::render((object)$rows[0],(string)$id,$classes,$type,$attrs,'base_url',static function($path){$root=realpath(FCPATH.'uploads/staff_profile_images');$file=realpath(FCPATH.$path);return$root!==false&&$file!==false&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file);});};
            $r=$item['row'];$f=$item['facts'];$timer=$item['running_timer'];$timerId=$timer?NativeCompanyResourceId::encode('company_standalone_timer',$timer['id']):'';
            $row=['id'=>$item['id'],'task_name'=>$r['name'],'status'=>$f['status'],'startdate'=>$f['startdate'],'duedate'=>$f['duedate'],'priority'=>$f['priority'],'recurring'=>$f['recurring']?1:0,'billed'=>$f['billed']===true?1:0,'is_assigned'=>in_array($a,$f['assigned'],true),'current_user_is_assigned'=>in_array($a,$f['assigned'],true),'current_user_is_creator'=>$item['current_user_is_creator']?1:0,'not_finished_timer_by_current_staff'=>$timerId,'assignees_ids'=>$members,'assignees'=>'','tags'=>$item['tags']];
            $row['native_timer_blocked']=$item['billing_pending']||!$edit;$row['native_timer_blocked_reason']=$item['billing_pending']?'Esta tarefa possui faturamento parcial ou em confirmação. Revise a operação antes de iniciar outro cronômetro.':_l('access_denied');
            $custom=[];foreach($page['definitions']as$d){$key='cf_'.$d['id'];$value=$item['custom_values'][(string)$d['id']]??'';if($d['type']==='date_picker'&&$value!=='')$value=$date($value);$row[$key]=htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$custom[]=$key;}
            $callbacks=['url'=>'admin_url','translate'=>'_l','escape'=>'e','date'=>$date,'statusById'=>static fn($id)=>$statusMap[$id]??throw new DomainException('native_work_status_unknown'),'statusLabel'=>static fn($id,...$_)=>$statusMap[$id]['name']??throw new DomainException('native_work_status_unknown'),'brightness'=>'adjust_hex_brightness','members'=>static fn($ids,$names)=>TaskMembersPresentation::render($ids,'sm','admin_url',$avatar,'e'),'tags'=>[TagPresentation::class,'render'],'priorityColor'=>static fn($id)=>$priorityMap[$id]['color']??throw new DomainException('native_task_priority_invalid'),'priorityLabel'=>static fn($id)=>$priorityMap[$id]['name']??throw new DomainException('native_task_priority_invalid')];
            $rendered=NativeTaskRowPresentation::render($row,$edit,$delete,$statuses,$priorities,$callbacks,$custom,$page['today']);
            $rendered['DT_RowAttr']=['data-native-task-id'=>$item['id'],'data-task-revision'=>(string)$r['revision'],'data-native-fields-revision'=>(string)$r['native_fields_revision'],'data-native-timer-id'=>$timerId,'data-timer-revision'=>(string)$r['revision'],'data-note-revision'=>$timer?(string)(new NativeCompanyTaskTimerDetails($this->db,$this->p))->forOwnTimesheet($i,$a,'company_standalone_timer',(int)$timer['id'])['revision']:'0','data-policy-hash'=>$hash,'data-billing-state'=>$r['billing_state']];
            $out[]=$rendered;
        }
        foreach($profiles as$id=>$snapshot)if($this->rows('SELECT profile_image FROM '.$this->p.'staff WHERE staffid=?',[$id])!==$snapshot)throw new DomainException('native_task_relation_stale');
        if(!$acl->can($i,$a,'tasks','view')||$edit!==$acl->can($i,$a,'tasks','edit')||$delete!==$acl->can($i,$a,'tasks','delete')||(new NativeCompanyTimerPolicy($this->db,$this->p))->read($i,$a)!==$policy||($store->exists($i,'dateformat')?$store->get($i,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat'))!==$format)throw new DomainException('native_task_relation_stale');
        return['draw'=>$page['draw'],'iTotalRecords'=>$page['recordsTotal'],'iTotalDisplayRecords'=>$page['recordsFiltered'],'aaData'=>$out];
    }
}
