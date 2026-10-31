<?php
require __DIR__.'/native_clients_controller.php';
function redirect($url,$method='auto',$status=null){$GLOBALS['native_admin_redirect']=[$url,$method,$status];}
$start=$GLOBALS['essentialChecks'];$access=new CompanyCustomerAccess($db,$p);
$db->query("INSERT INTO {$p}staff(staffid,active,admin,firstname,lastname) VALUES(51,1,0,'Assigned','A')");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(41,51,1)");
$controller->input->method='GET';$controller->input->post=[];
try{$controller->assign_admins((string)$nativeA);throw new RuntimeException('GET mutation accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()>=400,'native administrator assignment rejects GET');}
$revision=$access->assignments(41,12,$nativeA)['revision'];$controller->input->method='POST';$controller->input->post=['_native_company_csrf'=>'synthetic-native-csrf','revision'=>$revision,'customer_admins'=>['51']];
$controller->assign_admins((string)$nativeA);essentialCheck($access->can(41,51,$nativeA,'view')&&$GLOBALS['native_admin_redirect'][2]===303,'actual native assign controller writes exact grant and redirects original profile');
try{$controller->assign_admins((string)$nativeA);throw new RuntimeException('stale assignment accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===409,'native assign controller enforces revision');}
$controller->input->method='GET';$controller->input->post=[];$controller->client((string)$nativeA);$view=end($controller->load->views);essentialCheck($view[1]['native_customer_admin_manage']&&$view[1]['customer_admins'][0]['staff_id']==51&&count($view[1]['staff'])>=1,'native profile receives owned administrators and member candidates, no global staff model');
$controller->input->method='POST';$controller->input->post=['_native_company_csrf'=>'synthetic-native-csrf','revision'=>$access->assignments(41,12,$nativeA)['revision']];$controller->delete_customer_admin((string)$nativeA,'51');essentialCheck(!$access->can(41,51,$nativeA,'view'),'native remove administrator revokes only exact assignment');
CompanyRequestScope::$issuer=82;try{$controller->assign_admins((string)$nativeA);throw new RuntimeException('foreign assignment accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===403,'foreign company cannot manage owner administrators');}
echo 'Native customer admin controller: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
