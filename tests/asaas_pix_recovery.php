<?php
declare(strict_types=1);
define('APPPATH',dirname(__DIR__).'/application/');
require dirname(__DIR__).'/modules/asaas/libraries/Asaas/AutomaticPixRecovery.php';
use Asaas\AutomaticPixRecovery;
$n=0;function verify($value,$message){global $n;if(!$value)throw new RuntimeException($message);++$n;}
function denies($fn,$code){try{$fn();}catch(DomainException $e){verify($e->getMessage()===$code,$code);return;}throw new RuntimeException('expected '.$code);}
$source=['customer_id'=>'cus_synthetic','contract_id'=>'crm_synthetic','frequency'=>'MONTHLY','start_date'=>'2026-11-10','amount'=>'105.53'];
$matching=['id'=>'auth_synthetic','status'=>'CREATED','frequency'=>'MONTHLY','contractId'=>'crm_synthetic','customerId'=>'cus_synthetic'];
$other=array_replace($matching,['id'=>'auth_other','contractId'=>'other','customerId'=>'other']);
$calls=[];$read=function($path)use(&$calls,$matching,$other){$calls[]=$path;return count($calls)===1?['data'=>[$other],'hasMore'=>true,'totalCount'=>2]:['data'=>[$matching],'hasMore'=>false,'totalCount'=>2];};
verify(AutomaticPixRecovery::find($source,$read)==='auth_synthetic','find matching contract across pages');verify($calls===['pix/automatic/authorizations?limit=50&offset=0','pix/automatic/authorizations?limit=50&offset=1'],'bounded account list offsets');
denies(fn()=>AutomaticPixRecovery::find($source,fn()=>['data'=>[],'hasMore'=>false,'totalCount'=>0]),'asaas_pix_recovery_not_found');
denies(fn()=>AutomaticPixRecovery::find($source,fn()=>['data'=>[$matching,array_replace($matching,['id'=>'auth_duplicate'])],'hasMore'=>false,'totalCount'=>2]),'asaas_pix_recovery_conflict');
denies(fn()=>AutomaticPixRecovery::find($source,fn()=>['data'=>[array_replace($matching,['customerId'=>'other'])],'hasMore'=>false,'totalCount'=>1]),'asaas_pix_recovery_conflict');
$i=0;denies(fn()=>AutomaticPixRecovery::find($source,function()use(&$i,$matching){return ['data'=>[$matching],'hasMore'=>true,'totalCount'=>++$i+1];}),'asaas_pix_recovery_incomplete');
$i=0;denies(fn()=>AutomaticPixRecovery::find($source,function()use(&$i,$matching){return ['data'=>[$matching],'hasMore'=>++$i<2,'totalCount'=>2];}),'asaas_pix_recovery_incomplete');
$i=0;denies(function()use(&$i,$source,$other){return AutomaticPixRecovery::find($source,function()use(&$i,$other){return ['data'=>[array_replace($other,['id'=>'auth_'.++$i])],'hasMore'=>true,'totalCount'=>1000];});},'asaas_pix_recovery_incomplete');verify($i===5,'hard page budget even without candidate');
denies(fn()=>AutomaticPixRecovery::find($source,fn()=>['data'=>[],'hasMore'=>true,'totalCount'=>2]),'asaas_pix_response_invalid');
echo "OK: {$n} automatic Pix recovery checks, synthetic readers only.\n";
