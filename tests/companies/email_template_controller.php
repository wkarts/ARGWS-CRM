<?php
require __DIR__.'/email_template_scope.php';
class AdminController {public $emails_model;public $input;public $db;public $load;public $output;}
function show_404(){throw new RuntimeException('not_found');}
require APPPATH.'controllers/admin/Emails.php';
$controller=(new ReflectionClass(Emails::class))->newInstanceWithoutConstructor();
$controller->emails_model=$m;
$controller->input=new class {function post(){throw new RuntimeException('post_read_before_template_authorized');}};
CompanySettingsRuntime::$issuer=null;
foreach(['email_template','enable','disable'] as $method){
 try{$controller->$method(2);check(false,'expected cross-company rejection');}
 catch(RuntimeException $e){check($e->getMessage()==='not_found','native controller rejects foreign ID before payload/view/activation: '.$method);}
}
echo "Native email controller: foreign IDs rejected before effects.\n";
