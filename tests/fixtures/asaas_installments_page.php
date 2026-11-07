<?php
const BASEPATH=__DIR__;
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$environmentLabel=fn($v)=>$v;
$hidden=function(array $values)use($e){foreach($values as $key=>$value)echo '<input type="hidden" name="'.$e($key).'" value="'.$e($value).'">';};
$action_url='/settings/asaas';$preferences_csrf='synthetic-csrf';$can_settle=!in_array($argv[1]??'',['readonly','batch_readonly'],true);$can_remove=$can_settle;
$installment_groups=[['id'=>10,'invoice_id'=>50,'batch_id'=>'group_synthetic','account_id'=>'account_synthetic','environment'=>'sandbox']];
$installment_panel=['origin_id'=>10,'group_id'=>'<img src=x onerror=alert(1)>','members'=>[
 ['number'=>1,'id'=>'pay_first','due_date'=>'2026-11-20','value'=>'40.00','status'=>'PENDING','payment_id'=>null],
 ['number'=>2,'id'=>'pay_second','due_date'=>'2026-12-20','value'=>'65.53','status'=>'RECEIVED','payment_id'=>20],
]];
if(($argv[1]??'')==='removing')$installment_panel['members'][0]['removal']=['state'=>'uncertain'];
if(in_array($argv[1]??'',['batch','batch_readonly'],true)){
 $installment_batch_review=['group_origin'=>10,'review_hash'=>str_repeat('a',64),'reserved'=>true,'members'=>[$installment_panel['members'][0]],'excluded'=>[['id'=>'<svg onload=alert(1)>','reason'=>'received_local']]];
 $installment_batch_result=['complete'=>false,'items'=>[['id'=>'pay_first','state'=>'uncertain']]];
}
echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Parcelas sintéticas</title><body>';
require __DIR__.'/../../application/views/admin/settings/native_asaas_installments.php';echo '</body></html>';
