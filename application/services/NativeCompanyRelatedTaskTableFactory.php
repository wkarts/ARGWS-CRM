<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyRelatedTaskTable.php';require_once __DIR__.'/NativeCompanyTasksRead.php';
require_once __DIR__.'/NativeCompanyWorkAssignments.php';require_once __DIR__.'/WorkStatusPresentation.php';require_once __DIR__.'/TaskPriorityPresentation.php';
/** Original task filter options are authorized task facts, not an employee eligibility list. */
final class NativeCompanyRelatedTaskTableFactory
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$s,array$a=[]):array{return IssuerStorage::query($this->db,$s,$a)->result_array();}
    public function make(int$i,int$a,string$identifier='related_tasks',array$savedFilters=[]):NativeCompanyRelatedTaskTable
    {
        if($identifier!=='related_tasks')throw new DomainException('native_task_filter_invalid');$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tasks','view'))throw new DomainException('native_company_task_denied');$primary=$acl->primaryId();$members=[];$sets=[];$proofs=[];
        foreach(['company_task'=>['company_tasks','company_task_assignees','task_id'],'company_standalone_task'=>['company_standalone_tasks','company_standalone_assignees','task_id'],'task'=>['tasks','task_assigned','taskid']]as$kind=>[$table,$links,$key]){
            if($kind==='task'&&$primary!==$i)continue;$sql='SELECT id FROM '.$this->p.$table.($kind==='task'?'':' WHERE issuer_id=?').' ORDER BY id LIMIT 10001';$args=$kind==='task'?[]:[$i];$ids=$this->rows($sql,$args);if(count($ids)>10000)throw new DomainException('native_task_relation_limit');$sets[]=[$sql,$args,$ids];
            foreach($ids as$id){$canonical=NativeCompanyResourceId::encode($kind,$id['id']);try{$ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'task',$canonical,'view');}catch(DomainException$e){if(!in_array($e->getMessage(),['native_resource_reference_denied','native_company_task_denied','native_ticket_denied','native_ticket_department_denied','native_ticket_preferences_denied'],true))throw$e;continue;}
                $query=$kind==='task'?'SELECT staffid AS staff_id FROM '.$this->p.$links.' WHERE '.$key.'=? ORDER BY staffid':'SELECT staff_id FROM '.$this->p.$links.' WHERE issuer_id=? AND '.$key.'=? AND active=1 ORDER BY staff_id';$params=$kind==='task'?[$id['id']]:[$i,$id['id']];$assigned=$this->rows($query,$params);
                $names=[];foreach($assigned as$member){$staff=IssuerPolicy::id($member['staff_id']);if(!$acl->member($i,$staff))continue;$name=$this->rows('SELECT staffid,firstname,lastname FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$staff]);if(count($name)!==1)throw new DomainException('native_task_relation_stale');$names[$staff]=$name;$members[$staff]=['value'=>(string)$staff,'label'=>trim($name[0]['firstname'].' '.$name[0]['lastname'])];}$proofs[]=[$canonical,$ref,$query,$params,$assigned,$names];
            }
        }
        foreach($proofs as[$canonical,$ref,$q,$args,$assigned,$names]){if(NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'task',$canonical,'view')!==$ref||$this->rows($q,$args)!==$assigned)throw new DomainException('native_task_relation_stale');foreach($names as$staff=>$name)if(!$acl->member($i,$staff)||$this->rows('SELECT staffid,firstname,lastname FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$staff])!==$name)throw new DomainException('native_task_relation_stale');}
        foreach($sets as[$q,$args,$ids])if($this->rows($q,$args)!==$ids)throw new DomainException('native_task_relation_stale');if($primary!==$acl->primaryId()||!$acl->can($i,$a,'tasks','view'))throw new DomainException('native_company_task_denied');ksort($members,SORT_NUMERIC);
        $options=['status'=>array_map(static fn($s)=>['value'=>(string)$s['id'],'label'=>$s['name']],WorkStatusPresentation::tasks('_l')),'priority'=>array_map(static fn($s)=>['value'=>(string)$s['id'],'label'=>$s['name']],TaskPriorityPresentation::definitions('_l')),'assigned'=>array_values($members)];return new NativeCompanyRelatedTaskTable($this->db,$this->p,$i,$a,$options,$savedFilters);
    }
}
