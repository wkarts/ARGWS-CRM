<?php
declare(strict_types=1);
require __DIR__.'/hr_fixture.php';require_once APPPATH.'services/CompanyHrApi.php';
foreach(CompanyHrApi::SCOPES as $scope=>$caps)if((CompanyApiTokens::SCOPES[$scope]??null)!==$caps)throw new RuntimeException('Finite HR API integration required');
$api=new CompanyHrApi($db,$p);$tokens=new CompanyApiTokens($db,$p);$token=$tokens->issue($a,$master,'Synthetic HR collection witness',array_keys(CompanyHrApi::SCOPES),1,$key('hr-phantom-token'));
$unit=$hr->saveStructure($a,$master,'unit',null,null,['name'=>'Late audited unit'],$key('hr-late-unit'));
$position=$hr->saveStructure($a,$master,'position',null,null,['name'=>'Late audited role','unit_id'=>$unit],$key('hr-late-position'));
$employee=$hr->saveEmployee($a,$master,null,null,array_replace($employeeInputs[$a],['staff_id'=>21,'employee_code'=>'LATE-AUDITED','unit_id'=>$unit,'position_id'=>$position]),$key('hr-late-employee'));
$restore=[];foreach([['company_hr_units','id',$unit],['company_hr_positions','id',$position],['company_hr_employees','id',$employee],['company_hr_profiles','employee_id',$employee]] as [$table,$pk,$id]){$row=$db->query('SELECT * FROM '.$p.$table.' WHERE '.$pk.'=?',[$id])->row_array();$restore[]='INSERT INTO '.$p.$table.' (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_map(static fn($v)=>$v===null?'NULL':$db->pdo->quote((string)$v),array_values($row))).')';}
// Keep the real audited birth/receipt; temporarily absent rows will be restored by the real SQL trigger.
foreach([['company_hr_profiles','employee_id',$employee],['company_hr_employees','id',$employee],['company_hr_positions','id',$position],['company_hr_units','id',$unit]] as [$table,$pk,$id])$db->query('DELETE FROM '.$p.$table.' WHERE '.$pk.'=?',[$id]);
$reads=['employees'=>fn()=>$api->read($token['secret'],'hr_employees'),'report'=>fn()=>$api->read($token['secret'],'hr_reports',null,['name'=>'roster']),'export'=>fn()=>$api->export($token['secret'],'roster'),'structure'=>fn()=>$api->read($token['secret'],'hr_structure')];
$only=getenv('HR_PHANTOM_ONLY');if($only!==false&&$only!==''){if(!isset($reads[$only]))throw new RuntimeException('Unknown finite phantom case');$reads=[$only=>$reads[$only]];}
foreach($reads as $label=>$read){
 $before=hrSnapshot();$trigger=$p.'hr_read_phantom';$db->query('CREATE TRIGGER '.$trigger.' AFTER INSERT ON '.$p."issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_api_request_authorized' AND NOT EXISTS(SELECT 1 FROM ".$p.'company_hr_employees WHERE id='.$employee.') THEN '.implode('; ',$restore).'; END IF; END');
 $thrown=null;$result=null;try{$result=$read();}catch(Throwable $e){$thrown=$e->getMessage();}finally{$db->query('DROP TRIGGER '.$trigger);}
 $after=hrSnapshot();$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_hr_employees WHERE issuer_id=?',[$a])->row_array()['n'];$observation=['case'=>$label,'error'=>$thrown,'actual_employee_count'=>$count,'snapshot_unchanged'=>$after===$before,'returned_count'=>is_array($result)?($result['count']??count($result['items']??[])):null];
 if($label==='structure'&&is_array($result)){$units=array_map('intval',array_column($result['structure']['unit'],'id'));$observation['mixed_chart_reference']=count(array_filter($result['chart']['employees'],static fn($row)=>!in_array((int)$row['unit_id'],$units,true)))>0;}
 echo json_encode($observation,JSON_THROW_ON_ERROR).PHP_EOL;
 hrCheck($thrown==='issuer_hr_write_unconfirmed','API '.$label.' must reject a changed original collection after its final audit trigger');hrCheck($after===$before,'API '.$label.' rolls back restored eligible rows, audit and token usage');
}
hrCheck(count($api->read($token['secret'],'hr_employees')['items'])===1&&$api->read($token['secret'],'hr_reports',null,['name'=>'roster'])['count']===1,'Unchanged positive list and report remain available');
$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_api_request_authorized'")->row_array()['n'];$structureDto=$api->read($token['secret'],'hr_structure');$after=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_api_request_authorized'")->row_array()['n'];hrCheck($after-$before===1&&$structureDto['chart']['count']===1,'Structure and chart share one owned API audit/transaction envelope');
echo 'HR original collection witnesses and coherent structure DTO: '.$GLOBALS['staffChecks'].' checks; '.PHP_VERSION.'; 128MiB.'.PHP_EOL;
