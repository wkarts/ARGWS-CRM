<?php
define('BASEPATH',__DIR__);
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$action_url='https://synthetic.invalid/admin/settings/asaas';$preferences_csrf=str_repeat('c',64);
$hidden=static function(array $values)use($e){$values+=['_company_context'=>'synthetic-context','csrf_test'=>'synthetic-csrf'];foreach($values as $key=>$value)echo '<input type="hidden" name="'.$e($key).'" value="'.$e($value).'">';};
$environmentLabel=static fn($v)=>$v==='sandbox'?'Homologação':'Produção';
$can_configure=$can_issue=$can_settle=true;
$pix_operations=[['id'=>11,'invoice_id'=>12,'environment'=>'sandbox','receipt'=>13]];
$binding=['root_origin'=>11,'schedule_id'=>14,'frequency'=>'MONTHLY','amount'=>'105.53','account_row'=>15,'bank_timezone'=>'America/Sao_Paulo','holidays'=>['2026-12-24']];
$pix_series=['binding'=>$binding,'revision'=>str_repeat('a',64),'stopped'=>($argv[1]??'')==='stopped'];
$pix_future_ready=true;$pix_future_worker=$pix_series['stopped']?['enabled'=>1,'revision'=>str_repeat('d',64),'last_code'=>'payment_observed']:null;
$pix_series_review=$pix_series['stopped']?null:['binding'=>$binding,'revision'=>str_repeat('a',64)];
echo '<!doctype html><html><head><meta charset="utf-8"><style>input,select,textarea{max-width:100%;box-sizing:border-box}label{display:block}form{margin-bottom:20px}body{overflow-wrap:anywhere}</style></head><body>';
require dirname(__DIR__,2).'/application/views/admin/settings/native_asaas_pix_future.php';
echo '</body></html>';
