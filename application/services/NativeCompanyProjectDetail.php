<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyProjects.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/NativeCompanyWorkAssignments.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyProjectMetadataSchema.php';
require_once __DIR__.'/NativeCompanyWorkStatusCatalog.php';

/** Stable facts for the original project detail; does not admit the view route. */
final class NativeCompanyProjectDetail
{
    public function __construct(private $db,private string $prefix)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');}
    public function read(int $issuer,int $actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);
        $ref=NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'project',$id,'view');
        if($ref['source_kind']!=='company_project')throw new DomainException('native_project_access_denied');
        $project=(new CompanyProjects($this->db,$this->prefix))->get($issuer,$actor,$ref['source_id']);
        NativeCompanyProjectMetadataSchema::assertValid($this->db,$this->prefix);
        $statusSql='SELECT workflow_status,progress_from_tasks,progress,revision FROM '.$this->prefix.NativeCompanyProjectMetadataSchema::TABLE.' WHERE issuer_id=? AND project_id=?';
        $statusArgs=[$issuer,$ref['source_id']];$statusRows=IssuerStorage::query($this->db,$statusSql,$statusArgs)->result_array();
        if(count($statusRows)>1||($statusRows&&(!ctype_digit((string)$statusRows[0]['revision'])||(int)$statusRows[0]['revision']<1)))throw new DomainException('native_project_snapshot_changed');
        if($statusRows&&(!in_array((string)$statusRows[0]['progress_from_tasks'],['0','1'],true)||!ctype_digit((string)$statusRows[0]['progress'])||(int)$statusRows[0]['progress']>100))throw new DomainException('native_project_snapshot_changed');
        $state=NativeCompanyWorkStatusCatalog::projectState($project['status'],$statusRows[0]['workflow_status']??null);
        $customer=NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'client',$project['client_id'],'view');
        $members=(new NativeCompanyWorkAssignments($this->db,$this->prefix))->read($issuer,$actor,'project',$ref['source_id']);
        $fields=new NativeCompanyCustomFields($this->db,$this->prefix);
        $definitions=$fields->definitionsForResource($issuer,$actor,'projects',$ref['canonical_id'],['active'=>1]);
        $custom=$fields->values($issuer,$actor,'projects',$ref['canonical_id']);
        if(NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'project',$id,'view')!==$ref
            ||(new CompanyProjects($this->db,$this->prefix))->get($issuer,$actor,$ref['source_id'])!==$project
            ||IssuerStorage::query($this->db,$statusSql,$statusArgs)->result_array()!==$statusRows
            ||NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'client',$project['client_id'],'view')!==$customer
            ||(new NativeCompanyWorkAssignments($this->db,$this->prefix))->read($issuer,$actor,'project',$ref['source_id'])!==$members
            ||$fields->definitionsForResource($issuer,$actor,'projects',$ref['canonical_id'],['active'=>1])!==$definitions
            ||(new NativeCompanyCustomFields($this->db,$this->prefix))->values($issuer,$actor,'projects',$ref['canonical_id'])!==$custom)throw new DomainException('native_project_snapshot_changed');
        $visibleValues=array_intersect_key($custom,array_fill_keys(array_column($definitions,'id'),true));
        $progress=$statusRows?['from_tasks'=>(int)$statusRows[0]['progress_from_tasks'],'value'=>(int)$statusRows[0]['progress']]:null;
        return ['project'=>$project,'reference'=>$ref,'customer'=>$customer,'members'=>$members,'custom_definitions'=>$definitions,'custom_values'=>$visibleValues,'status'=>['state'=>$state,'id'=>NativeCompanyWorkStatusCatalog::id('project',$state)],'progress'=>$progress];
    }
    /** Progress from the same authorized source used by the original related-task table. */
    public function taskMetrics(int $issuer,int $actor,$id):array
    {
        require_once __DIR__.'/NativeCompanyRelatedTaskRead.php';
        $facts=(new NativeCompanyRelatedTaskRead($this->db,$this->prefix))->read($issuer,$actor,'project',$id);
        $total=count($facts['items']);$open=0;
        foreach($facts['items'] as$item){
            if(!isset($item['fields']['workflow_status'])||!in_array((int)$item['fields']['workflow_status'],[1,2,3,4,5],true))throw new DomainException('native_project_task_metrics_invalid');
            if((int)$item['fields']['workflow_status']!==5)$open++;
        }
        return ['total'=>$total,'open'=>$open,'completed'=>$total-$open,
            'completed_percent'=>$total===0?0:round(($total-$open)*100/$total,2),
            'open_percent'=>$total===0?0:round($open*100/$total,2)];
    }
    /** Original hours chart, with non-managers restricted to their own timers. */
    public function hoursChart(int $issuer,int $actor,$id,string $period,?int $now=null):array
    {
        require_once __DIR__.'/NativeCompanyTasksRead.php';require_once __DIR__.'/NativeCompanyProjectHoursChart.php';
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$acl=new IssuerResourceAccess($this->db,$this->prefix);
        $canCreate=$acl->can($issuer,$actor,'projects','create');
        $projection=(new NativeCompanyTasksRead($this->db,$this->prefix,date_default_timezone_get()))->projectTimers($issuer,$actor,$id,$now);
        $chart=(new NativeCompanyProjectHoursChart($projection,$period,$canCreate,$actor,'total_logged_time_only'))->get();
        if($acl->can($issuer,$actor,'projects','create')!==$canCreate)throw new DomainException('native_project_snapshot_changed');
        return $chart;
    }
    /** Authorized project file metadata, without hydrating bytes or task attachments. */
    public function files(int $issuer,int $actor,$id):array
    {
        require_once __DIR__.'/CompanyProjectExtras.php';
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);
        $ref=NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'project',$id,'view');
        if($ref['source_kind']!=='company_project')throw new DomainException('native_project_access_denied');
        $extras=new CompanyProjectExtras($this->db,$this->prefix);$files=$extras->files($issuer,$actor,'project',$ref['source_id']);
        if(NativeCompanyResourceReferences::resolve($this->db,$this->prefix,$issuer,$actor,'project',$id,'view')!==$ref||$extras->files($issuer,$actor,'project',$ref['source_id'])!==$files)throw new DomainException('native_project_snapshot_changed');
        return $files;
    }
    /** Real inputs for the original overview; absent grants produce no financial or task projection. */
    public function overview(int $issuer,int $actor,$id,string $period,callable $translate,?int $now=null):array
    {
        require_once __DIR__.'/NativeCompanyResourceTags.php';require_once __DIR__.'/NativeCompanyTasksRead.php';
        require_once __DIR__.'/NativeCompanyProjectMetadata.php';require_once __DIR__.'/CompanySettingsFormats.php';require_once __DIR__.'/CompanySettingsCatalog.php';
        if(!in_array($period,['this_week','last_week','this_month','last_month'],true))throw new DomainException('native_project_chart_invalid');
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$now??=time();$facts=$this->read($issuer,$actor,$id);
        $acl=new IssuerResourceAccess($this->db,$this->prefix);$canTasks=$acl->can($issuer,$actor,'tasks','view');$canCreate=$acl->can($issuer,$actor,'projects','create');
        $canFinance=$acl->can($issuer,$actor,'projects','edit')&&$acl->can($issuer,$actor,'customers','view')&&$acl->can($issuer,$actor,'invoices','view');
        $tags=(new NativeCompanyResourceTags($this->db,$this->prefix))->read($issuer,$actor,'project',$facts['reference']['canonical_id']);
        $metrics=$canTasks?$this->taskMetrics($issuer,$actor,$id):null;$timers=null;$chart=null;$seconds=null;
        if($canTasks){
            $timers=(new NativeCompanyTasksRead($this->db,$this->prefix,date_default_timezone_get()))->projectTimers($issuer,$actor,$id,$now);
            $seconds=$canCreate?$timers['total_seconds']:($timers['member_seconds'][$actor]??0);
            require_once __DIR__.'/NativeCompanyProjectHoursChart.php';$chart=(new NativeCompanyProjectHoursChart($timers,$period,$canCreate,$actor,'total_logged_time_only'))->get();
        }
        $finance=null;$currency=null;if($canFinance){$metadata=new NativeCompanyProjectMetadata($this->db,$this->prefix);$finance=IssuerTransaction::run($this->db,$this->prefix,NativeCompanyProjectMetadata::tables($this->db,$this->prefix),fn()=>$metadata->readInOwnedTransaction($issuer,$actor,(int)$facts['reference']['source_id']));if($finance!==null){require_once __DIR__.'/NativeCompanyCurrencyReference.php';$currency=(new NativeCompanyCurrencyReference($this->db,$this->prefix,$issuer,$actor))->currency($finance['currency_id']);}}
        $settings=new CompanySettingsStorage($this->db,$this->prefix);$setting=$settings->exists($issuer,'dateformat')?$settings->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
        $format=CompanySettingsFormats::dates()[$setting]??throw new DomainException('native_project_date_format_invalid');$labels=[];
        foreach(['created_at','start_date','due_date'] as$key){$value=$facts['project'][$key]??null;if($value===null||$value===''){$labels[$key]='';continue;}$date=substr((string)$value,0,10);$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new DomainException('native_project_date_invalid');$labels[$key]=$parsed->format($format);}
        $progress=null;if($facts['status']['state']==='completed')$progress=100;elseif($facts['progress']!==null){if($facts['progress']['from_tasks']===0)$progress=$facts['progress']['value'];elseif($metrics!==null)$progress=$metrics['completed_percent'];}
        $deadline=null;if(($facts['project']['due_date']??null)!==null){$zone=new DateTimeZone(date_default_timezone_get());$start=DateTimeImmutable::createFromFormat('!Y-m-d',$facts['project']['start_date'],$zone);$due=DateTimeImmutable::createFromFormat('!Y-m-d',$facts['project']['due_date'],$zone);if(!$start||!$due||$due<$start)throw new DomainException('native_project_date_invalid');$total=(int)$start->diff($due)->format('%a');$today=(new DateTimeImmutable('@'.$now))->setTimezone($zone)->setTime(0,0);$left=$today>=$due?0:($today<=$start?$total:min($total,(int)$today->diff($due)->format('%a')));$deadline=['total_days'=>$total,'days_left'=>$left,'percent'=>$total===0?null:round($left*100/$total,2)];}
        $statuses=NativeCompanyWorkStatusCatalog::owned('project',$translate);$status=null;foreach($statuses as$item)if($item['id']===$facts['status']['id']){$status=$item;break;}if($status===null)throw new DomainException('native_work_status_unknown');
        if($canTasks&&($this->taskMetrics($issuer,$actor,$id)!==$metrics||(new NativeCompanyTasksRead($this->db,$this->prefix,date_default_timezone_get()))->projectTimers($issuer,$actor,$id,$now)!==$timers))throw new DomainException('native_project_snapshot_changed');
        if($canFinance&&IssuerTransaction::run($this->db,$this->prefix,NativeCompanyProjectMetadata::tables($this->db,$this->prefix),fn()=>(new NativeCompanyProjectMetadata($this->db,$this->prefix))->readInOwnedTransaction($issuer,$actor,(int)$facts['reference']['source_id']))!==$finance)throw new DomainException('native_project_snapshot_changed');
        if($currency!==null&&(new NativeCompanyCurrencyReference($this->db,$this->prefix,$issuer,$actor))->currency($finance['currency_id'])!==$currency)throw new DomainException('native_project_snapshot_changed');
        if($this->read($issuer,$actor,$id)!==$facts||(new NativeCompanyResourceTags($this->db,$this->prefix))->read($issuer,$actor,'project',$facts['reference']['canonical_id'])!==$tags||$acl->can($issuer,$actor,'tasks','view')!==$canTasks||$acl->can($issuer,$actor,'projects','create')!==$canCreate||($acl->can($issuer,$actor,'projects','edit')&&$acl->can($issuer,$actor,'customers','view')&&$acl->can($issuer,$actor,'invoices','view'))!==$canFinance||($settings->exists($issuer,'dateformat')?$settings->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat'))!==$setting)throw new DomainException('native_project_snapshot_changed');
        return ['facts'=>$facts,'tags'=>$tags['tags'],'task_metrics'=>$metrics,'logged_seconds'=>$seconds,'chart'=>$chart,'finance'=>$finance,'currency'=>$currency,'date_labels'=>$labels,'status'=>$status,'progress'=>$progress,'deadline'=>$deadline,'period'=>$period];
    }
}
