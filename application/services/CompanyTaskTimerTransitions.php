<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTaskTimerPreferences.php';
/** Seals the known start/status result through the final audit and receipt. */
trait CompanyTaskTimerTransitions
{
 private array $timerStarts=[];
 private function rememberTimerStart(int $issuer,int $actor,int $id,array $before,string $status,string $now,int $timer,array $policy):void
 {
  $frame=CompanyTaskPriority::frame($this->db,$this->p,$this->taskKind(),$issuer,$id,false);$expected=array_intersect_key($before,$frame['native']);$expected['status']=$status;$expected['revision']=(int)$before['revision']+1;$expected['updated_at']=$now;
  if(array_map('strval',$expected)!==$frame['native'])throw new RuntimeException('issuer_task_timer_unconfirmed');
  $table=$this->taskKind()==='project'?'company_task_timers':'company_standalone_timers';
  $known=['id'=>(string)$timer,'issuer_id'=>(string)$issuer,'task_id'=>(string)$id,'actor_id'=>(string)$actor,'started_at'=>$now,'stopped_at'=>null,'seconds'=>'0'];
  $actual=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.$table.' WHERE id=? AND issuer_id=? AND task_id=? FOR UPDATE',[$timer,$issuer,$id])->row_array();
  $actual=array_map(static fn($v)=>$v===null?null:(string)$v,$actual);if($actual!==$known)throw new RuntimeException('issuer_task_timer_unconfirmed');
  CompanyTaskTimerPreferences::confirmInOwnedTransaction($this->db,$this->p,$issuer,$policy);
  $this->timerStarts[]=['issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'table'=>$table,'timer'=>$known,'frame'=>$frame,'policy'=>$policy];
 }
 private function confirmTimerStarts():void
 {
  foreach($this->timerStarts as $s){CompanyTaskTimerPreferences::confirmInOwnedTransaction($this->db,$this->p,$s['issuer'],$s['policy']);$this->authorize($s['issuer'],$s['actor'],'edit');
   if(CompanyTaskPriority::frame($this->db,$this->p,$this->taskKind(),$s['issuer'],$s['id'],false)!==$s['frame'])throw new RuntimeException('issuer_task_timer_unconfirmed');
   $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.$s['table'].' WHERE id=? AND issuer_id=? AND task_id=? FOR UPDATE',[$s['timer']['id'],$s['issuer'],$s['id']])->row_array();if(array_map(static fn($v)=>$v===null?null:(string)$v,$row)!==$s['timer'])throw new RuntimeException('issuer_task_timer_unconfirmed');
  }
 }
}
