<?php
namespace { require __DIR__.'/native_task_checklist_templates.php'; }
namespace NativeChecklistHttpTest {
use \NativeCompanyTaskChecklistTemplates;use \DomainException;use \Throwable;
function show_error($message,$status){throw new \LogicException('HTTP '.$status);}
class CompanyRequestScope {
 public static int$issuer; public static int$actor=7;
 public static function nativeFor($ci){return true;}
 public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}};}
 public static function assertNativeMutation($ci){if($ci->input->verb!=='POST'||($ci->input->body['_native_company_csrf']??null)!=='synthetic-proof')throw new DomainException('native_mutation_denied');}
}
class AdminController {
 public $db,$input,$output,$load;
 function __construct(){
  $this->db=$GLOBALS['db'];$this->input=new class{public string$verb='POST';public array$body=[];function post($key,$clean=false){return$this->body[$key]??null;}};
  $this->output=new class{public string$body='';function set_header($h){return$this;}function set_output($v){$this->body=$v;return$this;}};
  $this->load=new class{function model($n){throw new \RuntimeException('Global model loaded');}};
 }
}
class_alias(CompanyRequestScope::class,'CompanyRequestScope');
eval('namespace NativeChecklistHttpTest; use \\DomainException; use \\Throwable; use \\NativeCompanyTaskChecklistTemplates; '.substr(file_get_contents(APPPATH.'controllers/admin/Tasks.php'),5));
CompanyRequestScope::$issuer=$a;$controller=new Tasks();$controller->input->body=['description'=>'Original controller template','native_request_key'=>$key('http-create'),'_native_company_csrf'=>'synthetic-proof'];$controller->save_checklist_item_template();$created=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($catalog->get($a,7,$created['id'])===$created,'Original Tasks route calls actual scoped writer, not Tasks_model');
$controller->input->body=['native_revision'=>$created['revision'],'native_request_key'=>$key('http-delete'),'_native_company_csrf'=>'synthetic-proof'];$controller->input->verb='GET';
try{$controller->remove_checklist_item_template($created['id']);throw new \RuntimeException('GET accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Original delete URL denies GET mutation');}
$controller->input->verb='POST';unset($controller->input->body['_native_company_csrf']);try{$controller->remove_checklist_item_template($created['id']);throw new \RuntimeException('CSRF missing accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Original delete rejects absent native proof');}
$controller->input->body['_native_company_csrf']='synthetic-proof';CompanyRequestScope::$issuer=$b;try{$controller->remove_checklist_item_template($created['id']);throw new \RuntimeException('B deleted A');}catch(\LogicException$e){essentialCheck($e->getMessage()==='HTTP 422','Original delete rejects another issuer');}
CompanyRequestScope::$issuer=$a;$controller->remove_checklist_item_template($created['id']);essentialCheck(json_decode($controller->output->body,true)['success']===true,'Original POST deletes exact reviewed revision');
echo 'Original checklist controller: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
}
