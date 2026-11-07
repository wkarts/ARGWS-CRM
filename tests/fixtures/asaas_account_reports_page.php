<?php
const BASEPATH=__DIR__;define('APP_MODULES_PATH',__DIR__.'/../../modules/');
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$environmentLabel=fn($v)=>$v;
$hidden=function(array $values)use($e){foreach($values as $key=>$value)echo '<input type="hidden" name="'.$e($key).'" value="'.$e($value).'">';};
$action_url='/settings/asaas';$preferences_csrf='synthetic-csrf';$can_view_account_reports=($argv[1]??'')!=='denied';$can_export_account_reports=($argv[1]??'')==='export';
$accounts=[['id'=>10,'enabled'=>1,'verified_at'=>'2026-11-01','account_id'=>'wallet-synthetic','environment'=>'sandbox','revision'=>str_repeat('a',64)]];
$account_report=['kind'=>'balance','account_row'=>10,'account_revision'=>str_repeat('a',64),'filters'=>[],'page'=>['environment_label'=>'Homologação','started_at'=>'2026-11-07','columns'=>[['key'=>'balance','label'=>'Saldo','visible'=>true]],'rows'=>[['balance'=>'<img src=x onerror=alert(1)>']],'next_offset'=>null]];
echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatórios sintéticos</title><body>';
require __DIR__.'/../../application/views/admin/settings/native_asaas_account_reports.php';echo '</body></html>';
