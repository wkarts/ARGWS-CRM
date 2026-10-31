<?php
/** Production reader/presenter/native row. Request boundary is an explicit double, not HTTP proof. */
require __DIR__.'/native_invoice_rows.php';
class CompanyRequestScope
{
 public static int $issuer;public static bool $csrf=true,$contextValid=true;
 public static function nativeFor($ci):bool{return true;}
 public static function assertNativeMutation($ci):void{if($ci->input->method(true)!=='POST'||!self::$csrf||!self::$contextValid)throw new DomainException('native_company_csrf_invalid');}
 public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return 7;}};}
}
#[AllowDynamicProperties]class AdminController{public function __construct(){foreach(get_object_vars($GLOBALS['CI'])as$k=>$v)$this->$k=$v;}}
if(!function_exists('get_option')){function get_option($key){return$GLOBALS['nativeTableFixtureOptions'][$key]??'0';}}
function show_error($message,$status=500){$GLOBALS['CI']->output->status=$status;$GLOBALS['CI']->output->body=$message;}
$ci=$GLOBALS['CI'];
$ci->input=new class{public string $verb='POST';public array $posts=[],$gets=[];public function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}public function post($key=null,$filter=false){return $key===null?$this->posts:($this->posts[$key]??null);}public function get($key=null,$filter=false){return $key===null?$this->gets:($this->gets[$key]??null);}};
$ci->security=new class{public function get_csrf_token_name(){return 'synthetic_framework_csrf';}};
$ci->output=new class{public int $status=200;public string $body='';public array $headers=[];public function set_header($v){$this->headers[]=$v;return$this;}public function set_content_type($v){return$this;}public function set_output($v){$this->body=$v;return$this;}};
$ci->load=new class{public function model($name){throw new RuntimeException('global model invoked');}};
require_once APPPATH.'controllers/admin/Invoices.php';
$columns=[];for($i=0;$i<10;++$i)$columns[]=['data'=>$i,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];
$wire=['draw'=>7,'start'=>0,'length'=>-1,'search'=>['value'=>'','regex'=>'false'],'columns'=>$columns,'order'=>[['column'=>0,'dir'=>'desc']],'synthetic_framework_csrf'=>'synthetic'];
$ci->input->posts=$wire;CompanyRequestScope::$issuer=$a;$controller=new Invoices();
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$controller->table();$output=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($ci->output->status===200&&$output['draw']===7&&count($output['aaData'])===$output['iTotalDisplayRecords'],'same Invoices::table returns real native DataTables JSON and ALL rows');
essentialCheck(str_contains(implode('',array_map(static fn($row)=>implode('',$row),$output['aaData'])),'/invoices/list_invoices/')&&!str_contains(json_encode($output),'company_invoices'),'row links retain native URL surface, not aliases');
essentialCheck(in_array('Cache-Control: no-store',$ci->output->headers,true),'native table is not cacheable');
$ci->input->posts['issuer_id']=$b;$controller->table();essentialCheck($ci->output->status===422,'posted issuer override fails closed');$ci->input->posts=$wire;
foreach(['csrf','contextValid']as$flag){CompanyRequestScope::${$flag}=false;$controller->table();essentialCheck($ci->output->status===403,'invalid native authority cannot read table');CompanyRequestScope::${$flag}=true;}
$ci->input->verb='GET';$controller->table();essentialCheck($ci->output->status===403,'GET does not invoke legacy App_table');$ci->input->verb='POST';
$ci->output->status=200;CompanyRequestScope::$issuer=$b;$controller->table();$other=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck(!str_contains(json_encode($other['aaData']),'init_invoice('.$branded.')'),'same controller URL under B cannot output A invoice');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'table requests do not repair/adopt/rewrite financial records');
echo 'Native Invoices table controller/real row: '.$GLOBALS['essentialChecks']." cumulative checks PASS. Not HTTP/browser proof.\n";
