<?php
require __DIR__.'/native_task_form_followers.php';
require_once APPPATH.'services/CompanyTaskTimerPreferences.php';
require_once APPPATH.'services/CompanyTaskTimerStopPreferences.php';
foreach([CompanyTaskOperationPreferences::class,CompanyTaskTimerPreferences::class,CompanyTaskTimerStopPreferences::class]as$class){
 $service=new $class($db,$p);$old=$service->page($a,7);$values=$old;unset($values['revision']);foreach($values as$key=>&$value)$value=$key==='default_task_priority'?($value==='1'?'2':'1'):($value==='1'?'0':'1');unset($value);
 try{$service->saveInOwnedTransaction($a,7,$old['revision'],$values);throw new LogicException('Outside-owned call accepted');}catch(DomainException|RuntimeException$e){if($e instanceof LogicException)throw$e;}
 essentialCheck($service->page($a,7)===$old,'direct policy call cannot mutate outside owned transaction');
 try{IssuerTransaction::run($db,$p,$service->tables(),function()use($service,$a,$old,$values){$service->saveInOwnedTransaction($a,7,$old['revision'],$values);throw new DomainException('synthetic group failure');});}catch(DomainException$e){if($e->getMessage()!=='synthetic group failure')throw$e;}
 essentialCheck($service->page($a,7)===$old,'later native form failure rolls back existing policy writer');
 IssuerTransaction::run($db,$p,$service->tables(),fn()=>$service->saveInOwnedTransaction($a,7,$old['revision'],$values));$new=$service->page($a,7);$expected=$values;$expected=['revision'=>$old['revision']+1]+$expected;essentialCheck($new===$expected,'owned policy uses same real readback and revision contract');
}
echo "Original task policy writers compose atomically; outside-owned and rollback negatives passed.\n";
