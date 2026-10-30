<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/WorkStatusPresentation.php';

/**
 * Transport identities for existing owned states; not an authorization service.
 * These definition IDs are append-only and are NOT native legacy status IDs.
 * A caller must separately authorize issuer/resource and legacy lineage before using a decoded predicate.
 */
final class NativeCompanyWorkStatusCatalog
{
    private const DEFINITIONS=[
        'project'=>[
            1=>['active','company_project_status_active','#2563eb',true],
            2=>['completed','project_status_4','#16a34a',false],
            3=>['cancelled','project_status_5','#94a3b8',false],
            4=>['not_started','project_status_1','#475569',true],
            5=>['in_progress','project_status_2','#2563eb',true],
            6=>['on_hold','project_status_3','#f97316',true],
        ],
        'task'=>[
            1=>['todo','task_status_1','#64748b',true],
            2=>['in_progress','task_status_4','#3b82f6',true],
            3=>['done','task_status_5','#22c55e',false],
            4=>['awaiting_feedback','task_status_2','#f59e0b',true],
            5=>['testing','task_status_3','#6366f1',true],
        ],
        'ticket'=>[
            1=>['open','company_ticket_status_open','#2563eb',true],
            2=>['resolved','company_ticket_status_resolved','#16a34a',false],
            3=>['closed','company_ticket_status_closed','#94a3b8',false],
        ],
    ];
    private static function definitions(string $resource):array
    {
        if(!isset(self::DEFINITIONS[$resource]))throw new DomainException('native_work_status_resource_invalid');
        return self::DEFINITIONS[$resource];
    }
    public static function owned(string $resource,callable $translate):array
    {
        $rows=[];
        foreach(self::definitions($resource)as$id=>[$state,$label,$color,$default]){
            $rows[]=['id'=>NativeCompanyResourceId::encode('company_'.$resource.'_status',$id),
                'source_kind'=>'company_'.$resource.'_status','source_id'=>$id,'state'=>$state,
                'name'=>$translate($label),'color'=>$color,'order'=>$id,'filter_default'=>$default];
        }
        return $rows;
    }
    /** Native phase is used only when actually declared; historical active is never inferred as in-progress. */
    public static function projectState(string$lifecycle,$workflow):string
    {
        if(!in_array($lifecycle,['active','completed','cancelled'],true))throw new DomainException('native_work_status_unknown');
        if($workflow===null)return$lifecycle;
        if((!is_int($workflow)&&!is_string($workflow))||!preg_match('/^[0-5]$/D',(string)$workflow))throw new DomainException('native_work_status_unknown');
        $phase=(int)$workflow;$required=match($phase){0,1,2,3=>'active',4=>'completed',5=>'cancelled'};
        if($lifecycle!==$required)throw new DomainException('native_project_workflow_lifecycle_conflict');
        return [0=>'active',1=>'not_started',2=>'in_progress',3=>'on_hold',4=>'completed',5=>'cancelled'][$phase];
    }
    /** Preserve undeclared historical active as phase0; new native creation must explicitly select 1–5. */
    public static function projectWorkflow($canonicalId):int
    {
        $p=self::predicate('project',$canonicalId);return match($p['state']){'active'=>0,'not_started'=>1,'in_progress'=>2,'on_hold'=>3,'completed'=>4,'cancelled'=>5,default=>throw new DomainException('native_work_status_unknown')};
    }
    public static function id(string $resource,string $state):string
    {
        foreach(self::definitions($resource)as$id=>$definition)if($definition[0]===$state)return NativeCompanyResourceId::encode('company_'.$resource.'_status',$id);
        throw new DomainException('native_work_status_unknown');
    }
    /** Legacy custom ticket states are supplied by its authorized reader, never resolved by this pure catalogue. */
    public static function predicate(string $resource,$canonicalId,array $legacyIds=[]):array
    {
        $definitions=self::definitions($resource);
        $decoded=NativeCompanyResourceId::decode($resource.'_status',$canonicalId);
        if($decoded['source_kind']==='company_'.$resource.'_status'){
            if(!isset($definitions[$decoded['source_id']]))throw new DomainException('native_work_status_unknown');
            return ['lineage'=>'owned','state'=>$definitions[$decoded['source_id']][0]];
        }
        foreach($legacyIds as$id)if(!is_int($id)||$id<1||$id>=4294967296)throw new DomainException('native_work_status_catalog_invalid');
        if(!in_array($decoded['source_id'],$legacyIds,true))throw new DomainException('native_work_status_legacy_denied');
        return ['lineage'=>'legacy','state'=>$decoded['source_id']];
    }
}
