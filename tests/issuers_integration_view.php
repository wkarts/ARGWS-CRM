<?php
/** Render the actual native registry view: assignment controls now belong to Staff. */
define('BASEPATH',__DIR__);
require __DIR__.'/../application/services/issuers/IssuerPolicy.php';
require __DIR__.'/../application/language/portuguese_br/portuguese_br_lang.php';
function _l($key){return $GLOBALS['lang'][$key]??$key;}
function init_head(){} function init_tail(){}
function is_admin(){return $GLOBALS['view_admin'];}
function get_staff_user_id(){return 7;}
function admin_url($path){return '/admin/'.$path;}
function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function form_open($path){return '<form method="post" action="'.html_escape($path).'">';}
function form_close(){return '</form>';}
function issuers_can_access(...$args){return true;}
function renderIntegrationView($admin){
 $GLOBALS['view_admin']=$admin;
 $selected=['id'=>11,'legal_name'=>'Synthetic <Issuer>','tax_id'=>'12345678000195','active'=>1];
 $can_create=$admin;$can_manage=$admin;$profile=[];
 $catalog=$available=[$selected];$default_issuer=null;$issuers_csrf=str_repeat('a',64);$clients=$targets=[];
 ob_start();require __DIR__.'/../application/views/admin/issuers/manage.php';return ob_get_clean();
}
$n=0;function viewCheck($yes,$label){global$n;$n++;if(!$yes)throw new RuntimeException($label);}
$html=renderIntegrationView(true);
foreach(['member','remove_member','default'] as$action){viewCheck(!str_contains($html,'name="action" value="'.$action.'"'),'parallel assignment/default route removed: '.$action);}
viewCheck(!str_contains($html,'name="permissions[]"'),'generic company capability panel removed');
viewCheck(!str_contains($html,'value="manage_integrations"'),'generic integration capability not displayed');
viewCheck(str_contains($html,'href="/admin/staff"'),'assignment points to native Staff');
viewCheck(str_contains($html,'name="issuers_csrf" value="'.str_repeat('a',64).'"'),'company form retains CSRF');
viewCheck(str_contains($html,'name="issuer_id" value="11"'),'company target explicit');
viewCheck(str_contains($html,'Synthetic &lt;Issuer&gt;'),'issuer label escaped');
$staffView=renderIntegrationView(false);viewCheck(!str_contains($staffView,'href="/admin/staff"'),'ordinary user is not offered staff assignment');
echo "Issuer registry GUI: $n checks passed (actual view; local HTML helpers).\n";
