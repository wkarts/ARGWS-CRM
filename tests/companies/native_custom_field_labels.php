<?php
/** Real scoped SQL definitions and both production renderer layers.
 * Language fallback and form-validation helpers are the framework seams;
 * render_input/date/datetime/textarea/color are the original implementations.
 */
require __DIR__.'/native_assigned_custom_fields.php';
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
class CompanyRequestScope
{
    public static int $issuer;
    public static function nativeFor($ci):bool{return true;}
    public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 51;}};}
}
CompanyRequestScope::$issuer=$a;$GLOBALS['CI']=(object)['db'=>$db];
if(!function_exists('_l')){function _l($key,...$args){return $key;}}
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
if(!function_exists('set_value')){function set_value($name,$value=''){return e($value);}}
if(!function_exists('form_error')){function form_error($name){return '';}}
if(!function_exists('is_client_logged_in')){function is_client_logged_in(){return false;}}
if(!function_exists('_d')){function _d($value){return (string)$value;}}
if(!function_exists('_dt')){function _dt($value){return (string)$value;}}
if(!function_exists('clear_textarea_breaks')){function clear_textarea_breaks($value){return $value;}}
require APPPATH.'helpers/fields_helper.php';
require APPPATH.'helpers/custom_fields_helper.php';
$start=$GLOBALS['essentialChecks'];
$grant=$access->assignments($a,7,101);$access->assign($a,7,101,[51],$grant['revision']);
$label='Rótulo <img src=x onerror="fixture()"> <script>fixture()</script> & "literal"';
$literalFields=[];
foreach(['input','number','date_picker','date_picker_time','textarea','colorpicker'] as $type){
    $definition=NativeCompanyCustomFields::definition(['fieldto'=>'contacts','name'=>$label.' '.$type,'type'=>$type,'required'=>1]);
    $db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'contacts','literal_label_'.$type,json_encode($definition,JSON_THROW_ON_ERROR)]);
    $literalFields[$type]=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
}
$html=render_custom_fields('contacts',$id);
$dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
try{$dom->loadHTML('<?xml encoding="UTF-8">'.$html);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
$xpath=new DOMXPath($dom);
essentialCheck($xpath->query('//img|//script|//svg|//*[@onerror or @onload or @onfocus]')->length===0,'all original primitive field renderers keep native labels inert');
foreach($literalFields as $type=>$field){
    $name='custom_fields[contacts]['.$field.']';
    $control=$xpath->query('//*[@name="'.$name.'"]')->item(0);
    essentialCheck($control instanceof DOMElement&&$control->getAttribute('data-fieldid')===$field&&$control->hasAttribute('data-custom-field-required'),'original '.$type.' control preserves its wire identity and required contract');
    $actual=$xpath->query('//label[@for="'.$name.'"]')->item(0);
    essentialCheck($actual instanceof DOMElement&&$actual->textContent===$label.' '.$type,'original '.$type.' label displays every character exactly once');
}
CompanyRequestScope::$issuer=$b;
essentialThrows(fn()=>render_custom_fields('contacts',$id),'native_custom_field_denied','literal label support cannot cross company ownership');
echo 'Native custom-field primitive labels: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
