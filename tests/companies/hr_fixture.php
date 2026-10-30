<?php
declare(strict_types=1);
// This cleanup is registered before the shared fixture, so private FKs go first.
register_shutdown_function(static function(){global$db,$p;if(isset($db,$p)){try{if($db->pdo->inTransaction())$db->pdo->rollBack();$db->failOn=null;$db->ignoreOn=null;$db->observer=null;foreach(array_reverse(array_keys(CompanyHrSchema::definitions($p))) as $t)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$t);foreach(['company_api_token_requests','company_api_tokens'] as $t)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$t);}catch(Throwable $e){fwrite(STDERR,'HR fixture cleanup failed: '.$e->getMessage().PHP_EOL);}}});
require __DIR__.'/staff_fixture.php';
require_once APPPATH.'services/CompanyHr.php';
$db=staffFixture();$p=$db->prefix;$a=2;$b=3;$primary=1;$master=17;
CompanyApiSchema::ensure($db,$p);CompanyHrSchema::ensure($db,$p);CompanyHrSchema::ensure($db,$p);
$hr=new CompanyHr($db,$p);$key=static fn(string $s)=>hash('sha256',$s);
function hrCheck(bool $v,string $label):void{global$db;staffCheck($v,$label);$db->writes=[];}
function hrThrows(callable $f,string $code,string $label):void{global$db;staffThrows($f,$code,$label);$db->writes=[];}
function hrSnapshot():array{global$db,$p;$out=[];foreach(array_merge(array_keys(CompanyHrSchema::definitions($p)),['staff','issuers_staff','issuers_resource_permissions','issuers_audit','company_api_tokens']) as $t){$rows=$db->query('SELECT * FROM '.$p.$t)->result_array();foreach($rows as &$r)ksort($r);unset($r);usort($rows,static fn($x,$y)=>json_encode($x)<=>json_encode($y));$out[$t]=$rows;}return $out;}
$structure=[];$employees=[];$employeeInputs=[];
foreach([$a,$b,$primary] as $i){
 $unit=$hr->saveStructure($i,$master,'unit',null,null,['name'=>'Unit '.$i],$key('hr-unit-'.$i));
 $position=$hr->saveStructure($i,$master,'position',null,null,['name'=>'Position '.$i,'unit_id'=>$unit],$key('hr-position-'.$i));
 $workplace=$hr->saveStructure($i,$master,'workplace',null,null,['name'=>'Workplace '.$i],$key('hr-workplace-'.$i));
 $type=$hr->saveStructure($i,$master,'contract_type',null,null,['name'=>'Type '.$i],$key('hr-type-'.$i));$structure[$i]=['unit'=>$unit,'position'=>$position,'workplace'=>$workplace,'contract_type'=>$type];
 $input=['staff_id'=>$master,'employee_code'=>'MASTER-'.$i,'unit_id'=>$unit,'position_id'=>$position,'workplace_id'=>$workplace,'manager_id'=>null,'joined_on'=>'2026-01-01','left_on'=>null,'state'=>'active'];$employeeInputs[$i]=$input;$employees[$i]=$hr->saveEmployee($i,$master,null,null,$input,$key('hr-employee-'.$i));
}
$globalBefore=$db->query('SELECT * FROM '.$p.'staff ORDER BY staffid')->result_array();
$pdf="%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
