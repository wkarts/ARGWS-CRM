<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTaskCopiesSchema.php';
require_once __DIR__.'/CompanyTasks.php';
require_once __DIR__.'/CompanyProjects.php';
require_once __DIR__.'/CompanyStandaloneTasks.php';

/** Reviewed new task only: source remains private and no operational authority is inherited. */
final class CompanyTaskCopies
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private static function hash(array $v):string{return hash('sha256',self::json($v));}
    private static function key($v):string{if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new DomainException('company_task_copy_invalid');return $v;}
    private static function kind($v):string{if(!in_array($v,['project','standalone'],true))throw new DomainException('company_task_copy_invalid');return $v;}
    private static function text($v):string{if(!is_string($v)||strlen($v)>191||trim($v)===''||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new DomainException('company_task_copy_invalid');return trim($v);}
    private static function date($v):string{if(!is_string($v)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$v)||!checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4)))throw new DomainException('company_task_copy_invalid');return $v;}
    public function tables():array
    {
        $t=array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),array_keys(CompanyTaskCopiesSchema::definitions($this->p)),CompanyTaskTimeBillingFence::tables($this->db,$this->p),CompanyProjectFixedBillingFence::tables($this->db,$this->p),CompanyStandaloneBillingFence::tables($this->db,$this->p));
        if($this->db->table_exists($this->p.'company_task_billing_links')){CompanyTaskBillingSchema::assertValid($this->db,$this->p);$t=array_merge($t,['company_task_billing_links','company_task_billing_origins']);}
        return array_values(array_unique($t));
    }
    private function tx(callable $work){CompanyTaskCopiesSchema::assertValid($this->db,$this->p);CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyStandaloneTasksSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
    private function authorize(int $issuer,int $actor,string $kind):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
        foreach([['tasks','view'],['tasks','create']]as[$f,$c])if(!$acl->can($issuer,$actor,$f,$c))throw new DomainException('company_task_copy_denied');
        if($kind==='project'&&!$acl->can($issuer,$actor,'projects','view'))throw new DomainException('company_task_copy_denied');
    }
    private function source(int $issuer,string $kind,int $id):array
    {
        $table=$kind==='project'?'company_tasks':'company_standalone_tasks';$row=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;
        if(!$row||!in_array($row['status'],['todo','in_progress','done'],true))throw new DomainException('company_task_copy_denied');
        $project=null;if($kind==='project'){$project=$this->rows('SELECT * FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$row['project_id']])[0]??null;if(!$project)throw new DomainException('company_task_copy_denied');}
        $checklist=json_decode($row['checklist_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($checklist)||!array_is_list($checklist)||count($checklist)>30)throw new DomainException('company_task_copy_invalid');
        return ['kind'=>$kind,'task'=>$row,'project'=>$project];
    }
    public function preview($issuer,$actor,string $kind,$source):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$kind=self::kind($kind);$source=IssuerPolicy::id($source);
        return $this->tx(function()use($issuer,$actor,$kind,$source){$this->authorize($issuer,$actor,$kind);$snapshot=$this->source($issuer,$kind,$source);$writer=$kind==='project'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);$staff=$writer->staffOptions($issuer,$actor);$projects=$kind==='project'?(new CompanyProjects($this->db,$this->p))->page($issuer,$actor)['items']:[];$this->authorize($issuer,$actor,$kind);return ['source'=>$snapshot,'revision'=>self::hash($snapshot),'staff'=>$staff,'projects'=>$projects];});
    }
    private static function review(array $r,string $kind):array
    {
        if(array_diff(array_keys($r),['source_revision','name','due_date','assignee_id','target_project_id'])||count($r)!==5)throw new DomainException('company_task_copy_invalid');
        $target=$kind==='project'?IssuerPolicy::id($r['target_project_id']):0;if($kind==='standalone'&&!in_array($r['target_project_id'],[0,'0'],true))throw new DomainException('company_task_copy_invalid');
        return ['source_revision'=>self::key($r['source_revision']),'name'=>self::text($r['name']),'due_date'=>self::date($r['due_date']),'assignee_id'=>IssuerPolicy::id($r['assignee_id']),'target_project_id'=>$target];
    }
    private function confirmed(array $r,int $issuer,int $actor):array
    {
        if((int)$r['issuer_id']!==$issuer||(int)$r['actor_id']!==$actor)throw new DomainException('company_task_copy_denied');$kind=self::kind($r['kind']);$this->authorize($issuer,$actor,$kind);
        foreach(['source_json'=>'source_hash','review_json'=>'input_hash','result_json'=>'result_hash']as$json=>$hash)if(!hash_equals($r[$hash],hash('sha256',$r[$json])))throw new RuntimeException('issuer_task_copy_unconfirmed');
        $source=json_decode($r['source_json'],true,32,JSON_THROW_ON_ERROR);$review=self::review(json_decode($r['review_json'],true,32,JSON_THROW_ON_ERROR),$kind);$result=json_decode($r['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(($source['kind']??null)!==$kind||(int)($source['task']['id']??0)!==(int)$r['source_id']||$review['source_revision']!==$r['source_hash']||($result['kind']??null)!==$kind||($result['request_key']??null)!==$r['request_key']||($result['task_id']??null)!==(int)$r['result_id'])throw new RuntimeException('issuer_task_copy_unconfirmed');
        if(($result['project_id']??null)!==$review['target_project_id'])throw new RuntimeException('issuer_task_copy_unconfirmed');
        $this->source($issuer,$kind,(int)$r['source_id']);$target=$this->source($issuer,$kind,(int)$r['result_id']);if($kind==='project'&&(int)$target['task']['project_id']!==$review['target_project_id'])throw new RuntimeException('issuer_task_copy_unconfirmed');return $result;
    }
    public function create($issuer,$actor,string $kind,$source,array $review,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$kind=self::kind($kind);$source=IssuerPolicy::id($source);$review=self::review($review,$kind);$key=self::key($key);$inputHash=self::hash($review);
        return $this->tx(function()use($issuer,$actor,$kind,$source,$review,$key,$inputHash){
            $this->authorize($issuer,$actor,$kind);$this->source($issuer,$kind,$source);
            $old=$this->rows('SELECT * FROM '.$this->p.'company_task_copy_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if($old['kind']!==$kind||(int)$old['source_id']!==$source||!hash_equals($old['input_hash'],$inputHash))throw new DomainException('company_task_copy_conflict');return $this->confirmed($old,$issuer,$actor);}
            $snapshot=$this->source($issuer,$kind,$source);if(!hash_equals(self::hash($snapshot),$review['source_revision']))throw new DomainException('company_task_copy_stale');
            $task=$snapshot['task'];$checks=array_map(static fn($v)=>['text'=>$v['text'],'done'=>0],json_decode($task['checklist_json'],true,32,JSON_THROW_ON_ERROR));
            $input=['assignee_id'=>$review['assignee_id'],'name'=>$review['name'],'description'=>$task['description'],'due_date'=>$review['due_date'],'status'=>'todo','checklist'=>$checks];
            $writer=$kind==='project'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);if($kind==='project')$input=['project_id'=>$review['target_project_id']]+$input;
            $id=$writer->createReviewedTaskCopyInOwnedTransaction($issuer,$actor,$input);
            if($this->source($issuer,$kind,$source)!==$snapshot)throw new DomainException('company_task_copy_stale');
            $result=['kind'=>$kind,'task_id'=>$id,'project_id'=>$review['target_project_id'],'request_key'=>$key];
            $row=['request_key'=>$key,'issuer_id'=>$issuer,'actor_id'=>$actor,'kind'=>$kind,'source_id'=>$source,'result_id'=>$id,'source_hash'=>$review['source_revision'],'input_hash'=>$inputHash,'result_hash'=>self::hash($result),'source_json'=>self::json($snapshot),'review_json'=>self::json($review),'result_json'=>self::json($result),'created_at'=>gmdate('Y-m-d H:i:s')];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_task_copy_requests (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,'company_task_copied',$issuer,$id,gmdate('Y-m-d H:i:s'),self::json(['kind'=>$kind,'request_key'=>$key,'source'=>$source,'source_hash'=>$review['source_revision']])]);
            $audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND event=? AND issuer_id=? AND subject_id=?',[$audit,$actor,'company_task_copied',$issuer,$id]))throw new RuntimeException('issuer_task_copy_unconfirmed');
            $stored=$this->rows('SELECT * FROM '.$this->p.'company_task_copy_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if(!$stored)throw new RuntimeException('issuer_task_copy_unconfirmed');foreach($row as$field=>$value)if((string)($stored[$field]??'')!==(string)$value)throw new RuntimeException('issuer_task_copy_unconfirmed');
            $this->authorize($issuer,$actor,$kind);return $this->confirmed($stored,$issuer,$actor);
        });
    }
    public function receipt($issuer,$actor,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$key=self::key($key);
        return $this->tx(function()use($issuer,$actor,$key){$r=$this->rows('SELECT * FROM '.$this->p.'company_task_copy_requests WHERE issuer_id=? AND actor_id=? AND request_key=? FOR UPDATE',[$issuer,$actor,$key])[0]??throw new DomainException('company_task_copy_denied');return $this->confirmed($r,$issuer,$actor);});
    }
}
