<?php
/** Render production nested Sales views and custom-field helper; shell/primitive helpers are explicit seams. */
require __DIR__.'/native_sales_original_form.php';
class CompanyRequestScope{
    public static int $issuer;public static int $actor=12;public static bool $native=true,$sealed=true;
    public static function nativeFor($ci){return self::$native;}
    public static function sealedFor($ci){return self::$sealed;}
    public static function adminUrl($ci,$path){return admin_url($path);}public static function invalidateCaches($ci){}
    public static function assertNativeMutation($ci){if(!self::$sealed||strtoupper($ci->input->method(true))!=='POST'||$ci->input->post('_native_company_csrf',false)!=='synthetic-scope-csrf')throw new DomainException('native_company_csrf_invalid');if($ci->input->post('_company_context',false)!=='synthetic-company-'.self::$issuer)throw new DomainException('company_context_mismatch');}
    public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}};}
}
class NativeCompanyAdminPresentation{public static function label($key){return$key;}}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function init_head(){echo '<!-- explicit test shell -->';}function init_tail(){}
function is_client_logged_in(){return false;}
function get_staff_user_id(){return CompanyRequestScope::$actor;}
function staff_can($cap,$feature){return(new IssuerResourceAccess($GLOBALS['db'],$GLOBALS['p']))->can(CompanyRequestScope::$issuer,CompanyRequestScope::$actor,$feature,$cap);}
function form_open($path,$attrs=[]){return '<form method="post" action="'.e($path).'" id="'.e($attrs['id']??'').'" class="'.e($attrs['class']??'').'">';}
function form_close(){return '</form>';}
function form_hidden($name,$value=''){return '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}
function form_error($name){return '';}
function salesViewAttrs($attrs){$out='';foreach($attrs as$name=>$value)$out.=' '.e($name).'="'.e($value).'"';return$out;}
function render_input($name,$label='',$value='',$type='text',$attrs=[]){return '<input id="'.e($name).'" type="'.e($type).'" name="'.e($name).'" value="'.e($value).'"'.salesViewAttrs($attrs).'>';}
function render_date_input($name,$label='',$value='',$attrs=[]){return render_input($name,$label,$value,'text',$attrs);}
function render_datetime_input($name,$label='',$value='',$attrs=[]){return render_input($name,$label,$value,'text',$attrs);}
function render_textarea($name,$label='',$value='',$attrs=[]){return '<textarea name="'.e($name).'"'.salesViewAttrs($attrs).'>'.e($value).'</textarea>';}
function render_select($name,$options,$keys,$label='',$selected='',$attrs=[]){$out='<select name="'.e($name).'"'.salesViewAttrs($attrs).'>';foreach($options as$option){$title=is_array($keys[1])?implode(' ',array_map(fn($key)=>$option[$key],$keys[1])):$option[$keys[1]];$out.='<option value="'.e($option[$keys[0]]).'"'.((string)$selected===(string)$option[$keys[0]]?' selected':'').'>'.e($title).'</option>';}return$out.'</select>';}
function _d($value){return$value;}function _dt($value){return$value;}
function admin_url($path=''){return '/admin/'.$path;}
function get_option($key){throw new RuntimeException('ambient option used by original Sales form: '.$key);}
function get_all_countries(){return[];}function get_country_short_name($id){if($id)return 'Synthetic country';return '';}
function app_format_number($value){return(string)$value;}function app_format_money($value,$currency=''){return(string)$value;}
function process_text_content_for_display($value){return e($value);}
function slug_it($value){return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/','-',$value)),'-');}
function app_generate_hash(){static $counter=0;return hash('sha256','synthetic-control-'.++$counter);}
function set_checkbox($name,$value,$default=false){return$default?' checked="checked"':'';}
function format_estimate_status($status,$classes='',$label=true){return (string)$status;}
function format_proposal_status($status,$classes='',$label=true){return (string)$status;}
function is_sale_discount($row,$mode){return$mode==='percent'?(float)$row->discount_percent>0:(float)$row->discount_total>0&&(float)$row->discount_percent===0.0;}
function is_sale_discount_applied($row){return(float)$row->discount_total>0;}
$GLOBALS['essentialHooks']=new class{public function __call($name,$args){throw new RuntimeException('global hook used by scoped Sales form');}};
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12',[$a]);
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=12',[$a]);
foreach(['estimates','proposals']as$kind)foreach(['view','create','edit']as$cap)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$a,$kind,$cap]);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'customers','view')",[$a]);
$GLOBALS['CI']=(object)['db'=>$db];CompanyRequestScope::$issuer=$a;
require APPPATH.'helpers/custom_fields_helper.php';
class OriginalSalesViewLoader{
    public $load;public $input;public $uri;
    public function __construct(private array $data,private string $path){$this->load=$this;$this->input=new class{public function get($key){return null;}};$this->uri=new class($path){public function __construct(private string $path){}public function uri_string(){return$this->path;}};}
    public function view($path,$extra=[],$return=false){$this->data=array_merge($this->data,$extra);extract($this->data);if($return)ob_start();include APPPATH.'views/'.$path.'.php';if($return)return ob_get_clean();}
}
$viewStart=$GLOBALS['essentialChecks'];$originalRendered=[];
function originalSalesSuccessfulControls(string $html,string $formId):array{
    $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);try{$dom->loadHTML('<?xml encoding="UTF-8">'.$html);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}$xpath=new DOMXPath($dom);$pairs=[];
    foreach($xpath->query('//form[@id="'.$formId.'"]//input[@name]|//form[@id="'.$formId.'"]//textarea[@name]|//form[@id="'.$formId.'"]//select[@name]')as$node){
        $name=$node->getAttribute('name');if($name==='')continue;if(in_array($node->getAttribute('type'),['checkbox','radio'],true)&&!$node->hasAttribute('checked'))continue;
        if($node->nodeName==='select'){$selected=$xpath->query('option[@selected]',$node);if(!$selected->length&&!$node->hasAttribute('multiple'))$selected=$xpath->query('option[1]',$node);foreach($selected as$option)$pairs[]=rawurlencode($name).'='.rawurlencode($option->getAttribute('value'));}
        else$pairs[]=rawurlencode($name).'='.rawurlencode($node->nodeName==='textarea'?$node->textContent:$node->getAttribute('value'));
    }
    parse_str(implode('&',$pairs),$wire);return[$wire,$xpath];
}
foreach(['estimates'=>'estimate','proposals'=>'proposal']as$kind=>$type){
    foreach(['edit','create']as$mode){
        $id=$mode==='edit'?NativeCompanyResourceId::encode('company_'.$type,$originalSources[$kind]):'';
        $data=$originalForms[$kind]->data($a,12,$id,$ca);$loader=new OriginalSalesViewLoader($data,'admin/'.$kind.'/'.$type.($id!==''?'/'.$id:''));$GLOBALS['CI']->load=$loader;
        ob_start();try{$loader->view('admin/'.$kind.'/'.$type);$html=ob_get_clean();}catch(Throwable $error){ob_end_clean();throw$error;}$originalRendered[$kind][$mode]=$html;
        foreach(['id="'.$type.'-form"','name="currency"','name="_native_sales_request_key"','name="_native_sales_number_revision"','name="description"','name="status"',"native_sales_original_init('".$kind."')"]as$marker)essentialCheck(str_contains($html,$marker),'original '.$kind.' '.$mode.' retains '.$marker);
        essentialCheck(substr_count($html,'<form ')===1&&substr_count($html,'</form>')===1,'original '.$kind.' '.$mode.' has one form without unauthorized item-create modal');
        essentialCheck(!str_contains($html,'Private B')&&!str_contains($html,'name="issuer_id"'),'original '.$kind.' '.$mode.' never renders another company or context override');
        essentialCheck(!str_contains($html,'VAT "<safe>')&&str_contains($html,'&lt;safe&gt;'),'original '.$kind.' '.$mode.' escapes actual tax text in option attributes');
        [$actualWire,$xpath]=originalSalesSuccessfulControls($html,$type.'-form');
        essentialCheck($xpath->query('//tr[@class="main"]//td[contains(@class,"custom_field")]//*[@name and string-length(@name)>0]')->length===0&&!array_filter(array_keys($actualWire),'is_int'),'original '.$kind.' '.$mode.' custom-field preview contributes no successful named controls');
        if($mode==='edit'){
            essentialCheck(str_contains($html,'name="newitems[1][description]"')&&str_contains($html,'name="newitems[2][description]"')&&!str_contains($html,'[itemid]'),'original '.$kind.' edit uses full canonical lines without fake persisted IDs');
            essentialCheck(str_contains($html,'Edited private line')&&str_contains($html,'Optional private value')&&str_contains($html,'name="_native_sales_reason"')&&str_contains($html,'name="_native_sales_confirmed"'),'original '.$kind.' edit keeps line fields and existing reviewed-write controls');
            // The production JS adds only the calculated totals and the operator completes these review controls.
            $actualWire['subtotal']=$data[$type]->subtotal;$actualWire['total']=$data[$type]->total;$actualWire['_native_sales_reason']='Actual rendered review';$actualWire['_native_sales_confirmed']='1';
            $parsed=NativeCompanySalesOriginalInput::parse($kind,$actualWire,'Y-m-d|%Y-%m-%d');
            essentialCheck($parsed['metadata']['line_fields'][0][$originalLineField]==='Edited private line'&&array_values(array_filter($parsed['metadata']['line_fields'][0][$originalChoiceField],fn($v)=>$v!=='cfk_hidden'))===['A','B'],'actual '.$kind.' rendered edit controls parse with complete repeated checkbox values');
        }
    }
}
essentialCheck(str_contains($originalRendered['estimates']['edit'],'name="billing_street"')&&str_contains($originalRendered['estimates']['edit'],'name="terms"')&&str_contains($originalRendered['estimates']['edit'],'name="adminnote"'),'original estimate keeps billing, terms and internal note controls');
essentialCheck(str_contains($originalRendered['proposals']['edit'],'name="subject"')&&str_contains($originalRendered['proposals']['edit'],'name="proposal_to"')&&str_contains($originalRendered['proposals']['edit'],'name="content"')&&str_contains($originalRendered['proposals']['edit'],'value="lead" disabled'),'original proposal keeps its own contact/content controls and visible unresolved Lead option');
echo 'Original Sales nested production views: '.($GLOBALS['essentialChecks']-$viewStart)." added checks; shell/helper seams, no visual-equivalence or browser claim.\n";
