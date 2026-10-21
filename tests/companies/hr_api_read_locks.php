<?php
declare(strict_types=1);
require __DIR__.'/hr_fixture.php';require_once APPPATH.'services/CompanyHrApi.php';
$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,22,1)',[$a]);$candidate=$hr->saveEmployee($a,$master,null,null,array_replace($employeeInputs[$a],['staff_id'=>22,'employee_code'=>'LOCKED-EXCLUDED']),$key('read-lock-employee'));$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=22',[$a]);
$token=(new CompanyApiTokens($db,$p))->issue($a,$master,'Synthetic HR negative predicate lock',['hr:reports'],1,$key('read-lock-token'));$api=new CompanyHrApi($db,$p);$secondary=staffPdo();$secondary->exec('SET SESSION innodb_lock_wait_timeout=1');$secondaryId=(int)$secondary->query('SELECT CONNECTION_ID()')->fetchColumn();$parentId=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$fired=false;$blocked=false;$committed=false;
$db->observer=function($sql,$args,$database)use(&$fired,&$blocked,&$committed,$secondary,$a,$p):void{
 if($fired||!str_starts_with($sql,'SELECT issuer_id,staff_id,can_view FROM '.$p.'issuers_staff')||array_map('intval',$args)!==[$a,22])return;$fired=true;
 try{$q=$secondary->prepare('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=22');$q->execute([$a]);$committed=true;}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1205)throw $e;$blocked=true;}
};
try{$report=$api->read($token['secret'],'hr_reports',null,['name'=>'roster']);}finally{$db->observer=null;}
$member=(int)$db->query('SELECT can_view FROM '.$p.'issuers_staff WHERE issuer_id=? AND staff_id=22',[$a])->row()->can_view;
echo json_encode(['separate_connections'=>$secondaryId!==$parentId,'negative_candidate_observed'=>$fired,'secondary_blocked'=>$blocked,'secondary_committed'=>$committed,'final_membership'=>$member,'returned_count'=>$report['count']],JSON_THROW_ON_ERROR).PHP_EOL;
hrCheck($secondaryId!==$parentId&&$fired&&$blocked&&!$committed,'Excluded membership is locked on a real second connection before the API response is constructed');hrCheck($member===0&&$report['count']===1,'Read result and excluded-member state remain coherent through commit');
echo 'HR excluded eligibility current-read lock: '.$GLOBALS['staffChecks'].' checks; '.PHP_VERSION.'; 128MiB.'.PHP_EOL;
