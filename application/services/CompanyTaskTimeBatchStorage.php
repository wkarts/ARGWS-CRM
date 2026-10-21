<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyTaskTimeBillingStorage.php';
require_once __DIR__.'/CompanyTaskTimeBatchFence.php';
require_once __DIR__.'/CompanyStandaloneBillingFence.php';
require_once __DIR__.'/CompanyStandaloneTasksSchema.php';

/** Immutable per-selection evidence; native timer namespaces are never interchangeable. */
abstract class CompanyTaskTimeBatchStorage extends CompanyTaskTimeBillingStorage
{
    public function tables():array
    {
        return array_values(array_unique(array_merge(parent::tables(),array_keys(CompanyTaskTimeBatchSchema::definitions()),array_keys(CompanyStandaloneTasksSchema::definitions($this->prefix)),CompanyStandaloneBillingFence::tables($this->db,$this->prefix))));
    }
    protected function billingAuth(int $issuer,int $actor):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);foreach([['tasks','view'],['tasks','edit'],['customers','view'],['invoices','view'],['invoices','create']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('company_time_batch_denied');
    }
    protected function billingTx(int $issuer,int $actor,callable $work)
    {
        CompanyTaskTimeBatchSchema::assertValid($this->db,$this->prefix);CompanyStandaloneTasksSchema::assertValid($this->db,$this->prefix);CompanyStandaloneBillingSchema::assertValid($this->db,$this->prefix);return parent::billingTx($issuer,$actor,$work);
    }
    protected function privateClient(int $issuer,int $client):void
    {
        if(!$this->rows('SELECT c.userid FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND o.owner_issuer_id=? FOR UPDATE',[$client,$issuer]))throw new DomainException('company_time_batch_reference_denied');
    }
    protected static function selections($rows,string $kind):array
    {
        if(!in_array($kind,['projects','standalone'],true)||!is_array($rows)||!array_is_list($rows)||!$rows||count($rows)>500)throw new DomainException('company_time_batch_invalid');$out=[];
        foreach($rows as $row){if(!is_array($row)||array_diff(array_keys($row),['id','task_revision','project_revision']))throw new DomainException('company_time_batch_invalid');$id=IssuerPolicy::id($row['id']??null);$revision=IssuerPolicy::id($row['task_revision']??null);$project=$kind==='projects'?IssuerPolicy::id($row['project_revision']??null):0;if($kind==='standalone'&&!in_array($row['project_revision']??null,[0,'0'],true))throw new DomainException('company_time_batch_invalid');if(isset($out[$id]))throw new DomainException('company_time_batch_invalid');$out[$id]=['id'=>$id,'task_revision'=>$revision,'project_revision'=>$project];}
        ksort($out,SORT_NUMERIC);return array_values($out);
    }
    protected function selectedBatchSource(int $issuer,int $actor,string $kind,int $client,int $currency,array $selection,?array $captured=null,int $ownOrigin=0,bool $preview=false):array
    {
        $this->privateClient($issuer,$client);$ids=array_column($selection,'id');CompanyTaskTimeBatchFence::assertSelectionInOwned($this->db,$this->prefix,$kind,$issuer,$ids,$ownOrigin);$tasks=[];$projects=[];
        if($kind==='projects'){
            if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'projects','view'))throw new DomainException('company_time_batch_denied');
            $rows=$this->rows('SELECT n.id,n.task_id,t.project_id,t.revision FROM '.$this->prefix.'company_task_timers n JOIN '.$this->prefix.'company_tasks t ON t.id=n.task_id AND t.issuer_id=n.issuer_id WHERE n.issuer_id=? AND n.id IN('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY n.id FOR UPDATE',array_merge([$issuer],$ids));if(array_map(fn($r)=>(int)$r['id'],$rows)!==$ids)throw new DomainException('company_time_batch_denied');$byProject=[];
            foreach($rows as $i=>$row){$project=(int)$row['project_id'];$parent=$this->projectTime($issuer,$project);if($parent['client_id']!==$client)throw new DomainException('company_time_batch_target_mismatch');if($preview&&((int)$row['revision']!==$selection[$i]['task_revision']||$parent['revision']!==$selection[$i]['project_revision']))throw new DomainException('company_time_batch_stale');$byProject[$project][]=(int)$row['id'];}
            ksort($byProject,SORT_NUMERIC);foreach($byProject as $project=>$selected){$prior=null;if($captured!==null){$name=null;foreach($captured['projects'] as $p)if($p['id']===$project)$name=$p['name'];if($name===null)throw new RuntimeException('issuer_time_batch_unconfirmed');$prior=['project_name'=>$name,'tasks'=>$captured['tasks']];}$source=$this->selectedSource($issuer,$actor,$project,$selected,$prior);$projects[]=['id'=>$project,'client_id'=>$client,'name'=>$source['project_name']];foreach($source['tasks'] as $group){$task=$group['task']['id'];$portalNow=$this->portal($issuer,$task);if($portalNow&&$portalNow['billing_state']==='converting'&&$ownOrigin===0)throw new DomainException('company_time_batch_immutable');if($portalNow&&$portalNow['billing_state']==='billed'&&!$this->rows('SELECT timer_id FROM '.$this->prefix.'company_time_billing_claims WHERE issuer_id=? AND task_id=? AND state=? LIMIT 1',[$issuer,$task,'converted'])&&!$this->rows('SELECT timer_id FROM '.$this->prefix.'company_task_time_batch_claims WHERE issuer_id=? AND task_id=? AND state=? LIMIT 1',[$issuer,$task,'converted']))throw new DomainException('company_time_batch_immutable');if($this->rows('SELECT timer_id FROM '.$this->prefix.'company_time_billing_claims WHERE issuer_id=? AND task_id=? AND state=? LIMIT 1 FOR UPDATE',[$issuer,$task,'converting']))throw new DomainException('company_time_batch_immutable');$group['project_name']=$source['project_name'];$tasks[]=$group;}}
            foreach($ids as $timer)if($this->rows('SELECT timer_id FROM '.$this->prefix.'company_time_billing_claims WHERE timer_id=? FOR UPDATE',[$timer]))throw new DomainException('company_time_batch_overlap');
        }else{
            $rows=$this->rows('SELECT n.id,n.task_id,n.actor_id,n.started_at,n.stopped_at,n.seconds FROM '.$this->prefix.'company_standalone_timers n JOIN '.$this->prefix.'company_standalone_tasks t ON t.id=n.task_id AND t.issuer_id=n.issuer_id WHERE n.issuer_id=? AND n.id IN('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY n.id FOR UPDATE',array_merge([$issuer],$ids));if(array_map(fn($r)=>(int)$r['id'],$rows)!==$ids)throw new DomainException('company_time_batch_denied');$groups=[];
            foreach($rows as $i=>$timer){$task=(int)$timer['task_id'];if(!isset($groups[$task])){$row=$this->rows('SELECT * FROM '.$this->prefix.'company_standalone_tasks WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$task])[0]??null;if(!$row||!in_array($row['status'],['todo','in_progress','done'],true))throw new DomainException('company_time_batch_denied');foreach(['id','issuer_id','assignee_id','revision'] as $f)$row[$f]=(int)$row[$f];if($preview&&$row['revision']!==$selection[$i]['task_revision'])throw new DomainException('company_time_batch_stale');if($this->rows('SELECT task_id FROM '.$this->prefix.'company_standalone_billing_links WHERE issuer_id=? AND task_id=? AND state IN (?,?) FOR UPDATE',[$issuer,$task,'converting','converted'])||$this->rows('SELECT request_key FROM '.$this->prefix.'company_invoice_requests WHERE request_key=? FOR UPDATE',[CompanyStandaloneBillingFence::key($issuer,$task)]))throw new DomainException('company_time_batch_overlap');$this->assertBinding($issuer,$task,$client,$currency,$ownOrigin);$groups[$task]=['task'=>$row,'timers'=>[],'portal_origin'=>null,'portal_state_at_prepare'=>null,'project_name'=>'Tarefa avulsa'];}elseif($preview&&$groups[$task]['task']['revision']!==$selection[$i]['task_revision'])throw new DomainException('company_time_batch_stale');unset($timer['task_id']);$groups[$task]['timers'][]=self::timer($timer);}
            ksort($groups,SORT_NUMERIC);$tasks=array_values($groups);
        }
        usort($tasks,fn($a,$b)=>$a['task']['id']<=>$b['task']['id']);$result=['basis'=>'native_whole_timer_multi_source_v1','source_kind'=>$kind,'client_id'=>$client,'currency_id'=>$currency,'projects'=>$projects,'tasks'=>$tasks,'selection'=>$selection,'timer_ids'=>$ids,'timestamp_basis'=>'native_server_local_naive_duration_seconds_authoritative'];if(strlen(self::encode($result))>2097152)throw new DomainException('company_time_batch_limit');return$result;
    }
    protected static function timer(array $row):array
    {
        foreach(['id','actor_id','seconds'] as $field){if(!preg_match('/^(0|[1-9][0-9]{0,9})$/D',(string)($row[$field]??'')))throw new DomainException('company_time_batch_not_ready');$row[$field]=(int)$row[$field];}if($row['id']<1||$row['actor_id']<1||!is_string($row['stopped_at']??null))throw new DomainException('company_time_batch_not_ready');return$row;
    }
    protected function originBatch(int $issuer,int $origin,bool $requireAudit=true):array
    {
        $row=$this->rows('SELECT * FROM '.$this->prefix.'company_task_time_batch_origins WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$origin])[0]??null;if(!$row)throw new DomainException('company_time_batch_denied');if(!in_array($row['source_kind'],['projects','standalone'],true))throw new RuntimeException('issuer_time_batch_unconfirmed');foreach(['request'=>'input_hash','source'=>'source_hash','payload'=>'payload_hash','context'=>'origin_hash'] as $field=>$hash)if(!hash_equals($row[$hash],hash('sha256',$row[$field.'_json']))||self::encode($this->decode($row[$field.'_json']))!==$row[$field.'_json'])throw new RuntimeException('issuer_time_batch_unconfirmed');if($requireAudit){$metadata=$this->originMetadata($row,null);$proof=$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$issuer,(int)$row['actor_id'],$origin,'company_time_batch_prepared',self::encode($metadata)]);if(count($proof)!==1)throw new RuntimeException('issuer_time_batch_unconfirmed');}$source=$this->decode($row['source_json']);$payload=$this->decode($row['payload_json']);if($source['source_kind']!==$row['source_kind']||$source['client_id']!==(int)$row['client_id']||$source['currency_id']!==(int)$row['currency_id']||$payload['client_id']!==$source['client_id']||$payload['currency']!==$source['currency_id'])throw new RuntimeException('issuer_time_batch_unconfirmed');return$row;
    }
    protected function batch(int $issuer,int $origin):array
    {
        $row=$this->rows('SELECT * FROM '.$this->prefix.'company_task_time_batches WHERE issuer_id=? AND origin_id=? FOR UPDATE',[$issuer,$origin])[0]??null;if(!$row||!in_array($row['state'],['ready','converting','converted','disabled'],true))throw new RuntimeException('issuer_time_batch_unconfirmed');return$row;
    }
    protected function binding(int $issuer,int $task):?array
    {
        $row=$this->rows('SELECT * FROM '.$this->prefix.'company_standalone_time_clients WHERE task_id=? FOR UPDATE',[$task])[0]??null;if($row&&((int)$row['issuer_id']!==$issuer||!in_array($row['state'],['converting','converted'],true)))throw new RuntimeException('issuer_time_batch_unconfirmed');return$row;
    }
    protected function assertBinding(int $issuer,int $task,int $client,int $currency,int $ownOrigin=0):?array
    {
        $row=$this->binding($issuer,$task);if(!$row)return null;if((int)$row['client_id']!==$client||(int)$row['currency_id']!==$currency)throw new DomainException('company_time_batch_target_mismatch');$origin=$this->originBatch($issuer,(int)$row['origin_id']);$source=$this->decode($origin['source_json']);if($source['source_kind']!=='standalone'||(int)$origin['client_id']!==$client||(int)$origin['currency_id']!==$currency||!in_array($task,array_column(array_column($source['tasks'],'task'),'id'),true))throw new RuntimeException('issuer_time_batch_unconfirmed');$batch=$this->batch($issuer,(int)$row['origin_id']);if($row['state']==='converting'&&((int)$row['origin_id']!==$ownOrigin||$batch['state']!=='converting'))throw new DomainException('company_time_batch_immutable');if($row['state']==='converted'){
            $receipt=$this->receipt($issuer,(int)$row['origin_id']);$payload=$this->decode($origin['payload_json']);$canonical=array_merge(CompanyInvoiceAmounts::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment']))),['client_id'=>$payload['client_id'],'date'=>$payload['date'],'due_date'=>$payload['due_date'],'currency'=>$payload['currency'],'note'=>$payload['note']]);
            if($batch['state']!=='converted'||!$receipt||(int)$receipt['invoice_id']!==(int)$batch['invoice_id']||(int)$receipt['actor_id']!==(int)$batch['conversion_actor_id']||!hash_equals($receipt['input_hash'],hash('sha256',json_encode($canonical,JSON_THROW_ON_ERROR))))throw new RuntimeException('issuer_time_batch_unconfirmed');
        }return$row;
    }
    protected function claims(int $issuer,int $origin,array $source,bool $reserve=false,string $state='converting'):void
    {
        $kind=$source['source_kind'];$table=$kind==='projects'?'company_task_time_batch_claims':'company_standalone_time_claims';$native=$kind==='projects'?'company_task_timers':'company_standalone_timers';$taskTable=$kind==='projects'?'company_tasks':'company_standalone_tasks';$expected=[];
        foreach($source['tasks'] as $group){$task=$group['task']['id'];$project=$kind==='projects'?$group['task']['project_id']:null;foreach($group['timers'] as $timer){$id=$timer['id'];$where=$kind==='projects'?' AND t.project_id=?':'';$args=[$id,$issuer,$task];if($project!==null)$args[]=$project;$row=$this->rows('SELECT n.id,n.actor_id,n.started_at,n.stopped_at,n.seconds FROM '.$this->prefix.$native.' n JOIN '.$this->prefix.$taskTable.' t ON t.id=n.task_id AND t.issuer_id=n.issuer_id WHERE n.id=? AND n.issuer_id=? AND n.task_id=?'.$where.' FOR UPDATE',$args)[0]??null;if(!$row||self::timer($row)!==$timer)throw new RuntimeException('issuer_time_batch_unconfirmed');if($kind==='projects'){$p=$this->projectTime($issuer,$project);if($p['client_id']!==$source['client_id'])throw new RuntimeException('issuer_time_batch_unconfirmed');$portal=$this->portal($issuer,$task);if($portal){if(!in_array($portal['billing_state'],$state==='converted'?['billed']:['converting','billed'],true))throw new RuntimeException('issuer_time_batch_unconfirmed');unset($portal['billing_state']);}if($portal!==$group['portal_origin'])throw new RuntimeException('issuer_time_batch_unconfirmed');}$hash=self::hash($timer);$claim=$this->rows('SELECT * FROM '.$this->prefix.$table.' WHERE timer_id=? FOR UPDATE',[$id])[0]??null;if(!$claim&&$reserve){$record=['timer_id'=>$id,'issuer_id'=>$issuer,'task_id'=>$task,'origin_id'=>$origin,'state'=>'converting','timer_hash'=>$hash,'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')];if($project!==null)$record['project_id']=$project;$this->insert($table,$record);$claim=$this->rows('SELECT * FROM '.$this->prefix.$table.' WHERE timer_id=? FOR UPDATE',[$id])[0]??null;}if(!$claim||(int)$claim['issuer_id']!==$issuer||(int)$claim['task_id']!==$task||(int)$claim['origin_id']!==$origin||($project!==null&&(int)$claim['project_id']!==$project))throw new DomainException('company_time_batch_overlap');if($claim['state']!==$state||!hash_equals($claim['timer_hash'],$hash))throw new RuntimeException('issuer_time_batch_unconfirmed');$expected[]=$id;}}
        sort($expected,SORT_NUMERIC);$actual=array_map(fn($r)=>(int)$r['timer_id'],$this->rows('SELECT timer_id FROM '.$this->prefix.$table.' WHERE origin_id=? ORDER BY timer_id FOR UPDATE',[$origin]));$other=$kind==='projects'?'company_standalone_time_claims':'company_task_time_batch_claims';if($expected!==$actual||$this->rows('SELECT timer_id FROM '.$this->prefix.$other.' WHERE origin_id=? LIMIT 1 FOR UPDATE',[$origin]))throw new RuntimeException('issuer_time_batch_unconfirmed');
    }
    protected function receipt(int $issuer,int $origin):?array
    {
        $row=$this->rows('SELECT issuer_id,actor_id,invoice_id,input_hash FROM '.$this->prefix.'company_invoice_requests WHERE request_key=? FOR UPDATE',[CompanyTaskTimeBatchFence::key($issuer,$origin)])[0]??null;if($row&&(int)$row['issuer_id']!==$issuer)throw new RuntimeException('issuer_time_batch_unconfirmed');return$row;
    }
    private function originMetadata(array $origin,?int $invoice):array{return ['origin_id'=>(int)$origin['id'],'invoice_id'=>$invoice,'request_hash'=>$origin['input_hash'],'source_hash'=>$origin['source_hash'],'payload_hash'=>$origin['payload_hash'],'context_hash'=>$origin['origin_hash']];}
    protected function batchAudit(int $issuer,int $actor,int $origin,string $event,?int $invoice=null):void
    {
        $o=$this->originBatch($issuer,$origin,false);$json=self::encode($this->originMetadata($o,$invoice));$this->insert('issuers_audit',['issuer_id'=>$issuer,'actor_id'=>$actor,'subject_id'=>$origin,'event'=>$event,'created_at'=>gmdate('Y-m-d H:i:s'),'metadata_json'=>$json]);$id=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$id,$issuer,$actor,$origin,$event,$json]))throw new RuntimeException('issuer_time_batch_unconfirmed');
    }
}
