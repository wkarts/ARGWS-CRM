<?php
require_once __DIR__.'/../application/services/NativeCompanyProjectMetadataInput.php';
$n=0;function metadataCheck($v,$m){global$n;if(!$v)throw new RuntimeException($m);$n++;}
function metadataReject(callable$f){try{$f();}catch(DomainException$e){metadataCheck(true,'denied');return;}throw new RuntimeException('Invalid native configuration accepted');}
$input=['workflow_status'=>'2','billing_type'=>'1','currency_id'=>'1','project_cost'=>'100.01','project_rate_per_hour'=>'12.50','estimated_hours'=>'10.5','progress_from_tasks'=>'1','progress'=>'0'];
$fixed=NativeCompanyProjectMetadataInput::normalize($input,'active');metadataCheck($fixed['project_cost']==='100.01'&&$fixed['project_rate_per_hour']==='0.00'&&$fixed['estimated_hours']==='10.50','Fixed configuration exact');
$hourly=NativeCompanyProjectMetadataInput::normalize(array_replace($input,['billing_type'=>'2']),'active');metadataCheck($hourly['project_cost']==='0.00'&&$hourly['project_rate_per_hour']==='12.50','Hourly configuration exact');
$task=NativeCompanyProjectMetadataInput::normalize(array_replace($input,['billing_type'=>'3']),'active');metadataCheck($task['project_cost']==='0.00'&&$task['project_rate_per_hour']==='0.00','Task rate is not fabricated from project rate');
foreach([1,2,3]as$state){$actual=NativeCompanyProjectMetadataInput::normalize(array_replace($input,['workflow_status'=>$state]),'active');metadataCheck($actual['workflow_status']===$state,'Explicit workflow state retained');}
foreach([4=>'completed',5=>'cancelled']as$state=>$lifecycle){metadataCheck(NativeCompanyProjectMetadataInput::normalize(array_replace($input,['workflow_status'=>$state]),$lifecycle)['workflow_status']===$state,'Terminal state exact');metadataReject(fn()=>NativeCompanyProjectMetadataInput::normalize(array_replace($input,['workflow_status'=>$state]),'active'));}
metadataReject(fn()=>NativeCompanyProjectMetadataInput::normalize($input,'completed'));
foreach(['-1','1e2','1.234','01',true,1.5,'10000000000000','NaN']as$value)metadataReject(fn()=>NativeCompanyProjectMetadataInput::normalize(array_replace($input,['project_cost'=>$value]),'active'));
$max=NativeCompanyProjectMetadataInput::normalize(array_replace($input,['project_cost'=>'9999999999999.99']),'active');metadataCheck($max['project_cost']==='9999999999999.99','Decimal15,2 exact without float');
$null=NativeCompanyProjectMetadataInput::normalize(array_replace($input,['project_cost'=>'','estimated_hours'=>null]),'active');metadataCheck($null['project_cost']===null&&$null['estimated_hours']===null,'Unknown cost/hours remain null');
foreach([['progress'=>101],['progress_from_tasks'=>2],['currency_id'=>0],['billing_type'=>0],['workflow_status'=>6]]as$delta)metadataReject(fn()=>NativeCompanyProjectMetadataInput::normalize(array_replace($input,$delta),'active'));
metadataReject(fn()=>NativeCompanyProjectMetadataInput::normalize($input+['provider_token'=>'not accepted'],'active'));
$missing=$input;unset($missing['workflow_status']);metadataReject(fn()=>NativeCompanyProjectMetadataInput::normalize($missing,'active'));
echo "Native project metadata input: {$n} checks passed; explicit lifecycle/workflow and exact decimals.\n";

if(NativeCompanyProjectMetadataInput::normalize(array_replace($input,['workflow_status'=>0]),'active')['workflow_status']!==0)throw new RuntimeException('Historical active was relabelled');
