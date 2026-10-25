<?php
require __DIR__.'/native_payment_modes.php';
// Previous negative contract intentionally adds schema drift; restore only that synthetic column.
$db->query('ALTER TABLE '.$p.'company_payment_modes DROP COLUMN unexpected');
NativeCompanyPaymentModesSchema::assertValid($db,$p);
require_once APPPATH.'services/NativeCompanyPaymentModeTable.php';
class CompanyRequestScope{public static int $issuer;public static bool $csrf=true;public static function nativeFor($ci){return true;}public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return 7;}};}public static function assertNativeMutation($ci){if($ci->input->verb!=='POST'||!self::$csrf)throw new DomainException('native_company_csrf_denied');}}
#[AllowDynamicProperties]class AdminController{public function __construct(){foreach(get_object_vars($GLOBALS['CI'])as$k=>$v)$this->$k=$v;}}
function show_error($text,$status=500){$GLOBALS['CI']->output->status=$status;$GLOBALS['CI']->output->body=$text;}
function _l($key,...$args){return $key;}function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}function admin_url($v=''){return '/admin/'.$v;}
function process_text_content_for_display($v){return nl2br(e($v));}
function _attributes_to_string($values){$out='';foreach($values as$k=>$v)$out.=$k.'="'.$v.'" ';return$out;}
$ci=$GLOBALS['CI'];$ci->input=new class{public string $verb='POST';public array $posts=[];public bool $ajax=true;public function method($upper=false){return$upper?$this->verb:strtolower($this->verb);}public function post(){return$this->posts;}public function is_ajax_request(){return$this->ajax;}};
$ci->load=new class{public array $views=[];public function model($name){throw new RuntimeException('Global model called');}public function view($name,$data){$this->views[]=[$name,$data];}};
$ci->output=new class{public int $status=200;public string $body='';public function set_header($v){return$this;}public function set_content_type($v){return$this;}public function set_output($v){$this->body=$v;return$this;}};
CompanyRequestScope::$issuer=$a;require_once APPPATH.'controllers/admin/Paymentmodes.php';$controller=new Paymentmodes();
$ci->input->posts=['paymentmodeid'=>'','revision'=>'','name'=>'Synthetic controller offline','description'=>'<synthetic>','active'=>'on','show_on_pdf'=>'on'];$controller->manage();$created=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($created['success']===true&&$modes->get($a,7,$created['id'])['show_on_pdf']===1,'same manage maps native checkbox wire to owned persisted mode');
$ci->input->posts=['revision'=>$created['revision']];$controller->change_payment_mode_status($created['id'],'0');$changed=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);essentialCheck($changed['success']&&$modes->get($a,7,$created['id'])['active']===0,'same native status action persists under revision');
$controller->change_payment_mode_status($created['id'],'1');essentialCheck($ci->output->status===409,'stale toggle does not overwrite');
$ci->input->posts=['revision'=>$changed['revision']];CompanyRequestScope::$issuer=$b;$controller->delete($created['id']);essentialCheck($ci->output->status===403,'B cannot delete A mode');CompanyRequestScope::$issuer=$a;
$ci->input->verb='GET';$controller->delete($created['id']);essentialCheck($ci->output->status===403,'native delete GET cannot mutate');$ci->input->verb='POST';
CompanyRequestScope::$csrf=false;$controller->delete($created['id']);essentialCheck($ci->output->status===403,'CSRF failure cannot delete');CompanyRequestScope::$csrf=true;
$ci->output->status=200;$columns=[];for($i=0;$i<5;$i++)$columns[]=['data'=>$i,'name'=>'','searchable'=>true,'orderable'=>true,'search'=>['value'=>'','regex'=>false]];
$ci->input->posts=['draw'=>3,'length'=>-1,'start'=>0,'columns'=>$columns,'order'=>[['column'=>1,'dir'=>'asc']],'search'=>['value'=>'Synthetic controller offline','regex'=>false]];$controller->index();$table=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($table['draw']===3&&$table['iTotalDisplayRecords']===1&&count($table['aaData'][0])===5,'same native five-column table supports real filtering and ALL');
essentialCheck(str_contains(implode('',$table['aaData'][0]),'data-revision="'.$changed['revision'].'"'),'shared row carries mutation revision');
$ci->input->posts=['revision'=>$changed['revision']];$controller->delete($created['id']);essentialCheck(json_decode($ci->output->body,true)['success']===true,'explicit POST delete succeeds with current revision');
echo 'Native payment mode controller and shared row: '.$GLOBALS['essentialChecks']." cumulative PASS; not HTTP/browser.\n";
