<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyBlankTimerSchema.php';

/** Native task DTOs; ownership is checked by issuer and actor before and after every read. */
final class NativeCompanyTasksRead
{
    private DateTimeZone $storageTimezone;
    public function __construct(private $db,private string $p,?string $storageTimezone=null)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');$this->storageTimezone=new DateTimeZone($storageTimezone??date_default_timezone_get());}
    private function acl(int $issuer,int $actor,string $kind):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'tasks','view')||($kind==='project'&&!$acl->can($issuer,$actor,'projects','view')))throw new DomainException('native_company_task_denied');
    }
    private function rows(string $sql,array $params):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    /** Exact selected task for the original relation selector; this is not a search or a billing grant. */
    public function relationOption($issuer,$actor,$canonicalId):array
    {
        require_once __DIR__.'/NativeCompanyResourceReferences.php';
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->acl($issuer,$actor,'standalone');
        $identity=NativeCompanyResourceId::decode('task',$canonicalId);
        if($identity['source_kind']==='company_task')$this->acl($issuer,$actor,'project');
        if($identity['source_kind']==='task'){
            if((new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$issuer)throw new DomainException('native_resource_reference_denied');
            $scope=$this->rows('SELECT rel_type FROM '.$this->p.'tasks WHERE id=?',[$identity['source_id']]);
            if(count($scope)!==1)throw new DomainException('native_resource_reference_denied');
            if($scope[0]['rel_type']==='project')$this->acl($issuer,$actor,'project');
        }
        $resolve=fn()=>NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'task',$canonicalId,'view');
        $reference=$resolve();$project=null;$relation=null;
        if($reference['source_kind']==='company_task'){
            $this->acl($issuer,$actor,'project');
            $relationSql='SELECT project_id FROM '.$this->p.'company_tasks WHERE issuer_id=? AND id=?';$args=[$issuer,$reference['source_id']];
            $relation=$this->rows($relationSql,$args);if(count($relation)!==1)throw new DomainException('native_company_task_denied');
            $projectId=NativeCompanyResourceId::encode('company_project',$relation[0]['project_id']);
        }elseif($reference['source_kind']==='task'){
            $relationSql='SELECT rel_type,rel_id FROM '.$this->p.'tasks WHERE id=?';$args=[$reference['source_id']];
            $relation=$this->rows($relationSql,$args);if(count($relation)!==1)throw new DomainException('native_company_task_denied');
            $projectId=$relation[0]['rel_type']==='project'?(string)$relation[0]['rel_id']:null;
        }else $projectId=null;
        if($projectId!==null){$this->acl($issuer,$actor,'project');$project=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$projectId,'view');}
        if($resolve()!==$reference||($relation!==null&&$this->rows($relationSql,$args)!==$relation)||($project!==null&&NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$projectId,'view')!==$project))throw new DomainException('native_company_task_read_changed');
        $this->acl($issuer,$actor,$project===null?'standalone':'project');
        return ['id'=>$reference['canonical_id'],'name'=>$reference['name'],'link'=>$reference['url'],'source_kind'=>$reference['source_kind'],'source_id'=>$reference['source_id']];
    }
    /** Actual project timer rows for the original overview and timesheet components. No billing eligibility is inferred. */
    public function projectTimers($issuer,$actor,$canonicalProject,?int$now=null):array
    {
        require_once __DIR__.'/NativeCompanyResourceReferences.php';
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$now??=time();$this->acl($issuer,$actor,'project');
        $resolve=fn()=>NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$canonicalProject,'view');$project=$resolve();
        if($project['source_kind']==='company_project'){
            $sql='SELECT n.id,n.task_id,n.actor_id,n.started_at,n.stopped_at,n.seconds,t.name,t.revision,t.status FROM '.$this->p.'company_task_timers n JOIN '.$this->p.'company_tasks t ON t.issuer_id=n.issuer_id AND t.id=n.task_id WHERE n.issuer_id=? AND t.project_id=? ORDER BY n.id';$args=[$issuer,$project['source_id']];
        }elseif($project['source_kind']==='project'){
            require_once __DIR__.'/ClientIssuerScope.php';require_once __DIR__.'/CustomerRelationBoundary.php';require_once __DIR__.'/TaskClientBoundary.php';require_once __DIR__.'/InvoiceIssuerScope.php';
            $sql='SELECT n.id,n.task_id,n.staff_id actor_id,n.start_time,n.end_time,t.name,t.status FROM '.$this->p.'taskstimers n JOIN '.$this->p.'tasks t ON t.id=n.task_id WHERE t.rel_type=? AND t.rel_id=? AND '.\app\services\TaskClientBoundary::legacySql($this->db,$this->p,'t').' ORDER BY n.id';$args=['project',$project['source_id']];
        }else throw new DomainException('native_company_task_denied');
        $rows=$this->rows($sql,$args);$out=[];$total=0;$members=[];
        foreach($rows as$row){
            if($project['source_kind']==='company_project'){
                $start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$row['started_at'],$this->storageTimezone);$end=$row['stopped_at']===null?null:DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$row['stopped_at'],$this->storageTimezone);
                if(!$start||$start->format('Y-m-d H:i:s')!==$row['started_at']||($row['stopped_at']!==null&&(!$end||$end->format('Y-m-d H:i:s')!==$row['stopped_at']))||!ctype_digit((string)$row['seconds']))throw new DomainException('native_company_task_timer_invalid');
                $started=$start->getTimestamp();$ended=$end?->getTimestamp();$seconds=$ended===null?$now-$started:(int)$row['seconds'];$id=NativeCompanyResourceId::encode('company_task_timer',$row['id']);$task=NativeCompanyResourceId::encode('company_task',$row['task_id']);$revision=(int)$row['revision'];$basis='native_persisted_duration_seconds';
            }else{
                if(!ctype_digit((string)$row['start_time'])||($row['end_time']!==null&&!ctype_digit((string)$row['end_time'])))throw new DomainException('native_company_task_timer_invalid');$started=(int)$row['start_time'];$ended=$row['end_time']===null?null:(int)$row['end_time'];$seconds=($ended??$now)-$started;$id=(string)$row['id'];$task=(string)$row['task_id'];$revision=null;$basis='native_epoch_interval';
            }
            if($started>$now||($ended!==null&&$ended<$started)||$seconds<0||$total>PHP_INT_MAX-$seconds)throw new DomainException('native_company_task_timer_invalid');$staff=IssuerPolicy::id($row['actor_id']);$total+=$seconds;$members[$staff]=($members[$staff]??0)+$seconds;
            $out[]=['id'=>$id,'source_id'=>(int)$row['id'],'task_id'=>$task,'task_source_id'=>(int)$row['task_id'],'staff_id'=>$staff,'task_name'=>$row['name'],'start_time'=>$started,'end_time'=>$ended,'total_seconds'=>$seconds,'task_revision'=>$revision,'duration_basis'=>$basis,'source_kind'=>$project['source_kind']==='company_project'?'company_task_timer':'task_timer'];
        }
        if($this->rows($sql,$args)!==$rows||$resolve()!==$project)throw new DomainException('native_company_task_read_changed');$this->acl($issuer,$actor,'project');
        return ['project'=>$project,'timers'=>$out,'total_seconds'=>$total,'member_seconds'=>$members,'clock'=>$now,'storage_timezone'=>$this->storageTimezone->getName()];
    }
    /** Same header fields as Tasks_model::get_timers, plus explicit total/revision for scoped actions. */
    public function startedTimers($issuer,$actor,?int $now=null):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$now=$now??time();$this->acl($issuer,$actor,'standalone');NativeCompanyBlankTimerSchema::assertValid($this->db,$this->p);$out=[];
        foreach(['standalone','project'] as $kind){
            if($kind==='project'&&!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'projects','view'))continue;
            $this->acl($issuer,$actor,$kind);
            [$tasks,$timers,$source,$timerSource]=$kind==='project'?['company_tasks','company_task_timers','company_task','company_task_timer']:['company_standalone_tasks','company_standalone_timers','company_standalone_task','company_standalone_timer'];
            $sql='SELECT n.id,n.task_id,n.started_at,t.name,t.revision FROM '.$this->p.$timers.' n JOIN '.$this->p.$tasks.' t ON t.id=n.task_id AND t.issuer_id=n.issuer_id WHERE n.issuer_id=? AND n.actor_id=? AND n.stopped_at IS NULL ORDER BY n.id LIMIT 101';
            $rows=$this->rows($sql,[$issuer,$actor]);if(count($rows)>100)throw new DomainException('native_company_task_timer_limit');
            foreach($rows as $row){
                $start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$row['started_at'],$this->storageTimezone);
                if(!$start||$start->format('Y-m-d H:i:s')!==$row['started_at']||$start->getTimestamp()>$now)throw new DomainException('native_company_task_timer_invalid');
                $seconds=$now-$start->getTimestamp();
                foreach($this->rows('SELECT seconds FROM '.$this->p.$timers.' WHERE issuer_id=? AND task_id=? AND actor_id=? AND stopped_at IS NOT NULL ORDER BY id',[$issuer,$row['task_id'],$actor]) as $finished){
                    if(!ctype_digit((string)$finished['seconds'])||$seconds>PHP_INT_MAX-(int)$finished['seconds'])throw new DomainException('native_company_task_timer_invalid');$seconds+=(int)$finished['seconds'];
                }
                $out[]=['id'=>NativeCompanyResourceId::encode($timerSource,$row['id']),'task_id'=>NativeCompanyResourceId::encode($source,$row['task_id']),
                    'task_subject'=>$row['name'],'start_time'=>$start->getTimestamp(),'total_seconds'=>$seconds,'revision'=>(int)$row['revision'],'issuer_id'=>$issuer,'time_basis'=>'runtime_datetime','storage_timezone'=>$this->storageTimezone->getName()];
            }
            if($this->rows($sql,[$issuer,$actor])!==$rows)throw new DomainException('native_company_task_read_changed');$this->acl($issuer,$actor,$kind);
        }
        $blankSql='SELECT * FROM '.$this->p.'company_blank_task_timers WHERE issuer_id=? AND actor_id=? AND state=? ORDER BY id LIMIT 2';
        $blanks=$this->rows($blankSql,[$issuer,$actor,'active']);if(count($blanks)>1)throw new DomainException('native_company_task_timer_invalid');
        foreach($blanks as$row){$start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$row['started_at'],new DateTimeZone('UTC'));
            if(!$start||$start->format('Y-m-d H:i:s')!==$row['started_at']||$start->getTimestamp()>$now||$row['closed_at']!==null||$row['target_kind']!==null||$row['target_id']!==null||$row['target_timer_id']!==null||(int)$row['revision']<1)throw new DomainException('native_company_task_timer_invalid');
            $out[]=['id'=>NativeCompanyResourceId::encode('company_blank_task_timer',$row['id']),'task_id'=>'0','task_subject'=>'','start_time'=>$start->getTimestamp(),'total_seconds'=>$now-$start->getTimestamp(),'revision'=>(int)$row['revision'],'issuer_id'=>$issuer,'time_basis'=>'utc_datetime','storage_timezone'=>'UTC'];
        }
        if($this->rows($blankSql,[$issuer,$actor,'active'])!==$blanks)throw new DomainException('native_company_task_read_changed');
        $primary=(new IssuerResourceAccess($this->db,$this->p))->primaryId();
        if($primary===$issuer){
            require_once __DIR__.'/ClientIssuerScope.php';require_once __DIR__.'/CustomerRelationBoundary.php';require_once __DIR__.'/TaskClientBoundary.php';require_once __DIR__.'/InvoiceIssuerScope.php';
            // Legacy timer IDs are kept plain. They are visible only in the persisted primary context.
            $sql='SELECT n.id,n.task_id,n.start_time,t.name task_subject FROM '.$this->p.'taskstimers n LEFT JOIN '.$this->p.'tasks t ON t.id=n.task_id WHERE n.staff_id=? AND n.end_time IS NULL AND '.\app\services\TaskClientBoundary::legacyTimerSql($this->db,$this->p,'n').' ORDER BY n.id LIMIT 101';
            $legacy=$this->rows($sql,[$actor]);if(count($legacy)>100)throw new DomainException('native_company_task_timer_limit');
            foreach($legacy as $row){
                if(!ctype_digit((string)$row['start_time'])||(int)$row['start_time']>$now)throw new DomainException('native_company_task_timer_invalid');
                $total=$now-(int)$row['start_time'];
                if((int)$row['task_id']>0)foreach($this->rows('SELECT start_time,end_time FROM '.$this->p.'taskstimers WHERE staff_id=? AND task_id=? AND end_time IS NOT NULL ORDER BY id',[$actor,$row['task_id']]) as $finished){
                    $delta=(int)$finished['end_time']-(int)$finished['start_time'];if($delta<0||$total>PHP_INT_MAX-$delta)throw new DomainException('native_company_task_timer_invalid');$total+=$delta;
                }
                $out[]=['id'=>(string)$row['id'],'task_id'=>(string)($row['task_id']??0),'task_subject'=>$row['task_subject']??'',
                    'start_time'=>(int)$row['start_time'],'total_seconds'=>$total,'revision'=>null,'issuer_id'=>$issuer,'lineage'=>'legacy','time_basis'=>'unix_epoch','storage_timezone'=>null];
            }
            if($this->rows($sql,[$actor])!==$legacy)throw new DomainException('native_company_task_read_changed');
        }
        if((new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$primary)throw new DomainException('native_company_task_read_changed');
        $this->acl($issuer,$actor,'standalone');usort($out,static fn($a,$b)=>$a['start_time']<=>$b['start_time']);return $out;
    }
}
