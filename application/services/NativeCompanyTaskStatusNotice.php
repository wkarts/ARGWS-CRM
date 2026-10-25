<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceReferences.php';require_once __DIR__.'/WorkStatusPresentation.php';
/** Notifications derive only from committed-in-frame native status receipts, never caller text. */
final class NativeCompanyTaskStatusNotice
{
    public function __construct(private$db,private string$p){}
    private function rows(string$q,array$a=[]):array{return IssuerStorage::query($this->db,$q,$a)->result_array();}
    public static function tables($db,string$p):array
    {
        $base=['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','staff','issuers_audit','company_project_requests','company_tasks','company_standalone_tasks','company_projects','clients','issuers_clients','issuers_client_links','company_task_assignees','company_standalone_assignees','company_task_followers','company_standalone_followers','company_task_comments','company_standalone_comments'];
        if($db->table_exists($p.'company_ticket_catalog'))$base=array_merge($base,NativeCompanyResourceReferences::transactionTables($db,$p,'ticket'));return array_values(array_unique($base));
    }
    private function event(int$i,int$id):array
    {
        $row=$this->rows('SELECT id,issuer_id,actor_id,event,subject_id,metadata_json,created_at FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND id=?',[$i,$id]);if(count($row)!==1)throw new DomainException('native_task_notice_denied');$row=$row[0];$meta=json_decode($row['metadata_json'],true,32,JSON_THROW_ON_ERROR);$source=$meta['native_task_status']??null;
        if(!is_array($source)||array_keys($source)!==['request_key','operation','input_hash','source_kind','source_id','from','to','result_revision','date_finished','closed_timer_ids']||!preg_match('/^[a-f0-9]{64}$/D',$source['request_key']??'')||!preg_match('/^[a-f0-9]{64}$/D',$source['input_hash']??'')||!preg_match('/^[a-f0-9]{32}$/D',$meta['audit_receipt_nonce']??''))throw new DomainException('native_task_notice_denied');
        $op=match($source['source_kind']){'company_task'=>'task_native_status','company_standalone_task'=>'standalone_task_native_status',default=>throw new DomainException('native_task_notice_denied')};
        if($source['operation']!==$op||$row['event']!=='company_'.$op||(int)$row['subject_id']!==$source['source_id']||!is_int($source['result_revision'])||$source['result_revision']<1||!in_array($source['from'],['todo','awaiting_feedback','testing','in_progress','done'],true)||!in_array($source['to'],['todo','awaiting_feedback','testing','in_progress','done'],true))throw new DomainException('native_task_notice_denied');
        $receipt=$this->rows('SELECT actor_id,operation,input_hash,result_id FROM '.$this->p.'company_project_requests WHERE issuer_id=? AND request_key=?',[$i,$source['request_key']]);if(count($receipt)!==1||(int)$receipt[0]['actor_id']!==(int)$row['actor_id']||$receipt[0]['operation']!==$op||$receipt[0]['input_hash']!==$source['input_hash']||(int)$receipt[0]['result_id']!==$source['source_id'])throw new DomainException('native_task_notice_denied');
        return['audit'=>$row,'source'=>$source,'receipt'=>$receipt];
    }
    private function recipients(int$i,array$event):array
    {
        $s=$event['source'];$task=$s['source_id'];$project=$s['source_kind']==='company_task';$table=$project?'company_tasks':'company_standalone_tasks';$assign=$project?'company_task_assignees':'company_standalone_assignees';$followers=$project?'company_task_followers':'company_standalone_followers';$comments=$project?'company_task_comments':'company_standalone_comments';
        $row=$this->rows('SELECT assignee_id FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$i,$task]);if(count($row)!==1)throw new DomainException('native_task_notice_denied');
        $assigned=$this->rows('SELECT staff_id,active FROM '.$this->p.$assign.' WHERE issuer_id=? AND task_id=? ORDER BY staff_id',[$i,$task]);$out=array_map('intval',array_column(array_filter($assigned,static fn($r)=>(int)$r['active']===1),'staff_id'));
        if($row[0]['assignee_id']!==null&&!in_array((int)$row[0]['assignee_id'],array_map('intval',array_column($assigned,'staff_id')),true))$out[]=(int)$row[0]['assignee_id'];
        $follow=$this->rows('SELECT staff_id FROM '.$this->p.$followers.' WHERE issuer_id=? AND task_id=? ORDER BY staff_id',[$i,$task]);$comment=$this->rows('SELECT actor_id FROM '.$this->p.$comments.' WHERE issuer_id=? AND task_id=? ORDER BY id',[$i,$task]);
        $ops=$project?['task_created','task_native_created']:['standalone_task_created','standalone_task_native_created'];$created=$this->rows('SELECT actor_id FROM '.$this->p.'company_project_requests WHERE issuer_id=? AND result_id=? AND operation IN (?,?) ORDER BY request_key',[$i,$task,...$ops]);
        $out=array_values(array_unique(array_merge($out,array_map('intval',array_column($follow,'staff_id')),array_map('intval',array_column($comment,'actor_id')),array_map('intval',array_column($created,'actor_id')))));sort($out,SORT_NUMERIC);return array_values(array_filter($out,static fn($v)=>$v!==(int)$event['audit']['actor_id']));
    }
    public function source(int$i,int$recipient,int$audit):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$recipient,'tasks','view'))throw new DomainException('native_task_notice_denied');$event=$this->event($i,$audit);$s=$event['source'];if($s['from']===$s['to'])throw new DomainException('native_task_notice_denied');
        $recipients=$this->recipients($i,$event);if(!in_array($recipient,$recipients,true))throw new DomainException('native_task_notice_denied');$canonical=NativeCompanyResourceId::encode($s['source_kind'],$s['source_id']);$reference=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$recipient,'task',$canonical,'view');
        $status=match($s['to']){'todo'=>1,'awaiting_feedback'=>2,'testing'=>3,'in_progress'=>4,'done'=>5};$labels=array_column(WorkStatusPresentation::tasks('_l'),null,'id');
        $result=['actor_id'=>(int)$event['audit']['actor_id'],'resource_id'=>$canonical,'url'=>'tasks/view/'.$canonical,'title'=>_l('task_status'),'body'=>$reference['label'].' — '.$labels[$status]['name'],'revision'=>1];
        if($this->event($i,$audit)!==$event||$this->recipients($i,$event)!==$recipients||NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$recipient,'task',$canonical,'view')!==$reference||!$acl->can($i,$recipient,'tasks','view'))throw new DomainException('native_task_notice_denied');return$result;
    }
    public function queueInOwned(int$i,int$a,int$audit):array
    {
        require_once __DIR__.'/NativeCompanyNotifications.php';IssuerTransaction::assertOwned($this->db,$this->p,array_merge(self::tables($this->db,$this->p),NativeCompanyNotifications::tables('task_status')));$event=$this->event($i,$audit);if((int)$event['audit']['actor_id']!==$a)throw new DomainException('native_task_notice_denied');if($event['source']['from']===$event['source']['to'])return[];
        $out=[];$acl=new IssuerResourceAccess($this->db,$this->p);foreach($this->recipients($i,$event)as$recipient){if(!$acl->can($i,$recipient,'tasks','view'))continue;$source=$this->source($i,$recipient,$audit);$out[]=(new NativeCompanyNotifications($this->db,$this->p))->recordInOwnedTransaction($i,$a,$recipient,'task_status',$audit);if($this->source($i,$recipient,$audit)!==$source)throw new DomainException('native_task_notice_denied');}return$out;
    }
}
