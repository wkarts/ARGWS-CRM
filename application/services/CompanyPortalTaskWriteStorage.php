<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectPortalRulesStorage.php';require_once __DIR__.'/CompanyPortalTaskWriteSchema.php';require_once __DIR__.'/CompanyPortal.php';
/** Shared exact participants and private/native6 identity; contact is never a staff actor. */
abstract class CompanyPortalTaskWriteStorage extends CompanyProjectPortalRulesStorage
{
 protected ?CompanyPortal $portal=null;
 public function __construct($db,string $p,?string $secret=null){parent::__construct($db,$p);if($secret!==null)$this->portal=new CompanyPortal($db,$p,$secret);}
 public function tables():array{return array_merge(parent::tables(),array_keys(CompanyPortalTaskWriteSchema::definitions($this->p)));}
 protected function tx(callable $work){CompanyPortalTaskWriteSchema::assertValid($this->db,$this->p);return parent::tx($work);}
 protected function writeFlags(int $issuer,int $project):array
 {
  $flags=$this->rows('SELECT '.implode(',',CompanyPortalTaskWriteSchema::FLAGS).',revision FROM '.$this->p.'company_project_write_rules WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])[0]??null;if(!$flags)return array_fill_keys(CompanyPortalTaskWriteSchema::FLAGS,0)+['revision'=>0];foreach(CompanyPortalTaskWriteSchema::FLAGS as $f){if(!in_array((string)$flags[$f],['0','1'],true))throw new DomainException('company_portal_task_unavailable');$flags[$f]=(int)$flags[$f];}$flags['revision']=IssuerPolicy::id($flags['revision']);return $flags;
 }
 protected function context(array $session,int $issuer,int $project,string $flag,bool $writing=true):array
 {
  if(!in_array($flag,CompanyPortalTaskWriteSchema::FLAGS,true))throw new DomainException('company_portal_task_invalid');$who=$this->portal?->invoiceIdentity($session,$issuer,true);if(!$who||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=6 FOR UPDATE',[$who['contact_id']]))!==1)throw new DomainException('company_portal_task_unavailable');$parent=$this->parent($issuer,$project);$read=$this->flags($issuer,$project);$write=$this->writeFlags($issuer,$project);
  if((int)$parent['client_id']!==$who['client_id']||!in_array($parent['status'],$writing?['active']:['active','completed','cancelled'],true)||$read['view_tasks']!==1||$write[$flag]!==1||!$this->rows('SELECT project_id FROM '.$this->p.'company_portal_projects WHERE issuer_id=? AND project_id=? AND visible=1 FOR UPDATE',[$issuer,$project])||($flag==='comment_on_tasks'&&$read['view_task_comments']!==1)||($flag==='upload_on_tasks'&&$read['view_task_attachments']!==1))throw new DomainException('company_portal_task_unavailable');return ['who'=>$who,'parent'=>$parent,'read'=>$read,'write'=>$write];
 }
 protected function published(int $issuer,int $project,int $id,bool $current=true):array
 {
  $task=$this->task($issuer,$project,$id);$pub=$this->rows('SELECT visible,source_hash,task_revision FROM '.$this->p.'company_portal_project_tasks WHERE issuer_id=? AND project_id=? AND task_id=? AND visible=1 FOR UPDATE',[$issuer,$project,$id])[0]??null;if(!$pub||($current&&((int)$pub['task_revision']!==(int)$task['revision']||!hash_equals($pub['source_hash'],self::hash($task)))))throw new DomainException('company_portal_task_unavailable');return $task;
 }
 protected function accessibleTask(int $issuer,int $project,int $task,array $who,bool $current=true):array
 {
  $r=$this->task($issuer,$project,$task);$publication=$this->rows('SELECT visible,task_revision,source_hash FROM '.$this->p.'company_portal_project_tasks WHERE issuer_id=? AND project_id=? AND task_id=? FOR UPDATE',[$issuer,$project,$task])[0]??null;
  // A deliberate staff suspension is distinct from a newly created task with no public decision.
  if($publication&&(int)$publication['visible']!==1)throw new DomainException('company_portal_task_unavailable');
  $origin=$this->rows('SELECT client_id,contact_id,billing_state FROM '.$this->p.'company_portal_task_origins WHERE issuer_id=? AND project_id=? AND task_id=? FOR UPDATE',[$issuer,$project,$task])[0]??null;
  if($origin&&(int)$origin['client_id']===$who['client_id']&&(int)$origin['contact_id']===$who['contact_id']){if($origin['billing_state']!=='unbilled')throw new DomainException('company_portal_task_unavailable');return $r;}
  if(!$publication||($current&&((int)$publication['task_revision']!==(int)$r['revision']||!hash_equals($publication['source_hash'],self::hash($r)))))throw new DomainException('company_portal_task_unavailable');return $r;
 }
 protected static function text($value,int $max,bool $required=true):string{if(!is_string($value)||strlen($value)>$max||($required&&trim($value)==='')||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('company_portal_task_invalid');return trim($value);}
 protected static function revision($value):int{$id=IssuerPolicy::id($value);if($id>2147483646)throw new DomainException('company_portal_task_invalid');return $id;}
 protected function origin(string $table,int $issuer,int $project,int $task,array $who,array $extra=[]):void
 {
  if(!in_array($table,['company_portal_task_origins','company_portal_task_comments','company_portal_task_files'],true))throw new LogicException('Invalid provenance table');$data=$extra+['issuer_id'=>$issuer,'project_id'=>$project,'task_id'=>$task,'client_id'=>$who['client_id'],'contact_id'=>$who['contact_id'],'author_name'=>trim($who['firstname'].' '.$who['lastname']),'created_at'=>gmdate('Y-m-d H:i:s')];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.' (`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));$where=implode(' AND ',array_map(static fn($f)=>$f.'=?',array_keys($data)));if(!$this->rows('SELECT contact_id FROM '.$this->p.$table.' WHERE '.$where,array_values($data)))throw new RuntimeException('issuer_portal_task_unconfirmed');
 }
 protected function fullTask(int $issuer,int $project,int $task):array{$row=$this->rows('SELECT * FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND id=? FOR UPDATE',[$issuer,$project,$task])[0]??null;if(!$row)throw new DomainException('company_portal_task_unavailable');return $row;}
}
