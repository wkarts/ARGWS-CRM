<?php
const BASEPATH=__DIR__;
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$hidden=function(array $values)use($e){foreach($values as $key=>$value)echo '<input type="hidden" name="'.$e($key).'" value="'.$e($value).'">';};
$action_url='/settings/asaas';$preferences_csrf='synthetic-recreation-csrf';$can_remove=$can_issue=($argv[1]??'')!=='denied';
$installment_recreation_panel=['group_origin'=>10,'group_id'=>'<img src=x onerror=alert(1)>','amount'=>'105.53','count'=>2,'due_date'=>'2027-01-05','pending'=>null];
if(($argv[1]??'')==='review')$installment_recreation_review=['key'=>str_repeat('a',64),'count'=>3,'due_date'=>'2027-02-05'];
if(($argv[1]??'')==='pending')$installment_recreation_panel['pending']=['key'=>str_repeat('b',64)];
echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sucessão sintética</title><body>';
require __DIR__.'/../../application/views/admin/settings/native_asaas_installment_recreation.php';echo '</body></html>';
