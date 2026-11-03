<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTasksRead.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/NativeCompanyTaskFormValues.php';
require_once __DIR__.'/CompanyTasks.php';
require_once __DIR__.'/CompanyStandaloneTasks.php';

/** Original billable-task selection. This is not a quote, reservation or authorization to invoice. */
final class NativeCompanyBillableTaskRead
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authority(int$i,int$a):void{$acl=new IssuerResourceAccess($this->db,$this->p);foreach(['tasks','customers','invoices']as$f)if(!$acl->can($i,$a,$f,'view'))throw new DomainException('native_billable_task_denied');}
    /** Same selector distinction as Tasks_model: without a project, project tasks are excluded. */
    public function forCustomer($issuer,$actor,$customer,$project=null):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$client=IssuerPolicy::id($customer);$this->authority($i,$a);
        $resolve=fn($type,$id)=>NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,$type,$id,'view');$customerRef=$resolve('client',$client);$projectRef=$project===null||$project===''?null:$resolve('project',$project);
        if($projectRef!==null){$table=$projectRef['source_kind']==='company_project'?'company_projects':'projects';$key=$table==='company_projects'?'client_id':'clientid';$parent=$this->rows('SELECT '.$key.' AS client FROM '.$this->p.$table.' WHERE id=?',[$projectRef['source_id']]);if(count($parent)!==1||(int)$parent[0]['client']!==$client)throw new DomainException('native_billable_task_customer_mismatch');}
        $items=[];$undeclared=[];$blocked=[];$snapshots=[];$readRows=[];$sets=[];$taskRead=new NativeCompanyTasksRead($this->db,$this->p);
        $kind=$projectRef===null?'company_standalone_task':'company_task';$table=$kind==='company_task'?'company_tasks':'company_standalone_tasks';$writer=$kind==='company_task'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);
        if($projectRef===null||$projectRef['source_kind']==='company_project'){
            $sql='SELECT id FROM '.$this->p.$table.' WHERE issuer_id=?'.($projectRef===null?'':' AND project_id=?').' ORDER BY id LIMIT 501';$args=$projectRef===null?[$i]:[$i,$projectRef['source_id']];$ids=$this->rows($sql,$args);if(count($ids)>500)throw new DomainException('native_billable_task_limit');$sets[]=[$sql,$args,$ids];
            foreach($ids as$id){$canonical=NativeCompanyResourceId::encode($kind,$id['id']);$reference=$taskRead->relationOption($i,$a,$canonical);$row=$writer->get($i,$a,(int)$id['id']);$readRows[]=[$writer,(int)$id['id'],$row,$reference];$fields=$row['native_fields_json']??null;
                if($fields===null){if($projectRef!==null)$undeclared[]=['id'=>$canonical,'reason'=>'native_task_billable_undeclared'];continue;}
                $fields=NativeCompanyTaskFormValues::normalize(json_decode($fields,true,32,JSON_THROW_ON_ERROR)+['workflow_status'=>match($row['status']){'todo'=>1,'awaiting_feedback'=>2,'testing'=>3,'in_progress'=>4,'done'=>5,default=>throw new DomainException('native_billable_task_snapshot_changed')}]);if(NativeCompanyTaskFormValues::lifecycle($fields['workflow_status'])!==$row['status'])throw new DomainException('native_billable_task_snapshot_changed');
                if($fields['relation_type']===null){if($projectRef!==null)throw new DomainException('native_billable_task_snapshot_changed');continue;}
                $relation=$resolve($fields['relation_type'],NativeCompanyResourceId::encode($fields['relation_source_kind'],$fields['relation_source_id']));
                if($projectRef!==null){if($fields['relation_type']!=='project'||$relation!==$projectRef)throw new DomainException('native_billable_task_snapshot_changed');}
                elseif($fields['relation_type']==='project'||$this->relationCustomer($relation,$fields['relation_type'])!==$client)continue;
                $timerSql='SELECT id,stopped_at FROM '.$this->p.($kind==='company_task'?'company_task_timers':'company_standalone_timers').' WHERE issuer_id=? AND task_id=? ORDER BY id LIMIT 501';$timerArgs=[$i,$id['id']];$timerRows=$this->rows($timerSql,$timerArgs);if(count($timerRows)>500)throw new DomainException('native_billable_task_limit');$sets[]=[$timerSql,$timerArgs,$timerRows];$snapshots[]=[$writer,(int)$id['id'],$row,$reference,$fields['relation_type'],$relation];
                if($fields['billable']!==1)continue;
                if(!in_array($row['billing_state'],['none','ready','disabled'],true)){$blocked[]=['id'=>$canonical,'reason'=>'billing_'.$row['billing_state']];continue;}
                $items[]=['id'=>$canonical,'name'=>$row['name'],'rel_type'=>$fields['relation_type'],'rel_id'=>$relation['canonical_id'],'rel_name'=>$relation['name'],'billable'=>1,'billed'=>0,'hourly_rate'=>$fields['hourly_rate'],'started_timers'=>(bool)array_filter($timerRows,static fn($t)=>$t['stopped_at']===null),'source_kind'=>$kind,'source_id'=>(int)$id['id'],'source_revision'=>(int)$row['revision'],'basis'=>'declared_native_task_selection'];
            }
        }
        $primary=(new IssuerResourceAccess($this->db,$this->p))->primaryId();$legacy=[];
        if($primary===$i&&($projectRef===null||$projectRef['source_kind']==='project')){
            $sql='SELECT * FROM '.$this->p.'tasks WHERE billable=1 AND billed=0'.($projectRef===null?" AND rel_type<>'project'":" AND rel_type='project' AND rel_id=?").' ORDER BY id LIMIT 501';$args=$projectRef===null?[]:[$projectRef['source_id']];$rows=$this->rows($sql,$args);if(count($rows)>500)throw new DomainException('native_billable_task_limit');$sets[]=[$sql,$args,$rows];
            foreach($rows as$row){try{$reference=$taskRead->relationOption($i,$a,(string)$row['id']);$relation=$resolve($row['rel_type'],(string)$row['rel_id']);}catch(DomainException$e){if(!in_array($e->getMessage(),['native_resource_reference_denied','native_company_task_denied'],true))throw$e;continue;}if($this->relationCustomer($relation,$row['rel_type'])!==$client)continue;
                $timers=$this->rows('SELECT id,end_time FROM '.$this->p.'taskstimers WHERE task_id=? ORDER BY id',[$row['id']]);$legacy[]=[$row,$reference,$relation,$timers];$items[]=['id'=>(string)$row['id'],'name'=>$row['name'],'rel_type'=>$row['rel_type'],'rel_id'=>(string)$row['rel_id'],'rel_name'=>$relation['name'],'billable'=>1,'billed'=>0,'hourly_rate'=>(string)$row['hourly_rate'],'started_timers'=>(bool)array_filter($timers,static fn($t)=>$t['end_time']===null),'source_kind'=>'task','source_id'=>(int)$row['id'],'source_revision'=>hash('sha256',json_encode($row,JSON_THROW_ON_ERROR)),'basis'=>'native_legacy_recorded_selection'];
            }
        }
        foreach($readRows as[$writer,$id,$row,$reference])if($writer->get($i,$a,$id)!==$row||$taskRead->relationOption($i,$a,$reference['id'])!==$reference)throw new DomainException('native_billable_task_snapshot_changed');
        foreach($snapshots as[$writer,$id,$row,$reference,$type,$relation])if($writer->get($i,$a,$id)!==$row||$taskRead->relationOption($i,$a,$reference['id'])!==$reference||$resolve($type,$relation['canonical_id'])!==$relation||$this->relationCustomer($relation,$type)!==$client)throw new DomainException('native_billable_task_snapshot_changed');
        foreach($legacy as[$row,$reference,$relation,$timers])if($taskRead->relationOption($i,$a,(string)$row['id'])!==$reference||$resolve($row['rel_type'],$relation['canonical_id'])!==$relation||$this->relationCustomer($relation,$row['rel_type'])!==$client||$this->rows('SELECT id,end_time FROM '.$this->p.'taskstimers WHERE task_id=? ORDER BY id',[$row['id']])!==$timers)throw new DomainException('native_billable_task_snapshot_changed');
        foreach($sets as[$sql,$args,$rows])if($this->rows($sql,$args)!==$rows)throw new DomainException('native_billable_task_snapshot_changed');
        if($resolve('client',$client)!==$customerRef||($projectRef!==null&&$resolve('project',$projectRef['canonical_id'])!==$projectRef)||(new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$primary)throw new DomainException('native_billable_task_snapshot_changed');$this->authority($i,$a);return['items'=>$items,'undeclared'=>$undeclared,'blocked'=>$blocked,'basis'=>'selection_only_not_billing_authority'];
    }
    /** Selected task must still be present in the original authorized candidate set. */
    public function forTask($issuer,$actor,$task):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$this->authority($i,$a);$reader=new NativeCompanyTasksRead($this->db,$this->p);$ref=$reader->relationOption($i,$a,$task);
        if($ref['source_kind']==='task'){$rows=$this->rows('SELECT rel_type,rel_id FROM '.$this->p.'tasks WHERE id=?',[$ref['source_id']]);if(count($rows)!==1)throw new DomainException('native_billable_task_denied');$type=$rows[0]['rel_type'];$relationId=(string)$rows[0]['rel_id'];}
        else{
            $writer=$ref['source_kind']==='company_task'?new CompanyTasks($this->db,$this->p):new CompanyStandaloneTasks($this->db,$this->p);$row=$writer->get($i,$a,$ref['source_id']);if(($row['native_fields_json']??null)===null)throw new DomainException('native_billable_task_undeclared');
            $fields=NativeCompanyTaskFormValues::normalize(json_decode($row['native_fields_json'],true,32,JSON_THROW_ON_ERROR)+['workflow_status'=>match($row['status']){'todo'=>1,'awaiting_feedback'=>2,'testing'=>3,'in_progress'=>4,'done'=>5,default=>throw new DomainException('native_billable_task_snapshot_changed')}]);
            $type=$fields['relation_type'];if($type===null)throw new DomainException('native_billable_task_customer_mismatch');$relationId=NativeCompanyResourceId::encode($fields['relation_source_kind'],$fields['relation_source_id']);
        }
        $relation=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,$type,$relationId,'view');$client=$this->relationCustomer($relation,$type);if($client===null)throw new DomainException('native_billable_task_customer_mismatch');$result=$this->forCustomer($i,$a,$client,$type==='project'?$relation['canonical_id']:null);
        foreach($result['items']as$item)if($item['id']===$ref['id']){if($reader->relationOption($i,$a,$task)!==$ref||NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,$type,$relationId,'view')!==$relation)throw new DomainException('native_billable_task_snapshot_changed');return$item;}
        throw new DomainException('native_billable_task_unavailable');
    }
    /** Original project selector resolves the customer from the authorized project, never from browser claims. */
    public function forProject($issuer,$actor,$project):array
    {
        $i=IssuerPolicy::id($issuer);$a=IssuerPolicy::id($actor);$this->authority($i,$a);
        $reference=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$project,'view');$client=$this->relationCustomer($reference,'project');
        if($client===null)throw new DomainException('native_billable_task_customer_mismatch');$result=$this->forCustomer($i,$a,$client,$reference['canonical_id']);
        if(NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$project,'view')!==$reference||$this->relationCustomer($reference,'project')!==$client)throw new DomainException('native_billable_task_snapshot_changed');return$result;
    }
    private function relationCustomer(array$r,string$type):?int
    {
        if($type==='customer'){if($r['source_kind']!=='client')throw new DomainException('native_billable_task_relation_unsupported');return(int)$r['source_id'];}
        $map=['project'=>['projects','clientid'],'invoice'=>['invoices','clientid'],'estimate'=>['estimates','clientid'],'contract'=>['contracts','client'],'ticket'=>['tickets','userid'],'expense'=>['expenses','clientid'],'proposal'=>['proposals','rel_id']];
        $owned=['company_project'=>['company_projects','client_id'],'company_contract'=>['company_contracts','client_id'],'company_ticket'=>['company_tickets','client_id'],'company_expense'=>['company_expenses','client_id'],'company_estimate'=>['company_sales_documents','client_id'],'company_proposal'=>['company_sales_documents','client_id']];
        $kind=$r['source_kind'];if(isset($owned[$kind])){if($kind!=='company_'.$type)throw new DomainException('native_billable_task_relation_unsupported');$source=$owned[$kind];}else{if($kind!==$type)throw new DomainException('native_billable_task_relation_unsupported');$source=$map[$type]??null;}if($source===null)return null;[$table,$field]=$source;$key=$table==='tickets'?'ticketid':'id';$rows=$this->rows('SELECT '.$field.' AS client'.($table==='proposals'?',rel_type':'').' FROM '.$this->p.$table.' WHERE '.$key.'=?',[$r['source_id']]);if(count($rows)!==1)throw new DomainException('native_billable_task_snapshot_changed');if($table==='proposals'&&$rows[0]['rel_type']!=='customer')return null;return$rows[0]['client']===null?null:(int)$rows[0]['client'];
    }
}
