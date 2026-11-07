<?php
// Actual view, synthetic company data, no database/provider/production session.
define('BASEPATH',__DIR__);function init_head(){echo '<html><head><meta charset="utf-8"></head><body>';}
function init_tail(){}function base_url($p){return '/'.$p;}
function form_open($url,$attrs=[]){$s='<form method="post" action="'.htmlspecialchars($url).'"';foreach($attrs as$k=>$v)$s.=' '.$k.'="'.htmlspecialchars($v).'"';return$s.'><input type="hidden" name="_company_context" value="fixture-company"><input type="hidden" name="native_csrf" value="fixture-csrf">';}
function form_close(){return '</form>';}
class CompanyRequestScope{static function adminUrl($ci,$p){return '/admin/'.$p;}}
class Fixture{function render(){
$directory=[];foreach([1,2,3]as$id)$directory[]=['id'=>$id,'state'=>'active','customer'=>'Synthetic customer','currency'=>'BRL','next_date'=>'2027-01-01','payload'=>['items'=>[['description'=>'Reviewed line','qty'=>'2.00','rate'=>'10.00']]],'links'=>$id===3?[]:[0=>['item_id'=>'101','revision'=>str_repeat('a',64)]],'payload_hash'=>str_repeat('b',64),'source_hash'=>str_repeat('c',64),'payment_modes'=>['bank']];
$history=[];$proposal=null;$error=null;$can_manage=true;$actor=7;$csrf=str_repeat('d',64);$url='/admin/invoices/recurring?reprice=1';
if(($GLOBALS['argv'][1]??'')==='blocked')$proposal=['state'=>'blocked','block_reason'=>'source_changed','created_at'=>'2027-01-02 12:00:00','revision'=>str_repeat('e',64),'request_key'=>str_repeat('f',64),'execute_at'=>'2027-01-02 13:00:00','actor_id'=>7,'preview'=>['execute_local'=>'2027-01-02T10:00','timezone'=>'America/Sao_Paulo','rules'=>[],'targets'=>[]]];
require __DIR__.'/../../application/views/admin/invoices/native_repricing.php';
}}(new Fixture)->render();
