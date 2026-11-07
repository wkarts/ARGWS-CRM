<?php
const BASEPATH=__DIR__;
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$hidden=function(array $values)use($e){foreach($values as $key=>$value)echo '<input type="hidden" name="'.$e($key).'" value="'.$e($value).'">';};
$action_url='/settings/asaas';$preferences_csrf='synthetic-edit-csrf';$can_issue=$can_configure=($argv[1]??'')!=='denied';
$member=['id'=>'pay_one','number'=>1,'value'=>'40.00','due_date'=>'2026-12-10'];
$installment_edit_panel=['group_origin'=>10,'group_id'=>'<img src=x onerror=alert(1)>','members'=>[$member],'pending'=>null];
if(($argv[1]??'')==='review')$installment_edit_review=['key'=>str_repeat('a',64),'after'=>['pay_one'=>array_replace($member,['due_date'=>'2026-12-20'])]];
if(($argv[1]??'')==='pending')$installment_edit_panel['pending']=['key'=>str_repeat('b',64)];
echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Revisão sintética</title><body>';
require __DIR__.'/../../application/views/admin/settings/native_asaas_installment_edits.php';echo '</body></html>';
