<?php
require __DIR__.'/native_client_settings_policy.php';
define('BASEPATH',APPPATH.'../system/');
require APPPATH.'helpers/fields_helper.php';require APPPATH.'helpers/template_helper.php';
function _l($s,...$args){return$s;}function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}function set_value($key,$value=''){return$value;}function get_option($key){throw new LogicException('native settings must not read global options');}function form_hidden($name,$value){return'<input type="hidden" name="'.e($name).'" value="'.e($value).'">';}
$native_settings_clients=['values'=>array_replace(NativeCompanyClientsSettings::DEFAULTS,['customer_info_format'=>'{company_name}</textarea><script>bad()</script>','customer_default_country'=>'999']),'tabs'=>NativeCompanyClientsSettings::TABS,'themes'=>['perfex'],'countries'=>[['country_id'=>999,'short_name'=>'Synthetic country']],'estimate_request_forms'=>[['id'=>'123','name'=>'Synthetic estimate form']],'customer_field_definitions'=>[['id'=>42,'name'=>'<b>Field label</b>','show_on_client_portal'=>1]]];
$contacts_permissions=array_map(fn($id)=>['id'=>$id,'name'=>'Permission '.$id],range(1,6));
ob_start();require APPPATH.'views/admin/settings/includes/clients.php';$html=ob_get_clean();
preg_match_all('/name="settings\[([^\]]+)\](?:\[\])?"/',$html,$m);$keys=array_values(array_unique($m[1]));sort($keys);$expected=array_merge(NativeCompanyClientsSettings::fields(),['customer_settings']);sort($expected);check($keys===$expected,'same original form exposes all 18 product controls plus marker');
check(str_contains($html,'Synthetic estimate form')&&str_contains($html,'value="123"'),'original EstimateRequest selector receives real catalog DTO, not fabricated empty options');
check(str_contains($html,'value="999" selected')&&str_contains($html,'Synthetic country'),'original country selector keeps explicit selected issuer value');
check(!str_contains($html,'<script>bad()')&&str_contains($html,'&lt;/textarea&gt;')&&str_contains($html,'&lt;b&gt;Field label&lt;/b&gt;'),'format and custom labels cannot terminate controls or execute markup');
check(substr_count($html,'type="radio"')===22&&substr_count($html,'name="settings[default_contact_permissions][]"')===6,'same native boolean and six contact permissions controls retained');
echo "Native Clients settings original view: 5 checks passed with real render helpers; catalog DTO is an explicit boundary fixture.\n";
