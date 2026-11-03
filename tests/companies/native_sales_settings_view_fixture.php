<?php
/** Standalone view fixture; no session, database or native route admission. */
if(PHP_SAPI!=='cli')exit(2);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require APPPATH.'services/NativeCompanySalesSettings.php';
function _l($key,...$unused){return $key;}
function set_value($name,$value=''){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function get_option($key){throw new LogicException('Global option consulted: '.$key);}
function get_custom_fields($key){throw new LogicException('Global field catalog consulted');}
function total_rows(...$unused){throw new LogicException('Global table consulted');}
function clear_textarea_breaks($value){return $value;}
require APPPATH.'helpers/fields_helper.php';
$group=$argv[1]??'';$company=$argv[2]??'';
if(!in_array($group,['estimates','proposals'],true)||!in_array($company,['a','b'],true))exit(2);
$values=NativeCompanySalesSettings::defaults($group);
if($group==='estimates'){
 $values['estimate_prefix']=$company==='a'?'EST-A-':'EST-B-';$values['show_project_on_estimate']=$company==='a'?'0':'1';$native_settings_estimate_values=$values;
}else{
 $values['proposal_number_prefix']=$company==='a'?'PROP-A-':'PROP-B-';$values['show_project_on_proposal']=$company==='a'?'0':'1';$native_settings_proposal_values=$values;
 $native_settings_proposal_field_definitions=$company==='a'?[['name'=>'Campo privado A','id'=>'fixture-a','show_on_client_portal'=>0]]:[];
}
?><!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/plugins/bootstrap/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/css/style.min.css"></head><body><main class="container-fluid"><form id="settings-form"><?php include APPPATH.'views/admin/settings/includes/'.$group.'.php'; ?></form></main></body></html>
