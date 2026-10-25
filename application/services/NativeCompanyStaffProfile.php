<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffTimesheets.php';
require_once __DIR__.'/NativeCompanyNotifications.php';
require_once __DIR__.'/StaffAvatarPresentation.php';
require_once __DIR__.'/NativeCompanyFormatting.php';
/** Original public staff profile: same-company directory fields, not staff administration or payroll. */
final class NativeCompanyStaffProfile
{
    private const FIELDS='staffid,firstname,lastname,email,phonenumber,facebook,linkedin,skype,profile_image,active,last_activity';
    public function __construct(private $db,private string $p){}
    private function authority(int $i,int $a,int $subject):IssuerResourceAccess
    {
        foreach([$i,$a,$subject]as$id)IssuerPolicy::id($id);$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->member($i,$a)||$acl->primaryId()===$i)throw new DomainException('issuer_staff_denied');
        if(!$acl->isInstallationMaster($subject)&&!IssuerStorage::query($this->db,'SELECT staff_id FROM '.$this->p.'issuers_staff WHERE issuer_id=? AND staff_id=? AND can_view=1',[$i,$subject])->row_array())throw new DomainException('issuer_staff_denied');return$acl;
    }
    public function page(int $i,int $a,int $subject):array
    {
        $acl=$this->authority($i,$a,$subject);$sql='SELECT '.self::FIELDS.' FROM '.$this->p.'staff WHERE staffid=?';$row=IssuerStorage::query($this->db,$sql,[$subject])->row_array();if(!$row)throw new DomainException('issuer_staff_denied');
        $linkSql='SELECT department_source_kind,department_source_id FROM '.$this->p.'company_department_members WHERE issuer_id=? AND staff_id=? ORDER BY department_source_id';$links=IssuerStorage::query($this->db,$linkSql,[$i,$subject])->result_array();$departments=[];$proof=[];
        foreach($links as$link){if($link['department_source_kind']!=='company_ticket_department')throw new DomainException('issuer_staff_denied');$dep=IssuerStorage::query($this->db,'SELECT id,name FROM '.$this->p.'company_departments WHERE issuer_id=? AND id=?',[$i,$link['department_source_id']])->row_array();if(!$dep)throw new DomainException('issuer_staff_denied');$proof[]=$dep;$departments[]=['departmentid'=>NativeCompanyResourceId::encode('company_ticket_department',$dep['id']),'name'=>$dep['name']];}
        $social=[];foreach(['facebook','linkedin']as$key){$value=(string)$row[$key];$url=parse_url($value);$social[$key]=$url&&in_array(strtolower($url['scheme']??''),['http','https'],true)&&!empty($url['host'])&&!isset($url['user'])&&!isset($url['pass'])&&!preg_match('/[\x00-\x20]/',$value)?$value:null;}
        $stats=$subject===$a||$acl->isMaster($a);$data=['native_staff_profile'=>true,'native_staff_social_urls'=>$social,'native_profile_show_stats'=>$stats,'native_profile_can_edit'=>$acl->can($i,$a,'staff','view')&&$acl->can($i,$a,'staff','edit'),'staff_p'=>(object)$row,'departments'=>$departments,'staff_departments'=>$departments,'total_pages'=>null];
        if($stats)$data['logged_time']=(new NativeCompanyStaffTimesheets($this->db,$this->p,date_default_timezone_get()))->read($i,$a,$subject,['this_month'=>true],$subject===$a);
        if($subject===$a)$data['total_pages']=(int)ceil((new NativeCompanyNotifications($this->db,$this->p))->page($i,$a,0,15)['total']/15);
        $this->authority($i,$a,$subject);if($row!==IssuerStorage::query($this->db,$sql,[$subject])->row_array()||$links!==IssuerStorage::query($this->db,$linkSql,[$i,$subject])->result_array()||$stats!==($subject===$a||$acl->isMaster($a)))throw new DomainException('issuer_staff_stale');foreach($proof as$dep)if($dep!==IssuerStorage::query($this->db,'SELECT id,name FROM '.$this->p.'company_departments WHERE issuer_id=? AND id=?',[$i,$dep['id']])->row_array())throw new DomainException('issuer_staff_stale');return$data;
    }
    private static function notificationActor($ci,int $issuer,array $event):array
    {
        if($event['actor_kind']!=='staff')return ['profile_image'=>'','from_fullname'=>''];
        $id=IssuerPolicy::id($event['actor_id']);$p=db_prefix();$q='SELECT staffid FROM '.$p.'staff WHERE staffid=? AND (EXISTS(SELECT 1 FROM '.$p.'issuers_staff m WHERE m.staff_id=? AND m.issuer_id=? AND m.can_view=1) OR EXISTS(SELECT 1 FROM '.$p.'issuers_installation_identity x WHERE x.id=1 AND x.master_staff_id=?))';$args=[$id,$id,$issuer,$id];$authority=IssuerStorage::query($ci->db,$q,$args)->row_array();if(!$authority)return ['profile_image'=>'','from_fullname'=>''];
        $sql='SELECT firstname,lastname,profile_image FROM '.$p.'staff WHERE staffid=?';$row=IssuerStorage::query($ci->db,$sql,[$id])->row_array();if(!$row)throw new DomainException('issuer_notification_changed');
        $image=StaffAvatarPresentation::render((object)$row,$id,['staff-profile-image-small','img-circle','pull-left'],'small',[],'base_url','file_exists');$image='<a href="'.htmlspecialchars(CompanyRequestScope::adminUrl($ci,'staff/profile/'.$id),ENT_QUOTES,'UTF-8').'">'.$image.'</a>';
        if($authority!==IssuerStorage::query($ci->db,$q,$args)->row_array()||$row!==IssuerStorage::query($ci->db,$sql,[$id])->row_array())throw new DomainException('issuer_notification_changed');return ['profile_image'=>$image,'from_fullname'=>$row['firstname'].' '.$row['lastname']];
    }
    public static function notifications($ci):array
    {
        if(!CompanyRequestScope::nativeFor($ci))throw new DomainException('issuer_staff_denied');CompanyRequestScope::assertNativeMutation($ci);$c=CompanyRequestScope::context();$i=$c->issuerId();$a=$c->staffId();if((int)get_staff_user_id()!==$a)throw new DomainException('issuer_staff_denied');$page=$ci->input->post('page');if((!is_string($page)&&!is_int($page))||!preg_match('/^(0|[1-9][0-9]{0,8})$/D',(string)$page))throw new DomainException('issuer_notification_page_invalid');
        $service=new NativeCompanyNotifications($ci->db,db_prefix());$result=$service->page($i,$a,(int)$page,15);$format=NativeCompanyFormatting::current();if(!$format)throw new DomainException('native_format_denied');$out=[];
        foreach($result['items']as$row){$url=$row['url'];if(!is_string($url)||str_starts_with($url,'/')||str_contains($url,'://')||preg_match('/[\x00-\x20"\'<>]/',$url))throw new DomainException('issuer_notification_source_invalid');
            $actor=self::notificationActor($ci,$i,$row);$out[]=['id'=>NativeCompanyResourceId::encode('company_notification',$row['id']),'isread_inline'=>$row['inline_read_at']===null?0:1,'link'=>$url,'profile_image'=>$actor['profile_image'],'from_fullname'=>$actor['from_fullname'],'description'=>htmlspecialchars($row['title'].($row['body']===''?'':': '.$row['body']),ENT_QUOTES,'UTF-8'),'date'=>htmlspecialchars(time_ago($row['created_at']),ENT_QUOTES,'UTF-8'),'full_date'=>htmlspecialchars($format->datetime($row['created_at']),ENT_QUOTES,'UTF-8')];
        }
        if($service->page($i,$a,(int)$page,15)!==$result)throw new DomainException('issuer_notification_changed');return$out;
    }
}
