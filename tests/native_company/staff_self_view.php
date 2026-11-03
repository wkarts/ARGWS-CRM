<?php
require __DIR__.'/staff_self_endpoint.php';$db->query('UPDATE '.$p.'company_departments SET issuer_id=41 WHERE id=100');$data=NativeCompanyStaffEndpoints::ownPage($ci);$data['title']='Personal native profile';
foreach(['init_head','init_tail']as$name)if(!function_exists($name))eval('function '.$name.'(){echo "";}');
if(!function_exists('form_open_multipart')){function form_open_multipart($url,$attrs=[]){return form_open($url,$attrs);}}
if(!function_exists('form_open')){function form_open($url,$attrs=[]){return '<form action="'.htmlspecialchars($url,ENT_QUOTES).'">';}}
if(!function_exists('form_close')){function form_close(){return '</form>';}}
if(!function_exists('form_hidden')){function form_hidden($key,$value=''){return '<input type="hidden" name="'.htmlspecialchars($key,ENT_QUOTES).'" value="'.htmlspecialchars((string)$value,ENT_QUOTES).'">';}}
if(!function_exists('render_input')){function render_input($name,$label,$value='',$type='text',$attrs=[]){return '<input name="'.htmlspecialchars($name,ENT_QUOTES).'" value="'.htmlspecialchars((string)$value,ENT_QUOTES).'" type="'.$type.'">';}}
if(!function_exists('render_textarea')){function render_textarea($name,$label,$value='',$attrs=[]){return '<textarea name="'.$name.'">'.htmlspecialchars((string)$value,ENT_QUOTES).'</textarea>';}}
if(!function_exists('is_language_disabled')){function is_language_disabled(){return false;}}
if(!function_exists('_dt')){function _dt($value){return (string)$value;}}
if(!function_exists('time_ago')){function time_ago($value){return 'relative date';}}
if(!function_exists('staff_can')){function staff_can($cap,$feature=''){return false;}}
if(!function_exists('admin_url')){function admin_url($path=''){return '/admin/'.$path;}}
if(!function_exists('_l')){function _l($key){return$key;}}
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES);}}
$renderer=new class{public $uri;public $app;public function __construct(){$this->uri=new class{public function uri_string(){return 'admin/staff/edit_profile';}};$this->app=new class{public function __call($name,$args){throw new RuntimeException('Unexpected global App hydration');}};}public function render($data){extract($data);ob_start();require APPPATH.'views/admin/staff/profile.php';return ob_get_clean();}};
$html=$renderer->render($data);essentialCheck(str_contains($html,'staff_profile_table')&&str_contains($html,'staff_password_change_form')&&str_contains($html,'two_factor_auth_form'),'original profile password and 2FA forms retained');
essentialCheck(substr_count($html,'name="native_staff_self_revision"')===3&&str_contains($html,'two_factor_oldpassword'),'all original personal mutators carry CAS and 2FA reauthentication');
essentialCheck(str_contains($html,'Own department')&&!str_contains($html,'private-hash')&&!str_contains($html,'synthetic-third-password'),'native original view renders own department without stored credentials');
preg_match_all('~<script[^>]*>(.*?)</script>~s',$html,$matches);$path=sys_get_temp_dir().'/native-staff-self-view-'.bin2hex(random_bytes(8)).'.js';file_put_contents($path,implode("\n",$matches[1]));$output=[];$exit=0;exec('node --check '.escapeshellarg($path).' 2>&1',$output,$exit);unlink($path);essentialCheck($exit===0,'rendered original profile JavaScript parses: '.implode(' ',$output));
echo "Original personal profile: four render/Node checks; HTML helper primitives, not browser.\n";
