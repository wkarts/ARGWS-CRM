<?php
/** Actual controller/adapter/SQL and original form + fields/custom-field renderers; framework/shell I/O only are spies. */
require __DIR__.'/native_expense_original_form.php';
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function html_escape($value){return e($value);}function set_value($name,$value){return e($value);}function is_html($value){return strip_tags($value)!==$value;}
function clear_textarea_breaks($value){return $value;}
function form_error($name){return '';}function _d($date){return (string)$date;}
function init_head(){}function init_tail(){}function form_close(){return '</form>';}
function get_option($key){throw new RuntimeException('Global options must not hydrate original expense form');}
function is_admin(){throw new RuntimeException('Global privileges must not hydrate original expense form');}
function base_url($path=''){return 'https://synthetic.invalid/'.$path;}function admin_url($path=''){return base_url('admin/'.$path);}
function form_hidden($name,$value=''){return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}
function form_open_multipart($url,$attrs=[]){$html='<form action="'.e($url).'" method="post" enctype="multipart/form-data"';foreach($attrs as $key=>$value)$html.=' '.e($key).'="'.e($value).'"';return $html.'>'.form_hidden('_native_company_csrf','synthetic-scope-csrf').form_hidden('_company_context','synthetic-context');}
function form_open($url,$attrs=[]){return str_replace(' enctype="multipart/form-data"','',form_open_multipart($url,$attrs));}
function show_error($message,$status=500){$GLOBALS['CI']->output->set_status_header($status);}
class CompanyRequestScope
{
    public static bool $sealed=true;public static int $issuer;public static int $actor=7;
    public static function nativeFor($ci):bool{return true;}public static function sealedFor($ci):bool{return self::$sealed;}
    public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}};}
    public static function assertNativeMutation($ci):void{if(!self::$sealed||$ci->input->method(true)!=='POST'||$ci->input->post('_native_company_csrf',false)!=='synthetic-scope-csrf')throw new DomainException('native_expense_form_csrf_denied');}
    public static function adminUrl($ci,$path){return admin_url($path);}public static function invalidateCaches($ci):void{}
}
class AdminController{public function __construct(){}public function __get($key){return $GLOBALS['CI']->$key;}}
class NativeExpenseTestLoader
{
    public array $shared=[],$views=[];public string $html='';public $load;
    public function __construct(){$this->load=$this;}
    public function __get($key){return $GLOBALS['CI']->$key;}
    public function model($name){throw new RuntimeException('Global expense models forbidden');}
    public function view($path,$data=[],$return=false){
        if(!in_array($path,['admin/expenses/expense','admin/expenses/expense_recurring_options'],true))throw new RuntimeException('Unexpected original expense view');
        $this->views[]=[$path,$data];$this->shared=array_merge($this->shared,$data);extract($this->shared);
        ob_start();try{include APPPATH.'views/'.$path.'.php';$html=ob_get_clean();}catch(Throwable $error){ob_end_clean();$GLOBALS['nativeExpenseRenderError']=$error->getMessage();throw $error;}
        if($path==='admin/expenses/expense')$this->html=$html;if($return)return $html;if($path!=='admin/expenses/expense')echo $html;
    }
}
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
require_once APPPATH.'helpers/fields_helper.php';require_once APPPATH.'helpers/custom_fields_helper.php';
require_once APPPATH.'controllers/admin/Expenses.php';
$GLOBALS['CI']->input=new class{public string $verb='GET';public array $posts=[],$gets=[];public function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}public function post($key=null,$filter=false){return $key===null?$this->posts:($this->posts[$key]??null);}public function get($key,$filter=false){return $this->gets[$key]??null;}};
$GLOBALS['CI']->output=new class{public int $status=200;public string $body='',$mime='';public array $headers=[];public function set_header($value){$this->headers[]=$value;return $this;}public function set_status_header($value){$this->status=$value;return $this;}public function set_output($value){$this->body=$value;return $this;}public function set_content_type($value){$this->mime=$value;return $this;}};
$GLOBALS['CI']->session=new class{public array $values=[];public function userdata($key){return $this->values[$key]??null;}public function set_userdata($key,$value){$this->values[$key]=$value;}};
$GLOBALS['CI']->config=new class{public function base_url(){return 'https://synthetic.invalid/';}};
$GLOBALS['CI']->load=new NativeExpenseTestLoader();
$GLOBALS['essentialHooks']=new class{public function __call($name,$args){throw new RuntimeException('Global hooks must not hydrate original expense form');}};
$start=$GLOBALS['essentialChecks'];CompanyRequestScope::$issuer=$a;$controller=new Expenses();$http=$GLOBALS['CI']->input;$out=$GLOBALS['CI']->output;
$reset=function(string $verb='GET',array $posts=[],array $gets=[])use($http,$out){$http->verb=$verb;$http->posts=$posts;$http->gets=$gets;$out->status=200;$out->body='';$out->mime='';$GLOBALS['CI']->load->views=[];$GLOBALS['CI']->load->shared=[];$GLOBALS['CI']->load->html='';};
$reset();$controller->expense();$html=$GLOBALS['CI']->load->html;
essentialCheck($out->status===200&&$out->body===$html&&str_contains($html,'id="expense-form"')&&str_contains($html,'tw-max-w-4xl')&&str_contains($html,'id="expenseRecurringOptions"'),'Actual original controller returns its buffered original expense panel, optional fields and recurring controls'.($GLOBALS['nativeExpenseRenderError']??$out->body));
essentialCheck(str_contains($html,'name="native_expense_form"')&&str_contains($html,'native_expenses_csrf')&&str_contains($html,'_native_company_csrf')&&str_contains($html,'_company_context'),'Original form retains all native context/CSRF fields');
essentialCheck(str_contains($html,'name="custom_fields[expenses]['.$field['id'].']"')&&!str_contains($html,'Private B reference'),'Actual custom-field renderer includes A definitions and excludes B');
essentialCheck(str_contains($html,'name="tax2"')&&str_contains($html,'name="reference_no"')&&str_contains($html,'name="paymentmode"'),'Original view retains both taxes, reference and payment-mode fields');
essentialCheck(str_contains($html,'data-native-expense-category="new"')&&str_contains($html,'data-native-category-modal data-inline="1"')&&str_contains($html,'name="native_expense_category"'),'Original expense form restores the inline category modal without leaving the entered expense fields');
$token=$GLOBALS['CI']->session->userdata('native_expenses_csrf');$payload=$wire;$payload['request_key']=hash('sha256','original-http-create');$payload['native_expenses_csrf']=$token;$payload['_native_company_csrf']='synthetic-scope-csrf';
$reset('POST',$payload);$controller->expense();$reply=json_decode($out->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($out->status===200&&$reply['success']===true&&str_contains($reply['url'],'expenses/list_expenses/'.$reply['expenseid'])&&preg_match('/^[a-f0-9]{64}$/D',$reply['upload_key']),'Actual original AJAX POST returns a confirmed typed detail URL and separate upload key');
$httpExpense=$reply['expenseid'];$httpSource=NativeCompanyExpenseOriginalForm::source($httpExpense);
essentialCheck($ex->get($a,7,$httpSource)['native_form']['reference_no']===$wire['reference_no'],'Controller invokes the actual audited profile writer');
$reset('POST',$payload);$controller->expense();essentialCheck(json_decode($out->body,true)['expenseid']===$httpExpense,'Original controller duplicate save returns the same persisted expense');
$bad=$payload;unset($bad['native_expenses_csrf']);$reset('POST',$bad);$controller->expense();essentialCheck($out->status===403,'Dedicated expense CSRF denied before writer');
$bad=$payload;unset($bad['_native_company_csrf']);$reset('POST',$bad);$controller->expense();essentialCheck($out->status===403,'Native mutation boundary denied before writer');
$reset();$controller->expense($httpExpense);$editHtml=$GLOBALS['CI']->load->html;
essentialCheck($out->status===200&&str_contains($editHtml,'Original &lt;script&gt;name')&&!str_contains($editHtml,'Original <script>name'),'Actual original edit safely renders persisted profile text');
essentialCheck(str_contains($editHtml,'value="Original custom field"')&&str_contains($editHtml,'value="'.$httpExpense.'"'),'Actual original edit restores scoped field value and canonical form identity');
$reset('HEAD');$controller->expense($httpExpense);essentialCheck($out->status===200&&$out->body===''&&$GLOBALS['CI']->load->views===[],'Original HEAD returns no private page body');
$reset('DELETE');$controller->expense($httpExpense);essentialCheck($out->status===405,'Original form denies unsupported methods');
$reset();$controller->get_customer_change_data($ca);$customer=json_decode($out->body,true,512,JSON_THROW_ON_ERROR);essentialCheck($out->status===200&&$customer['client_name']==='Private A'&&isset($customer['customer_has_projects'],$customer['client_currency']),'Original customer-change endpoint uses the actual scoped projection');
$reset();$controller->get_customer_change_data($cb);essentialCheck($out->status===403,'Known B customer never hydrates original customer-change data');
$reset('POST',$payload);$controller->get_customer_change_data($ca);essentialCheck($out->status===405,'Customer-change read route cannot mutate on POST');
CompanyRequestScope::$issuer=$b;$reset();$controller->expense($httpExpense);essentialCheck($out->status===403&&$GLOBALS['CI']->load->views===[],'Original expense form rejects a retained A deep link after company switch');CompanyRequestScope::$issuer=$a;
$upload=$payload;$upload['expense_id']=$httpExpense;$upload['request_key']=hash('sha256','bad-upload');$_FILES=[];$reset('POST',$upload);$controller->add_expense_attachment($httpExpense);essentialCheck($out->status===422&&(new CompanyExpenseFiles($db,$p))->page($a,7,$httpSource)===[],'Original attachment path refuses a fabricated/non-uploaded file without changing evidence');
CompanyRequestScope::$sealed=false;$reset();$controller->expense($httpExpense);essentialCheck($out->status===403&&$GLOBALS['CI']->load->views===[],'Unsealed original form never begins private projection');CompanyRequestScope::$sealed=true;
echo 'Original expense HTTP/view: '.($GLOBALS['essentialChecks']-$start).' added actual controller/SQL/renderer checks passed ('.$db->driver().'); shell, form transport and scope verification are explicit spies.'.PHP_EOL;
if(in_array('--emit-html',$argv,true))echo json_encode(['html'=>$html,'edit_html'=>$editHtml],JSON_THROW_ON_ERROR).PHP_EOL;
