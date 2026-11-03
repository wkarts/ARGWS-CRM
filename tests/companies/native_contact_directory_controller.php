<?php
/** Original contacts directory with scoped SQL and native controller transport. */
require __DIR__.'/native_contact_controller.php';
class NativeDirectoryInput extends NativeContactHttpInput {public bool $ajax=false;function is_ajax_request(){return $this->ajax;}}
$directoryInput=new NativeDirectoryInput();$controller->input=$directoryInput;
$controller->load=new class {public array $views=[];function view($name,$data){$this->views[]=[$name,$data];}};
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$actor=7;$directoryInput->verb='GET';$controller->output->status=200;
$controller->all_contacts();$view=end($controller->load->views);
essentialCheck($view[0]==='admin/clients/all_contacts'&&$view[1]['native_customers']===true,'contact directory opens the original view');
$directoryInput->verb='POST';$directoryInput->ajax=true;$directoryInput->body=['draw'=>'3','start'=>'0','length'=>'25','search'=>['value'=>'Form']];
$controller->all_contacts();$page=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($page['draw']===3&&count($page['data'])===1&&str_contains($page['data'][0][0],'Form'),'company owner receives its scoped contact row');
CompanyRequestScope::$issuer=$b;$controller->all_contacts();$page=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($page['data']===[],'other company cannot enumerate owner contacts');
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$actor=999;$directoryInput->verb='GET';$directoryInput->ajax=false;
try{$controller->all_contacts();throw new RuntimeException('directory without membership accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===403,'revoked actor cannot open contact directory');}
CompanyRequestScope::$actor=7;
echo "Native contact directory controller ownership passed.\n";
