<?php
/** Exact-money daily-interest profile and explicit contract; no bank requests. */
require __DIR__.'/banco_brasil_api_client.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/ApiDailyInterest.php';
use BancoBrasil\ApiDailyInterest;
apiCheck(ApiDailyInterest::declaration(['interest_exempt'=>'1'])===[],'legacy exemption profile retains byte-compatible definition');
apiCheck(ApiDailyInterest::declaration(['interest_policy'=>'exempt','interest_policy_confirmed'=>'1'])===[],'new explicit exempt form reuses existing profile');
apiReject(fn()=>ApiDailyInterest::declaration(['interest_policy'=>'daily_fixed']),'bb_api_contract_declaration_required');
apiReject(fn()=>ApiDailyInterest::declaration(['interest_exempt'=>'1','interest_policy'=>'daily_fixed','interest_policy_confirmed'=>'1']),'bb_api_contract_declaration_required');
$terms=ApiDailyInterest::declaration(['interest_policy'=>'daily_fixed','interest_policy_confirmed'=>'1'])+['interest_exempt'=>false];
apiCheck(ApiDailyInterest::parameters($terms,['daily_interest'=>'0.10'],10000)===['tipo'=>1,'valor'=>'0.10'],'daily fixed BRL amount remains exact');
apiCheck(ApiDailyInterest::amount('0.1')==='0.10','equivalent daily amount canonicalized before idempotency hash');
apiReject(fn()=>ApiDailyInterest::parameters([],['daily_interest'=>'0.10'],10000),'bb_api_interest_profile_denied');
apiReject(fn()=>ApiDailyInterest::parameters($terms,['daily_interest'=>'100.01'],10000),'bb_api_interest_exceeds_nominal');
foreach(['0','-0.01','1.001','1e2','NaN',' 1.00','01.00'] as $invalid)apiReject(fn()=>ApiDailyInterest::amount($invalid),'bb_api_interest_invalid');
$wire=ApiDailyInterest::encode(['jurosMora'=>['tipo'=>1,'valor'=>'0.10'],'other'=>'0.10']);apiCheck($wire==='{"jurosMora":{"tipo":1,"valor":0.10},"other":"0.10"}','only validated interest leaf becomes numeric JSON without float conversion');
$payload=BancoBrasil\ApiClient::decodeExact($wire);ApiDailyInterest::confirm(['codigoTipoJuroMora'=>'1','valorJuroMoraTitulo'=>'0.10','percentualJuroMoraTitulo'=>'0'],$payload);apiCheck(true,'account query confirms daily fixed interest type, amount and zero percent');
apiReject(fn()=>ApiDailyInterest::confirm(['codigoTipoJuroMora'=>'2','valorJuroMoraTitulo'=>'0.10','percentualJuroMoraTitulo'=>'0'],$payload),'bb_api_interest_query_mismatch');
apiReject(fn()=>ApiDailyInterest::confirm(['codigoTipoJuroMora'=>'1','valorJuroMoraTitulo'=>'0.11','percentualJuroMoraTitulo'=>'0'],$payload),'bb_api_interest_query_mismatch');
echo "BB fixed daily interest: {$n} cumulative contract checks; no external bank operations.\n";
