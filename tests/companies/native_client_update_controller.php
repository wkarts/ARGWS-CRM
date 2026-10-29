<?php
require __DIR__.'/native_clients_controller.php';

function redirect($url,$method='location',$status=302){$GLOBALS['nativeClientRedirect']=[$url,$status];}
$controller->input->get=[];$controller->input->method='GET';$controller->client((string)$nativeA);$editView=end($controller->load->views);
essentialCheck($editView[1]['native_customer_action']===admin_url('clients/client/'.$nativeA),'Original edit form submits to its existing customer ID');
$controller->input->get=['source'=>'company'];$controller->client((string)$nativeA);$companyEditView=end($controller->load->views);
essentialCheck($companyEditView[1]['native_customer_action']===admin_url('clients/client/'.$nativeA.'?source=company'),'Company source edit preserves both the identity and the source query');
$controller->input->get=[];$controller->client();$createView=end($controller->load->views);
essentialCheck($createView[1]['native_customer_action']===admin_url('clients/client'),'Original create form retains its create endpoint');
$controller->input->get=[];$controller->input->method='POST';$staleRevision=$adapterA->details($nativeA)['revision'];
$fullEdit=array_replace($payload,['_native_company_csrf'=>'synthetic-native-csrf','revision'=>$staleRevision,'company'=>'Original controller updated identity']);$controller->input->post=$fullEdit;$controller->client((string)$nativeA);
$fresh=$adapterA->details($nativeA);essentialCheck($fresh['client']['company']==='Original controller updated identity'&&$fresh['revision']!==$staleRevision&&$GLOBALS['nativeClientRedirect'][1]===303,'Actual original client POST persists identity and rotates profile revision before redirect');
$controller->input->post=array_replace($fullEdit,['company'=>'Forbidden stale edit']);try{$controller->client((string)$nativeA);throw new RuntimeException('Stale full profile accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===409,'Actual original client rejects stale full profile with HTTP409');}essentialCheck($adapterA->details($nativeA)===$fresh,'Rejected stale full form preserves exact current profile');
echo 'Original Clients update/redirect and stale full-form HTTP409 passed.'.PHP_EOL;
