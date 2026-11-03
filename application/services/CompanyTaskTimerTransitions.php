<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyTaskTimerPreferences.php';require_once __DIR__.'/CompanyTaskAutoStop.php';
/** Preserve the prior task envelope: the known start is the only permitted new effect. */
trait CompanyTaskTimerTransitions
{
 private array $timerStarts=[];
 private function timerTable():string{return $this->taskKind()==='project'?'company_task_timers':'company_standalone_timers';}
 private static function timerRows(array $rows):array{return array_map(static fn($r)=>array_map(static fn($v)=>$v===null?null:(string)$v,$r),$rows);}
 private function timerHistory(int $issuer,int $id):array
 {
  return self::timerRows(IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.$this->timerTable().' WHERE issuer_id=? AND task_id=? ORDER BY id FOR UPDATE',[$issuer,$id])->result_array());
 }
 private function confirmActorActiveTimer(int $actor,?array $expected):void
 {
  foreach(['company_task_timers','company_standalone_timers']as$table){$rows=self::timerRows(IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.$table.' WHERE actor_id=? AND stopped_at IS NULL ORDER BY id FOR UPDATE',[$actor])->result_array());$known=$expected!==null&&$table===$this->timerTable()?[$expected]:[];if($rows!==$known)throw new RuntimeException('issuer_task_timer_unconfirmed');}
 }
 private function timerStartBefore(int $issuer,int $actor,int $id,string $now):array
 {
  $autoStop=CompanyTaskAutoStop::prepare($this->db,$this->p,$issuer,$actor,$this->taskKind(),$id,$now);$this->confirmActorActiveTimer($actor,null);
  return ['auto_stop'=>$autoStop,'task'=>CompanyTaskPriority::frame($this->db,$this->p,$this->taskKind(),$issuer,$id,false),'timers'=>$this->timerHistory($issuer,$id)];
 }
 private function rememberTimerStart(int $issuer,int $actor,int $id,array $before,string $status,string $now,int $timer,array $policy,array $prior):void
 {
  $expected=$prior['task'];$native=array_map(static fn($v)=>$v===null?null:(string)$v,array_intersect_key($before,$expected['native']));if($native!==$expected['native'])throw new RuntimeException('issuer_task_timer_unconfirmed');$expected['native']['status']=$status;$expected['native']['revision']=(string)((int)$before['revision']+1);$expected['native']['updated_at']=$now;
  if(CompanyTaskPriority::frame($this->db,$this->p,$this->taskKind(),$issuer,$id,false)!==$expected)throw new RuntimeException('issuer_task_timer_unconfirmed');
  $known=['id'=>(string)$timer,'issuer_id'=>(string)$issuer,'task_id'=>(string)$id,'actor_id'=>(string)$actor,'started_at'=>$now,'stopped_at'=>null,'seconds'=>'0'];$history=$prior['timers'];$history[]=$known;
  if($this->timerHistory($issuer,$id)!==$history)throw new RuntimeException('issuer_task_timer_unconfirmed');$this->confirmActorActiveTimer($actor,$known);CompanyTaskTimerPreferences::confirmInOwnedTransaction($this->db,$this->p,$issuer,$policy);
  $this->timerStarts[]=['auto_stop'=>$prior['auto_stop'],'issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'timer'=>$known,'history'=>$history,'frame'=>$expected,'policy'=>$policy];
 }
 private function confirmTimerStarts():void
 {
  foreach($this->timerStarts as$s){CompanyTaskAutoStop::confirm($this->db,$this->p,$s['issuer'],$s['auto_stop']);CompanyTaskTimerPreferences::confirmInOwnedTransaction($this->db,$this->p,$s['issuer'],$s['policy']);$this->authorize($s['issuer'],$s['actor'],'edit');
   if(CompanyTaskPriority::frame($this->db,$this->p,$this->taskKind(),$s['issuer'],$s['id'],false)!==$s['frame']||$this->timerHistory($s['issuer'],$s['id'])!==$s['history'])throw new RuntimeException('issuer_task_timer_unconfirmed');$this->confirmActorActiveTimer($s['actor'],$s['timer']);
  }
 }
}
