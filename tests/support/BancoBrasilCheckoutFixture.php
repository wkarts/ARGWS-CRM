<?php
// Synthetic presentation fixture. Production views/helpers/gateway; no framework bootstrap, DB or provider.
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$root=dirname(__DIR__,2);define('BASEPATH',$root.'/system/');define('APPPATH',$root.'/application/');
$scenario=$argv[1]??'catalog';
if(!in_array($scenario,['catalog','admin','admin-unreviewed','portal','portal-unpublished','portal-other-invoice'],true))throw new RuntimeException('unknown scenario');
$options=[];$formDepth=0;$alerts=[];
$lang=[];require APPPATH.'language/portuguese_br/portuguese_br_lang.php';
function set_alert($kind,$message){$GLOBALS['alerts'][]=[$kind,$message];}
function html_escape($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function e($v){return html_escape($v);}
function _l($v,...$unused){return $GLOBALS['lang'][$v]??$v;}
function set_value($name,$value){return html_escape($value);}
function clear_textarea_breaks($text,$replace=''){return str_replace(['<br />','<br>','<br/>'],$replace,$text);}
function get_option($name){return (string)($GLOBALS['options'][$name]??'');}
function add_option($name,$value,...$unused){$GLOBALS['options'][$name]??=$value;}
function &get_instance(){static $ci;if(!$ci)$ci=(object)['input'=>new class{function get($key){return null;}},'uri'=>new class{function segment($key){return '';}}];return $ci;}
function hooks(){static $hooks;if(!$hooks)$hooks=new class{function add_action(...$args){}function do_action(...$args){}};return $hooks;}
function form_open($url,$attrs=[]){if($GLOBALS['formDepth']++!==0)throw new RuntimeException('nested production forms');return '<form method="post" action="'.html_escape($url).'"><input type="hidden" name="csrf_test_name" value="synthetic-csrf">';}
function form_close(){if(--$GLOBALS['formDepth']!==0)throw new RuntimeException('unbalanced production forms');return '</form>';}
require APPPATH.'libraries/gateways/App_gateway.php';
require $root.'/modules/banco_brasil/libraries/Banco_brasil_gateway.php';
$gateway=new Banco_brasil_gateway();
$defaults=array_column($gateway->getSettings(false),'default_value','name');
if($defaults['active']!==0||$defaults['default_selected']!==0)throw new RuntimeException('unsafe distributed defaults');
if(($argv[2]??'')==='--saved-enabled'){
 $options['paymentmethod_banco_brasil_active']='1';$options['paymentmethod_banco_brasil_default_selected']='1';
}
$payment_gateways=$gateway->initMode([]);
if(($argv[2]??'')==='--saved-enabled'&&($options['paymentmethod_banco_brasil_active']!=='1'||$options['paymentmethod_banco_brasil_default_selected']!=='1'))throw new RuntimeException('persisted switches rewritten');
if(count($payment_gateways)!==1||$payment_gateways[0]['id']!=='banco_brasil'||$payment_gateways[0]['active']!=='0'||$payment_gateways[0]['selected_by_default']!=='0')throw new RuntimeException('native catalog unsafe defaults');
if($gateway->process_payment([])!==false||count($alerts)!==1||$alerts[0][0]!=='warning')throw new RuntimeException('legacy gateway must fail safely without invoice access');
$instrument=['issuer_id'=>41,'origin_id'=>701,'invoice_id'=>501,'client_id'=>61,'profile_id'=>91,'environment'=>'sandbox','amount'=>'105.53','due_date'=>'2030-12-31','line'=>str_repeat('1',47),'barcode'=>str_repeat('1',44),'revision'=>str_repeat('a',64)];
if(str_starts_with($scenario,'portal')){
 $renderer=new class {public $config;function __construct(){$this->config=new class{function site_url($path){return '/'.$path;}};}function render($root,$scenario,$instrument){
 $issuer=41;$portal_csrf='synthetic-portal-csrf';$script_nonce='synthetic-nonce';$message='';$error='';
 $identity=['issuer_name'=>'Empresa sintética 41','firstname'=>'Contato','lastname'=>'Sintético','company'=>'Cliente sintético 61','email'=>'fixture@example.invalid'];
 $invoices=['items'=>[['id'=>501,'number'=>'FAT-501','date'=>'2030-12-01','duedate'=>'2030-12-31','currency'=>'BRL','total'=>'105.53','status'=>1]],'next'=>null];
 $bb_checkout=$scenario==='portal-unpublished'?[]:[501=>true];$bb_instrument=$scenario==='portal-unpublished'?null:$instrument;
 if($scenario==='portal-other-invoice')$bb_instrument['invoice_id']=999;
 require $root.'/application/views/company_portal/index.php';}};
 $renderer->render($root,$scenario,$instrument);
}else{
 echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
 foreach(['assets/css/reset.min.css','assets/builds/vendor-admin.css','assets/builds/tailwind.css','assets/css/style.min.css'] as $asset)echo '<link rel="stylesheet" href="/'.$asset.'">';
 echo '</head><body class="admin"><main class="content"><div class="panel_s"><div class="panel-body">';
 if($scenario==='catalog'){
 require APPPATH.'helpers/fields_helper.php';
 echo form_open('/fixture/settings');require APPPATH.'views/admin/settings/includes/payment_gateways.php';echo form_close();
 echo '<script src="/assets/plugins/jquery/jquery.min.js"></script><script src="/assets/plugins/bootstrap/js/bootstrap.min.js"></script>';
 }else{
 $selected=['id'=>41];$csrf='synthetic-bb-csrf';$checkout_preview=$scenario==='admin'?$instrument:null;
 $bbAdminUrl=static fn($path)=>'/admin/'.$path;
 $api_ready=true;$can_manage=true;$can_api_query=true;$can_api_issue=false;$can_api_cancel=false;$can_settle=false;
 $api_profiles=[];$api_operations=[];$api_receipts=[];$api_origins=[['id'=>701,'invoice_id'=>501,'profile_id'=>91,'remote_id'=>'12345678901234567','state'=>'api_registered']];
 require $root.'/modules/banco_brasil/views/api_operations.php';
 }
 echo '</div></div></main></body></html>';
}
if($formDepth!==0)throw new RuntimeException('unclosed production form');
