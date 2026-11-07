<?php
const BASEPATH=__DIR__;
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$environmentLabel=fn($v)=>$v;
$hidden=function(array $values)use($e){foreach($values as $key=>$value)echo '<input type="hidden" name="'.$e($key).'" value="'.$e($value).'">';};
$action_url='/settings/asaas';$preferences_csrf='synthetic-csrf';$can_view_asaas_reports=($argv[1]??'')==='allowed';
$can_configure=$can_view_asaas_reports;
$pix_inbox=['workers'=>[],'events'=>[['id'=>1,'account_row'=>10,'event_type'=>'PIX_AUTOMATIC_RECURRING_AUTHORIZATION_ACTIVATED','remote_id'=>'<img src=x onerror=alert(1)>','state'=>'queued','attempts'=>0]]];
$accounts=[['id'=>10,'enabled'=>1,'verified_at'=>'2026-11-01','account_id'=>'wallet-synthetic','environment'=>'sandbox','revision'=>str_repeat('a',64)]];
$automatic_pix_monitor=['account_row'=>10,'account_revision'=>str_repeat('a',64),'filters'=>['status'=>'ACTIVE'],'page'=>['items'=>[['remote_id'=>'<img src=x onerror=alert(1)>','status'=>'ACTIVE','frequency'=>'MONTHLY']],'next_offset'=>50]];
echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pix sintético</title><body><ul id="asaas-settings-tabs"><li><a role="tab" id="tab-accounts" aria-controls="accounts" href="#accounts">Contas</a></li><li><a role="tab" id="tab-pix" aria-controls="automatic-pix" href="#automatic-pix">Pix Automático</a></li></ul><section id="accounts">Conta</section>';
require __DIR__.'/../../application/views/admin/settings/native_asaas_automatic_pix.php';echo '</body></html>';
