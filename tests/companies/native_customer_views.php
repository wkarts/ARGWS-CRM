<?php
/** Render the original customer views using controller-generated scoped data. */
ob_start();require __DIR__.'/native_clients_controller.php';ob_end_clean();
function init_head(){echo '<!doctype html><html><body>';}
function init_tail(){}
function staff_can(...$args){throw new LogicException('native customer view queried global permission');}
function staff_cant(...$args){throw new LogicException('native customer view queried global permission');}
function have_assigned_customers(...$args){throw new LogicException('native customer view queried legacy assignments');}
function render_datatable($columns,$id,...$args){echo '<table class="table-'.$id.'"></table>';}
$renderer=new class {
    public object $load;
    function __construct(){$this->load=new class {function view($name,$data=[]){}};}
    function render($file,$data){extract($data);ob_start();try{include APPPATH.'views/'.$file.'.php';return ob_get_contents();}finally{ob_end_clean();}}
};
CompanyRequestScope::$issuer=41;$controller->input->method='GET';$controller->input->post=[];$controller->input->get=[];
$controller->index();$directory=end($controller->load->views)[1];
$assigned=$directory;$assigned['native_customer_capabilities']=['view'=>true,'create'=>false,'edit'=>false,'share'=>false];
$html=$renderer->render('admin/clients/manage',$assigned);
essentialCheck(str_contains($html,'customers_summary_total')&&!str_contains($html,'href="https://fixture.test/admin/clients/client"'),'assigned-only directory renders summary without offering unauthorized creation');
essentialCheck(str_contains($html,'href="https://fixture.test/admin/clients/all_contacts"'),'authorized native customer directory offers the original contact directory');
$html=$renderer->render('admin/clients/manage',$directory);
essentialCheck(str_contains($html,'href="https://fixture.test/admin/clients/client"'),'authorized native directory offers original customer creation');
$controller->client();$form=end($controller->load->views)[1];$html=$renderer->render('admin/clients/client',$form);$createHtml=$html;
essentialCheck(str_contains($html,'save-and-add-contact')&&str_contains($html,'only-save'),'native customer creation shows both original save actions');
$form['native_customer_capabilities']['create']=false;$html=$renderer->render('admin/clients/client',$form);
essentialCheck(!str_contains($html,'save-and-add-contact')&&!str_contains($html,'only-save'),'native creation controls stay hidden when capability is absent');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='customers' AND capability='view'");
$controller->index();$createOnly=end($controller->load->views)[1];$html=$renderer->render('admin/clients/manage',$createOnly);
essentialCheck(!$createOnly['native_customer_capabilities']['view']&&$createOnly['native_customer_capabilities']['create']&&str_contains($html,'href="https://fixture.test/admin/clients/client"'),'create-only actor can reach original creation without directory authority');
essentialCheck(!str_contains($html,'class="table-clients"')&&!str_contains($html,'initDataTable('),'create-only actor cannot request or enumerate customer table');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='customers' AND capability='create'");
try{$controller->index();throw new RuntimeException('unauthorized customer directory accepted');}catch(NativeClientHttpError$e){essentialCheck($e->getCode()===403,'directory rejects actor without view, assignment or create grant');}
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(41,12,'customers','view'),(41,12,'customers','create')");
if(in_array('--html-json',$argv??[],true))echo json_encode(['create'=>$createHtml],JSON_THROW_ON_ERROR);
else echo "Native customer views use scoped capabilities and preserve save/contact action.\n";
