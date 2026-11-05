<?php
/** Real controllers/services/views; framework boundary is an explicit test double. */
require __DIR__.'/native_credit_directory.php';
require __DIR__.'/native_credit_form_schema_fixture.php';
class CompanyRequestScope
{
    public static int $issuer,$actor=7; public static bool $sealed=true,$csrf=true;
    public static function nativeFor($ci): bool{return true;}
    public static function sealedFor($ci): bool{return self::$sealed;}
    public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}};}
    public static function assertNativeMutation($ci): void{if($ci->input->method(true)!=='POST'||!self::$csrf)throw new DomainException('native_company_csrf_invalid');}
    public static function adminUrl($ci,$path): string{return '/admin/'.$path;}
    public static function invalidateCaches($ci): void{}
}
#[AllowDynamicProperties]class AdminController{public function __construct(){foreach(get_object_vars($GLOBALS['CI'])as$key=>$value)$this->$key=$value;}}
function show_error($message,$status=500){$GLOBALS['CI']->output->status=$status;$GLOBALS['CI']->output->body=$message;}
function redirect($url,$method='location',$status=302){$GLOBALS['CI']->output->status=$status;$GLOBALS['CI']->output->redirect=$url;}
function get_option($key){return '0';}
function app_format_money($value,$currency){return $currency.' '.$value;}
function _d($value){return(string)$value;}
function _dt($value){return(string)$value;}
function form_open($url,$attributes=[]){$extra='';foreach($attributes as$key=>$value)$extra.=' '.htmlspecialchars($key,ENT_QUOTES,'UTF-8').'="'.htmlspecialchars($value,ENT_QUOTES,'UTF-8').'"';return '<form method="post" action="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"'.$extra.'>';}
function form_close(){return'</form>';}
$ci=$GLOBALS['CI'];
$ci->input=new class{public string $verb='POST';public array $posts=[],$gets=[];public function method($upper=false){return$upper?$this->verb:strtolower($this->verb);}public function post($key=null,$filter=false){return$key===null?$this->posts:($this->posts[$key]??null);}public function get($key=null,$filter=false){return$key===null?$this->gets:($this->gets[$key]??null);}};
$ci->output=new class{public int $status=200;public string $body='',$redirect='';public array $headers=[];public function set_status_header($status){$this->status=$status;return$this;}public function set_header($value){$this->headers[]=$value;return$this;}public function set_content_type($value){return$this;}public function set_output($value){$this->body=$value;return$this;}};
$ci->security=new class{public function get_csrf_token_name(){return'framework_csrf';}};
$ci->session=new class{private array $data=[];public function userdata($key){return$this->data[$key]??null;}public function set_userdata($key,$value){$this->data[$key]=$value;}};
$ci->load=new class{public array $views=[];public function model($name){throw new RuntimeException('Global model must not be loaded');}public function view($view,$data=[],$return=false){$this->views[]=[$view,$data];if(in_array($view,['admin/credit_notes/credit_note_preview_template','admin/credit_notes/native_preview'],true)){ob_start();$render=function()use($view,$data){extract($data,EXTR_SKIP);require APPPATH.'views/'.$view.'.php';};$render->bindTo($GLOBALS['CI'],null)();$html=ob_get_clean();if($return)return$html;echo$html;}return'';}};
require_once APPPATH.'controllers/admin/Credit_notes.php';
require_once APPPATH.'services/NativeCompanyCreditsController.php';
CompanyRequestScope::$issuer=$a;$controller=new Credit_notes();
$columns=[];for($i=0;$i<9;$i++)$columns[]=['data'=>$i,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];
$wire=['draw'=>'12','start'=>'0','length'=>'-1','search'=>['value'=>'','regex'=>'false'],'columns'=>$columns,'order'=>[['column'=>'7','dir'=>'desc']],'framework_csrf'=>'fixture'];
$ci->input->posts=$wire;$controller->table();$response=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($ci->output->status===200&&$response['draw']===12&&$response['iTotalRecords']===2&&count($response['aaData'][0])===10,'actual credit controller returns original 8 columns plus custom field and row class');
essentialCheck(str_contains($response['aaData'][0][0],'init_credit_note('.$credit.')')&&str_contains($response['aaData'][0][8],'&lt;script&gt;'),'original split-preview link and escaped custom content are rendered');
$ci->input->posts=$wire+['issuer_id'=>$b];$controller->table();essentialCheck($ci->output->status===422,'client cannot override issuer in table payload');
$ci->input->posts=$wire;CompanyRequestScope::$csrf=false;$controller->table();essentialCheck($ci->output->status===403,'invalid table CSRF stops before data');CompanyRequestScope::$csrf=true;
$ci->output->status=200;CompanyRequestScope::$issuer=$b;$controller->table();$other=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);essentialCheck($other['iTotalRecords']===1&&!str_contains(json_encode($other['aaData']),'init_credit_note('.$credit.')'),'same endpoint after switch lists only B credit');
$ci->input->verb='GET';$ci->output->status=200;ob_start();$controller->get_credit_note_data_ajax($credit);$html=ob_get_clean();essentialCheck($ci->output->status===403&&$html==='','foreign preview produces no financial HTML');
CompanyRequestScope::$issuer=$a;$ci->output->status=200;ob_start();$controller->get_credit_note_data_ajax($credit);$html=ob_get_clean();
essentialCheck($ci->output->status===200&&str_contains($html,'id="credit-note-preview"')&&str_contains($html,'id="invoices_credited"')&&str_contains($html,'id="tab_refunds"'),'real preview keeps original document/credit/refund tab surfaces');
essentialCheck(str_contains($html,'BRL 60.00')&&str_contains($html,'Credit exact &lt;script&gt;')&&str_contains($html,'id="apply_credits"')&&str_contains($html,'name="movement[parent_key]"'),'preview shows reconciled balances, escaped snapshot, original apply modal and audit reversals');
$ci->input->gets=['credit'=>(string)$credit];$ci->output->status=200;$controller->index();$last=end($ci->load->views);essentialCheck($ci->output->status===200&&$last[0]==='admin/credit_notes/manage'&&$last[1]['credit_note_id']===$credit,'published credit query selects the original directory/preview surface');
$ci->input->gets=[];$controller->credit_note();$last=end($ci->load->views);essentialCheck($last[0]==='admin/credit_notes/credit_note'&&$last[1]['native_credit_form']===true&&count($last[1]['clients'])>0,'original new-credit route gets explicit company options');
$ci->output->status=200;$controller->credit_note($credit);essentialCheck($ci->output->status===409,'existing immutable credit is not silently converted to legacy update');
$ci->input->verb='POST';$ci->input->posts=['action'=>'refund','credit_id'=>(string)$credit,'request_key'=>hash('sha256','native-controller-credit-refund'),'native_credit_notes_csrf'=>$ci->session->userdata('native_credit_notes_csrf'),'movement'=>['amount'=>'1.00','date'=>'2026-10-19','reference'=>'native-controller-refund','reason'=>'Synthetic confirmed external evidence']];
$controller->index();essentialCheck($ci->output->status===303&&str_contains($ci->output->redirect,'credit='.$credit)&&(new CompanyCredits($db,$p))->get($a,7,$credit)['available']==='59.00','original list POST preserves canonical ledger mutation and published redirect');
$controller->index();essentialCheck((new CompanyCredits($db,$p))->get($a,7,$credit)['available']==='59.00','retry cannot duplicate the ledger movement');
CompanyRequestScope::$issuer=$b;$ci->output->status=200;$ci->input->posts['request_key']=hash('sha256','native-controller-cross-credit');$controller->index();essentialCheck($ci->output->status===403&&(new CompanyCredits($db,$p))->get($a,7,$credit)['available']==='59.00','same mutation cannot target old-company credit after switch');
echo 'Native credit routes/preview/controller boundary double: '.$GLOBALS['essentialChecks']." cumulative checks PASS. Not HTTP/browser proof.\n";
