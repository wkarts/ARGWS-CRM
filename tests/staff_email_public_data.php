<?php
/** Serialization transformation and route-wiring contracts; no HTTP server/database required. */
require __DIR__.'/../application/services/StaffEmailIdentity.php';
$checks=0;
function publicIdentityCheck($value,$message){global $checks;++$checks;if(!$value)throw new RuntimeException($message);}
$row=['staffid'=>17,'email'=>' Mixed@Example.Invalid ','firstname'=>'Synthetic','permissions'=>['tasks'=>['view']],'customfields'=>['staff_email_identity'=>'unrelated custom value'],'staff_email_identity'=>'mixed@example.invalid'];
$expected=$row;unset($expected['staff_email_identity']);
publicIdentityCheck(StaffEmailIdentity::publicData($row)===$expected,'single array retains every existing field');
publicIdentityCheck(isset($row['staff_email_identity']),'source array untouched');
$object=(object)$row;$result=StaffEmailIdentity::publicData($object);
publicIdentityCheck((array)$result===$expected&&$result!==$object,'single object cloned with original shape');
publicIdentityCheck(isset($object->staff_email_identity),'source object untouched');
publicIdentityCheck(StaffEmailIdentity::publicData([$row,$row])===[$expected,$expected],'list of staff preserves list shape');
$result=StaffEmailIdentity::publicData([17=>$object]);publicIdentityCheck(array_keys($result)===[17]&&(array)$result[17]===$expected,'keyed staff list preserves identifiers');
foreach([null,false,[]]as$value)publicIdentityCheck(StaffEmailIdentity::publicData($value)===$value,'empty response contract');
$api=file_get_contents(__DIR__.'/../modules/api/controllers/Staffs.php');
publicIdentityCheck(substr_count($api,'$this->response(StaffEmailIdentity::publicData($data), REST_Controller::HTTP_OK)')===2,'API get and search serialize sanitized staff data');
$timesheets=file_get_contents(__DIR__.'/../modules/timesheets/controllers/Api_timesheets.php');
$start=strpos($timesheets,'public function staff_get(');$end=strpos($timesheets,"\n\t/**",$start);
publicIdentityCheck(str_contains(substr($timesheets,$start,$end-$start),'"result" => StaffEmailIdentity::publicData($data)'),'Timesheets staff endpoint strips storage metadata');
echo 'Staff public data: '.$checks.' checks passed (transformation and route-wiring contracts).'.PHP_EOL;
