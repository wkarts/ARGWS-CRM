<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectTimesheetsStorage.php';require_once __DIR__.'/CompanyPortal.php';
/** Selected stopped timer publications; native timesheets adds no bypass of task/time/team gates. */
final class CompanyPortalProjectTimesheets extends CompanyProjectTimesheetsStorage
{
    private CompanyPortal $portal;
    public function __construct($db,string $p,private string $secret){parent::__construct($db,$p);if(strlen($secret)<32)throw new InvalidArgumentException('Portal secret unavailable');$this->portal=new CompanyPortal($db,$p,$secret);}
    private function context(array $session,int $issuer,int $project):array
    {
        $who=$this->portal->invoiceIdentity($session,$issuer,true);if(!$who||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=6 FOR UPDATE',[$who['contact_id']]))!==1)throw new DomainException('company_project_portal_unavailable');$parent=$this->parent($issuer,$project);if((int)$parent['client_id']!==$who['client_id']||!in_array($parent['status'],['active','completed','cancelled'],true)||!$this->rows('SELECT project_id FROM '.$this->p.'company_portal_projects WHERE issuer_id=? AND project_id=? AND visible=1 FOR UPDATE',[$issuer,$project]))throw new DomainException('company_project_portal_unavailable');$rules=$this->timesheetRules($issuer,$project);$flags=$this->flags($issuer,$project);if($rules['view_timesheets']!==1||$rules['project_timesheets']!==1||$flags['view_tasks']!==1||$flags['view_task_total_logged_time']!==1)throw new DomainException('company_project_portal_unavailable');return ['who'=>$who,'parent'=>$parent,'rules'=>$rules,'flags'=>$flags];
    }
    private function cursor(int $issuer,int $project,int $contact,string $started,int $id):string{$data=json_encode([$issuer,$project,$contact,$started,$id],JSON_THROW_ON_ERROR);$encoded=rtrim(strtr(base64_encode($data),'+/','-_'),'=');return $encoded.'.'.hash_hmac('sha256','timesheets:'.$encoded,$this->secret);}
    private function before(?string $cursor,int $issuer,int $project,int $contact):?array
    {
        if($cursor===null)return null;if(strlen($cursor)>240||!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/D',$cursor,$m)||!hash_equals(hash_hmac('sha256','timesheets:'.$m[1],$this->secret),$m[2]))throw new DomainException('company_project_portal_unavailable');$raw=base64_decode(strtr($m[1],'-_','+/'),true);$a=is_string($raw)?json_decode($raw,true,8,JSON_THROW_ON_ERROR):null;if(!is_array($a)||!array_is_list($a)||count($a)!==5||$a[0]!==$issuer||$a[1]!==$project||$a[2]!==$contact||!is_string($a[3])||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$a[3])||!is_int($a[4]))throw new DomainException('company_project_portal_unavailable');IssuerPolicy::id($a[4]);return [$a[3],$a[4]];
    }
    private function taskPublication(int $issuer,int $project,int $task):array
    {
        $r=$this->task($issuer,$project,$task);$v=$this->rows('SELECT task_revision,source_hash FROM '.$this->p.'company_portal_project_tasks WHERE issuer_id=? AND project_id=? AND task_id=? AND visible=1 FOR UPDATE',[$issuer,$project,$task])[0]??null;if(!$v||(int)$v['task_revision']!==(int)$r['revision']||!hash_equals($v['source_hash'],self::hash($r)))throw new DomainException('company_project_portal_unavailable');return $r;
    }
    private static function timestamp(string $value):void
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));if(!$date||$date->format('Y-m-d H:i:s')!==$value)throw new DomainException('company_project_portal_unavailable');
    }
    private function team(int $issuer,int $actor):?string
    {
        if($actor<1)return null;$r=$this->rows('SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=? AND active=1 FOR UPDATE',[$actor])[0]??null;$acl=new IssuerResourceAccess($this->db,$this->p);return $r&&$acl->can($issuer,$actor,'projects','view')&&$acl->can($issuer,$actor,'tasks','view')?trim($r['firstname'].' '.$r['lastname']):null;
    }
    private function projection(int $issuer,int $project,array $ctx,?array $before):array
    {
        $args=[$issuer,$project,'timer'];$where='';if($before!==null){$where=' AND (r.started_at<? OR (r.started_at=? AND r.id<?))';array_push($args,$before[0],$before[0],$before[1]);}
        $rows=$this->rows('SELECT r.id,r.task_id,r.started_at,v.source_hash FROM '.$this->p.'company_portal_task_children v JOIN '.$this->p.'company_task_timers r ON r.issuer_id=v.issuer_id AND r.task_id=v.task_id AND v.child_key=CAST(r.id AS CHAR) JOIN '.$this->p.'company_tasks t ON t.issuer_id=v.issuer_id AND t.project_id=v.project_id AND t.id=v.task_id JOIN '.$this->p.'company_portal_project_tasks tv ON tv.issuer_id=t.issuer_id AND tv.project_id=t.project_id AND tv.task_id=t.id AND tv.visible=1 AND tv.task_revision=t.revision WHERE v.issuer_id=? AND v.project_id=? AND v.kind=? AND v.visible=1 AND r.stopped_at IS NOT NULL'.$where.' ORDER BY r.started_at DESC,r.id DESC LIMIT 51 FOR UPDATE',$args);$items=[];$next=null;
        foreach(array_slice($rows,0,50) as $row){$task=$this->taskPublication($issuer,$project,(int)$row['task_id']);$r=$this->child($issuer,$project,(int)$row['task_id'],'timer',self::childKey('timer',$row['id']));if(!hash_equals($row['source_hash'],self::hash($r)))throw new DomainException('company_project_portal_unavailable');self::timestamp($r['started_at']);self::timestamp($r['stopped_at']);$out=['task_id'=>(int)$task['id'],'task_name'=>$task['name'],'started_at'=>$r['started_at'],'stopped_at'=>$r['stopped_at'],'seconds'=>(int)$r['seconds']];if($ctx['flags']['view_team_members']===1){$name=$this->team($issuer,(int)$r['actor_id']);if($name!==null)$out['staff_name']=$name;}$items[]=$out;}
        if(count($rows)>50){$last=$rows[49];$next=$this->cursor($issuer,$project,$ctx['who']['contact_id'],$last['started_at'],(int)$last['id']);}return ['items'=>$items,'next'=>$next];
    }
    public function page(array $session,$issuer,$project,?string $cursor=null):?array
    {
        $issuer=IssuerPolicy::id($issuer);$project=IssuerPolicy::id($project);return $this->tx(function()use($session,$issuer,$project,$cursor):?array{
            try{$ctx=$this->context($session,$issuer,$project);}catch(DomainException $e){return null;}$before=$this->before($cursor,$issuer,$project,$ctx['who']['contact_id']);$out=$this->projection($issuer,$project,$ctx,$before);$this->audit($issuer,0,$project,'company_portal_project_timesheets_listed',['contact_id'=>$ctx['who']['contact_id']]);if($this->context($session,$issuer,$project)!==$ctx||$this->projection($issuer,$project,$ctx,$before)!==$out)throw new DomainException('company_project_portal_unavailable');return $out;
        });
    }
}
