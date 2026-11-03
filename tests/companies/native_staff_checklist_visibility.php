<?php
require __DIR__.'/native_task_checklist_visibility.php';
$source=file_get_contents(APPPATH.'controllers/admin/Staff.php');
$source=str_replace('use NativeCompanySpecializedOperations;','use \\NativeCompanySpecializedOperations;',$source);
eval('namespace NativeTaskHttpTest; use \\DomainException; use \\Throwable; '.substr($source,5));
$staffController=new \NativeTaskHttpTest\Staff();$staffController->input->verb='POST';
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=92',[$a]);
\NativeTaskHttpTest\CompanyRequestScope::$issuer=$a;\NativeTaskHttpTest\CompanyRequestScope::$actor=92;
$staffController->input->body=['task_id'=>$canonical,'hideCompleted'=>'1','_native_company_csrf'=>'synthetic-csrf'];
$staffController->save_completed_checklist_visibility();
essentialCheck(json_decode($staffController->output->body,true)['hide']===1&&$pref->read($a,92,$canonical)===1,'official Staff endpoint persists the actual view-only actor preference');
essentialCheck($pref->read($a,7,$canonical)===0,'Staff action cannot change another actor preference');
$staffController->input->verb='GET';try{$staffController->save_completed_checklist_visibility();throw new RuntimeException('GET accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','official Staff endpoint requires POST');}
$staffController->input->verb='POST';unset($staffController->input->body['_native_company_csrf']);try{$staffController->save_completed_checklist_visibility();throw new RuntimeException('Missing CSRF accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','official Staff endpoint requires CSRF');}
$staffController->input->body['_native_company_csrf']='synthetic-csrf';\NativeTaskHttpTest\CompanyRequestScope::$issuer=$b;
try{$staffController->save_completed_checklist_visibility();throw new RuntimeException('Foreign task accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 403','official Staff endpoint resolves the active company before the preference');}
echo 'Original Staff checklist preference: actor, company and mutation boundaries passed.'.PHP_EOL;

$failure=new ReflectionMethod(\NativeTaskHttpTest\Staff::class,'nativeStaffFailure');
foreach(['issuer_staff_denied'=>403,'native_company_csrf_invalid'=>403,'issuer_master_protected'=>403,'issuer_staff_stale'=>409,'issuer_staff_conflict'=>409,'issuer_staff_invalid'=>422] as $code=>$status){try{$failure->invoke($staffController,new DomainException($code));throw new RuntimeException('Staff domain failure accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP '.$status,'native staff HTTP boundary maps '.$code.' without exposing the domain exception');}}
try{$failure->invoke($staffController,new RuntimeException('synthetic private storage failure'));throw new RuntimeException('Storage failure accepted');}catch(LogicException $e){essentialCheck($e->getMessage()==='HTTP 503','native staff unconfirmed storage failure stays unavailable');}
echo 'Native Staff HTTP errors: 7 additional denied/CSRF/protected/stale/conflict/invalid/storage status checks passed.'.PHP_EOL;
