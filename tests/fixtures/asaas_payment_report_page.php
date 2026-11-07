<?php
const BASEPATH=__DIR__;
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$environmentLabel=fn($v)=>$v;
$hidden=function(array $values)use($e){foreach($values as $key=>$value)echo '<input type="hidden" name="'.$e($key).'" value="'.$e($value).'">';};
$action_url='/settings/asaas';$preferences_csrf='synthetic-csrf';$can_configure=($argv[1]??'')==='write';
$accounts=[['id'=>10,'enabled'=>1,'verified_at'=>'2026-11-01','account_id'=>'wallet-synthetic','environment'=>'sandbox','revision'=>str_repeat('a',64)]];
$remote_monitor=['account_row'=>10,'account_revision'=>str_repeat('a',64),'filters'=>['status'=>'RECEIVED','date_field'=>'due','date_from'=>'2026-01-01'],'page'=>['items'=>[['remote_id'=>'<img src=x onerror=alert(1)>','status'=>'RECEIVED','billing_type'=>'PIX','value'=>'10.01','origin_id'=>null,'invoice_id'=>null]],'next_offset'=>null]];
echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatório sintético</title><body>';
require __DIR__.'/../../application/views/admin/settings/native_asaas_remote_monitor.php';echo '</body></html>';
