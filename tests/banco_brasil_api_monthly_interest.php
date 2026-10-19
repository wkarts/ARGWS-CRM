<?php
/** Exact percentage publication only; no local monthly accrual or financial receipt formula. */
require __DIR__.'/banco_brasil_api_interest.php';
use BancoBrasil\ApiDailyInterest;
$monthly=ApiDailyInterest::declaration(['interest_policy'=>'monthly_percentage','interest_policy_confirmed'=>'1'])+['interest_exempt'=>false];
apiCheck(ApiDailyInterest::parameters($monthly,['monthly_interest_percentage'=>'1.25'],10000)===['tipo'=>2,'porcentagem'=>'1.25'],'monthly percentage is a distinct declared policy with official type2');
apiCheck(ApiDailyInterest::monthlyRate('1.2')==='1.20','equivalent percentages normalize exactly before request hash');
foreach(['0','0.00','-1','100.01','1.001','1e2',' 1.25','01.25',1.25] as $invalid)apiReject(fn()=>ApiDailyInterest::monthlyRate($invalid),'bb_api_interest_invalid');
apiReject(fn()=>ApiDailyInterest::parameters([],['monthly_interest_percentage'=>'1.25'],10000),'bb_api_interest_profile_denied');
apiReject(fn()=>ApiDailyInterest::parameters($terms,['monthly_interest_percentage'=>'1.25','daily_interest'=>'0.10'],10000),'bb_api_interest_profile_denied');
apiReject(fn()=>ApiDailyInterest::parameters($monthly,['monthly_interest_percentage'=>'1.25','daily_interest'=>'0.10'],10000),'bb_api_interest_profile_denied');
$monthlyWire=ApiDailyInterest::encode(['jurosMora'=>['tipo'=>2,'porcentagem'=>'1.25'],'other'=>'1.25']);apiCheck($monthlyWire==='{"jurosMora":{"tipo":2,"porcentagem":1.25},"other":"1.25"}','only validated monthly rate leaf becomes exact JSON number');
$monthlyPayload=BancoBrasil\ApiClient::decodeExact($monthlyWire);ApiDailyInterest::confirm(['codigoTipoJuroMora'=>'2','valorJuroMoraTitulo'=>'0','percentualJuroMoraTitulo'=>'1.25'],$monthlyPayload);apiCheck(true,'query proves type2, exact monthly rate and no fixed daily amount');
foreach([['codigoTipoJuroMora'=>'1','valorJuroMoraTitulo'=>'0','percentualJuroMoraTitulo'=>'1.25'],['codigoTipoJuroMora'=>'2','valorJuroMoraTitulo'=>'0.01','percentualJuroMoraTitulo'=>'1.25'],['codigoTipoJuroMora'=>'2','valorJuroMoraTitulo'=>'0','percentualJuroMoraTitulo'=>'1.26'],['codigoTipoJuroMora'=>'2','percentualJuroMoraTitulo'=>'1.25']] as $mismatch)apiReject(fn()=>ApiDailyInterest::confirm($mismatch,$monthlyPayload),'bb_api_interest_query_mismatch');
echo 'BB monthly percentage contract: '.$n.' cumulative checks; no accrual or bank operations.'.PHP_EOL;
