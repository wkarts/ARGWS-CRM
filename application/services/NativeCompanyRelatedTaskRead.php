<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskRelationAuthority.php';
require_once __DIR__.'/CompanyStandaloneTasks.php';
require_once __DIR__.'/NativeCompanyWorkAssignments.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/CompanyTaskRecurring.php';
/** Source-aware input to the original related-tasks table; no models, provider calls or writes. */
final class NativeCompanyRelatedTaskRead
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function access(int$i,int$a):void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'tasks','view'))throw new DomainException('native_company_task_denied');}
    public function read(int$i,int$a,string$type,$canonical):array
    {
        $this->access($i,$a);if($type==='project')return$this->project($i,$a,$canonical);if(!in_array($type,['ticket','invoice'],true))throw new DomainException('native_task_relation_writer_required');
        $parent=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,$type,$canonical,'view');
        if(!in_array($parent['source_kind'],$type==='ticket'?['company_ticket']:['invoice','company_invoice_draft'],true))throw new DomainException('native_task_legacy_relation_projection_required');
        $pdo=$this->db->conn_id??$this->db->pdo??null;$driver=$pdo instanceof PDO?$pdo->getAttribute(PDO::ATTR_DRIVER_NAME):(string)($this->db->dbdriver??'');
        if(!in_array($driver,['mysql','mysqli','sqlite'],true))throw new DomainException('native_task_relation_storage_required');
        $extract=static fn($key)=>($driver==='sqlite'?'json_extract(native_fields_json,\'$.'.$key.'\')':'JSON_UNQUOTE(JSON_EXTRACT(native_fields_json,\'$.'.$key.'\'))');
        $sql='SELECT id,native_fields_json,native_fields_revision FROM '.$this->p.'company_standalone_tasks WHERE issuer_id=? AND native_fields_json IS NOT NULL AND '.$extract('relation_type').'=? AND '.$extract('relation_source_kind').'=? AND '.'CAST('.$extract('relation_source_id').' AS CHAR)'.'=? ORDER BY id LIMIT 10001';
        $args=[$i,$type,$parent['source_kind'],(string)$parent['source_id']];$selected=$this->rows($sql,$args);if(count($selected)>10000)throw new DomainException('native_task_relation_limit');$items=[];$proofs=[];$writer=new CompanyStandaloneTasks($this->db,$this->p);
        foreach($selected as$selection){
            $id=IssuerPolicy::id($selection['id']);$taskId=NativeCompanyResourceId::encode('company_standalone_task',$id);$reference=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'task',$taskId,'view');$row=$writer->get($i,$a,$id);
            if($row['native_fields_json']!==$selection['native_fields_json']||(string)$row['native_fields_revision']!==(string)$selection['native_fields_revision'])throw new DomainException('native_task_relation_stale');
            $fields=NativeCompanyTaskFormValues::normalize(json_decode($row['native_fields_json'],true,32,JSON_THROW_ON_ERROR)+['workflow_status'=>match($row['status']){'todo'=>1,'awaiting_feedback'=>2,'testing'=>3,'in_progress'=>4,'done'=>5,default=>throw new DomainException('native_task_form_invalid')}]);
            $members=(new NativeCompanyWorkAssignments($this->db,$this->p))->read($i,$a,'standalone',$id);$tags=(new NativeCompanyResourceTags($this->db,$this->p))->read($i,$a,'task',$taskId);
            $followers=$this->rows('SELECT staff_id FROM '.$this->p.'company_standalone_followers WHERE issuer_id=? AND task_id=? ORDER BY staff_id',[$i,$id]);
            $schedules=$this->rows('SELECT * FROM '.$this->p.'company_task_schedules WHERE issuer_id=? AND kind=? AND source_id=? ORDER BY id',[$i,'standalone',$id]);foreach($schedules as$schedule)(new CompanyTaskRecurring($this->db,$this->p))->get($i,$a,'standalone',(int)$schedule['id']);
            $tableRow=$row;unset($tableRow['comments'],$tableRow['timers']);
            $items[]=['id'=>$taskId,'source_kind'=>'company_standalone_task','source_id'=>$id,'reference'=>$reference,'row'=>$tableRow,'fields'=>$fields,'assignees'=>$members['members'],'followers'=>array_map('intval',array_column($followers,'staff_id')),'tags'=>$tags['tags'],'schedules'=>$schedules];
            $proofs[]=[$id,$reference,$row,$members,$tags,$followers,$schedules];
        }
        foreach($proofs as[$id,$reference,$row,$members,$tags,$followers,$schedules]){
            $taskId=NativeCompanyResourceId::encode('company_standalone_task',$id);
            if($writer->get($i,$a,$id)!==$row||NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'task',$taskId,'view')!==$reference||(new NativeCompanyWorkAssignments($this->db,$this->p))->read($i,$a,'standalone',$id)!==$members||(new NativeCompanyResourceTags($this->db,$this->p))->read($i,$a,'task',$taskId)!==$tags||$this->rows('SELECT staff_id FROM '.$this->p.'company_standalone_followers WHERE issuer_id=? AND task_id=? ORDER BY staff_id',[$i,$id])!==$followers||$this->rows('SELECT * FROM '.$this->p.'company_task_schedules WHERE issuer_id=? AND kind=? AND source_id=? ORDER BY id',[$i,'standalone',$id])!==$schedules)throw new DomainException('native_task_relation_stale');
        }
        if($this->rows($sql,$args)!==$selected||NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,$type,$canonical,'view')!==$parent)throw new DomainException('native_task_relation_stale');$this->access($i,$a);
        return['parent'=>$parent,'items'=>$items];
    }
    private function project(int$i,int$a,$canonical):array
    {
        $parent=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$canonical,'view');
        // Do not omit historical tasks of a legacy project or infer ownership.
        if($parent['source_kind']!=='company_project')throw new DomainException('native_task_legacy_relation_projection_required');
        require_once __DIR__.'/NativeCompanyTaskIndexProjection.php';$projection=new NativeCompanyTaskIndexProjection($this->db,$this->p);
        $pdo=$this->db->conn_id??$this->db->pdo??null;$driver=$pdo instanceof PDO?$pdo->getAttribute(PDO::ATTR_DRIVER_NAME):(string)($this->db->dbdriver??'');if(!in_array($driver,['mysql','mysqli','sqlite'],true))throw new DomainException('native_task_relation_storage_required');
        $extract=static fn($key)=>($driver==='sqlite'?'json_extract(native_fields_json,\'$.'.$key.'\')':'JSON_UNQUOTE(JSON_EXTRACT(native_fields_json,\'$.'.$key.'\'))');
        $invalid='SELECT id FROM '.$this->p.'company_standalone_tasks WHERE issuer_id=? AND native_fields_json IS NOT NULL AND '.$extract('relation_type').'=? AND '.$extract('relation_source_kind').'=? AND CAST('.$extract('relation_source_id').' AS CHAR)=? LIMIT 1';$invalidArgs=[$i,'project','company_project',(string)$parent['source_id']];
        if($this->rows($invalid,$invalidArgs))throw new DomainException('native_task_relation_writer_required');
        $query='SELECT id FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? ORDER BY id LIMIT 10001';$args=[$i,$parent['source_id']];$selected=$this->rows($query,$args);if(count($selected)>10000)throw new DomainException('native_task_relation_limit');$items=[];
        foreach($selected as$row){$id=NativeCompanyResourceId::encode('company_task',$row['id']);$item=$projection->readOne($i,$a,$id);if((int)$item['row']['project_id']!==(int)$parent['source_id'])throw new DomainException('native_task_relation_stale');$items[]=$item;}
        if($this->rows($invalid,$invalidArgs)||$this->rows($query,$args)!==$selected||NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$canonical,'view')!==$parent)throw new DomainException('native_task_relation_stale');foreach($items as$item)if($projection->readOne($i,$a,$item['id'])!==$item)throw new DomainException('native_task_relation_stale');$this->access($i,$a);return['parent'=>$parent,'items'=>$items];
    }
}
