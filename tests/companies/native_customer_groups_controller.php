<?php
/** Original group actions with real scoped service and disposable storage. */
require __DIR__.'/native_clients_controller.php';
function redirect($url,$method='auto',$code=null){$GLOBALS['nativeGroupRedirect']=[$url,$method,$code];}
function form_open($url,$attributes=[]){return '<form action="'.e($url).'">';}
function form_hidden($name,$value){return '<input name="'.e($name).'" value="'.e($value).'">';}
function form_close(){return '</form>';}
class NativeGroupAjaxInput extends NativeClientInput {function is_ajax_request(){return true;}}
foreach([41,82] as $issuer)$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(?,12,'customers','manage_groups')",[$issuer]);
$groups=new CompanyCustomerGroups($db,$p);
$controller->input->method='GET';$controller->input->post=[];$controller->groups();$view=end($controller->load->views);
essentialCheck($view[0]==='admin/clients/groups_manage'&&$view[1]['native_customers']===true,'group directory renders original native view');
$controller->input->method='POST';$controller->input->post=['name'=>'Owned group'];
try{$controller->group();throw new RuntimeException('group without token accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()>=400,'group mutation rejects missing CSRF');}
essentialCheck($groups->all(41,12)===[],'rejected group mutation changes no catalog');
$controller->input->post['_native_company_csrf']='synthetic-native-csrf';$controller->group();$result=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);$group=(int)$result['id'];
essentialCheck($result['success']&&$groups->all(41,12)[0]['id']===$group,'authorized group creation writes owned catalog');
$originalInput=$controller->input;$ajax=new NativeGroupAjaxInput();$ajax->method='POST';$ajax->post=['draw'=>'4','length'=>'25','search'=>['value'=>'Owned']];$controller->input=$ajax;
try{$controller->groups();throw new RuntimeException('group table without token accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()>=400,'group table rejects missing native CSRF');}
$ajax->post['_native_company_csrf']='synthetic-native-csrf';$controller->groups();$page=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($page['draw']===4&&count($page['data'])===1&&$page['data'][0][0]==='Owned group','group DataTable returns the owner group');
CompanyRequestScope::$issuer=82;$controller->groups();$page=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($page['recordsTotal']===0&&$page['data']===[],'other company cannot enumerate owner groups');
CompanyRequestScope::$issuer=41;$controller->input=$originalInput;
$revision=$groups->all(41,12)[0]['revision'];CompanyRequestScope::$issuer=82;
$controller->input->post=['id'=>(string)$group,'name'=>'Foreign edit','revision'=>$revision,'_native_company_csrf'=>'synthetic-native-csrf'];
try{$controller->group();throw new RuntimeException('foreign group accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===403,'other issuer cannot modify group');}
CompanyRequestScope::$issuer=41;$controller->input->post['revision']=str_repeat('0',64);
try{$controller->group();throw new RuntimeException('stale group accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===409,'group edit rejects stale revision');}
$controller->input->post['revision']=$revision;$controller->input->post['name']='Owned group renamed';$controller->group();
essentialCheck($groups->all(41,12)[0]['name']==='Owned group renamed','owned group edit persists');
$controller->input->post=['revision'=>$groups->all(41,12)[0]['revision'],'_native_company_csrf'=>'synthetic-native-csrf'];$controller->delete_group((string)$group);
essentialCheck($groups->all(41,12)===[]&&($GLOBALS['nativeGroupRedirect'][2]??null)===303,'owned group delete uses original group destination');
echo "Native customer groups controller ownership and CSRF passed.\n";
