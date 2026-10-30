<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTaskNativeSnapshot.php';
require_once __DIR__.'/CompanyTaskPrioritySchema.php';require_once __DIR__.'/CompanyTaskOperationPreferences.php';
/** Native enum from tasks_helper (without ambient hooks); optional historical sidecar reads. */
final class CompanyTaskPriority
{
 public static function labels():array{return [1=>'Baixa',2=>'Média',3=>'Alta',4=>'Urgente'];}
 public static function value($v):int{if(!(is_int($v)||is_string($v))||!in_array($v,[1,2,3,4,'1','2','3','4'],true))throw new DomainException('company_project_invalid_input');return(int)$v;}
 public static function table(string $kind):string{return match($kind){'project'=>'company_task_priorities','standalone'=>'company_standalone_priorities',default=>throw new LogicException('Invalid task kind')};}
 public static function raw($db,string$p,string$kind,int$i,int$id):?array
 {
  $t=self::table($kind);if(!$db->table_exists($p.$t))return null;IssuerTransaction::assertOwned($db,$p,[$t]);$r=IssuerStorage::query($db,'SELECT issuer_id,task_id,priority FROM '.$p.$t.' WHERE issuer_id=? AND task_id=? FOR UPDATE',[$i,$id])->row_array();if(!$r){if(self::auditMarkers($db,$p,$kind,$i,$id))throw new RuntimeException('issuer_task_priority_unconfirmed');return null;}self::value($r['priority']);return array_map('strval',$r);
 }
 public static function read($db,string$p,string$kind,int$i,int$id):int{return self::value(self::raw($db,$p,$kind,$i,$id)['priority']??2);}
 public static function save($db,string$p,string$kind,int$i,int$id,int$value,bool$new):void
 {
  $t=self::table($kind);if(!$db->table_exists($p.$t))throw new DomainException('company_task_priority_unavailable');IssuerTransaction::assertOwned($db,$p,[$t]);$r=self::raw($db,$p,$kind,$i,$id);if($new&&$r!==null)throw new RuntimeException('issuer_task_priority_unconfirmed');if($r===null)IssuerStorage::query($db,'INSERT INTO '.$p.$t.'(issuer_id,task_id,priority) VALUES(?,?,?)',[$i,$id,self::value($value)]);else IssuerStorage::query($db,'UPDATE '.$p.$t.' SET priority=? WHERE issuer_id=? AND task_id=?',[$value,$i,$id]);if(self::raw($db,$p,$kind,$i,$id)!==['issuer_id'=>(string)$i,'task_id'=>(string)$id,'priority'=>(string)$value])throw new RuntimeException('issuer_task_priority_unconfirmed');
 }
 public static function auditMarkers($db,string$p,string$kind,int$i,int$id):array
 {
  IssuerTransaction::assertOwned($db,$p,['issuers_audit']);$pattern='{"task!_priority!_v1":{"kind":"'.$kind.'",%';$rows=IssuerStorage::query($db,'SELECT id,actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND metadata_json LIKE ? ESCAPE \'!\' ORDER BY id FOR UPDATE',[$i,$id,$pattern])->result_array();return array_map(static fn($r)=>array_map('strval',$r),$rows);
 }
 public static function birthHash($db,string$p,string$kind,int$i,int$id):string
 {
  $frame=self::frame($db,$p,$kind,$i,$id,false);return hash('sha256',json_encode(['native'=>$frame['native'],'priority'=>$frame['priority'],'followers'=>$frame['followers']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
 }
 public static function frame($db,string$p,string$kind,int$i,int$id,bool$audits=true):array
 {
  $parent=$kind==='project'?'company_tasks':'company_standalone_tasks';IssuerTransaction::assertOwned($db,$p,[$parent]);$row=IssuerStorage::query($db,'SELECT * FROM '.$p.$parent.' WHERE issuer_id=? AND id=? FOR UPDATE',[$i,$id])->row_array();if(!$row)throw new DomainException('company_project_access_denied');$followers=[];if($kind==='project'){IssuerTransaction::assertOwned($db,$p,['company_task_followers']);$followers=IssuerStorage::query($db,'SELECT issuer_id,task_id,staff_id FROM '.$p.'company_task_followers WHERE issuer_id=? AND task_id=? ORDER BY staff_id FOR UPDATE',[$i,$id])->result_array();$followers=array_map(static fn($r)=>array_map('strval',$r),$followers);}$registry=IssuerStorage::query($db,'SELECT active,settings_json FROM '.$p.'issuers_registry WHERE id=? FOR UPDATE',[$i])->row_array();if(!$registry||(int)$registry['active']!==1)throw new DomainException('company_project_access_denied');return ['native'=>array_map(static fn($v)=>$v===null?null:(string)$v,CompanyTaskNativeSnapshot::row($row)),'priority'=>self::raw($db,$p,$kind,$i,$id),'followers'=>$followers,'registry'=>array_map('strval',$registry)]+($audits?['audit_markers'=>self::auditMarkers($db,$p,$kind,$i,$id)]:[]);
 }
}
