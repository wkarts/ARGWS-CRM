<?php
declare(strict_types=1);
require_once __DIR__.'/PortalRequestScope.php';
require_once __DIR__.'/NativePortalPreferences.php';
require_once __DIR__.'/utilities/Locale.php';
/** Explicit data/assets for the existing perfex template, not a second public theme. */
final class NativePortalPresentation
{
 public static function initialize($ci):array{
  if(!PortalRequestScope::sealedFor($ci))throw new DomainException('native_portal_context_required');
  $ci->output->set_header('Cache-Control: private, no-store, max-age=0')->set_header('Pragma: no-cache')->set_header('Vary: Cookie')->set_header('Referrer-Policy: no-referrer');
  $ci->load->helper(['url','form','general','func','fields']);$ci->load->library('form_validation');
  if(!class_exists('ForceUTF8\\Encoding'))require_once APPPATH.'vendor/autoload.php';
  $issuer=PortalRequestScope::issuerId();$store=new CompanySettingsStorage($ci->db,db_prefix());$options=(new NativePortalPreferences($ci->db,db_prefix()))->publicValues($issuer);
  if((new NativePortalPreferences($ci->db,db_prefix()))->captchaIgnored($issuer,(string)$ci->input->ip_address()))$options['use_recaptcha_customers_area']='0';
  $languages=self::languages();$configured=$store->get($issuer,'active_language');$selected=$ci->session->userdata('native_portal_language');$selected=is_array($selected)?($selected[$issuer]??null):null;
  $language=$options['disable_language']==='0'&&is_string($selected)&&in_array($selected,$languages,true)?$selected:(in_array($configured,$languages,true)?$configured:'portuguese_br');
  $fallback=$ci->lang->load('english_lang','english',true);$translated=$ci->lang->load($language.'_lang',$language,true);
  $ci->lang->language=array_replace(is_array($fallback)?$fallback:[],is_array($translated)?$translated:[]);$ci->lang->set_last_loaded_language($language);
  $locale='en';foreach(\app\services\utilities\Locale::app()as$name=>$code)if(strtolower($name)===strtolower($language)||str_starts_with(strtolower($language),strtolower($name).'_'))$locale=$code;$GLOBALS['language']=$language;$GLOBALS['locale']=$locale;
  $ci->form_validation->set_error_delimiters('<p class="text-danger alert-validation">','</p>');
  foreach(['companyname'=>'','enable_gdpr'=>'0','gdpr_show_terms_and_conditions_in_footer'=>'0','gdpr_enable_terms_and_conditions'=>'0','show_gdpr_link_in_footer'=>'0','company_requires_vat_number_field'=>'1','customer_default_country'=>'','rtl_support_client'=>'0']as$k=>$default)$options[$k]=$store->exists($issuer,$k)?$store->get($issuer,$k):$default;
  require_once __DIR__.'/CompanyAssets.php';$branding=(new CompanyAssets($ci->db,db_prefix()))->brandingForPortal($ci,$issuer);
  return['native_portal_logo'=>$branding['logo_dark']!==''?$branding['logo_dark']:($branding['logo']!==''?$branding['logo']:base_url('assets/images/argws/logo-dark.png')),'native_portal'=>true,'native_portal_issuer'=>$issuer,'native_portal_options'=>$options,'native_portal_csrf'=>PortalRequestScope::csrfToken($ci),'native_portal_identity'=>PortalRequestScope::identity(),'locale'=>$locale,'language'=>$language,'isRTL'=>($options['rtl_support_client']==='1'?'true':'false'),'native_portal_languages'=>$languages,'menu'=>self::anonymousMenu($options),'navigationEnabled'=>true,'subMenuEnabled'=>false];
 }
 public static function legal($ci,string$key):void{
  if(!PortalRequestScope::sealedFor($ci)||!in_array($key,['terms_and_conditions','privacy_policy'],true)||strtolower($ci->router->fetch_class())!==$key||strtolower($ci->router->fetch_method())!=='index'||!in_array($ci->input->method(true),['GET','HEAD'],true))throw new DomainException('native_portal_context_invalid');
  require_once __DIR__.'/NativeCompanyContentHtml.php';$issuer=PortalRequestScope::issuerId();$store=new CompanySettingsStorage($ci->db,db_prefix());$html=NativeCompanyContentHtml::purify($store->get($issuer,$key));
  if(!IssuerStorage::query($ci->db,'SELECT id FROM '.db_prefix().'issuers_registry WHERE id=? AND active=1',[$issuer])->row_array())throw new DomainException('native_portal_context_invalid');
  $ci->data([($key==='privacy_policy'?'policy':'terms')=>$html,'title'=>_l($key).' - '.$store->get($issuer,'companyname')]);$ci->view($key);$ci->layout();
 }
 public static function languages():array{
  $out=[];foreach(glob(APPPATH.'language/*',GLOB_ONLYDIR)?:[]as$dir){$name=basename($dir);if(preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$name)&&is_file($dir.'/'.$name.'_lang.php'))$out[]=$name;}sort($out,SORT_STRING);return$out;
 }
 public static function changeLanguage($ci,string$language):void{
  PortalRequestScope::assertMutation($ci);$issuer=PortalRequestScope::issuerId();$policy=(new NativePortalPreferences($ci->db,db_prefix()))->publicValues($issuer);
  if($policy['disable_language']!=='0'||!in_array($language,self::languages(),true))throw new DomainException('native_portal_language_denied');
  $all=$ci->session->userdata('native_portal_language');if(!is_array($all))$all=[];$all[$issuer]=$language;$ci->session->set_userdata(['native_portal_language'=>$all]);
 }
 public static function url(string$path=''):string{
  if(preg_match('/[\x00-\x20?#\\\\]/',$path)||str_contains($path,'..')||str_starts_with($path,'/'))throw new DomainException('native_portal_url_invalid');
  return site_url($path).'?issuer_id='.PortalRequestScope::issuerId();
 }
 private static function anonymousMenu(array$options):array{
  $menu=['login'=>['name'=>_l('clients_nav_login'),'href'=>self::url('authentication/login')]];
  if($options['allow_registration']==='1')$menu['register']=['name'=>_l('clients_register_string'),'href'=>self::url('authentication/register')];return$menu;
 }
 public static function css(bool$rtl=false):string{
  $paths=['assets/plugins/bootstrap/css/bootstrap.min.css','assets/plugins/inter/inter.css','assets/plugins/font-awesome/css/fontawesome.min.css','assets/plugins/font-awesome/css/solid.min.css','assets/plugins/bootstrap-select/css/bootstrap-select.min.css','assets/builds/tailwind.css','assets/themes/perfex/css/style.css'];
  if($rtl)array_splice($paths,1,0,['assets/plugins/bootstrap-arabic/css/bootstrap-arabic.min.css']);
  $out='';foreach($paths as$p)$out.='<link rel="stylesheet" href="'.htmlspecialchars(base_url($p),ENT_QUOTES,'UTF-8').'">';return$out;
 }
 public static function scripts(bool$captcha=false):string{
  $out='';foreach(['assets/plugins/bootstrap/js/bootstrap.min.js','assets/plugins/bootstrap-select/js/bootstrap-select.min.js']as$p)$out.='<script src="'.htmlspecialchars(base_url($p),ENT_QUOTES,'UTF-8').'"></script>';if($captcha)$out.='<script src="https://www.google.com/recaptcha/api.js" async defer></script>';return$out;
 }
 public static function cookiePath($ci):string{
  $base=$ci->config->item('base_url');$parts=is_string($base)?parse_url($base):false;if(!$parts||($parts['scheme']??'')!=='https'||!isset($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))throw new DomainException('native_portal_secure_origin_required');
  $path=$parts['path']??'/';return rtrim($path,'/').'/';
 }
}
