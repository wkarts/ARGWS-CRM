<?php
/** Real native customer controller and scoped SQLite/MySQL storage. */
require __DIR__.'/native_clients_controller.php';
function redirect($url,$method='auto',$code=null){$GLOBALS['nativeClientRedirect']=[$url,$method,$code];}
$controller->input->post=array_replace($payload,['company'=>'Create with contact','request_key'=>hash('sha256','native-client-create-contact'),'_native_company_csrf'=>'synthetic-native-csrf','save_and_add_contact'=>'true']);
$controller->client();$redirect=$GLOBALS['nativeClientRedirect']??[];
essentialCheck(count($redirect)===3&&preg_match('~^https://fixture\.test/admin/clients/client/([1-9][0-9]*)\?group=contacts&new_contact=true$~D',$redirect[0],$createdMatch)===1&&$redirect[1]==='location'&&$redirect[2]===303,'create-and-add-contact redirects to original contact modal after save');
essentialCheck($adapterA->details((int)$createdMatch[1])['client']['company']==='Create with contact','contact destination belongs to customer actually saved in the current company');
$controller->input->post=array_replace($payload,['company'=>'Create without contact','request_key'=>hash('sha256','native-client-create-plain'),'_native_company_csrf'=>'synthetic-native-csrf']);
$controller->client();$redirect=$GLOBALS['nativeClientRedirect']??[];
essentialCheck(preg_match('~^https://fixture\.test/admin/clients/client/[1-9][0-9]*$~D',$redirect[0])===1&&$redirect[2]===303,'ordinary customer creation returns to its native profile');
echo "Native customer create/contact redirect passed.\n";
