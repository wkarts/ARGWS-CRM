<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffTimesheets.php';
require_once __DIR__.'/NativeCompanyTimesheetReportAuthority.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/NativeCompanyStaffTimeChart.php';
require_once __DIR__.'/CompanySettingsStorage.php';
/** Existing Staff/timesheets data, with concrete report permission separate from wages and task mutation. */
final class NativeCompanyStaffTimeReport
{
    private DateTimeZone $zone;
    public function __construct(private $db,private string $p,string $timezone){$this->zone=new DateTimeZone($timezone);}
    public function preferences(int $i):array
    {
        $store=new CompanySettingsStorage($this->db,$this->p);$out=[];foreach(['round_off_task_timer_option','round_off_task_timer_time']as$key)$out[$key]=$store->exists($i,$key)?$store->get($i,$key):NativeCompanyTaskSettings::extras()[$key];NativeCompanyTaskSettings::validateExtras($out);return$out;
    }
    public function range(array $input,int $now):array
    {
        $range=$input['range']??'today';if(!is_string($range)||!in_array($range,['today','this_month','last_month','this_week','last_week','period'],true))throw new DomainException('native_staff_timesheet_filter_invalid');$date=(new DateTimeImmutable('@'.$now))->setTimezone($this->zone);
        [$from,$to]=match($range){'today'=>[$date->format('Y-m-d'),$date->format('Y-m-d')],'this_month'=>[$date->format('Y-m-01'),$date->format('Y-m-t')],'last_month'=>[$date->modify('first day of last month')->format('Y-m-d'),$date->modify('last day of last month')->format('Y-m-d')],'this_week'=>[$date->modify('monday this week')->format('Y-m-d'),$date->modify('sunday this week')->format('Y-m-d')],'last_week'=>[$date->modify('monday last week')->format('Y-m-d'),$date->modify('sunday last week')->format('Y-m-d')],default=>[$input['period-from']??null,$input['period-to']??null]};
        foreach([$from,$to]as$value){if(!is_string($value)||!($parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$value,$this->zone))||$parsed->format('Y-m-d')!==$value)throw new DomainException('native_staff_timesheet_filter_invalid');}if($from>$to)throw new DomainException('native_staff_timesheet_filter_invalid');return['range'=>$range,'from'=>$from,'to'=>$to];
    }
    private function guard(int $i,int $a,bool $all):IssuerResourceAccess
    {$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->member($i,$a)||$acl->primaryId()===$i||($all&&!$acl->can($i,$a,'reports','view-timesheets')))throw new DomainException('native_timesheet_report_denied');return$acl;}
    public function read(int $i,int $a,bool $all,array $input=[],?int $now=null):array
    {
        $acl=$this->guard($i,$a,$all);$now??=time();$range=$this->range($input,$now);$preferences=$this->preferences($i);$authority=new NativeCompanyTimesheetReportAuthority($this->db,$this->p);$subjects=$all?$authority->subjects($i,$a):[['staff_id'=>$a,'name'=>null]];
        $staff=$input['staff_id']??'';if($staff!==''&&$staff!==null){$staff=IssuerPolicy::id($staff);if(!$all&&$staff!==$a)throw new DomainException('native_timesheet_report_denied');if($all)$authority->assertTarget($i,$a,$staff);$subjects=array_values(array_filter($subjects,static fn($r)=>$r['staff_id']===$staff));}
        $projects=$input['project_id']??[];if($projects===''||$projects===null)$projects=[];if(!is_array($projects)||!array_is_list($projects)||count($projects)>1000)throw new DomainException('native_staff_timesheet_filter_invalid');$projectIds=[];foreach($projects as$id){$decoded=NativeCompanyResourceId::decode('project',$id);if($decoded['source_kind']!=='company_project'||isset($projectIds[$decoded['source_id']]))throw new DomainException('native_staff_timesheet_filter_invalid');$projectIds[$decoded['source_id']]=true;}
        $client=$input['clientid']??'';$client=$client===''||$client===null?null:IssuerPolicy::id($client);
        // A request filter is never accepted merely because it happened to return no rows.
        $filterProof=[];
        foreach(array_keys($projectIds)as$id){$q='SELECT issuer_id,client_id FROM '.$this->p.'company_projects WHERE id=?';$project=IssuerStorage::query($this->db,$q,[$id])->row_array();$filterProof[]=[$q,[$id],$project];if(!$project||(int)$project['issuer_id']!==$i)throw new DomainException('native_staff_timesheet_filter_invalid');$owner=IssuerStorage::query($this->db,'SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$project['client_id']])->row_array();$filterProof[]=['SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$project['client_id']],$owner];if(!$owner||(int)$owner['owner_issuer_id']!==$i)throw new DomainException('native_staff_timesheet_filter_invalid');}
        if($client!==null){$owner=IssuerStorage::query($this->db,'SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$client])->row_array();$filterProof[]=['SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$client],$owner];if(!$owner||(int)$owner['owner_issuer_id']!==$i)throw new DomainException('native_staff_timesheet_filter_invalid');}
        $reader=new NativeCompanyStaffTimesheets($this->db,$this->p,$this->zone->getName());$tags=new NativeCompanyResourceTags($this->db,$this->p);$rows=[];$proof=[];$tagProof=[];$statusProof=[];
        foreach($subjects as$subject){$id=$subject['staff_id'];$snapshot=$all?$reader->report($i,$a,$id,['period-from'=>$range['from'],'period-to'=>$range['to']],$now):$reader->reportOwn($i,$a,['period-from'=>$range['from'],'period-to'=>$range['to']],$now);$proof[]=[$id,$snapshot];
            foreach($snapshot['timesheets']as$row){if($row['task_id']===0)continue;if($projectIds&&!isset($projectIds[$row['project_id']]))continue;if(!$projectIds&&$client!==null&&$row['client_id']!==$client)continue;
                $taskIdentity=NativeCompanyResourceId::decode('task',$row['task_id']);$taskTable=$taskIdentity['source_kind']==='company_task'?'company_tasks':'company_standalone_tasks';$statusSql='SELECT status FROM '.$this->p.$taskTable.' WHERE issuer_id=? AND id=?';$statusArgs=[$i,$taskIdentity['source_id']];$status=IssuerStorage::query($this->db,$statusSql,$statusArgs)->row_array();if(!$status||!in_array($status['status'],['todo','in_progress','awaiting_feedback','testing','done'],true))throw new DomainException('native_staff_timesheet_invalid');$statusProof[]=[$statusSql,$statusArgs,$status];$row['task_status']=$status['status'];
                $decoration=$tags->read($i,$a,'timesheet',$row['id']);$tagProof[]=[$row['id'],$decoration];$row['tags']=$decoration['tags'];$row['tags_revision']=$decoration['revision'];$row['staff_name']=$subject['name'];$rows[]=$row;
            }
        }
        foreach($filterProof as[$q,$args,$before])if(IssuerStorage::query($this->db,$q,$args)->row_array()!==$before)throw new DomainException('native_staff_timesheet_stale');
        foreach($proof as[$id,$before])if(($all?$reader->report($i,$a,$id,['period-from'=>$range['from'],'period-to'=>$range['to']],$now):$reader->reportOwn($i,$a,['period-from'=>$range['from'],'period-to'=>$range['to']],$now))!==$before)throw new DomainException('native_staff_timesheet_stale');foreach($statusProof as[$q,$args,$before])if(IssuerStorage::query($this->db,$q,$args)->row_array()!==$before)throw new DomainException('native_staff_timesheet_stale');foreach($tagProof as[$id,$before])if($tags->read($i,$a,'timesheet',$id)!==$before)throw new DomainException('native_staff_timesheet_stale');if($this->preferences($i)!==$preferences||($all&&$staff===''&&$authority->subjects($i,$a)!==$subjects))throw new DomainException('native_staff_timesheet_stale');$this->guard($i,$a,$all);
        return ['rows'=>$rows,'subjects'=>$subjects,'range'=>$range,'rounding'=>$preferences,'view_all'=>$all];
    }
}
