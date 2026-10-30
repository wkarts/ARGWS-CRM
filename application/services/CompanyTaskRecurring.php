<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTaskRecurringSchema.php';
require_once __DIR__.'/CompanyTaskRecurringDates.php';
require_once __DIR__.'/CompanyTasks.php';
require_once __DIR__.'/CompanyStandaloneTasks.php';
require_once __DIR__.'/CompanyApiTokens.php';

/** Own local task recurrence. No legacy cron, transport, financial copy or ambient company. */
final class CompanyTaskRecurring
{
    private array $proofs=[];
    public function __construct(private $db,private string $p,private $originGuard=null)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||($originGuard!==null&&!is_callable($originGuard)))throw new InvalidArgumentException('Invalid recurrence context');
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function json(array $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    private static function hash(array $v):string{return hash('sha256',self::json($v));}
    private static function decode(string $v):array{$a=json_decode($v,true,32,JSON_THROW_ON_ERROR);if(!is_array($a)||array_is_list($a))throw new RuntimeException('issuer_task_recurring_unconfirmed');return $a;}
    private static function strings(array $r):array{return array_map(static fn($v)=>$v===null?null:(string)$v,$r);}
    private static function key(string $v):string{if(!preg_match('/^[a-f0-9]{64}$/D',$v))throw new DomainException('company_task_recurring_invalid');return $v;}
    private static function kind(string $kind):string{if(!in_array($kind,['project','standalone'],true))throw new DomainException('company_task_recurring_invalid');return $kind;}
    private static function origin(?array $origin):?array
    {
        if($origin===null)return null;
        if(array_keys($origin)!==['token_id','scope']||!is_string($origin['token_id'])||!preg_match('/^[a-f0-9]{32}$/D',$origin['token_id'])||$origin['scope']!=='tasks:write')throw new DomainException('company_task_recurring_invalid');return $origin;
    }
    public function tables():array
    {
        return array_values(array_unique(array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit','company_api_tokens','company_api_token_requests'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),CompanyTaskPrioritySchema::tables($this->db,$this->p),CompanyTaskAutoStop::tables($this->db,$this->p),(new CompanyTasks($this->db,$this->p))->copyTransactionParticipants(),(new CompanyStandaloneTasks($this->db,$this->p))->copyTransactionParticipants(),array_keys(CompanyTaskRecurringSchema::definitions($this->p)))));
    }
    private function access(int $i,int $a,string $kind,string $cap):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($i,$a);
        foreach(array_unique(['view',$cap,...($cap==='create'?['edit']:[])])as$c)if(!$acl->can($i,$a,'tasks',$c))throw new DomainException('company_task_recurring_access_denied');
        if($kind==='project'&&!$acl->can($i,$a,'projects','view'))throw new DomainException('company_task_recurring_access_denied');
    }
    private function guard():void{if($this->originGuard!==null&&($this->originGuard)()===false)throw new DomainException('company_api_scope_denied');}
    private function tx(int $i,int $a,string $kind,string $cap,callable $work)
    {
        CompanyTaskRecurringSchema::assertValid($this->db,$this->p);CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyStandaloneTasksSchema::assertValid($this->db,$this->p);CompanyApiSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$kind,$cap,$work){
            $this->proofs=[];try{$this->access($i,$a,$kind,$cap);$this->guard();$result=$work();$this->guard();$this->access($i,$a,$kind,$cap);foreach($this->proofs as$proof)$proof();return $result;}finally{$this->proofs=[];}
        });
    }
    private function watch(callable $read,array $expected):void
    {
        $this->proofs[]=static function()use($read,$expected){if($read()!==$expected)throw new RuntimeException('issuer_task_recurring_unconfirmed');};
    }
    private function insert(string $t,array $row):void
    {
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$t.' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
    }
    private function exact(string $table,string $where,array $args,array $expected):array
    {
        $read=fn()=>array_map([self::class,'strings'],$this->rows('SELECT * FROM '.$this->p.$table.' WHERE '.$where.' FOR UPDATE',$args));$want=[self::strings($expected)];
        $got=$read();if($got!==$want)throw new RuntimeException('issuer_task_recurring_unconfirmed');$this->watch($read,$want);return $got[0];
    }
    private function audit(int $i,int $a,int $id,string $event,array $payload):void
    {
        $row=['actor_id'=>$a,'event'=>$event,'issuer_id'=>$i,'subject_id'=>$id,'created_at'=>gmdate('Y-m-d H:i:s'),'metadata_json'=>self::json($payload)];
        $this->insert('issuers_audit',$row);$audit=(int)$this->db->insert_id();if($audit<1)throw new RuntimeException('issuer_task_recurring_unconfirmed');
        $read=fn()=>array_map([self::class,'strings'],$this->rows('SELECT id,actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=? FOR UPDATE',[$audit]));$want=[self::strings(['id'=>$audit]+$row)];if($read()!==$want)throw new RuntimeException('issuer_task_recurring_unconfirmed');$this->watch($read,$want);
    }
    private function auditExists(int $i,int $a,int $id,string $event,array $payload):void
    {
        if(count($this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=? FOR UPDATE',[$i,$a,$id,$event,self::json($payload)]))!==1)throw new RuntimeException('issuer_task_recurring_unconfirmed');
    }
    private function writer(string $kind){return $kind==='project'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);}
    private function taskFrame(int $i,string $kind,int $id):array
    {
        $frame=CompanyTaskPriority::frame($this->db,$this->p,$kind,$i,$id);
        $prefix=$kind==='project'?'company_task_':'company_standalone_';
        foreach(['comments','timers']as$child){$rows=$this->rows('SELECT * FROM '.$this->p.$prefix.$child.' WHERE issuer_id=? AND task_id=? ORDER BY id LIMIT 501 FOR UPDATE',[$i,$id]);if(count($rows)>500)throw new DomainException('company_task_recurring_limit');$frame[$child]=array_map([self::class,'strings'],$rows);}
        $frame['project']=null;$frame['dependencies']=[];$frame['files']=[];
        if($kind==='project'){
            $frame['project']=self::strings($this->rows('SELECT * FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$frame['native']['project_id']])[0]??throw new DomainException('company_task_recurring_access_denied'));
            $frame['dependencies']=array_map([self::class,'strings'],$this->rows('SELECT * FROM '.$this->p.'company_task_dependencies WHERE issuer_id=? AND (task_id=? OR depends_on=?) ORDER BY task_id,depends_on FOR UPDATE',[$i,$id,$id]));
            $frame['files']=array_map([self::class,'strings'],$this->rows('SELECT file_key,issuer_id,project_id,task_id,actor_id,filename,mime,content_hash,byte_size,SHA2(content,256) actual_hash,active,created_at FROM '.$this->p.'company_project_files WHERE issuer_id=? AND task_id=? ORDER BY file_key FOR UPDATE',[$i,$id]));
        }
        return $frame;
    }
    private static function template(array $frame,string $kind):array
    {
        $r=$frame['native'];$checks=json_decode($r['checklist_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($checks)||!array_is_list($checks)||count($checks)>30)throw new DomainException('company_task_recurring_invalid');$reset=[];
        foreach($checks as$c){if(!is_array($c)||array_keys($c)!==['text','done']||!is_string($c['text'])||!in_array($c['done'],[0,1],true))throw new DomainException('company_task_recurring_invalid');$reset[]=['text'=>$c['text'],'done'=>0];}
        $out=['assignee_id'=>IssuerPolicy::id($r['assignee_id']),'name'=>$r['name'],'description'=>$r['description'],'status'=>'todo','checklist'=>$reset,'priority'=>CompanyTaskPriority::value($frame['priority']['priority']??2)];
        if($kind==='project')$out=['project_id'=>IssuerPolicy::id($r['project_id'])]+$out;return $out;
    }
    private function source(int $i,array $contract):array
    {
        $frame=$this->taskFrame($i,$contract['kind'],$contract['source_id']);
        if($contract['kind']==='project'&&(int)$frame['native']['project_id']!==$contract['template']['project_id'])throw new DomainException('company_task_recurring_reference_denied');return $frame;
    }
    private static function payload(array $contract,string $date):array{return $contract['template']+['due_date'=>CompanyTaskRecurringDates::due($contract['calendar'],$date)];}
    private function validateOrigin(array $c):void
    {
        $this->access($c['issuer_id'],$c['actor_id'],$c['kind'],'create');
        if($c['api_origin']!==null)(new CompanyApiTokens($this->db,$this->p))->guardOriginInOwnedTransaction($c['api_origin']['token_id'],$c['api_origin']['scope'],$c['issuer_id'],$c['actor_id']);
    }
    private static function creationSeal(array $row):array
    {
        return ['request_key'=>$row['request_key'],'input_hash'=>$row['input_hash'],'contract_hash'=>$row['contract_hash'],'kind'=>$row['kind'],'source_id'=>(int)$row['source_id']];
    }
    private function schedule(int $i,int $id,?string $kind=null):array
    {
        $r=$this->rows('SELECT * FROM '.$this->p.'company_task_schedules WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$id])[0]??throw new DomainException('company_task_recurring_access_denied');$r=self::strings($r);
        if($kind!==null&&$r['kind']!==$kind)throw new DomainException('company_task_recurring_access_denied');
        $c=self::decode($r['contract_json']);
        if(array_keys($c)!==['version','issuer_id','actor_id','kind','source_id','source_revision','template','calendar','api_origin']||$c['version']!==1||$c['issuer_id']!==$i||$c['actor_id']!==(int)$r['actor_id']||$c['kind']!==self::kind($r['kind'])||$c['source_id']!==(int)$r['source_id']||!is_int($c['source_revision'])||$c['source_revision']<1||!is_array($c['template'])||!is_array($c['calendar'])||CompanyTaskRecurringDates::configuration($c['calendar'])!==$c['calendar']||self::origin($c['api_origin'])!==$c['api_origin']||!hash_equals($r['contract_hash'],self::hash($c))||$r['contract_json']!==self::json($c))throw new RuntimeException('issuer_task_recurring_unconfirmed');
        $input=['kind'=>$c['kind'],'source_id'=>$c['source_id'],'source_revision'=>$c['source_revision'],'calendar'=>$c['calendar'],'api_origin'=>$c['api_origin']];
        if(!hash_equals($r['input_hash'],self::hash($input))||!in_array($r['state'],['active','paused','cancelled','completed'],true)||(int)$r['revision']<1||(int)$r['completed_cycles']>$c['calendar']['cycles'])throw new RuntimeException('issuer_task_recurring_unconfirmed');
        $count=(int)$r['completed_cycles'];$next=$count===$c['calendar']['cycles']?null:CompanyTaskRecurringDates::occurrence($c['calendar'],$count);
        if($r['next_date']!==$next||($r['state']==='completed')!==($count===$c['calendar']['cycles']))throw new RuntimeException('issuer_task_recurring_unconfirmed');
        $this->auditExists($i,(int)$r['actor_id'],$id,'company_task_schedule_created',self::creationSeal($r));
        $runs=$this->rows('SELECT * FROM '.$this->p.'company_task_schedule_runs WHERE issuer_id=? AND schedule_id=? ORDER BY cycle FOR UPDATE',[$i,$id]);
        if(count($runs)!==$count)throw new RuntimeException('issuer_task_recurring_unconfirmed');foreach($runs as$n=>$run){if((int)$run['cycle']!==$n)throw new RuntimeException('issuer_task_recurring_unconfirmed');$this->checkRun($r,$c,self::strings($run));}
        $this->history($r,$c,array_map([self::class,'strings'],$runs));
        return ['row'=>$r,'contract'=>$c,'runs'=>array_map([self::class,'strings'],$runs)];
    }
    private function watchSchedule(int $i,int $id,string $kind):void
    {
        $expected=$this->schedule($i,$id,$kind);$this->watch(fn()=>$this->schedule($i,$id,$kind),$expected);
        $read=fn()=>array_map([self::class,'strings'],$this->rows('SELECT * FROM '.$this->p.'company_task_schedule_requests WHERE issuer_id=? AND schedule_id=? ORDER BY request_key FOR UPDATE',[$i,$id]));$this->watch($read,$read());
    }
    /** Rebuild mutable state from exact audited receipts; a changed state flag is not a lease. */
    private function history(array $r,array $c,array $runs):void
    {
        $requests=$this->rows('SELECT * FROM '.$this->p.'company_task_schedule_requests WHERE issuer_id=? AND schedule_id=? ORDER BY created_at,request_key LIMIT 1001 FOR UPDATE',[$r['issuer_id'],$r['id']]);
        if(count($requests)>1000)throw new DomainException('company_task_recurring_limit');$events=[];$creation=0;
        foreach($requests as$request){$q=self::strings($request);$result=$this->checkReceipt($q);$rev=$result['revision'];
            if($q['action']==='create'){if($q['request_key']!==$r['request_key']||$q['input_hash']!==$r['input_hash']||$q['actor_id']!==$r['actor_id']||$rev!==1||$result['state']!=='active'||$q['created_at']!==$r['created_at'])throw new RuntimeException('issuer_task_recurring_unconfirmed');$creation++;}
            elseif(!in_array($q['action'],['pause','resume','cancel'],true)||!hash_equals($q['input_hash'],self::hash([$c['kind'],(int)$r['id'],$rev-1,$q['action']])))throw new RuntimeException('issuer_task_recurring_unconfirmed');
            if(isset($events[$rev]))throw new RuntimeException('issuer_task_recurring_unconfirmed');$events[$rev]=['action'=>$q['action'],'state'=>$result['state'],'at'=>$q['created_at']];
        }
        foreach($runs as$run){$rev=(int)$run['revision'];if(isset($events[$rev]))throw new RuntimeException('issuer_task_recurring_unconfirmed');$events[$rev]=['action'=>'run','cycle'=>(int)$run['cycle'],'at'=>$run['created_at']];}
        if($creation!==1||count($events)!==(int)$r['revision'])throw new RuntimeException('issuer_task_recurring_unconfirmed');ksort($events);$state=null;$cycles=0;$at=null;
        foreach($events as$revision=>$event){
            if($event['action']==='create'){$expected=$revision===1?'active':null;}
            elseif($event['action']==='run'){$expected=$state==='active'&&$event['cycle']===$cycles?(++$cycles===$c['calendar']['cycles']?'completed':'active'):null;}
            else $expected=match($event['action']){'pause'=>$state==='active'?'paused':null,'resume'=>$state==='paused'?'active':null,'cancel'=>in_array($state,['active','paused'],true)?'cancelled':null,default=>null};
            if($expected===null||($event['action']!=='run'&&$event['state']!==$expected))throw new RuntimeException('issuer_task_recurring_unconfirmed');$state=$expected;$at=$event['at'];
        }
        if($state!==$r['state']||$cycles!==(int)$r['completed_cycles']||$at!==$r['updated_at']||array_keys($events)!==range(1,(int)$r['revision']))throw new RuntimeException('issuer_task_recurring_unconfirmed');
    }
    private function checkRun(array $schedule,array $c,array $r):void
    {
        $date=CompanyTaskRecurringDates::occurrence($c['calendar'],(int)$r['cycle']);$birth=self::decode($r['birth_json']);
        if($r['kind']!==$c['kind']||$r['run_date']!==$date||$r['contract_hash']!==$schedule['contract_hash']||$r['request_key']!==self::runKey((int)$schedule['issuer_id'],(int)$schedule['id'],(int)$r['cycle'])||!hash_equals($r['birth_hash'],self::hash($birth))||$r['birth_json']!==self::json($birth)||(int)($birth['native']['id']??0)!==(int)$r['task_id']||(int)($birth['native']['issuer_id']??0)!==(int)$schedule['issuer_id']||($birth['native']['due_date']??null)!==CompanyTaskRecurringDates::due($c['calendar'],$date)||($birth['native']['status']??null)!=='todo')throw new RuntimeException('issuer_task_recurring_unconfirmed');
        $table=$c['kind']==='project'?'company_tasks':'company_standalone_tasks';$task=$this->rows('SELECT issuer_id,id'.($c['kind']==='project'?',project_id':'').' FROM '.$this->p.$table.' WHERE issuer_id=? AND id=? FOR UPDATE',[$schedule['issuer_id'],$r['task_id']])[0]??null;
        if(!$task||($c['kind']==='project'&&(int)$task['project_id']!==$c['template']['project_id']))throw new RuntimeException('issuer_task_recurring_unconfirmed');
        $this->auditExists((int)$schedule['issuer_id'],(int)$schedule['actor_id'],(int)$schedule['id'],'company_task_schedule_run',['row_hash'=>self::hash($r)]);
    }
    private function checkReceipt(array $r):array
    {
        $result=self::decode($r['result_json']);if(array_keys($result)!==['id','revision','state']||!is_int($result['id'])||!is_int($result['revision'])||$result['revision']<1||$result['id']!==(int)$r['schedule_id']||!is_string($result['state'])||!hash_equals($r['result_hash'],self::hash($result))||$r['result_json']!==self::json($result))throw new RuntimeException('issuer_task_recurring_unconfirmed');
        $this->auditExists((int)$r['issuer_id'],(int)$r['actor_id'],(int)$r['schedule_id'],'company_task_schedule_request',['row_hash'=>self::hash($r)]);return $result;
    }
    private function receipt(string $key,int $i,int $a,string $action,string $hash):?array
    {
        $r=$this->rows('SELECT * FROM '.$this->p.'company_task_schedule_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;if(!$r)return null;$r=self::strings($r);
        if((int)$r['issuer_id']!==$i||(int)$r['actor_id']!==$a||$r['action']!==$action||!hash_equals($r['input_hash'],$hash))throw new DomainException('company_task_recurring_request_conflict');
        return $this->checkReceipt($r);
    }
    private function saveReceipt(string $key,int $i,int $a,int $id,string $action,string $hash,array $result,string $now):void
    {
        $r=['request_key'=>$key,'issuer_id'=>$i,'actor_id'=>$a,'schedule_id'=>$id,'action'=>$action,'input_hash'=>$hash,'result_hash'=>self::hash($result),'result_json'=>self::json($result),'created_at'=>$now];$this->insert('company_task_schedule_requests',$r);
        $this->exact('company_task_schedule_requests','request_key=?',[$key],$r);$this->audit($i,$a,$id,'company_task_schedule_request',['row_hash'=>self::hash(self::strings($r))]);
    }
    public function create($issuer,$actor,string $kind,$source,$sourceRevision,array $calendar,string $key,?array $apiOrigin=null):int
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$source=IssuerPolicy::id($source);$revision=IssuerPolicy::id($sourceRevision);$kind=self::kind($kind);$calendar=CompanyTaskRecurringDates::configuration($calendar);$key=self::key($key);$apiOrigin=self::origin($apiOrigin);
        $hash=self::hash(['kind'=>$kind,'source_id'=>$source,'source_revision'=>$revision,'calendar'=>$calendar,'api_origin'=>$apiOrigin]);
        return $this->tx($i,$a,$kind,'create',function()use($i,$a,$kind,$source,$revision,$calendar,$key,$apiOrigin,$hash):int{
            $prior=$this->receipt($key,$i,$a,'create',$hash);
            if($prior){$s=$this->schedule($i,$prior['id'],$kind);if($s['row']['request_key']!==$key)throw new RuntimeException('issuer_task_recurring_unconfirmed');$this->validateOrigin($s['contract']);$frame=$this->source($i,$s['contract']);$this->watch(fn()=>$this->source($i,$s['contract']),$frame);$this->watchSchedule($i,$prior['id'],$kind);$this->proofs[]=fn()=>$this->validateOrigin($s['contract']);return $prior['id'];}
            $sourceFrame=$this->taskFrame($i,$kind,$source);if((int)$sourceFrame['native']['revision']!==$revision)throw new DomainException('company_task_recurring_stale');
            $c=['version'=>1,'issuer_id'=>$i,'actor_id'=>$a,'kind'=>$kind,'source_id'=>$source,'source_revision'=>$revision,'template'=>self::template($sourceFrame,$kind),'calendar'=>$calendar,'api_origin'=>$apiOrigin];$this->validateOrigin($c);
            $writer=$this->writer($kind);$writer->validateRecurringTaskInOwnedTransaction($i,$a,self::payload($c,$calendar['first_date']));$writer->validateRecurringTaskInOwnedTransaction($i,$a,self::payload($c,CompanyTaskRecurringDates::occurrence($calendar,$calendar['cycles']-1)));
            if((int)($this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_task_schedules WHERE issuer_id=? AND state IN (?,?)',[$i,'active','paused'])[0]['n']??0)>=500)throw new DomainException('company_task_recurring_limit');
            $now=gmdate('Y-m-d H:i:s');$r=['issuer_id'=>$i,'actor_id'=>$a,'kind'=>$kind,'source_id'=>$source,'request_key'=>$key,'input_hash'=>$hash,'contract_hash'=>self::hash($c),'contract_json'=>self::json($c),'state'=>'active','revision'=>1,'completed_cycles'=>0,'next_date'=>$calendar['first_date'],'created_at'=>$now,'updated_at'=>$now];
            $this->insert('company_task_schedules',$r);$id=(int)$this->db->insert_id();if($id<1||$id>2147483647)throw new RuntimeException('issuer_task_recurring_unconfirmed');$r=['id'=>$id]+$r;
            $this->exact('company_task_schedules','id=?',[$id],$r);$this->audit($i,$a,$id,'company_task_schedule_created',self::creationSeal($r));$this->saveReceipt($key,$i,$a,$id,'create',$hash,['id'=>$id,'revision'=>1,'state'=>'active'],$now);
            $this->watch(fn()=>$this->taskFrame($i,$kind,$source),$sourceFrame);$this->proofs[]=function()use($c,$writer){$this->validateOrigin($c);$writer->validateRecurringTaskInOwnedTransaction($c['issuer_id'],$c['actor_id'],self::payload($c,$c['calendar']['first_date']));};$this->watchSchedule($i,$id,$kind);return $id;
        });
    }
    private static function view(array $s):array
    {
        $r=$s['row'];$c=$s['contract'];return ['id'=>(int)$r['id'],'issuer_id'=>(int)$r['issuer_id'],'actor_id'=>(int)$r['actor_id'],'kind'=>$r['kind'],'source_id'=>(int)$r['source_id'],'state'=>$r['state'],'revision'=>(int)$r['revision'],'completed_cycles'=>(int)$r['completed_cycles'],'next_date'=>$r['next_date'],'calendar'=>$c['calendar'],'template'=>$c['template'],'api_origin'=>$c['api_origin'],'runs'=>array_map(static fn($run)=>['cycle'=>(int)$run['cycle']+1,'date'=>$run['run_date'],'task_id'=>(int)$run['task_id']],$s['runs'])];
    }
    public function get($issuer,$actor,string $kind,$id):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$kind=self::kind($kind);
        return $this->tx($i,$a,$kind,'view',function()use($i,$kind,$id){$s=$this->schedule($i,$id,$kind);$this->watch(fn()=>$this->schedule($i,$id,$kind),$s);return self::view($s);});
    }
    public function page($issuer,$actor,string $kind,$after=0,?int $source=null):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$kind=self::kind($kind);$after=in_array($after,[0,'0'],true)?0:IssuerPolicy::id($after);if($source!==null)$source=IssuerPolicy::id($source);
        return $this->tx($i,$a,$kind,'view',function()use($i,$kind,$after,$source){
            if($source!==null)$this->taskFrame($i,$kind,$source);$args=[$i,$kind,$after];if($source!==null)$args[]=$source;
            $ids=$this->rows('SELECT id FROM '.$this->p.'company_task_schedules WHERE issuer_id=? AND kind=? AND id>?'.($source!==null?' AND source_id=?':'').' ORDER BY id LIMIT 51 FOR UPDATE',$args);$items=[];
            foreach(array_slice($ids,0,50)as$r){$id=(int)$r['id'];$s=$this->schedule($i,$id,$kind);$items[]=self::view($s);$this->watch(fn()=>$this->schedule($i,$id,$kind),$s);}return ['items'=>$items,'next'=>count($ids)>50?(int)$ids[49]['id']:null];
        });
    }
    public function transition($issuer,$actor,string $kind,$id,$revision,string $action,string $key):int
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=IssuerPolicy::id($revision);$kind=self::kind($kind);$key=self::key($key);if(!in_array($action,['pause','resume','cancel'],true))throw new DomainException('company_task_recurring_invalid');$hash=self::hash([$kind,$id,$revision,$action]);
        return $this->tx($i,$a,$kind,'edit',function()use($i,$a,$kind,$id,$revision,$action,$key,$hash):int{
            $s=$this->schedule($i,$id,$kind);$r=$s['row'];$c=$s['contract'];
            if($action==='resume'){
                if($c['actor_id']!==$a)throw new DomainException('company_task_recurring_access_denied');$this->validateOrigin($c);$frame=$this->source($i,$c);$this->watch(fn()=>$this->source($i,$c),$frame);
                if($r['next_date']!==null){$writer=$this->writer($kind);$input=self::payload($c,$r['next_date']);$last=self::payload($c,CompanyTaskRecurringDates::occurrence($c['calendar'],$c['calendar']['cycles']-1));$writer->validateRecurringTaskInOwnedTransaction($i,$a,$input);$writer->validateRecurringTaskInOwnedTransaction($i,$a,$last);$this->proofs[]=function()use($writer,$i,$a,$input,$last){$writer->validateRecurringTaskInOwnedTransaction($i,$a,$input);$writer->validateRecurringTaskInOwnedTransaction($i,$a,$last);};}$this->proofs[]=fn()=>$this->validateOrigin($c);
            }
            if($this->receipt($key,$i,$a,$action,$hash)!==null){$this->watchSchedule($i,$id,$kind);return $id;}
            if((int)$r['revision']!==$revision||$revision>=2147483647)throw new DomainException('company_task_recurring_stale');
            if((int)($this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_task_schedule_requests WHERE issuer_id=? AND schedule_id=?',[$i,$id])[0]['n']??0)>=1000)throw new DomainException('company_task_recurring_limit');
            $state=match($action){'pause'=>$r['state']==='active'?'paused':null,'resume'=>$r['state']==='paused'?'active':null,'cancel'=>in_array($r['state'],['active','paused'],true)?'cancelled':null};if($state===null)throw new DomainException('company_task_recurring_state');
            $r['state']=$state;$r['revision']=(string)($revision+1);$r['updated_at']=gmdate('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_task_schedules SET state=?,revision=?,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$r['state'],$r['revision'],$r['updated_at'],$i,$id,$revision]);$this->exact('company_task_schedules','issuer_id=? AND id=?',[$i,$id],$r);
            $this->saveReceipt($key,$i,$a,$id,$action,$hash,['id'=>$id,'revision'=>$revision+1,'state'=>$state],$r['updated_at']);$this->watchSchedule($i,$id,$kind);return $id;
        });
    }
    public static function runKey(int $issuer,int $id,int $cycle):string{return hash('sha256','company-task-schedule-v1|'.$issuer.'|'.$id.'|'.$cycle);}
    /** Lock acts as a claim until COMMIT. A killed process releases it and rolls back every effect. */
    public function run($issuer,$actor,$id,?string $utc=null,?int $expectedCycle=null):?int
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$utc=CompanyTaskRecurringDates::now($utc);
        $hint=$this->rows('SELECT kind,completed_cycles FROM '.$this->p.'company_task_schedules WHERE issuer_id=? AND id=?',[$i,$id])[0]??throw new DomainException('company_task_recurring_access_denied');$kind=self::kind($hint['kind']);$cycle=$expectedCycle??(int)$hint['completed_cycles'];if($cycle<0||$cycle>120)throw new DomainException('company_task_recurring_invalid');
        return $this->tx($i,$a,$kind,'create',function()use($i,$a,$kind,$id,$utc,$cycle):?int{
            $s=$this->schedule($i,$id,$kind);$r=$s['row'];$c=$s['contract'];if($c['actor_id']!==$a)throw new DomainException('company_task_recurring_access_denied');$this->validateOrigin($c);$this->proofs[]=fn()=>$this->validateOrigin($c);
            $source=$this->source($i,$c);$this->watch(fn()=>$this->source($i,$c),$source);
            if($cycle<(int)$r['completed_cycles']){$this->watchSchedule($i,$id,$kind);return (int)$s['runs'][$cycle]['task_id'];}
            if($cycle!==(int)$r['completed_cycles'])throw new DomainException('company_task_recurring_stale');
            if($r['state']!=='active'||$r['next_date']>CompanyTaskRecurringDates::localDate($c['calendar'],$utc)){$this->watchSchedule($i,$id,$kind);return null;}
            if($c['calendar']['require_previous_done']===1){$previous=$cycle===0?$source:$this->taskFrame($i,$kind,(int)$s['runs'][$cycle-1]['task_id']);
                if($previous['native']['status']!=='done'){$this->watchSchedule($i,$id,$kind);return null;}foreach(json_decode($previous['native']['checklist_json'],true,32,JSON_THROW_ON_ERROR)as$check)if(($check['done']??null)!==1)throw new DomainException('company_project_checklist_open');foreach($previous['timers']as$t)if($t['stopped_at']===null)throw new DomainException('company_project_timer_running');
                if($cycle>0)$this->watch(fn()=>$this->taskFrame($i,$kind,(int)$s['runs'][$cycle-1]['task_id']),$previous);
            }
            $input=self::payload($c,$r['next_date']);$writer=$this->writer($kind);$preferences=CompanyTaskOperationPreferences::readInOwnedTransaction($this->db,$this->p,$i);
            $child=$writer->createRecurringTaskInOwnedTransaction($i,$a,$input);$frame=$this->taskFrame($i,$kind,$child);
            if($frame['comments']!==[]||$frame['timers']!==[]||$frame['files']!==[]||$frame['dependencies']!==[])throw new RuntimeException('issuer_task_recurring_unconfirmed');
            $birth=['native'=>$frame['native'],'priority'=>$frame['priority'],'followers'=>$frame['followers'],'audit_markers'=>$frame['audit_markers']];
            $revision=(int)$r['revision'];if($revision>=2147483647)throw new DomainException('company_task_recurring_stale');
            $run=['issuer_id'=>$i,'schedule_id'=>$id,'cycle'=>$cycle,'revision'=>$revision+1,'kind'=>$kind,'run_date'=>$r['next_date'],'task_id'=>$child,'request_key'=>self::runKey($i,$id,$cycle),'contract_hash'=>$r['contract_hash'],'birth_hash'=>self::hash($birth),'birth_json'=>self::json($birth),'created_at'=>gmdate('Y-m-d H:i:s')];
            $this->insert('company_task_schedule_runs',$run);$this->exact('company_task_schedule_runs','issuer_id=? AND schedule_id=? AND cycle=?',[$i,$id,$cycle],$run);$this->audit($i,$a,$id,'company_task_schedule_run',['row_hash'=>self::hash(self::strings($run))]);
            $completed=$cycle+1;$r['completed_cycles']=(string)$completed;$r['revision']=(string)($revision+1);$r['state']=$completed===$c['calendar']['cycles']?'completed':'active';$r['next_date']=$r['state']==='completed'?null:CompanyTaskRecurringDates::occurrence($c['calendar'],$completed);$r['updated_at']=$run['created_at'];
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_task_schedules SET state=?,revision=?,completed_cycles=?,next_date=?,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$r['state'],$r['revision'],$r['completed_cycles'],$r['next_date'],$r['updated_at'],$i,$id,$revision]);$this->exact('company_task_schedules','issuer_id=? AND id=?',[$i,$id],$r);
            $this->watch(fn()=>$this->taskFrame($i,$kind,$child),$frame);$this->proofs[]=function()use($writer,$i,$a,$input,$preferences){$writer->validateRecurringTaskInOwnedTransaction($i,$a,$input);CompanyTaskOperationPreferences::confirmInOwnedTransaction($this->db,$this->p,$i,$preferences);};$this->watchSchedule($i,$id,$kind);return $child;
        });
    }
    /** Durable bounded discovery advances past blocked origins, never granting their authority. */
    public function processPending(?string $utc=null,int $limit=25):array
    {
        $utc=CompanyTaskRecurringDates::now($utc);if($limit<1||$limit>25)throw new DomainException('company_task_recurring_invalid');CompanyTaskRecurringSchema::assertValid($this->db,$this->p);
        $refs=IssuerTransaction::run($this->db,$this->p,['issuers_lock','company_task_schedules','company_task_schedule_cursor'],function()use($limit){
            $rows=$this->rows('SELECT * FROM '.$this->p.'company_task_schedule_cursor FOR UPDATE');if(count($rows)!==1||(int)$rows[0]['id']!==1||(int)$rows[0]['last_id']>(int)$rows[0]['upper_id']||(int)$rows[0]['revision']<1||(int)$rows[0]['revision']>=2147483647)throw new RuntimeException('issuer_task_recurring_unconfirmed');$c=$rows[0];$last=(int)$c['last_id'];$upper=(int)$c['upper_id'];
            if($last===$upper){$last=0;$upper=(int)($this->rows('SELECT MAX(id) max_id FROM '.$this->p.'company_task_schedules')[0]['max_id']??0);}
            $refs=$this->rows('SELECT id,issuer_id,actor_id,completed_cycles FROM '.$this->p.'company_task_schedules WHERE id>? AND id<=? AND state=? ORDER BY id LIMIT '.$limit.' FOR UPDATE',[$last,$upper,'active']);$newLast=count($refs)<$limit?$upper:(int)$refs[count($refs)-1]['id'];
            $want=['id'=>'1','last_id'=>(string)$newLast,'upper_id'=>(string)$upper,'revision'=>(string)((int)$c['revision']+1)];IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_task_schedule_cursor SET last_id=?,upper_id=?,revision=? WHERE id=1 AND revision=?',[$want['last_id'],$want['upper_id'],$want['revision'],$c['revision']]);if(array_map([self::class,'strings'],$this->rows('SELECT * FROM '.$this->p.'company_task_schedule_cursor FOR UPDATE'))!==[$want])throw new RuntimeException('issuer_task_recurring_unconfirmed');return $refs;
        });
        $out=['examined'=>count($refs),'completed'=>0,'waiting'=>0,'blocked'=>0];foreach($refs as$r)try{$result=$this->run((int)$r['issuer_id'],(int)$r['actor_id'],(int)$r['id'],$utc,(int)$r['completed_cycles']);if($result===null)$out['waiting']++;else $out['completed']++;}catch(Throwable $e){$out['blocked']++;}return $out;
    }
}
