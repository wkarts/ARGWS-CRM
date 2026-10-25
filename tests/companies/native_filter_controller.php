<?php
require __DIR__.'/native_invoice_table_factory.php';
require_once APPPATH.'services/NativeCompanyFilters.php';NativeCompanyFilterSchema::ensure($db,$p);
unset($db->data_cache['table_names']);
essentialCheck($db->table_exists($p.'filters')&&$db->table_exists($p.'filter_defaults'),'actual native filter tables supplied by invoice fixture');
class NativeFilterHttpError extends RuntimeException{}
function show_error($message,$code=500){throw new NativeFilterHttpError($message,$code);}
#[AllowDynamicProperties]class AdminController {public function __construct(){}}
class CompanyRequestScope{
 public static int $issuer;public static bool $valid=true;
 public static function nativeFor($controller){return true;}
 public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 7;}};}
 public static function assertNativeMutation($controller){if(!self::$valid||$controller->input->method(true)!=='POST')throw new DomainException('native_csrf_denied');}
}
require APPPATH.'controllers/admin/Filters.php';
$controller=new Filters();$controller->output=new class{public array $headers=[];function set_header($value){$this->headers[]=$value;return$this;}function set_content_type($value){return$this;}};$controller->db=$db;$controller->input=new class{public string $verb='POST';public array $body=[];function post($key=null,$filter=false){return$key===null?$this->body:($this->body[$key]??null);}function method($upper=false){return$upper?$this->verb:strtolower($this->verb);}};
$controller->security=new class{function get_csrf_token_name(){return 'ci_csrf';}};$controller->load=new class{function model($name){throw new RuntimeException('global filter model forbidden');}};
CompanyRequestScope::$issuer=$a;
$input=['name'=>'Native owned filter','identifier'=>'invoices','view'=>'invoices','is_shared'=>'true','is_default'=>'true','rules'=>['match_type'=>'and','rules'=>[['id'=>'total','type'=>'NumberRule','operator'=>'greater','value'=>'1.00','has_dynamic_value'=>false]]]];
$controller->input->body=$input;ob_start();try{$controller->create();$created=json_decode(ob_get_contents(),true,512,JSON_THROW_ON_ERROR);}finally{ob_end_clean();}
essentialCheck($created['name']===$input['name']&&$created['is_default']===true&&$created['revision']==='1','native create atomically saves filter/default and returns component DTO');
$controller->input->body=['revision'=>$created['revision'],'name'=>'Edited']+$input;ob_start();try{$controller->update($created['id']);$updated=json_decode(ob_get_contents(),true,512,JSON_THROW_ON_ERROR);}finally{ob_end_clean();}
essentialCheck($updated['revision']==='2'&&$updated['name']==='Edited','native update carries CAS revision');
try{$controller->update($created['id']);throw new RuntimeException('stale update accepted');}catch(NativeFilterHttpError$e){essentialCheck($e->getCode()===409,'stale native filter rejected');}
CompanyRequestScope::$issuer=$b;$controller->input->body=['identifier'=>'invoices','view'=>'invoices','revision'=>'2'];try{$controller->delete($created['id']);throw new RuntimeException('foreign deletion accepted');}catch(NativeFilterHttpError$e){essentialCheck($e->getCode()===403,'foreign context cannot delete saved filter');}CompanyRequestScope::$issuer=$a;
$controller->input->verb='GET';try{$controller->mark_as_default($created['id'],'invoices','invoices');throw new RuntimeException('GET default accepted');}catch(NativeFilterHttpError$e){essentialCheck($e->getCode()===403,'default writes require POST');}$controller->input->verb='POST';
$store=new NativeCompanyFilters($db,$p,$a,7,(new NativeCompanyInvoiceTableFactory($db,$p))->make($a,7));$before=$store->listing();
$db->failOn='INSERT INTO '.$p.'company_native_filter_defaults';$controller->input->body=['name'=>'Atomic fail']+$input;try{$controller->create();throw new RuntimeException('ignored default failure');}catch(NativeFilterHttpError$e){essentialCheck($e->getCode()>=400,'default failure aborts save');}$db->failOn=null;
essentialCheck($store->listing()===$before,'default failure rolls back source filter and audit');
echo "Native Filters controller/SQLite POST CAS isolation and atomic defaults passed.\n";
