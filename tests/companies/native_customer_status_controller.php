<?php
/** Original customer status controller, issuer isolation and revision fences. */
require __DIR__.'/native_clients_controller.php';
if(!$db->table_exists($p.'company_portal_tokens'))$db->query('CREATE TABLE '.$p.'company_portal_tokens(contact_id int)');
if(!$db->table_exists($p.'user_auto_login'))$db->query('CREATE TABLE '.$p.'user_auto_login(user_id int,staff int)');
unset($db->data_cache['table_names']);
$before=$adapterA->details($nativeA);$controller->input->method='POST';$controller->input->post=['revision'=>$before['revision']];
try{$controller->change_client_status($nativeA,'0');throw new RuntimeException('status without token accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()>=400,'status change requires native CSRF');}
essentialCheck($adapterA->details($nativeA)===$before,'tokenless status change preserves customer');
$controller->input->post['_native_company_csrf']='synthetic-native-csrf';CompanyRequestScope::$issuer=82;
try{$controller->change_client_status($nativeA,'0');throw new RuntimeException('foreign status accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===403,'foreign company cannot change customer status');}
CompanyRequestScope::$issuer=41;$controller->change_client_status($nativeA,'0');
essentialCheck((json_decode($controller->output->body,true)['success']??false)&&(int)$adapterA->details($nativeA)['client']['active']===0,'owner status action disables owned customer');
try{$controller->change_client_status($nativeA,'1');throw new RuntimeException('stale status accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===409,'status action rejects old revision');}
$controller->input->post['revision']=$adapterA->details($nativeA)['revision'];$controller->change_client_status($nativeA,'1');
essentialCheck((int)$adapterA->details($nativeA)['client']['active']===1,'owner can reactivate with current revision');
echo "Native customer status controller isolation, CSRF and revision passed.\n";
