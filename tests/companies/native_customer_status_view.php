<?php
/** Original inactive customer view exposes the protected native activation action. */
ob_start();require __DIR__.'/native_customer_views.php';ob_end_clean();
function form_open($url,$attributes=[]){return '<form action="'.e($url).'" method="POST" class="'.e($attributes['class']??'').'">';}
function form_hidden($name,$value=''){return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}
function form_close(){return '</form>';}
function base_url($path=''){return 'https://fixture.test/'.ltrim($path,'/');}
function is_gdpr(){return false;}
if(!$db->table_exists($p.'company_portal_tokens'))$db->query('CREATE TABLE '.$p.'company_portal_tokens(contact_id int)');
if(!$db->table_exists($p.'user_auto_login'))$db->query('CREATE TABLE '.$p.'user_auto_login(user_id int,staff int)');
unset($db->data_cache['table_names']);
$db->query("UPDATE {$p}clients SET registration_confirmed=1 WHERE userid=?",[$nativeA]);
$current=$adapterA->details($nativeA);$clients->setActive(41,12,$nativeA,false,$current['revision']);
$controller->input->method='GET';$controller->input->get=[];$controller->client((string)$nativeA);$data=end($controller->load->views)[1];$html=$renderer->render('admin/clients/client',$data);
$activationHtml=$html;
essentialCheck(str_contains($html,'class="native-customer-activate"')&&str_contains($html,'/clients/change_client_status/'.$nativeA.'/1')&&str_contains($html,'name="_native_company_csrf"')&&str_contains($html,'name="revision"'),'owned inactive customer exposes scoped POST with CSRF and revision');
essentialCheck(!str_contains($html,'/clients/mark_as_active/'),'native view never exposes legacy GET mutation');
$data['native_customer_capabilities']['edit']=false;$html=$renderer->render('admin/clients/client',$data);
essentialCheck(!str_contains($html,'native-customer-activate'),'customer viewer without edit cannot activate');
if(in_array('--html-json',$argv??[],true)){
    $nativeScript=$renderer->render('admin/clients/client_js',$data);$optionalData=$data;$optionalData['native_customer_company_required']='0';$legacyData=$data;$legacyData['native_customers']=false;
    echo json_encode(['view'=>$activationHtml,'script'=>$nativeScript,'optional_script'=>$renderer->render('admin/clients/client_js',$optionalData),'legacy_script'=>$renderer->render('admin/clients/client_js',$legacyData)],JSON_THROW_ON_ERROR);
}
else echo "Native customer activation view uses protected POST.\n";
