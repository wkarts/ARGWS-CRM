<?php
/** Render the real PIGE view with synthetic framework helpers and public data. */
define('BASEPATH',__DIR__);
function init_head(){} function init_tail(){}
function html_escape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function admin_url($path){return '/admin/'.$path;}
function module_dir_url($module,$path){return '/modules/'.$module.'/'.$path;}
function form_open($action,$attributes=[]){$html='<form method="post" action="'.html_escape($action).'">';foreach($attributes as $key=>$value)$html=substr($html,0,-1).' '.$key.'="'.html_escape($value).'">';return $html;}
function form_close(){return '</form>';}
$title='Synthetic PIGE settings';$pige_csrf=str_repeat('a',64);$company_context_token='synthetic-context';
$issuers=[['id'=>7,'legal_name'=>'Company A'],['id'=>8,'legal_name'=>'Company B </script><script>window.leaked=true</script>']];
$connections=[];$external=[];
for($i=0;$i<12;$i++){
    $key='school-'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
    $connections[]=['connection_key'=>$key,'scope'=>['school_id'=>'11111111-1111-4111-8111-111111111111','issuer_id'=>$i%2?'8':'7','account_id'=>'account-'.$i,'environment'=>'sandbox'],'revision'=>3,'enabled'=>true,'mapping'=>['enabled'=>true,'actor_id'=>7,'currency_id'=>1]];
    $external[$key]=['revision'=>2,'connection_revision'=>$i===0?2:3,'mapping'=>['provider'=>'asaas','account_row'=>4,'actor_id'=>7,'enabled'=>true,'allow_reconcile'=>false]];
}
require __DIR__.'/../modules/pige_bridge/views/settings.php';
