<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTasksRead.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';

/** Read-only primary lineage in the same task table; original legacy actions keep their IDs. */
final class NativeCompanyLegacyTaskProjection
{
    public function __construct(private $db,private string $p){}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function read(int $issuer,int $actor,array $reference):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if($acl->primaryId()!==$issuer||!$acl->can($issuer,$actor,'tasks','view')||$reference['source_kind']!=='task')throw new DomainException('native_resource_reference_denied');
        $id=IssuerPolicy::id($reference['source_id']);$canonical=(string)$id;
        $resolve=fn()=>NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'task',$canonical,'view');$resolved=$resolve();
        $rows=$this->rows('SELECT * FROM '.$this->p.'tasks WHERE id=?',[$id]);if(count($rows)!==1)throw new DomainException('native_task_index_changed');$source=$rows[0];
        foreach(['name','description','status','startdate','duedate','priority','billable','billed','addedfrom','recurring']as$field)if(!array_key_exists($field,$source))throw new DomainException('native_task_legacy_projection_invalid');
        $workflow=(int)$source['status'];if(!in_array($workflow,[1,2,3,4,5],true)||!in_array((string)$source['billable'],['0','1'],true)||!in_array((string)$source['billed'],['0','1'],true))throw new DomainException('native_task_legacy_projection_invalid');
        $members=$this->rows('SELECT s.staffid,s.firstname,s.lastname FROM '.$this->p.'task_assigned a JOIN '.$this->p.'staff s ON s.staffid=a.staffid WHERE a.taskid=? ORDER BY s.staffid',[$id]);
        $followers=$this->rows('SELECT staffid FROM '.$this->p.'task_followers WHERE taskid=? ORDER BY staffid',[$id]);
        $tags=(new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'task',$canonical);
        $row=$source+['due_date'=>$source['duedate'],'revision'=>null,'native_fields_revision'=>null,'billing_state'=>(int)$source['billed']===1?'converted':'none'];
        $fields=['start_date'=>$source['startdate'],'billable'=>(int)$source['billable'],'workflow_status'=>$workflow];
        if($resolve()!==$resolved||$this->rows('SELECT * FROM '.$this->p.'tasks WHERE id=?',[$id])!==$rows||$this->rows('SELECT s.staffid,s.firstname,s.lastname FROM '.$this->p.'task_assigned a JOIN '.$this->p.'staff s ON s.staffid=a.staffid WHERE a.taskid=? ORDER BY s.staffid',[$id])!==$members||$this->rows('SELECT staffid FROM '.$this->p.'task_followers WHERE taskid=? ORDER BY staffid',[$id])!==$followers||(new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'task',$canonical)!==$tags||$acl->primaryId()!==$issuer)throw new DomainException('native_task_index_changed');
        return ['id'=>$canonical,'source_kind'=>'task','source_id'=>$id,'reference'=>$resolved,'row'=>$row,'fields'=>$fields,'assignees'=>$members,'assignment_revision'=>null,'followers'=>array_map('intval',array_column($followers,'staffid')),'tags'=>$tags['tags'],'tag_revision'=>$tags['revision'],'schedules'=>[],'legacy_recurring'=>(int)$source['recurring']>0];
    }
}
