<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketTableFactory.php';
require_once __DIR__.'/NativeCompanyFilters.php';
require_once __DIR__.'/NativeCompanyTableOrder.php';
require_once __DIR__.'/NativeCompanyTicketMetrics.php';
final class NativeCompanyTicketIndexPresentation
{
    public function __construct(private$db,private string$p){}
    public function read(int$i,int$a,string$status=''):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tickets','view')||$acl->primaryId()===$i)throw new DomainException('native_ticket_access_denied');$catalog=new NativeCompanyTicketCatalog($this->db,$this->p);$statuses=$catalog->list($i,$a,'status');if($status!==''&&!in_array($status,array_column($statuses,'ticketstatusid'),true))throw new DomainException('native_ticket_status_invalid');
        $policy=(new NativeCompanyTicketPreferences($this->db,$this->p))->read($i,$a);$factory=new NativeCompanyTicketTableFactory($this->db,$this->p);$table=$factory->make($i,$a);$filters=(new NativeCompanyFilters($this->db,$this->p,$i,$a,$table))->listing();$table=$factory->make($i,$a,'tickets',$filters);$order=new NativeCompanyTableOrder($this->db,$this->p,$i,$a);$custom=(new NativeCompanyCustomFields($this->db,$this->p))->definitions($i,$a,'tickets',['active'=>1,'show_on_table'=>1]);
        $metrics=new NativeCompanyTicketMetrics($this->db,$this->p);$counts=$metrics->weeklyOpeningCounts($i,$a,date('Y-m-d',strtotime('monday this week')));$chart=['labels'=>array_map(static fn($day)=>_l('wd_'.$day),['monday','tuesday','wednesday','thursday','friday','saturday','sunday']),'datasets'=>[['label'=>_l('home_weekend_ticket_opening_statistics'),'backgroundColor'=>'rgba(197, 61, 169, 0.5)','borderColor'=>'#c53da9','borderWidth'=>1,'tension'=>false,'data'=>$counts]]];$departments=new NativeCompanyDepartments($this->db,$this->p);$admin=$acl->administrative($i,$a);
        $out=['table'=>$table,'chosen_ticket_status'=>$status,'weekly_tickets_opening_statistics'=>json_encode($chart,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT),'title'=>_l('support_tickets'),'statuses'=>$statuses,'departments'=>$admin||$policy['values']['staff_access_only_assigned_departments']!=='1'?$departments->catalog($i,$a):$departments->forStaff($i,$a),'priorities'=>$catalog->list($i,$a,'priority'),'services'=>$catalog->list($i,$a,'service'),'bodyclass'=>'tickets-page','default_tickets_list_statuses'=>array_values(array_column(array_filter($statuses,static fn($r)=>in_array($r['code'],['open','in_progress','on_hold'],true)),'ticketstatusid')),'native_ticket_summary'=>$metrics->nativeSummary($i,$a),'native_ticket_index'=>['can_create'=>$acl->can($i,$a,'tickets','create'),'can_edit'=>$acl->can($i,$a,'tickets','edit'),'can_delete'=>$acl->can($i,$a,'tickets','edit')&&($admin||$policy['values']['allow_non_admin_members_to_delete_tickets_and_replies']==='1'),'services'=>$policy['values']['services'],'structure'=>['services'=>$policy['values']['services'],'order'=>$order->read('tickets'),'hidden_columns'=>$order->readHidden('tickets'),'custom_fields'=>$custom]]];
        if(!$acl->can($i,$a,'tickets','view')||$catalog->list($i,$a,'status')!==$statuses||(new NativeCompanyTicketPreferences($this->db,$this->p))->read($i,$a)!==$policy)throw new DomainException('native_ticket_changed');return$out;
    }
}
