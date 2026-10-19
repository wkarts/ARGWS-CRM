<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectPortalRulesSchema.php';require_once __DIR__.'/CompanyProjectsSchema.php';require_once __DIR__.'/CompanyProjectExtrasSchema.php';require_once __DIR__.'/issuers/IssuerResourceAccess.php';require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Closed native project/task parents. No legacy tasks or contact-as-staff authority. */
abstract class CompanyProjectPortalRulesStorage
{
    protected const CORE='id,issuer_id,project_id,name,description,status,due_date,revision';
    public function __construct(protected $db,protected string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');}
    protected function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array{return array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','contacts','contact_permissions','issuers_audit','company_projects','company_tasks','company_task_comments','company_task_timers','company_project_files','company_portal_projects'],array_keys(CompanyProjectPortalRulesSchema::definitions($this->p)));}
    protected function tx(callable $work){CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyProjectPortalRulesSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
    public static function defaults():array{return array_fill_keys(CompanyProjectPortalRulesSchema::FLAGS,0);}
    protected function audit(int $issuer,int $actor,int $project,string $event,array $meta):void
    {
        $json=json_encode($meta,JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$project,gmdate('Y-m-d H:i:s'),$json]);if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[(int)$this->db->insert_id(),$actor,$issuer,$project,$event,$json]))throw new RuntimeException('issuer_project_portal_unconfirmed');
    }
    protected function parent(int $issuer,int $project):array
    {
        $row=$this->rows('SELECT p.id,p.issuer_id,p.client_id,p.name,p.status,p.revision FROM '.$this->p.'company_projects p JOIN '.$this->p.'issuers_clients o ON o.client_id=p.client_id AND o.owner_issuer_id=p.issuer_id JOIN '.$this->p.'clients c ON c.userid=o.client_id AND c.active=1 WHERE p.issuer_id=? AND p.id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$row)throw new DomainException('company_project_portal_unavailable');return $row;
    }
    protected function task(int $issuer,int $project,int $task):array
    {
        $r=$this->rows('SELECT '.self::CORE.' FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND id=? FOR UPDATE',[$issuer,$project,$task])[0]??null;if(!$r)throw new DomainException('company_project_portal_unavailable');return $r;
    }
    protected function flags(int $issuer,int $project):array
    {
        $r=$this->rows('SELECT '.implode(',',CompanyProjectPortalRulesSchema::FLAGS).',revision FROM '.$this->p.'company_project_portal_rules WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$r)return self::defaults()+['revision'=>0];foreach(CompanyProjectPortalRulesSchema::FLAGS as $f){if(!in_array((string)$r[$f],['0','1'],true))throw new DomainException('company_project_portal_unavailable');$r[$f]=(int)$r[$f];}if($r['view_tasks']===0)foreach(CompanyProjectPortalRulesSchema::FLAGS as $f)if($f!=='view_tasks'&&$r[$f]!==0)throw new DomainException('company_project_portal_unavailable');$r['revision']=IssuerPolicy::id($r['revision']);return $r;
    }
    protected static function hash(array $row):string{return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));}
    protected static function childKey(string $kind,$key):string
    {
        if($kind==='task'){if($key!==null&&$key!=='')throw new DomainException('company_project_portal_invalid');return '';}
        if($kind==='file'){if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_project_portal_invalid');return $key;}
        if($kind==='checklist'){if((!is_int($key)&&!is_string($key))||!preg_match('/^(0|[1-9][0-9]?)$/D',(string)$key)||(int)$key>29)throw new DomainException('company_project_portal_invalid');return (string)$key;}
        if(!in_array($kind,['comment','timer'],true))throw new DomainException('company_project_portal_invalid');return (string)IssuerPolicy::id($key);
    }
    /** Selected public source only. Never loads other children or a blob to count/list. */
    protected function child(int $issuer,int $project,int $task,string $kind,string $key):array
    {
        if($kind==='comment')$r=$this->rows('SELECT id,issuer_id,task_id,actor_id,body,created_at FROM '.$this->p.'company_task_comments WHERE issuer_id=? AND task_id=? AND id=? FOR UPDATE',[$issuer,$task,$key])[0]??null;
        elseif($kind==='timer'){$r=$this->rows('SELECT id,issuer_id,task_id,actor_id,started_at,stopped_at,seconds FROM '.$this->p.'company_task_timers WHERE issuer_id=? AND task_id=? AND id=? AND stopped_at IS NOT NULL FOR UPDATE',[$issuer,$task,$key])[0]??null;if($r&&(!preg_match('/^[0-9]+$/D',(string)$r['seconds'])||(int)$r['seconds']>2147483646||strtotime($r['stopped_at'])-strtotime($r['started_at'])!==(int)$r['seconds']))throw new DomainException('company_project_portal_unavailable');}
        elseif($kind==='file')$r=$this->rows('SELECT file_key,issuer_id,project_id,task_id,filename,mime,byte_size,content_hash FROM '.$this->p.'company_project_files WHERE issuer_id=? AND project_id=? AND task_id=? AND file_key=? AND active=1 FOR UPDATE',[$issuer,$project,$task,$key])[0]??null;
        elseif($kind==='checklist'){$row=$this->rows('SELECT checklist_json FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND id=? FOR UPDATE',[$issuer,$project,$task])[0]??null;$list=$row?json_decode($row['checklist_json'],true,32,JSON_THROW_ON_ERROR):null;$r=is_array($list)&&array_is_list($list)&&count($list)<=30?($list[(int)$key]??null):null;if($r){if(array_diff(array_keys($r),['text','done'])||!is_string($r['text']??null)||strlen($r['text'])>191||!in_array($r['done']??null,[0,1],true))throw new DomainException('company_project_portal_unavailable');$r=['issuer_id'=>$issuer,'project_id'=>$project,'task_id'=>$task,'index'=>$key,'text'=>$r['text'],'done'=>$r['done']];}}
        else throw new DomainException('company_project_portal_invalid');
        if(!$r)throw new DomainException('company_project_portal_unavailable');return $r;
    }
}
