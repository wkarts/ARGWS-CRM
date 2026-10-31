<?php
/** Production helpers + real SQLite ownership; rendering primitive is a deterministic input seam. */
require __DIR__.'/native_assigned_custom_fields.php';
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
if(!class_exists('CompanyRequestScope',false)){
    class CompanyRequestScope{
        public static int $issuer;public static int $actor=51;
        public static function nativeFor($ci){return true;}
        public static function context(){return new class{public function issuerId(){return CompanyRequestScope::$issuer;}public function staffId(){return CompanyRequestScope::$actor;}};}
    }
}
CompanyRequestScope::$issuer=$a;$GLOBALS['CI']=(object)['db'=>$db];
if(!function_exists('is_client_logged_in')){function is_client_logged_in(){return false;}}
if(!function_exists('render_input')){function render_input($name,$label,$value,$type,$attrs){return '<input name="'.htmlspecialchars($name,ENT_QUOTES).'" value="'.htmlspecialchars($value,ENT_QUOTES).'">';}}
if(!function_exists('form_error')){function form_error($name){return '';}}
require APPPATH.'helpers/custom_fields_helper.php';
$grant=$access->assignments($a,7,101);$access->assign($a,7,101,[51],$grant['revision']);
essentialCheck(get_custom_field_value($id,$ids['contacts'],'contacts',false)==='edited','actual value helper uses assigned resource rather than global definitions');
$html=render_custom_fields('contacts',$id);
essentialCheck(str_contains($html,'value="edited"')&&!str_contains($html,'Administrator private'),'actual render helper keeps assignment and private-definition exclusion');
$html=render_custom_fields('contacts',false,[],['native_purpose'=>'create','native_client_id'=>101]);
essentialCheck(str_contains($html,(string)$ids['contacts']),'actual creation helper uses explicit internally supplied parent');
essentialThrows(fn()=>render_custom_fields('contacts',false,[],['native_purpose'=>'create','native_client_id'=>202]),'native_custom_field_denied','other company parent cannot hydrate definitions');
essentialThrows(fn()=>get_custom_fields('contacts'),'native_custom_field_denied','resource grant does not open global helper');
$grant=$access->assignments($a,7,101);$access->assign($a,7,101,[],$grant['revision']);
essentialThrows(fn()=>get_custom_field_value($id,$ids['contacts'],'contacts',false),'native_custom_field_denied','revoked assignment blocks helper before value hydration');
echo "Native custom-field helper ownership, creation parent and revoked access passed.\n";
