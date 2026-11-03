<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/NativeCompanyTimesheetReportAuthority.php';
/** Tag lineage belongs to the timer, never its task or an integer-colliding timer family. */
final class NativeCompanyTimerTagReference
{
    private const TABLES=['company_task_timer'=>'company_task_timers','company_standalone_timer'=>'company_standalone_timers'];
    public static function resolve($db,string $p,int $issuer,int $actor,$canonical,string $cap='view'):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||$cap!=='view')throw new DomainException('native_timer_tags_writer_required');
        $identity=NativeCompanyResourceId::decode('timer',$canonical);$kind=$identity['source_kind'];$id=$identity['source_id'];$table=self::TABLES[$kind]??throw new DomainException('native_tags_reference_denied');$acl=new IssuerResourceAccess($db,$p);if(!$acl->member($issuer,$actor)||$acl->primaryId()===$issuer)throw new DomainException('native_tags_reference_denied');
        $queries=[];$read=static function(string $sql,array $args)use($db,&$queries):array{$rows=IssuerStorage::query($db,$sql,$args)->result_array();$queries[]=[$sql,$args,$rows];if(count($rows)!==1)throw new DomainException('native_tags_reference_denied');return$rows[0];};
        $timer=$read('SELECT id,issuer_id,actor_id,task_id,started_at,stopped_at,seconds FROM '.$p.$table.' WHERE id=?',[$id]);if((int)$timer['issuer_id']!==$issuer)throw new DomainException('native_tags_reference_denied');$subject=IssuerPolicy::id($timer['actor_id']);
        $taskKind=$kind==='company_task_timer'?'company_task':'company_standalone_task';$taskTable=$kind==='company_task_timer'?'company_tasks':'company_standalone_tasks';$task=$read('SELECT id,issuer_id'.($kind==='company_task_timer'?',project_id':'').' FROM '.$p.$taskTable.' WHERE id=?',[$timer['task_id']]);if((int)$task['issuer_id']!==$issuer)throw new DomainException('native_tags_reference_denied');
        if($kind==='company_task_timer'){$project=$read('SELECT id,issuer_id,client_id FROM '.$p.'company_projects WHERE id=?',[$task['project_id']]);if((int)$project['issuer_id']!==$issuer)throw new DomainException('native_tags_reference_denied');$owner=$read('SELECT owner_issuer_id FROM '.$p.'issuers_clients WHERE client_id=?',[$project['client_id']]);if((int)$owner['owner_issuer_id']!==$issuer)throw new DomainException('native_tags_reference_denied');}
        $authorize=static function()use($db,$p,$issuer,$actor,$subject,$acl,$taskKind,$timer):void{
            if(!$acl->member($issuer,$actor))throw new DomainException('native_tags_reference_denied');
            if($subject===$actor)return;
            if($acl->can($issuer,$actor,'reports','view-timesheets')){(new NativeCompanyTimesheetReportAuthority($db,$p))->assertTarget($issuer,$actor,$subject);return;}
            if($acl->can($issuer,$actor,'staff','view')){require_once __DIR__.'/CompanyStaff.php';(new CompanyStaff($db,$p))->assertAdministrativeTarget($issuer,$actor,$subject);return;}
            NativeCompanyResourceReferences::resolve($db,$p,$issuer,$actor,'task',NativeCompanyResourceId::encode($taskKind,$timer['task_id']),'view');
        };
        $authorize();foreach($queries as[$q,$args,$rows])if(IssuerStorage::query($db,$q,$args)->result_array()!==$rows)throw new DomainException('native_tags_changed');$authorize();
        return ['source_kind'=>$kind,'source_id'=>$id,'feature'=>'tasks','canonical_id'=>NativeCompanyResourceId::encode($kind,$id),'tag_lineage'=>'owned','tag_rel_type'=>'timesheet','timer_snapshot'=>$timer];
    }
}
