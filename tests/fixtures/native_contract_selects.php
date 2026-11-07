<?php
// Presentation fixture only; real view and assets, no database or authentication claims.
define('BASEPATH',dirname(__DIR__,2).'/system/');
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function _l($s){return $s;}
function admin_url($s){return '/admin/'.$s;}
function form_open($url,$attrs){$a='';foreach($attrs as$k=>$v)$a.=' '.e($k).'="'.e($v).'"';return '<form action="'.e($url).'"'.$a.'>';}
function form_close(){return '</form>';}
function render_custom_fields(...$args){return '';}
class NativeCompanyFormatting {function __construct(...$args){}function date($s){return $s;}}
class CompanySettingsFormats {static function dates(){return ['Y-m-d'=>'Y-m-d'];}}
$contract=null;$contract_form_format=['dateformat'=>'Y-m-d','time_format'=>'24'];$contract_form_client=null;$contract_form_project=null;$createUrl='/admin/contracts/contract';$hidden=function(){};$contract_id=null;$contract_form_fields_hash='synthetic';$contract_form_fields=[];$clients=[];$contract_form_can_projects=true;$contract_currencies=[['name'=>'BRL']];$contract_form_base_currency='BRL';$comment_admin=true;$contract_types=[];$native_contracts_csrf='synthetic';
include dirname(__DIR__,2).'/application/views/admin/contracts/native_form.php';
