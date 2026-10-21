<?php
require __DIR__.'/task_recurring_fixture.php';
$tokens=new CompanyApiTokens($db,$p);$origins=[];foreach([$a,$b,$primary]as$i)$origins[$i]=$tokens->issue($i,7,'Synthetic bounded worker '.$i,['tasks:write'],1,$key('rr-worker-token-'.$i));
$plans=[];
for($n=0;$n<30;$n++){
    $issuer=[$a,$b,$primary][$n%3];$kind=$n%2?'project':'standalone';$origin=$n<25?['token_id'=>$origins[$issuer]['token_id'],'scope'=>'tasks:write']:null;
    $id=$rr->create($issuer,7,$kind,$sources[$kind][$issuer],5,rrCalendar(['cycles'=>2]),$key('rr-worker-plan-'.$n),$origin);$plans[]=['issuer'=>$issuer,'kind'=>$kind,'id'=>$id];$db->queries=[];
}
foreach($origins as$i=>$token)$tokens->revoke($i,7,$token['token_id']);
$_SESSION=['issuer_id'=>$a,'staff_id'=>12];$first=$rr->processPending('2026-10-31 12:00:00',25);rrCheck($first===['examined'=>25,'completed'=>0,'waiting'=>0,'blocked'=>25],'bounded worker counts invalid persisted origins without adopting ambient session');
$second=$rr->processPending('2026-10-31 12:00:00',25);rrCheck($second===['examined'=>5,'completed'=>5,'waiting'=>0,'blocked'=>0],'durable discovery advances past blocked first page without starving valid companies');
foreach($plans as$n=>$r){$plan=$rr->get($r['issuer'],7,$r['kind'],$r['id']);rrCheck($plan['completed_cycles']===($n<25?0:1),'worker advances only eligible original owner/cycle');if($n>=25){$writer=$r['kind']==='project'?$ts:$standalone;rrCheck((int)$writer->get($r['issuer'],7,$plan['runs'][0]['task_id'])['issuer_id']===$r['issuer'],'generated task belongs to persisted issuer');}}
$cursor=$db->query('SELECT * FROM '.$p.'company_task_schedule_cursor')->row_array();rrCheck((int)$cursor['last_id']===(int)$cursor['upper_id'],'discovery finishes the finite captured range');
$third=$rr->processPending('2026-10-31 12:00:00',25);$fourth=$rr->processPending('2026-10-31 12:00:00',25);rrCheck($third['blocked']===25&&$fourth===['examined'=>5,'completed'=>0,'waiting'=>5,'blocked'=>0],'restart wraps cursor but never advances a future date');
$rr->processPending('2026-11-01 12:00:00',25);$final=$rr->processPending('2026-11-01 12:00:00',25);rrCheck($final['completed']===5,'next local day generates exactly the remaining eligible cycles');
foreach(array_slice($plans,25)as$r)rrCheck($rr->get($r['issuer'],7,$r['kind'],$r['id'])['state']==='completed','worker reaches finite completed state');
$before=rrSnapshot();$cursor=$db->query('SELECT * FROM '.$p.'company_task_schedule_cursor')->row_array();$db->failOn='UPDATE '.$p.'company_task_schedule_cursor';try{rrThrows(fn()=>$rr->processPending('2026-11-02 12:00:00',25),'issuer_storage_failed','cursor write failure cannot announce a reserved page');}finally{$db->failOn=null;}rrCheck(rrSnapshot()===$before&&$db->query('SELECT * FROM '.$p.'company_task_schedule_cursor')->row_array()===$cursor,'discovery failure changes no cursor or domain effect');
$db->query('UPDATE '.$p.'company_task_schedule_cursor SET last_id=upper_id+1 WHERE id=1');try{rrThrows(fn()=>$rr->processPending('2026-11-02 12:00:00',25),'issuer_task_recurring_unconfirmed','invalid durable cursor fails closed');}finally{$db->query('UPDATE '.$p.'company_task_schedule_cursor SET last_id=?,upper_id=?,revision=? WHERE id=1',[$cursor['last_id'],$cursor['upper_id'],$cursor['revision']]);}
foreach([0,26]as$limit)rrThrows(fn()=>$rr->processPending('2026-11-02 12:00:00',$limit),'company_task_recurring_invalid','worker batch has strict finite bounds');
rrCheck($financialBefore===$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()&&hooks()->trace===[],'background recurrence has no financial or external transport side effect');
echo 'Task recurrence rebuilt bounded worker: '.($GLOBALS['essentialChecks']-$recurringStart).' NEW assertions; '.$GLOBALS['essentialChecks'].' cumulative; '.PHP_VERSION.'.'.PHP_EOL;
