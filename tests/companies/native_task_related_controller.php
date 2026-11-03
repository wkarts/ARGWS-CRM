<?php
require __DIR__.'/native_task_related_rows.php';
foreach(['NativeCompanyRelatedTaskPage','NativeCompanyRelatedTaskRows']as$class)class_alias($class,'NativeTaskHttpTest\\'.$class);
function relatedHttpThrows(callable$fn,string$reason,string$label):void{try{$fn();throw new RuntimeException('Expected HTTP rejection');}catch(LogicException$e){essentialCheck($e->getMessage()===$reason,$label);}}
\NativeTaskHttpTest\CompanyRequestScope::$actor=7;$wire['_native_company_csrf']='synthetic-csrf';$controller->input->verb='POST';$controller->input->body=$wire;$controller->init_relation_tasks($ticketCanonical,'ticket');$result=json_decode($controller->output->body,true,64,JSON_THROW_ON_ERROR);
essentialCheck($result['iTotalRecords']===1&&str_contains($result['aaData'][0][2],'Updated ticket-linked task'),'Original Tasks::init_relation_tasks executes actual page and shared row');
$controller->input->verb='GET';relatedHttpThrows(fn()=>$controller->init_relation_tasks($ticketCanonical,'ticket'),'HTTP 403','GET cannot use contextual read transport that requires POST CSRF');
$controller->input->verb='POST';$controller->input->body=array_replace($wire,['length'=>'9999']);relatedHttpThrows(fn()=>$controller->init_relation_tasks($ticketCanonical,'ticket'),'HTTP 422','Original endpoint rejects unbounded page');
$controller->input->body=$wire;relatedHttpThrows(fn()=>$controller->init_relation_tasks(NativeCompanyResourceId::encode('company_ticket',2),'ticket'),'HTTP 403','Original endpoint denies foreign ticket');
echo 'Original related task endpoint real PDO: '.$GLOBALS['essentialChecks']." cumulative checks passed; HTTP/browser pending.\n";
