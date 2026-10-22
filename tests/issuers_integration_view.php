<?php
/** Render the actual native registry view: assignment controls now belong to Staff. */
define('BASEPATH',__DIR__);
define('APPPATH',dirname(__DIR__).'/application/');
require __DIR__.'/../application/services/issuers/IssuerPolicy.php';
// Load the real presentation helpers without starting a request or resolving a company.
require_once APPPATH.'services/CompanyRequestScope.php';
require __DIR__.'/../application/language/portuguese_br/portuguese_br_lang.php';
function _l($key){return $GLOBALS['lang'][$key]??$key;}
function init_head(){} function init_tail(){}
function is_admin(){return $GLOBALS['view_admin'];}
function get_staff_user_id(){return 7;}
function admin_url($path){return '/admin/'.$path;}
function base_url($path){return '/'.ltrim($path,'/');}
function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function form_open($path){return '<form method="post" action="'.html_escape($path).'">';}
function form_close(){return '</form>';}
function issuers_can_access(...$args){return true;}
final class IssuerIntegrationViewFixture {
 public object $config;
 public object $input;
 public function __construct(){
  $this->input=new class {public function get($key,$x=false){return null;}};
  $this->config=new class {public function site_url($path){return '/'.ltrim($path,'/');}};
 }
 public function render($admin){
  $GLOBALS['view_admin']=$admin;
  $selected=['id'=>11,'legal_name'=>'Synthetic <Issuer>','tax_id'=>'12345678000195','active'=>1];
  $can_create=$admin;$can_manage=$admin;$profile=[];$status_permissions=[11=>$admin];
  $catalog=$available=[$selected];$default_issuer=null;$issuers_csrf=str_repeat('a',64);$clients=$targets=[];
  // The framework renders these real partials with an application object as $this.
  ob_start();
  try {require APPPATH.'views/admin/issuers/manage.php';return ob_get_contents();}
  finally {ob_end_clean();}
 }
}
function renderIntegrationView($admin){return (new IssuerIntegrationViewFixture())->render($admin);}
$n=0;function viewCheck($yes,$label){global$n;$n++;if(!$yes)throw new RuntimeException($label);}
$html=renderIntegrationView(true);
foreach(['member','remove_member','default'] as$action){viewCheck(!str_contains($html,'name="action" value="'.$action.'"'),'parallel assignment/default route removed: '.$action);}
viewCheck(!str_contains($html,'name="permissions[]"'),'generic company capability panel removed');
viewCheck(!str_contains($html,'value="manage_integrations"'),'generic integration capability not displayed');
viewCheck(str_contains($html,'data-company-registry'),'native authorized registry grid rendered');
viewCheck(str_contains($html,'data-new-company'),'creation entry is a modal button');
viewCheck(str_contains($html,'name="company-create-frame"'),'wizard is contained in modal');
viewCheck(str_contains($html,'name="issuers_csrf" value="'.str_repeat('a',64).'"'),'company form retains CSRF');
viewCheck(str_contains($html,'name="issuer_id" value="11"'),'company target explicit');
viewCheck(str_contains($html,'Synthetic &lt;Issuer&gt;'),'issuer label escaped');
viewCheck(!str_contains($html,'data-company-identity'),'grid has no parallel company editor');
viewCheck(str_contains($html,'src="/assets/js/company-registry.js"'),'grid asset uses URL helper');
viewCheck(str_contains($html,'name="action" value="issuer_status"'),'status retained without identity editor');
viewCheck(str_contains($html,'name="action" value="show"'),'wizard initial request is explicit read-only POST');
$staffView=renderIntegrationView(false);viewCheck(!str_contains($staffView,'href="/admin/staff"'),'ordinary user is not offered staff assignment');
viewCheck(!str_contains($staffView,'data-company-identity'),'ordinary user is not offered the identity editor');
echo "Issuer registry GUI: $n checks passed (actual view; local HTML helpers).\n";
