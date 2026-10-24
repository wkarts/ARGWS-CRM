<?php
/** Pure synthetic projections; no SDK, transport or financial writer is executed. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__,2).'/system/');
require dirname(__DIR__,2).'/modules/gerencianet_gateway/services/CompanyProof.php';
$checks=0;function cpCheck(bool $ok,string $label):void{++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($label);}
$origin=['kind'=>'pix','provider_id'=>str_repeat('a',26),'amount_minor'=>1234];
$response=['txid'=>$origin['provider_id'],'status'=>'CONCLUIDA','valor'=>['original'=>'12.34'],'pix'=>[['txid'=>$origin['provider_id'],'valor'=>'12.34','endToEndId'=>str_repeat('E',32),'horario'=>'2026-10-23T15:00:00Z']]];
$proof=\Efi\CompanyProof::query($origin,$response);cpCheck($proof['receipt_eligible']&&$proof['date']==='2026-10-23','exact Pix settlement date');
foreach(['missing_date','partial','refund','multiple'] as $case){$bad=$response;if($case==='missing_date')unset($bad['pix'][0]['horario']);if($case==='partial')$bad['pix'][0]['valor']='1.00';if($case==='refund')$bad['pix'][0]['devolucoes']=[['id'=>'synthetic']];if($case==='multiple')$bad['pix'][]=$bad['pix'][0];cpCheck(!\Efi\CompanyProof::query($origin,$bad)['receipt_eligible'],$case.' cannot become financial authority');}
$card=['kind'=>'card','provider_id'=>'123','amount_minor'=>1234];$paid=['code'=>200,'data'=>['charge_id'=>123,'total'=>1234,'status'=>'paid','payment'=>['paid_at'=>'2026-10-23T15:00:00Z']]];
cpCheck(!\Efi\CompanyProof::query($card,$paid)['receipt_eligible'],'card paid is not settlement proof');
$boleto=$card;$boleto['kind']='boleto';cpCheck(!\Efi\CompanyProof::query($boleto,$paid)['receipt_eligible'],'missing nominal payment evidence denied');
// Deliberately synthetic complete projection, NOT a claim that detailCharge documents these fields.
$paid['data']['payment']+=['payment_method'=>'banking_billet','paid_value'=>1234];cpCheck(!\Efi\CompanyProof::query($boleto,$paid)['receipt_eligible'],'invented detail payload cannot establish undocumented payment units');
$paid['data']['status']='refunded';cpCheck(\Efi\CompanyProof::query($boleto,$paid)['adverse'],'adverse status retained');
try{\Efi\CompanyProof::query($origin,array_replace($response,['txid'=>str_repeat('b',26)]));throw new LogicException('wrong ID accepted');}catch(DomainException $expected){++$checks;}
cpCheck(!str_contains(json_encode($proof),'endToEndId'),'unnecessary provider receipt identifier omitted from public projection');
cpCheck(\Efi\CompanyProof::provider('boleto','99999999999999999999')==='99999999999999999999','canonical decimal provider ID never converted to saturating integer');
echo "Efí named proof projection: {$checks} checks PASS; synthetic evidence only\n";
