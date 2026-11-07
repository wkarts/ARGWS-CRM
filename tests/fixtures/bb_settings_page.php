<?php
// Actual native view; synthetic contract/account references, no bank calls.
define('BASEPATH',__DIR__);require_once __DIR__.'/../../modules/banco_brasil/libraries/BancoBrasil/LayoutCatalog.php';
function html_escape($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function init_head(){echo '<html><head><meta charset="utf-8"></head><body>';}
function init_tail(){}function base_url($p){return '/'.$p;}
function form_open($url,$attrs=[]){return '<form method="post" action="'.html_escape($url).'"><input type="hidden" name="_company_context" value="synthetic-company">';}function form_close(){return '</form>';}
class CompanyRequestScope{static function adminUrl($ci,$p){return '/admin/'.$p;}}
class Fixture{function render(){
$selected=['id'=>1,'legal_name'=>'Synthetic company','tax_id'=>'11222333000181'];$connections=[['id'=>1,'account_id'=>'001:00452-3:000000123873-X','environment'=>'sandbox','agreement_number'=>'1234567']];$layouts=\BancoBrasil\LayoutCatalog::all();$layout_bindings=[['id'=>2,'connection_id'=>1,'layout_id'=>\BancoBrasil\Agreement::LAYOUT_ID],['id'=>3,'connection_id'=>1,'layout_id'=>\BancoBrasil\LayoutCatalog::CNAB400]];$can_manage=true;$api_ready=true;$api_profiles=[];$origination_ready=true;$channel_preferences=['api'=>true,'cnab'=>true,'revision'=>str_repeat('a',64)];$channel_preferences_error=false;$storage_error=false;$notice=null;$csrf=str_repeat('b',64);$bb_module_views=__DIR__.'/../../modules/banco_brasil/views/';
require __DIR__.'/../../application/views/admin/settings/native_banco_brasil.php';
}}(new Fixture)->render();
