<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketIndexRead.php';
require_once __DIR__.'/NativeCompanyFormatting.php';
require_once __DIR__.'/StaffAvatarPresentation.php';
require_once __DIR__.'/TagPresentation.php';
require_once __DIR__.'/CompanyCustomerAccess.php';
/** Explicit data for the SAME native row partial. No hook/model/global option hydration. */
final class NativeCompanyTicketRowPresentation
{
    public function __construct(private$db,private string$p){}
    public function project(int$i,int$a,array$entry,array$definitions):array
    {
        $reader=new NativeCompanyTicketRead($this->db,$this->p);$dto=$entry['source'];$id=$dto['ticketid'];
        if($reader->get($i,$a,$id)!==$dto)throw new DomainException('native_ticket_changed');
        $acl=new IssuerResourceAccess($this->db,$this->p);$s=new CompanySettingsStorage($this->db,$this->p);$f=[];foreach(['dateformat','time_format']as$k)$f[$k]=$s->exists($i,$k)?$s->get($i,$k):CompanySettingsCatalog::defaultValue('localization',$k);CompanySettingsFormats::validate($f);$format=new NativeCompanyFormatting(CompanySettingsFormats::dates()[$f['dateformat']],$f['time_format']);
        $row=$entry['row'];$row['1']='';$row[$this->p.'tickets.date']=$row['date'];$columns=['1','ticketid','subject','tags','department_name','service_name','contact_full_name','status','priority','lastreply',$this->p.'tickets.date'];$custom=[];
        foreach($definitions as$d){$key='cf_'.$d['id'];$columns[]=$key;$value=$entry['custom_values'][$d['id']]??'';if(!is_string($value))throw new DomainException('native_ticket_projection_invalid');$custom[$key]=in_array($d['type'],['date_picker','date_picker_time'],true)?$format->date($value):$value;$row[$key]=$value;}
        if(!preg_match('/^#[a-f0-9]{6}$/iD',(string)$row['statuscolor']))throw new DomainException('native_ticket_projection_invalid');
        $name='';$image='';if((int)$dto['assigned']>0){$staff=IssuerStorage::query($this->db,'SELECT staffid,firstname,lastname,profile_image FROM '.$this->p.'staff WHERE staffid=?',[$dto['assigned']])->row_array();if(!$staff)throw new DomainException('native_ticket_assignee_unavailable');$name=trim($staff['firstname'].' '.$staff['lastname']);$root=realpath(dirname(__DIR__,2));$image=StaffAvatarPresentation::render((object)$staff,(string)$dto['assigned'],['staff-profile-image-xs'],'small',[],static fn($path)=>base_url($path),static function($path)use($root){$full=realpath($root.'/'.$path);$directory=realpath($root.'/uploads/staff_profile_images');return$directory!==false&&$full!==false&&str_starts_with($full,$directory.DIRECTORY_SEPARATOR)&&is_file($full);});}
        $tags=(new NativeCompanyResourceTags($this->db,$this->p))->read($i,$a,'ticket',$id);if($tags!==$entry['tags'])throw new DomainException('native_ticket_changed');
        $canCustomer=$dto['userid']!==null&&(new CompanyCustomerAccess($this->db,$this->p))->can($i,$a,(int)$dto['userid'],'view');
        $native=['revision'=>$dto['revision_token'],'tags_revision'=>$tags['revision'],'date_label'=>$format->datetime($row['date']),'lastreply_label'=>$format->datetime($row['lastreply']), 'assignee_name'=>$name,'assignee_image'=>$image,'tags_html'=>TagPresentation::render($tags['tags']),'custom_labels'=>$custom,'can_view_customer'=>$canCustomer,'can_edit'=>$acl->can($i,$a,'tickets','edit'),'can_delete'=>$acl->can($i,$a,'tickets','edit')&&($acl->administrative($i,$a)||$dto['policy']['values']['allow_non_admin_members_to_delete_tickets_and_replies']==='1'),'public_url'=>null];
        // A URL cannot be advertised until the existing public capability has an admitted native reader.
        // This is an explicit acceptance gap, not a new policy value or a fake URL.
        $native['public_link_pending']=$dto['policy']['values']['disable_ticket_public_url']==='0';
        if($reader->get($i,$a,$id)!==$dto)throw new DomainException('native_ticket_changed');
        return['aColumns'=>$columns,'aRow'=>$row,'tagsColumns'=>3,'contactColumn'=>6,'native_ticket_row'=>$native];
    }
}
