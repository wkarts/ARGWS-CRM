<?php
const BASEPATH=__DIR__;
define('APPPATH',__DIR__.'/../../application/');
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$environmentLabel=fn($v)=>$v;
$hidden=function(array $values)use($e){foreach($values as $key=>$value)echo '<input type="hidden" name="'.$e($key).'" value="'.$e($value).'">';};
$action_url='/settings/asaas';$preferences_csrf='synthetic-csrf';$can_view_asaas_reports=in_array($argv[1]??'',['allowed','operations'],true);
$can_configure=$can_view_asaas_reports;
$pix_inbox=['workers'=>[],'events'=>[['id'=>1,'account_row'=>10,'event_type'=>'PIX_AUTOMATIC_RECURRING_AUTHORIZATION_ACTIVATED','remote_id'=>'<img src=x onerror=alert(1)>','state'=>'queued','attempts'=>0]]];
$accounts=[['id'=>10,'enabled'=>1,'verified_at'=>'2026-11-01','account_id'=>'wallet-synthetic','environment'=>'sandbox','revision'=>str_repeat('a',64)]];
$automatic_pix_monitor=['account_row'=>10,'account_revision'=>str_repeat('a',64),'filters'=>['status'=>'ACTIVE'],'page'=>['items'=>[['remote_id'=>'<img src=x onerror=alert(1)>','status'=>'ACTIVE','frequency'=>'MONTHLY']],'next_offset'=>50]];
if(in_array($argv[1]??'',['operations','operations-denied'],true)){
 $can_issue=$can_settle=$can_configure;
 $accounts[0]['webhook_configured']=true;$activeAccount=$accounts[0];
 $invoice=['id'=>8,'revision'=>str_repeat('b',64),'request_key'=>str_repeat('c',64),'data'=>['number'=>'INV-8']];
 $pix_operations=$can_configure?[['id'=>4,'invoice_id'=>8,'environment'=>'sandbox','state'=>'created','remote_id'=>'pay_test','receipt'=>null,'initial_proof'=>['id'=>9,'status'=>'RECEIVED','amount'=>'10.00','net_value'=>'9.00','fee'=>'1.00','payment_date'=>'2026-11-07']]]:[];
 $pix_receipt_jobs=$can_configure?['workers'=>[],'jobs'=>[['event_id'=>7,'origin_id'=>4,'state'=>'queued','last_code'=>'awaiting_canonical_identity','attempts'=>0]]]:null;
}
echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pix sintético</title><body><ul id="asaas-settings-tabs"><li><a role="tab" id="tab-accounts" aria-controls="accounts" href="#accounts">Contas</a></li><li><a role="tab" id="tab-pix" aria-controls="automatic-pix" href="#automatic-pix">Pix Automático</a></li></ul><section id="accounts">Conta</section>';
require __DIR__.'/../../application/views/admin/settings/native_asaas_automatic_pix.php';echo '</body></html>';
