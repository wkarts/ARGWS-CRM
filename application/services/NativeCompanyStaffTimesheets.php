<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskTimerDetails.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
/** Restricted native staff-report projection. Allocation is not payment/settlement authority. */
final class NativeCompanyStaffTimesheets
{
    private array $reads=[];
    private DateTimeZone $zone;
    public function __construct(private $db,private string $p,string $storageTimezone){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$this->zone=new DateTimeZone($storageTimezone);}
    private function rows(string $sql,array $args=[]):array{$rows=IssuerStorage::query($this->db,$sql,$args)->result_array();$this->reads[]=[$sql,$args,$rows];return$rows;}
    private function one(string $sql,array $args=[]):?array{$r=$this->rows($sql,$args);if(count($r)>1)throw new RuntimeException('native_staff_timesheet_invalid');return$r[0]??null;}
    private function authority(int $i,int $a,int $subject,bool $own,bool $report=false):IssuerResourceAccess
    {
        foreach([$i,$a,$subject]as$id)IssuerPolicy::id($id);$acl=new IssuerResourceAccess($this->db,$this->p);
        if($report){require_once __DIR__.'/NativeCompanyTimesheetReportAuthority.php';(new NativeCompanyTimesheetReportAuthority($this->db,$this->p))->assertTarget($i,$a,$subject);}
        elseif(!$acl->member($i,$a)||($own&&!$acl->member($i,$subject))||($own?$a!==$subject:!$acl->can($i,$a,'staff','view')))throw new DomainException('native_staff_timesheet_denied');
        if(!$own&&!$report){require_once __DIR__.'/CompanyStaff.php';(new CompanyStaff($this->db,$this->p))->assertAdministrativeTarget($i,$a,$subject);}
        // The principal route still uses its historical reader; never silently omit its legacy timers.
        if($acl->primaryId()===$i)throw new DomainException('native_staff_timesheet_primary_required');return$acl;
    }
    private function timestamp(string $value,bool $utc=false):int
    {$date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,$utc?new DateTimeZone('UTC'):$this->zone);if(!$date||$date->format('Y-m-d H:i:s')!==$value)throw new RuntimeException('native_staff_timesheet_invalid');return$date->getTimestamp();}
    private function parent(int $i,string $kind,int $task):array
    {
        $table=$kind==='company_task_timer'?'company_tasks':'company_standalone_tasks';
        // Establish identity and parent ownership before SELECT of private labels.
        $row=$this->one('SELECT id,issuer_id'.($kind==='company_task_timer'?',project_id':'').' FROM '.$this->p.$table.' WHERE id=?',[$task]);if(!$row||(int)$row['issuer_id']!==$i)throw new DomainException('native_staff_timesheet_denied');
        $project=null;if($kind==='company_task_timer'){$project=$this->one('SELECT id,issuer_id,client_id FROM '.$this->p.'company_projects WHERE id=?',[$row['project_id']]);if(!$project||(int)$project['issuer_id']!==$i)throw new DomainException('native_staff_timesheet_denied');$owner=$this->one('SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$project['client_id']]);if(!$owner||(int)$owner['owner_issuer_id']!==$i)throw new DomainException('native_staff_timesheet_denied');}
        $label=$this->one('SELECT name FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$i,$task]);if(!$label)throw new DomainException('native_staff_timesheet_denied');$projectName=$project?$this->one('SELECT name FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=?',[$i,$project['id']]):null;
        return['task_id'=>NativeCompanyResourceId::encode($kind==='company_task_timer'?'company_task':'company_standalone_task',$task),'name'=>$label['name'],'rel_type'=>$project?'project':'','rel_id'=>$project?NativeCompanyResourceId::encode('company_project',$project['id']):0,'relation_name'=>$projectName['name']??'','project_id'=>$project?(int)$project['id']:null,'client_id'=>$project?(int)$project['client_id']:null];
    }
    private function proof(int $i,string $key,?array $link):string
    {
        $receipt=$this->one('SELECT issuer_id,invoice_id FROM '.$this->p.'company_invoice_requests WHERE request_key=?',[$key]);
        if($receipt&&(int)$receipt['issuer_id']!==$i)throw new RuntimeException('native_staff_billing_unconfirmed');
        if($link&&!in_array($link['state'],['ready','disabled','converting','converted'],true))throw new RuntimeException('native_staff_billing_unconfirmed');
        if(($link['state']??null)==='converted'){
            if(!$receipt||(int)$receipt['invoice_id']<1||(int)$receipt['invoice_id']!==(int)$link['invoice_id'])throw new RuntimeException('native_staff_billing_unconfirmed');
            $invoice=$this->one('SELECT issuer_id FROM '.$this->p.'invoices WHERE id=?',[$receipt['invoice_id']]);if(!$invoice||(int)$invoice['issuer_id']!==$i)throw new RuntimeException('native_staff_billing_unconfirmed');return'converted';
        }
        if($receipt||($link['state']??null)==='converting')return'converting';return'unallocated';
    }
    private function whole(int $i,string $family,string $field,int $id,string $key):string
    {
        $link=$this->one('SELECT issuer_id,origin_id,state,invoice_id FROM '.$this->p.$family.'_links WHERE '.$field.'=?',[$id]);if($link){if((int)$link['issuer_id']!==$i)throw new RuntimeException('native_staff_billing_unconfirmed');$origin=$this->one('SELECT issuer_id,'.$field.' FROM '.$this->p.$family.'_origins WHERE id=?',[$link['origin_id']]);if(!$origin||(int)$origin['issuer_id']!==$i||(int)$origin[$field]!==$id)throw new RuntimeException('native_staff_billing_unconfirmed');}
        return$this->proof($i,$key,$link);
    }
    private function claim(int $i,string $table,string $batches,string $origins,string $prefix,int $timer,int $task,?int $project,string $kind):string
    {
        $claim=$this->one('SELECT issuer_id,task_id,origin_id,state'.($project===null?'':',project_id').' FROM '.$this->p.$table.' WHERE timer_id=?',[$timer]);if(!$claim)return'unallocated';
        if((int)$claim['issuer_id']!==$i||(int)$claim['task_id']!==$task||($project!==null&&(int)$claim['project_id']!==$project)||!in_array($claim['state'],['converting','converted'],true))throw new RuntimeException('native_staff_billing_unconfirmed');
        $origin=$this->one('SELECT issuer_id'.($prefix==='company-time-batch-invoice:'?',project_id':',source_kind').' FROM '.$this->p.$origins.' WHERE id=?',[$claim['origin_id']]);
        if(!$origin||(int)$origin['issuer_id']!==$i||($prefix==='company-time-batch-invoice:'?(int)$origin['project_id']!==$project:$origin['source_kind']!==$kind))throw new RuntimeException('native_staff_billing_unconfirmed');
        $batch=$this->one('SELECT issuer_id,state,invoice_id FROM '.$this->p.$batches.' WHERE origin_id=?',[$claim['origin_id']]);if(!$batch||(int)$batch['issuer_id']!==$i||$batch['state']!==$claim['state'])throw new RuntimeException('native_staff_billing_unconfirmed');return$this->proof($i,hash('sha256',$prefix.$i.':'.$claim['origin_id']),$batch);
    }
    private function billing(int $i,string $kind,int $timer,int $task,?int $project):string
    {
        if($kind==='company_blank_task_timer')return'unallocated';
        $states=[];if($project!==null){$states[]=$this->whole($i,'company_task_billing','task_id',$task,hash('sha256','company-task-invoice:'.$i.':'.$task));$states[]=$this->whole($i,'company_project_fixed_billing','project_id',$project,hash('sha256','company-project-fixed-invoice:'.$i.':'.$project));$states[]=$this->claim($i,'company_time_billing_claims','company_time_billing_batches','company_time_billing_origins','company-time-batch-invoice:',$timer,$task,$project,'projects');}
        else$states[]=$this->whole($i,'company_standalone_billing','task_id',$task,hash('sha256','company-standalone-task-invoice:'.$i.':'.$task));
        $states[]=$this->claim($i,$project===null?'company_standalone_time_claims':'company_task_time_batch_claims','company_task_time_batches','company_task_time_batch_origins','company-task-time-multi-batch-invoice:',$timer,$task,$project,$project===null?'standalone':'projects');
        $active=array_filter($states,static fn($s)=>$s!=='unallocated');if(count($active)>1)throw new RuntimeException('native_staff_billing_overlap');return$active?array_values($active)[0]:'unallocated';
    }
    /** $filter uses native range keys; dates here are already normalized YYYY-MM-DD. */
    public function read(int $i,int $a,int $subject,array $filter=['this_month'=>true],bool $own=false,?int $now=null):array
    {
        return $this->readInternal($i,$a,$subject,$filter,$own,$now,false);
    }
    /** Same duration/source checks, without salary fields or implicit staff/tasks grants. */
    public function report(int $i,int $a,int $subject,array $filter=['this_month'=>true],?int $now=null):array
    {return $this->readInternal($i,$a,$subject,$filter,false,$now,true);}
    /** Own report keeps the self-only authority path and removes wage fields. */
    public function reportOwn(int $i,int $a,array $filter=['this_month'=>true],?int $now=null):array
    {
        $result=$this->readInternal($i,$a,$a,$filter,true,$now,false);
        foreach($result['timesheets']as&$row){unset($row['hourly_rate'],$row['rate_declared']);}unset($row);
        return$result;
    }
    private function readInternal(int $i,int $a,int $subject,array $filter,bool $own,?int $now,bool $report):array
    {
        $this->reads=[];$acl=$this->authority($i,$a,$subject,$own,$report);$now??=time();if($now<1)throw new InvalidArgumentException('Invalid clock');
        if(array_diff(array_keys($filter),['today','this_month','last_month','this_week','last_week','period-from','period-to']))throw new DomainException('native_staff_timesheet_filter_invalid');
        $period=null;if(isset($filter['period-from'])||isset($filter['period-to'])){foreach(['period-from','period-to']as$key){$value=$filter[$key]??null;if(!is_string($value)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$value)||!($d=DateTimeImmutable::createFromFormat('!Y-m-d',$value,$this->zone))||$d->format('Y-m-d')!==$value)throw new DomainException('native_staff_timesheet_filter_invalid');}$period=[$filter['period-from'],$filter['period-to']];if($period[0]>$period[1])throw new DomainException('native_staff_timesheet_filter_invalid');}
        $rows=[];$details=new NativeCompanyTaskTimerDetails($this->db,$this->p);$snapshots=[];
        foreach(['company_task_timer'=>'company_task_timers','company_standalone_timer'=>'company_standalone_timers','company_blank_task_timer'=>'company_blank_task_timers']as$kind=>$table){
            foreach($this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND actor_id=? ORDER BY id',[$i,$subject])as$row){$id=IssuerPolicy::id($row['id']);$blank=$kind==='company_blank_task_timer';
                if($blank){if(!in_array($row['state'],['active','assigned','discarded'],true))throw new RuntimeException('native_staff_timesheet_invalid');if($row['state']==='assigned'){$targetTable=match($row['target_kind']){'company_task'=>'company_task_timers','company_standalone_task'=>'company_standalone_timers',default=>throw new RuntimeException('native_staff_timesheet_invalid')};$target=$this->one('SELECT issuer_id,actor_id,task_id,seconds,started_at,stopped_at FROM '.$this->p.$targetTable.' WHERE id=?',[$row['target_timer_id']]);if(!$target||(int)$target['issuer_id']!==$i||(int)$target['actor_id']!==$subject||(int)$target['task_id']!==(int)$row['target_id']||$target['stopped_at']===null||$row['closed_at']===null||$this->timestamp($target['started_at'])!==$this->timestamp($row['started_at'],true)||$this->timestamp($target['stopped_at'])!==$this->timestamp($row['closed_at'],true)||(int)$target['seconds']!==$this->timestamp($row['closed_at'],true)-$this->timestamp($row['started_at'],true))throw new RuntimeException('native_staff_timesheet_invalid');continue;}if($row['state']==='discarded'){if($row['closed_at']===null)throw new RuntimeException('native_staff_timesheet_invalid');continue;}if($row['closed_at']!==null||$row['target_kind']!==null||$row['target_id']!==null||$row['target_timer_id']!==null)throw new RuntimeException('native_staff_timesheet_invalid');$parent=['task_id'=>0,'name'=>'','rel_type'=>'','rel_id'=>0,'relation_name'=>'','project_id'=>null];$stop=null;}
                else{$parent=$this->parent($i,$kind,IssuerPolicy::id($row['task_id']));$stop=$row['stopped_at'];}
                $start=$this->timestamp($row['started_at'],$blank);$end=$stop===null?$now:$this->timestamp($stop);if($end<$start||$start>$now)throw new RuntimeException('native_staff_timesheet_invalid');$seconds=$end-$start;
                if(!$blank&&$stop!==null&&(!ctype_digit((string)$row['seconds'])||(int)$row['seconds']!==$seconds))throw new RuntimeException('native_staff_timesheet_invalid');
                $snapshot=$report?$details->forReport($i,$a,$subject,$kind,$id):($own?$details->forOwnTimesheet($i,$a,$kind,$id):$details->forStaffTimesheet($i,$a,$subject,$kind,$id));$snapshots[]=[$kind,$id,$snapshot];$state=$this->billing($i,$kind,$id,$blank?0:(int)$row['task_id'],$parent['project_id']);
                $canOpen=$acl->can($i,$a,'tasks','view')&&($parent['project_id']===null||$acl->can($i,$a,'projects','view'));
                $rows[]=$parent+['id'=>NativeCompanyResourceId::encode($kind,$id),'staff_id'=>$subject,'start_time'=>$start,'end_time'=>$end,'not_finished'=>$stop===null,'total'=>$seconds,'hourly_rate'=>$snapshot['hourly_rate']??null,'note'=>$snapshot['note'],'rate_declared'=>$snapshot['declared'],'billing_state'=>$state,'billed'=>$state==='converted'?true:($state==='converting'?null:false),'can_open_task'=>$canOpen,'source_kind'=>$kind,'source_id'=>$id];
            }
        }
        $this->authority($i,$a,$subject,$own,$report);foreach($this->reads as[$q,$args,$before])if(IssuerStorage::query($this->db,$q,$args)->result_array()!==$before)throw new DomainException('native_staff_timesheet_stale');foreach($snapshots as[$kind,$id,$before])if(($report?$details->forReport($i,$a,$subject,$kind,$id):($own?$details->forOwnTimesheet($i,$a,$kind,$id):$details->forStaffTimesheet($i,$a,$subject,$kind,$id)))!==$before)throw new DomainException('native_staff_timesheet_stale');
        $this->authority($i,$a,$subject,$own,$report);
        if($report){foreach($rows as&$reportRow){unset($reportRow['hourly_rate'],$reportRow['rate_declared']);}unset($reportRow);}
        $date=(new DateTimeImmutable('@'.$now))->setTimezone($this->zone);$ranges=['today'=>[$date->format('Y-m-d'),$date->format('Y-m-d')],'this_month'=>[$date->format('Y-m-01'),$date->format('Y-m-t')],'last_month'=>[$date->modify('first day of last month')->format('Y-m-d'),$date->modify('last day of last month')->format('Y-m-d')],'this_week'=>[$date->modify('monday this week')->format('Y-m-d'),$date->modify('sunday this week')->format('Y-m-d')],'last_week'=>[$date->modify('monday last week')->format('Y-m-d'),$date->modify('sunday last week')->format('Y-m-d')]];
        $out=['timesheets'=>[],'total'=>0,'this_month'=>0,'last_month'=>0,'this_week'=>0,'last_week'=>0,'today'=>0];foreach($rows as$row){$out['total']+=$row['total'];$day=(new DateTimeImmutable('@'.$row['start_time']))->setTimezone($this->zone)->format('Y-m-d');$include=$period&&$day>=$period[0]&&$day<=$period[1];foreach($ranges as$key=>[$from,$to])if($day>=$from&&$day<=$to){$out[$key]+=$row['total'];if(!empty($filter[$key]))$include=true;}if($include)$out['timesheets'][$row['id']]=$row;}return$out;
    }
}
