<?php
require __DIR__.'/native_invoice_form_data.php';
// Explicit shell/formatting seams. The nested invoice PHP views themselves are production files.
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function init_head(){echo '<!-- synthetic shell seam, not a screenshot of production -->';}
function init_tail(){}
function get_staff_user_id(){return CompanyRequestScope::$actor;}
function staff_can($cap,$feature){return(new IssuerResourceAccess($GLOBALS['db'],$GLOBALS['p']))->can(CompanyRequestScope::$issuer,CompanyRequestScope::$actor,$feature,$cap);}
function form_open($url,$attributes=[]){return '<form method="post" action="'.e($url).'" id="'.e($attributes['id']??'').'">';}
function form_hidden($name,$value=''){return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}
function form_close(){return '</form>';}
function render_input($name,$label='',$value='',$type='text',$attrs=[]){return '<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'">';}
function render_select($name,$options,$keys,$label='',$selected='',$attrs=[]){$html='<select name="'.e($name).'">';foreach($options as$option){$title=is_array($keys[1])?implode(' ',array_map(fn($key)=>$option[$key],$keys[1])):$option[$keys[1]];$html.='<option value="'.e($option[$keys[0]]).'">'.e($title).'</option>';}return$html.'</select>';}
function render_date_input($name,$label='',$value='',$attrs=[]){return '<input name="'.e($name).'" value="'.e($value).'">';}
function render_textarea($name,$label='',$value='',$attrs=[]){return '<textarea name="'.e($name).'">'.e($value).'</textarea>';}
function _d($value){return$value;}
function admin_url($path=''){return '/admin/'.$path;}
function get_country_short_name($id){throw new RuntimeException('unexpected country lookup on unselected new form');}
function get_all_countries(){return[];} // Reference-country rendering seam; no customer data.
function app_format_number($value){return(string)$value;}
function app_format_money($value,$currency=''){return(string)$value;}
function process_text_content_for_display($value){return e($value);}
function get_option($key){throw new RuntimeException('ambient option invoked by scoped invoice form: '.$key);}
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$actor=12;
$GLOBALS['essentialHooks']=new class{public function __call($name,$args){throw new RuntimeException('global hook invoked by scoped invoice form');}};
$ci=$GLOBALS['CI'];$ci->input=new class{public function get($key){return null;}};$ci->uri=new class{public function uri_string(){return 'admin/invoices/invoice';}};
$ci->load=new class($ci,$data){private array $data;public $load,$input,$uri;public function __construct($ci,$data){$this->data=$data;$this->load=$this;$this->input=$ci->input;$this->uri=$ci->uri;}public function view($path,$new=[]){$this->data=array_merge($this->data,$new);extract($this->data);include APPPATH.'views/'.$path.'.php';}};
ob_start();$ci->load->view('admin/invoices/invoice');$html=ob_get_clean();
foreach(['id="invoice-form"','name="clientid"','name="currency"','name="recurring"','name="_native_invoice_request_key"','name="_native_invoice_number_revision"','name="description"','validate_invoice_form();']as$marker)essentialCheck(str_contains($html,$marker),'original full new form retains '.$marker);
essentialCheck(!str_contains($html,'Private B')&&!str_contains($html,'company_invoices'),'original full form has no foreign data or parallel URL');
essentialCheck(substr_count($html,'<form ')===1&&substr_count($html,'</form>')===1,'create-only user has one actual invoice form, no unauthorized item-create modal');
essentialCheck(!str_contains($html,'<synthetic>')&&str_contains($html,'&lt;synthetic&gt;'),'actual tax option escapes its native attributes and label');
essentialCheck(str_contains($html,'id="cancel_overdue_reminders"')&&!str_contains($html,'PRIVATE TEMPLATE BODY'),'original reminder checkbox uses actual issuer capability without hydrating template content');
echo 'Original nested invoice form actual view: '.$GLOBALS['essentialChecks']." cumulative PASS; shell/formatting seams, no HTTP/browser proof.\n";
