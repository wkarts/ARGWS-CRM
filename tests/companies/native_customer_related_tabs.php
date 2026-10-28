<?php
/** Original customer groups, permissions and scoped nested table inputs. */
require __DIR__.'/native_customer_views.php';
if(!$db->table_exists($p.'user_meta'))$db->query('CREATE TABLE '.$p.'user_meta(staff_id int,meta_key varchar(191),meta_value text)');
$db->query('INSERT INTO '.$p.'user_meta(staff_id,meta_key,meta_value) VALUES(?,?,?)',[12,'issuer:41:projects-table-last-order','[[1,"asc"]]']);
$db->query('INSERT INTO '.$p.'user_meta(staff_id,meta_key,meta_value) VALUES(?,?,?)',[12,'issuer:82:projects-table-last-order','[[2,"desc"]]']);
unset($db->data_cache['table_names']);CompanyRequestScope::$issuer=41;$controller->input->method='GET';
foreach(['invoices','projects'] as$group){
    $controller->input->get=['group'=>$group];
    try{$controller->client((string)$nativeA);throw new RuntimeException('ungranted related group accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===403,$group.' requires its own ACL');}
    $db->query('INSERT INTO '.$p."issuers_resource_permissions VALUES(41,12,?,'view')",[$group]);
    $controller->client((string)$nativeA);$data=end($controller->load->views)[1];$fixtureData[$group]=$data;
    essentialCheck(($data['tab']['view']??null)==='admin/clients/groups/'.$group,$group.' uses original customer group');
    $renderer->load=new class{public array$seen=[];function view($name,$data=[]){$this->seen[]=[$name,$data];echo '<table class="scoped-native-table"></table>';}};
    $html=$renderer->render('admin/clients/groups/'.$group,$data);[$path,$args]=$renderer->load->seen[0];
    essentialCheck($path==='admin/'.$group.'/table_html'&&str_contains($html,'scoped-native-table'),'original nested '.$group.' table rendered');
    essentialCheck((int)$args['client']->userid===$nativeA&&isset($args['native_'.($group==='invoices'?'invoice':'project').'_custom_fields']),'table receives exact customer and scoped field definitions');
    if($group==='projects')essentialCheck($args['native_project_table_order']==='[[1,"asc"]]','project ordering comes from company A preference');
}
$renderer->load=new class{function view($name,$data=[]){extract($data);include APPPATH.'views/'.$name.'.php';}};
$oldCi=$GLOBALS['CI'];$GLOBALS['CI']=$controller;
try{foreach(['invoices','projects'] as$group){$html=$renderer->render('admin/clients/groups/'.$group,$fixtureData[$group]);essentialCheck(str_contains($html,'table-'.$group.'-single-client'),'actual original nested '.$group.' table renders without global data or ACL');}}finally{$GLOBALS['CI']=$oldCi;}
CompanyRequestScope::$issuer=82;$controller->input->get=['group'=>'projects'];
try{$controller->client((string)$nativeB);throw new RuntimeException('foreign related grant accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===403,'project grant does not transfer to company B');}
$db->query('INSERT INTO '.$p."issuers_resource_permissions VALUES(82,12,'projects','view')");
$controller->client((string)$nativeB);$other=end($controller->load->views)[1];
essentialCheck($other['native_project_table_order']==='[[2,"desc"]]','company B receives only its own table order');
echo "Original invoice/project customer groups, ACL and scoped table data passed.\n";
