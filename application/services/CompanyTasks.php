<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectsStorage.php';
require_once __DIR__.'/CompanyTaskBillingSourceLocks.php';
require_once __DIR__.'/CompanyTaskCreationDefaults.php';
final class CompanyTasks extends CompanyProjectsStorage
{
    use CompanyTaskCreationDefaults;
    use CompanyTaskBillingSourceLocks;
    protected string $feature='tasks';
    private static function checklist($items):string
    {
        if(!is_array($items)||count($items)>30||!array_is_list($items))throw new DomainException('company_project_invalid_input');$result=[];foreach($items as $item){if(!is_array($item)||array_diff(array_keys($item),['text','done'])||!in_array($item['done']??null,[0,1,'0','1'],true))throw new DomainException('company_project_invalid_input');$result[]=['text'=>self::text($item['text']??null,191),'done'=>(int)$item['done']];}return json_encode($result,JSON_THROW_ON_ERROR);
    }
    private static function input(array $input):array
    {
        if(array_diff(array_keys($input),['project_id','assignee_id','name','description','due_date','status','checklist']))throw new DomainException('company_project_invalid_input');$data=['project_id'=>IssuerPolicy::id($input['project_id']??null),'assignee_id'=>IssuerPolicy::id($input['assignee_id']??null),'name'=>self::text($input['name']??null,191),'description'=>self::text($input['description']??'',4000,false),'due_date'=>self::date($input['due_date']??null),'status'=>$input['status']??'todo','checklist_json'=>self::checklist($input['checklist']??[])];if(!in_array($data['status'],['todo','in_progress','done'],true))throw new DomainException('company_project_invalid_input');return $data;
    }
    private function parent(int $issuer,int $project,bool $active=false):array
    {
        $row=$this->rows('SELECT id,status,start_date,due_date FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$row)throw new DomainException('company_project_access_denied');if($active&&$row['status']!=='active')throw new DomainException('company_project_closed');return $row;
    }
    private function references(int $issuer,array $data):void
    {
        $project=$this->parent($issuer,$data['project_id'],true);if($data['due_date']<$project['start_date']||$data['due_date']>$project['due_date'])throw new DomainException('company_project_invalid_input');$this->responsible($issuer,$data['assignee_id']);if($data['status']==='done')foreach(json_decode($data['checklist_json'],true,32,JSON_THROW_ON_ERROR) as $check)if($check['done']!==1)throw new DomainException('company_project_checklist_open');
    }
    private function row(int $issuer,int $id):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_tasks WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$row)throw new DomainException('company_project_access_denied');$this->parent($issuer,(int)$row['project_id']);return $row;
    }
    private function insertCreatedInOwned(int $issuer,int $actor,array $data):int
    {
        $this->references($issuer,$data);$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_tasks(issuer_id,project_id,assignee_id,name,description,due_date,status,checklist_json,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,?)',[$issuer,$data['project_id'],$data['assignee_id'],$data['name'],$data['description'],$data['due_date'],$data['status'],$data['checklist_json'],$now,$now]);$id=(int)$this->db->insert_id();$row=$this->row($issuer,$id);foreach($data as $f=>$v)if((string)$row[$f]!== (string)$v)throw new RuntimeException('issuer_project_write_unconfirmed');return $id;
    }
    public function create($issuer,$actor,array $input,string $key):int
    {
        return $this->createUsingOwnDefaults($issuer,$actor,$input,$key,'task_created');
    }
    /** Canonical copy compositor; cannot join an arbitrary caller transaction. */
    public function createCopyInOwnedTransaction($issuer,$actor,array $input):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);if($data['status']!=='todo')throw new DomainException('company_project_invalid_input');
        CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyStandaloneTasksSchema::assertValid($this->db,$this->p);
        IssuerTransaction::assertOwned($this->db,$this->p,['company_project_copy_requests','company_project_copy_snapshots']);
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),$this->apiTables()));
        return ($this->guardedWork(function()use($issuer,$actor,$data):int{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'create');$id=$this->insertCreatedInOwned($issuer,$actor,$data);$this->audit($issuer,$actor,$id,'company_task_created');$this->authorize($issuer,$actor,'create');return $id;}))();
    }
    /** Canonical copy compositor; cannot join an arbitrary caller transaction. */
    public function createReviewedTaskCopyInOwnedTransaction($issuer,$actor,array $input):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);if($data['status']!=='todo')throw new DomainException('company_project_invalid_input');
        CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyStandaloneTasksSchema::assertValid($this->db,$this->p);
        IssuerTransaction::assertOwned($this->db,$this->p,['company_task_copy_requests']);
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),$this->apiTables()));
        return ($this->guardedWork(function()use($issuer,$actor,$data):int{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'create');$id=$this->insertCreatedInOwned($issuer,$actor,$data);$this->audit($issuer,$actor,$id,'company_task_created');$this->authorize($issuer,$actor,'create');return $id;}))();
    }
    public function update($issuer,$actor,$id,$revision,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$data=self::input($input);return $this->operation($issuer,$actor,'edit',$key,'task_updated',[$id,$revision,$data],function()use($issuer,$actor,$id,$revision,$data):int{$old=$this->row($issuer,$id);$this->assertTaskUnclaimed($issuer,$actor,$id);if((int)$old['revision']!==$revision)throw new DomainException('company_project_stale');if((int)$old['project_id']!==$data['project_id'])throw new DomainException('company_project_reference_denied');$this->references($issuer,$data);require_once __DIR__.'/CompanyProjectExtras.php';if($data['status']==='done')CompanyProjectExtras::assertTaskReady($this->db,$this->p,$issuer,$id);elseif($old['status']==='done')CompanyProjectExtras::assertTaskReopenAllowed($this->db,$this->p,$issuer,$id);if($data['status']==='done'&&$this->rows('SELECT id FROM '.$this->p.'company_task_timers WHERE issuer_id=? AND task_id=? AND stopped_at IS NULL FOR UPDATE',[$issuer,$id]))throw new DomainException('company_project_timer_running');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tasks SET assignee_id=?,name=?,description=?,due_date=?,status=?,checklist_json=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$data['assignee_id'],$data['name'],$data['description'],$data['due_date'],$data['status'],$data['checklist_json'],date('Y-m-d H:i:s'),$issuer,$id,$revision]);$row=$this->row($issuer,$id);$this->assertTaskUnclaimed($issuer,$actor,$id);if((int)$row['revision']!==$revision+1)throw new RuntimeException('issuer_project_write_unconfirmed');foreach($data as $f=>$v)if((string)$row[$f]!== (string)$v)throw new RuntimeException('issuer_project_write_unconfirmed');return $id;});
    }
    public function activity($issuer,$actor,$id,$revision,string $action,string $key,string $body=''):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);if(!in_array($action,['comment','start','stop'],true))throw new DomainException('company_project_invalid_input');$body=self::text($body,4000,$action==='comment');
        return $this->operation($issuer,$actor,'edit',$key,'task_'.$action,[$id,$revision,$action,$body],function()use($issuer,$actor,$id,$revision,$action,$body):int{
            $row=$this->row($issuer,$id);$this->assertTaskUnclaimed($issuer,$actor,$id);if((int)$row['revision']!==$revision)throw new DomainException('company_project_stale');$this->parent($issuer,(int)$row['project_id'],true);if($row['status']==='done')throw new DomainException('company_project_closed');$now=date('Y-m-d H:i:s');
            if($action==='comment'){if(count($this->rows('SELECT id FROM '.$this->p.'company_task_comments WHERE issuer_id=? AND task_id=? LIMIT 501',[$issuer,$id]))>=500)throw new DomainException('company_project_limit');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_task_comments(issuer_id,task_id,actor_id,body,created_at) VALUES(?,?,?,?,?)',[$issuer,$id,$actor,$body,$now]);$comment=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'company_task_comments WHERE id=? AND issuer_id=? AND task_id=? AND actor_id=? AND body=?',[$comment,$issuer,$id,$actor,$body]))throw new RuntimeException('issuer_project_write_unconfirmed');}
            elseif($action==='start'){
                if($this->rows('SELECT id FROM '.$this->p.'company_task_timers WHERE actor_id=? AND stopped_at IS NULL FOR UPDATE',[$actor])||$this->rows('SELECT id FROM '.$this->p.'company_standalone_timers WHERE actor_id=? AND stopped_at IS NULL FOR UPDATE',[$actor]))throw new DomainException('company_project_timer_running');if(count($this->rows('SELECT id FROM '.$this->p.'company_task_timers WHERE issuer_id=? AND task_id=? LIMIT 501',[$issuer,$id]))>=500)throw new DomainException('company_project_limit');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_task_timers(issuer_id,task_id,actor_id,started_at,stopped_at,seconds) VALUES(?,?,?,?,NULL,0)',[$issuer,$id,$actor,$now]);$timer=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'company_task_timers WHERE id=? AND issuer_id=? AND task_id=? AND actor_id=? AND stopped_at IS NULL',[$timer,$issuer,$id,$actor]))throw new RuntimeException('issuer_project_write_unconfirmed');
            }else{
                $timer=$this->rows('SELECT id,started_at FROM '.$this->p.'company_task_timers WHERE issuer_id=? AND task_id=? AND actor_id=? AND stopped_at IS NULL FOR UPDATE',[$issuer,$id,$actor])[0]??null;if(!$timer)throw new DomainException('company_project_timer_missing');$seconds=time()-strtotime($timer['started_at']);if($seconds<0||$seconds>2147483646)throw new DomainException('company_project_invalid_input');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_task_timers SET stopped_at=?,seconds=? WHERE id=? AND issuer_id=? AND task_id=? AND actor_id=? AND stopped_at IS NULL',[$now,$seconds,$timer['id'],$issuer,$id,$actor]);if(!$this->rows('SELECT id FROM '.$this->p.'company_task_timers WHERE id=? AND issuer_id=? AND task_id=? AND stopped_at=? AND seconds=?',[$timer['id'],$issuer,$id,$now,$seconds]))throw new RuntimeException('issuer_project_write_unconfirmed');
            }
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tasks SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$now,$issuer,$id,$revision]);if((int)$this->row($issuer,$id)['revision']!==$revision+1)throw new RuntimeException('issuer_project_write_unconfirmed');return $id;
        });
    }
    public function get($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);return $this->transaction(function()use($issuer,$actor,$id):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);$row['billing_state']=$this->db->table_exists($this->p.'company_task_billing_links')?($this->rows('SELECT state FROM '.$this->p.'company_task_billing_links WHERE issuer_id=? AND task_id=?',[$issuer,$id])[0]['state']??'none'):'none';$fixedState=CompanyProjectFixedBillingFence::stateInOwned($this->db,$this->p,$issuer,$actor,(int)$row['project_id']);if(in_array($fixedState,['converting','converted'],true))$row['billing_state']=$fixedState;$timeState=CompanyTaskTimeBillingFence::stateInOwned($this->db,$this->p,$issuer,$id);$row['time_billing_state']=$timeState;if($timeState!==null)$row['billing_state']=$timeState;$row['comments']=$this->rows('SELECT id,actor_id,body,created_at FROM '.$this->p.'company_task_comments WHERE issuer_id=? AND task_id=? ORDER BY id LIMIT 500',[$issuer,$id]);$row['timers']=$this->rows('SELECT actor_id,started_at,stopped_at,seconds FROM '.$this->p.'company_task_timers WHERE issuer_id=? AND task_id=? ORDER BY id LIMIT 500',[$issuer,$id]);$row['checklist']=json_decode($row['checklist_json'],true,32,JSON_THROW_ON_ERROR);$this->authorize($issuer,$actor,'view');return $row;});
    }
    public function page($issuer,$actor,$project,$after=0):array
    {
        $read=function()use($issuer,$actor,$project,$after){
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$after=$after===0?0:IssuerPolicy::id($after);$this->authorize($issuer,$actor,'view');$this->parent($issuer,$project);$rows=$this->rows('SELECT id,name,status,due_date,assignee_id,revision FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND id>? ORDER BY id LIMIT 51',[$issuer,$project,$after]);$this->authorize($issuer,$actor,'view');return ['items'=>array_slice($rows,0,50),'next'=>count($rows)>50?(int)$rows[49]['id']:null];

        };return $this->originGuard===null?$read():$this->transaction($read);
    }
}
