<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectsStorage.php';require_once __DIR__.'/CompanyStandaloneTasksSchema.php';require_once __DIR__.'/CompanyStandaloneBillingFence.php';
require_once __DIR__.'/CompanyTaskCreationDefaults.php';
require_once __DIR__.'/CompanyTaskPriorityOperations.php';
final class CompanyStandaloneTasks extends CompanyProjectsStorage
{
    use CompanyTaskCreationDefaults;
    use CompanyTaskPriorityOperations;
    protected function taskKind():string{return 'standalone';}
    protected string $feature='tasks';
    protected function apiTables():array{return array_values(array_unique(array_merge(parent::apiTables(),CompanyStandaloneBillingFence::tables($this->db,$this->p),CompanyTaskAutoStop::tables($this->db,$this->p))));}
    private function assertBillingMutable(int $issuer,int $actor,int $task):void{CompanyStandaloneBillingFence::assertAvailableInOwned($this->db,$this->p,$issuer,$actor,$task);}
    protected function receiptResourceTable(string $operation):string{return 'company_standalone_tasks';}
    protected function authorize(int $issuer,int $actor,string $cap):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'tasks','view')||!$acl->can($issuer,$actor,'tasks',$cap))throw new DomainException('company_project_access_denied');
    }
    protected function responsible(int $issuer,int $staff):void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$staff,'tasks','view'))throw new DomainException('company_project_reference_denied');}
    protected function transaction(callable $work)
    {
        CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyStandaloneTasksSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),CompanyTaskPrioritySchema::tables($this->db,$this->p),$this->apiTables()),$this->guardedWork($work));
    }
    public function staffOptions($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');$rows=$this->rows('SELECT s.staffid,s.firstname,s.lastname FROM '.$this->p.'staff s WHERE s.active=1 AND (EXISTS (SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.issuer_id=? AND m.staff_id=s.staffid AND m.can_view=1) OR EXISTS (SELECT 1 FROM '.$this->p.'issuers_installation_identity i WHERE i.id=1 AND i.master_staff_id=s.staffid AND s.admin=1)) ORDER BY s.staffid LIMIT 501',[$issuer]);if(count($rows)>500)throw new DomainException('company_project_limit');$rows=array_values(array_filter($rows,fn($r)=>(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$r['staffid'],'tasks','view')));$this->authorize($issuer,$actor,'view');return $rows;
    }
    private static function checklist($items):string
    {
        if(!is_array($items)||count($items)>30||!array_is_list($items))throw new DomainException('company_project_invalid_input');$result=[];foreach($items as $item){if(!is_array($item)||array_diff(array_keys($item),['text','done'])||!in_array($item['done']??null,[0,1,'0','1'],true))throw new DomainException('company_project_invalid_input');$result[]=['text'=>self::text($item['text']??null,191),'done'=>(int)$item['done']];}return json_encode($result,JSON_THROW_ON_ERROR);
    }
    private static function input(array $input):array
    {
        if(array_diff(array_keys($input),['assignee_id','name','description','due_date','status','checklist','priority']))throw new DomainException('company_project_invalid_input');$data=['assignee_id'=>IssuerPolicy::id($input['assignee_id']??null),'name'=>self::text($input['name']??null,191),'description'=>self::text($input['description']??'',4000,false),'due_date'=>self::date($input['due_date']??null),'status'=>$input['status']??'todo','checklist_json'=>self::checklist($input['checklist']??[])];if(!in_array($data['status'],['todo','in_progress','done'],true))throw new DomainException('company_project_invalid_input');if(array_key_exists('priority',$input))$data['priority']=CompanyTaskPriority::value($input['priority']);return $data;
    }
    private function references(int $issuer,array $data):void
    {
        $this->responsible($issuer,$data['assignee_id']);if($data['status']==='done')foreach(json_decode($data['checklist_json'],true,32,JSON_THROW_ON_ERROR) as $check)if($check['done']!==1)throw new DomainException('company_project_checklist_open');
    }
    private function row(int $issuer,int $id):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_standalone_tasks WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$row)throw new DomainException('company_project_access_denied');$row['priority']=CompanyTaskPriority::read($this->db,$this->p,'standalone',$issuer,$id);return $row;
    }
    private function insertCreatedInOwned(int $issuer,int $actor,array $data):int
    {
        $this->references($issuer,$data);$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_standalone_tasks(issuer_id,assignee_id,name,description,due_date,status,checklist_json,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,1,?,?)',[$issuer,$data['assignee_id'],$data['name'],$data['description'],$data['due_date'],$data['status'],$data['checklist_json'],$now,$now]);$id=(int)$this->db->insert_id();$this->createPriorityInOwned($issuer,$actor,$id,$data,$now);$row=$this->row($issuer,$id);foreach($data as $f=>$v)if((string)$row[$f]!== (string)$v)throw new RuntimeException('issuer_project_write_unconfirmed');return $id;
    }
    public function create($issuer,$actor,array $input,string $key):int
    {
        return $this->createUsingOwnDefaults($issuer,$actor,$input,$key,'standalone_task_created');
    }
    /** Canonical copy compositor; cannot join an arbitrary caller transaction. */
    public function createReviewedTaskCopyInOwnedTransaction($issuer,$actor,array $input):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);if($data['status']!=='todo')throw new DomainException('company_project_invalid_input');
        CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyStandaloneTasksSchema::assertValid($this->db,$this->p);
        IssuerTransaction::assertOwned($this->db,$this->p,['company_task_copy_requests']);
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),$this->apiTables()));
        return ($this->guardedWork(function()use($issuer,$actor,$data):int{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'create');$id=$this->insertCreatedInOwned($issuer,$actor,$data);$this->audit($issuer,$actor,$id,'company_standalone_task_created');$this->authorize($issuer,$actor,'create');return $id;}))();
    }
    public function update($issuer,$actor,$id,$revision,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$data=self::input($input);return $this->operation($issuer,$actor,'edit',$key,'standalone_task_updated',[$id,$revision,$data],function()use($issuer,$actor,$id,$revision,$data):int{$old=$this->row($issuer,$id);$this->assertBillingMutable($issuer,$actor,$id);if((int)$old['revision']!==$revision)throw new DomainException('company_project_stale');$this->references($issuer,$data);if($data['status']==='done'&&$this->rows('SELECT id FROM '.$this->p.'company_standalone_timers WHERE issuer_id=? AND task_id=? AND stopped_at IS NULL FOR UPDATE',[$issuer,$id]))throw new DomainException('company_project_timer_running');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_standalone_tasks SET assignee_id=?,name=?,description=?,due_date=?,status=?,checklist_json=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$data['assignee_id'],$data['name'],$data['description'],$data['due_date'],$data['status'],$data['checklist_json'],date('Y-m-d H:i:s'),$issuer,$id,$revision]);if(array_key_exists('priority',$data))CompanyTaskPriority::save($this->db,$this->p,'standalone',$issuer,$id,$data['priority'],false);$row=$this->row($issuer,$id);$this->assertBillingMutable($issuer,$actor,$id);if((int)$row['revision']!==$revision+1)throw new RuntimeException('issuer_project_write_unconfirmed');foreach($data as $f=>$v)if((string)$row[$f]!== (string)$v)throw new RuntimeException('issuer_project_write_unconfirmed');return $id;});
    }
    public function activity($issuer,$actor,$id,$revision,string $action,string $key,string $body=''):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);if(!in_array($action,['comment','start','stop'],true))throw new DomainException('company_project_invalid_input');$body=self::text($body,4000,$action==='comment');
        return $this->operation($issuer,$actor,'edit',$key,'standalone_task_'.$action,[$id,$revision,$action,$body],function()use($issuer,$actor,$id,$revision,$action,$body):int{
            $row=$this->row($issuer,$id);$this->assertBillingMutable($issuer,$actor,$id);if((int)$row['revision']!==$revision)throw new DomainException('company_project_stale');if($row['status']==='done')throw new DomainException('company_project_closed');$now=date('Y-m-d H:i:s');
            if($action==='comment'){if(count($this->rows('SELECT id FROM '.$this->p.'company_standalone_comments WHERE issuer_id=? AND task_id=? LIMIT 501',[$issuer,$id]))>=500)throw new DomainException('company_project_limit');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_standalone_comments(issuer_id,task_id,actor_id,body,created_at) VALUES(?,?,?,?,?)',[$issuer,$id,$actor,$body,$now]);$comment=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'company_standalone_comments WHERE id=? AND issuer_id=? AND task_id=? AND actor_id=? AND body=?',[$comment,$issuer,$id,$actor,$body]))throw new RuntimeException('issuer_project_write_unconfirmed');}
            elseif($action==='start'){
                $timerPolicy=CompanyTaskTimerPreferences::readInOwnedTransaction($this->db,$this->p,$issuer);
                if(count($this->rows('SELECT id FROM '.$this->p.'company_standalone_timers WHERE issuer_id=? AND task_id=? LIMIT 501',[$issuer,$id]))>=500)throw new DomainException('company_project_limit');$timerBefore=$this->timerStartBefore($issuer,$actor,$id,$now);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_standalone_timers(issuer_id,task_id,actor_id,started_at,stopped_at,seconds) VALUES(?,?,?,?,NULL,0)',[$issuer,$id,$actor,$now]);$timer=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'company_standalone_timers WHERE id=? AND issuer_id=? AND task_id=? AND actor_id=? AND stopped_at IS NULL',[$timer,$issuer,$id,$actor]))throw new RuntimeException('issuer_project_write_unconfirmed');
            }else{
                $timer=$this->rows('SELECT id,started_at FROM '.$this->p.'company_standalone_timers WHERE issuer_id=? AND task_id=? AND actor_id=? AND stopped_at IS NULL FOR UPDATE',[$issuer,$id,$actor])[0]??null;if(!$timer)throw new DomainException('company_project_timer_missing');$seconds=time()-strtotime($timer['started_at']);if($seconds<0||$seconds>2147483646)throw new DomainException('company_project_invalid_input');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_standalone_timers SET stopped_at=?,seconds=? WHERE id=? AND issuer_id=? AND task_id=? AND actor_id=? AND stopped_at IS NULL',[$now,$seconds,$timer['id'],$issuer,$id,$actor]);if(!$this->rows('SELECT id FROM '.$this->p.'company_standalone_timers WHERE id=? AND issuer_id=? AND task_id=? AND stopped_at=? AND seconds=?',[$timer['id'],$issuer,$id,$now,$seconds]))throw new RuntimeException('issuer_project_write_unconfirmed');
            }
            $timerStatus=$action==='start'&&$timerPolicy[CompanyTaskTimerPreferences::TRANSITION]==='1'&&$row['status']==='todo'?'in_progress':$row['status'];IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_standalone_tasks SET status=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$timerStatus,$now,$issuer,$id,$revision]);if((int)$this->row($issuer,$id)['revision']!==$revision+1)throw new RuntimeException('issuer_project_write_unconfirmed');if($action==='start')$this->rememberTimerStart($issuer,$actor,$id,$row,$timerStatus,$now,$timer,$timerPolicy,$timerBefore);return $id;
        });
    }
    public function get($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);return $this->transaction(function()use($issuer,$actor,$id):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);$row['billing_state']=CompanyStandaloneBillingFence::stateInOwned($this->db,$this->p,$issuer,$actor,$id)??'none';$row['comments']=$this->rows('SELECT actor_id,body,created_at FROM '.$this->p.'company_standalone_comments WHERE issuer_id=? AND task_id=? ORDER BY id LIMIT 500',[$issuer,$id]);$row['timers']=$this->rows('SELECT actor_id,started_at,stopped_at,seconds FROM '.$this->p.'company_standalone_timers WHERE issuer_id=? AND task_id=? ORDER BY id LIMIT 500',[$issuer,$id]);$row['checklist']=json_decode($row['checklist_json'],true,32,JSON_THROW_ON_ERROR);$this->authorize($issuer,$actor,'view');return $row;});
    }
    public function page($issuer,$actor,$after=0):array
    {
        $read=function()use($issuer,$actor,$after){
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$after=$after===0?0:IssuerPolicy::id($after);$this->authorize($issuer,$actor,'view');$rows=$this->rows('SELECT id,name,status,due_date,assignee_id,revision FROM '.$this->p.'company_standalone_tasks WHERE issuer_id=? AND id>? ORDER BY id LIMIT 51',[$issuer,$after]);foreach($rows as &$item)$item['priority']=CompanyTaskPriority::read($this->db,$this->p,'standalone',$issuer,(int)$item['id']);unset($item);$this->authorize($issuer,$actor,'view');return ['items'=>array_slice($rows,0,50),'next'=>count($rows)>50?(int)$rows[49]['id']:null];

        };return $this->transaction($read);
    }
}
