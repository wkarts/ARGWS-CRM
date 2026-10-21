<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTaskTimerStopPreferences.php';
require_once __DIR__.'/CompanyTaskTimeBillingFence.php';
require_once __DIR__.'/CompanyProjectFixedBillingFence.php';
require_once __DIR__.'/CompanyStandaloneBillingFence.php';
require_once __DIR__.'/CompanyTaskBillingSchema.php';

/** A stop composed inside the existing start transaction, never a cross-company grant. */
final class CompanyTaskAutoStop
{
    public static function tables($db,string $p):array
    {
        $tables=array_merge(CompanyTaskTimeBillingFence::tables($db,$p),CompanyProjectFixedBillingFence::tables($db,$p),CompanyStandaloneBillingFence::tables($db,$p));
        if($db->table_exists($p.'company_task_billing_links')||$db->table_exists($p.'company_task_billing_origins')){
            CompanyTaskBillingSchema::assertValid($db,$p);
            $tables=array_merge($tables,['company_task_billing_links','company_task_billing_origins']);
        }
        return array_values(array_unique($tables));
    }
    private static function names(string $kind):array
    {
        return match($kind){'project'=>['company_tasks','company_task_timers'],'standalone'=>['company_standalone_tasks','company_standalone_timers'],default=>throw new LogicException('Invalid task kind')};
    }
    private static function rows($db,string $sql,array $bind):array
    {
        return array_map(static fn($r)=>array_map(static fn($v)=>$v===null?null:(string)$v,$r),IssuerStorage::query($db,$sql,$bind)->result_array());
    }
    private static function history($db,string $p,string $kind,int $issuer,int $id):array
    {
        [, $table]=self::names($kind);
        return self::rows($db,'SELECT * FROM '.$p.$table.' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$issuer,$id]);
    }
    /** Same current references and billing fences required by an explicit native stop. */
    private static function references($db,string $p,string $kind,int $issuer,int $actor,array $row):?array
    {
        $acl=new IssuerResourceAccess($db,$p);
        if(!$acl->can($issuer,$actor,'tasks','view')||!$acl->can($issuer,$actor,'tasks','edit')||($kind==='project'&&!$acl->can($issuer,$actor,'projects','view')))throw new DomainException('company_project_access_denied');
        $assignee=IssuerPolicy::id($row['assignee_id']);
        if(!$acl->can($issuer,$assignee,'tasks','view')||($kind==='project'&&!$acl->can($issuer,$assignee,'projects','view')))throw new DomainException('company_project_reference_denied');
        if($row['status']==='done')throw new DomainException('company_project_closed');
        if($kind==='standalone'){
            CompanyStandaloneBillingFence::assertAvailableInOwned($db,$p,$issuer,$actor,(int)$row['id']);
            return null;
        }
        $parent=IssuerStorage::query($db,'SELECT * FROM '.$p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$row['project_id']])->row_array();
        if(!$parent)throw new DomainException('company_project_access_denied');
        if($parent['status']!=='active')throw new DomainException('company_project_closed');
        if($row['due_date']<$parent['start_date']||$row['due_date']>$parent['due_date'])throw new DomainException('company_project_invalid_input');
        CompanyProjectFixedBillingFence::assertAvailableInOwned($db,$p,$issuer,$actor,(int)$parent['id']);
        CompanyTaskTimeBillingFence::assertMutableInOwned($db,$p,$issuer,(int)$row['id']);
        if($db->table_exists($p.'company_task_billing_links')&&IssuerStorage::query($db,'SELECT task_id FROM '.$p.'company_task_billing_links WHERE issuer_id=? AND task_id=? AND state IN (?,?) FOR UPDATE',[$issuer,$row['id'],'converting','converted'])->row_array())throw new DomainException('company_task_billing_immutable');
        return array_map(static fn($v)=>$v===null?null:(string)$v,$parent);
    }
    public static function prepare($db,string $p,int $issuer,int $actor,string $newKind,int $newId,string $now):array
    {
        IssuerTransaction::assertOwned($db,$p,array_merge(['company_project_requests','company_tasks','company_standalone_tasks','company_task_timers','company_standalone_timers','issuers_audit'],self::tables($db,$p)));
        $policy=CompanyTaskTimerStopPreferences::readInOwnedTransaction($db,$p,$issuer);
        $active=[];
        foreach(['project','standalone']as$kind){[, $table]=self::names($kind);foreach(self::rows($db,'SELECT * FROM '.$p.$table.' WHERE actor_id=? AND stopped_at IS NULL ORDER BY id FOR UPDATE',[$actor])as$row)$active[]=['kind'=>$kind,'row'=>$row];}
        if(!$active)return ['policy'=>$policy,'stopped'=>null];
        // Even a fully privileged master must explicitly stop the timer in its owning company.
        if(count($active)!==1||$policy[CompanyTaskTimerStopPreferences::AUTO_STOP]!=='1'||(int)$active[0]['row']['issuer_id']!==$issuer||($active[0]['kind']===$newKind&&(int)$active[0]['row']['task_id']===$newId))throw new DomainException('company_project_timer_running');
        $kind=$active[0]['kind'];$timer=$active[0]['row'];$id=(int)$timer['task_id'];[$taskTable,$timerTable]=self::names($kind);
        $frame=CompanyTaskPriority::frame($db,$p,$kind,$issuer,$id,false);
        $parent=self::references($db,$p,$kind,$issuer,$actor,$frame['native']);
        $history=self::history($db,$p,$kind,$issuer,$id);
        $start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$timer['started_at']);$end=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$now);
        if(!$start||!$end||$start->format('Y-m-d H:i:s')!==$timer['started_at']||$end->format('Y-m-d H:i:s')!==$now)throw new DomainException('company_project_invalid_input');
        $seconds=$end->getTimestamp()-$start->getTimestamp();
        if($seconds<0||$seconds>2147483646||(int)$frame['native']['revision']>=2147483647||$timer['seconds']!=='0')throw new DomainException('company_project_invalid_input');
        IssuerStorage::query($db,'UPDATE '.$p.$timerTable.' SET stopped_at=?,seconds=? WHERE id=? AND issuer_id=? AND task_id=? AND actor_id=? AND stopped_at IS NULL',[$now,$seconds,$timer['id'],$issuer,$id,$actor]);
        IssuerStorage::query($db,'UPDATE '.$p.$taskTable.' SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$now,$issuer,$id,$frame['native']['revision']]);
        $frame['native']['revision']=(string)((int)$frame['native']['revision']+1);$frame['native']['updated_at']=$now;
        $found=0;foreach($history as&$record)if($record['id']===$timer['id']){$record['stopped_at']=$now;$record['seconds']=(string)$seconds;++$found;}unset($record);
        if($found!==1)throw new RuntimeException('issuer_task_timer_unconfirmed');
        $metadata=json_encode(['task_timer_auto_stop_v1'=>['kind'=>$kind,'task_id'=>$id,'timer_id'=>(int)$timer['id'],'started_at'=>$timer['started_at'],'stopped_at'=>$now,'seconds'=>$seconds,'trigger_kind'=>$newKind,'trigger_task_id'=>$newId]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $fields=['actor_id'=>(string)$actor,'event'=>'company_task_timer_auto_stopped','issuer_id'=>(string)$issuer,'subject_id'=>(string)$id,'created_at'=>$now,'metadata_json'=>$metadata];
        IssuerStorage::query($db,'INSERT INTO '.$p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',array_values($fields));$audit=(int)$db->insert_id();
        if($audit<1)throw new RuntimeException('issuer_task_timer_unconfirmed');
        $result=['policy'=>$policy,'stopped'=>['kind'=>$kind,'issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'frame'=>$frame,'history'=>$history,'parent'=>$parent,'audit'=>['id'=>(string)$audit]+$fields]];
        self::confirm($db,$p,$issuer,$result);
        return $result;
    }
    public static function confirm($db,string $p,int $issuer,array $proof):void
    {
        CompanyTaskTimerStopPreferences::confirmInOwnedTransaction($db,$p,$issuer,$proof['policy']);
        if($proof['stopped']===null)return;
        $s=$proof['stopped'];
        if(CompanyTaskPriority::frame($db,$p,$s['kind'],$issuer,$s['id'],false)!==$s['frame']||self::history($db,$p,$s['kind'],$issuer,$s['id'])!==$s['history']||self::references($db,$p,$s['kind'],$issuer,$s['actor'],$s['frame']['native'])!==$s['parent'])throw new RuntimeException('issuer_task_timer_unconfirmed');
        $audit=IssuerStorage::query($db,'SELECT id,actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$p.'issuers_audit WHERE id=?',[$s['audit']['id']])->row_array();
        if(!$audit||array_map(static fn($v)=>$v===null?null:(string)$v,$audit)!==$s['audit'])throw new RuntimeException('issuer_task_timer_unconfirmed');
    }
}
