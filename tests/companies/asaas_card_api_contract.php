<?php
require_once __DIR__.'/../../modules/asaas/libraries/Asaas/CompanyClient.php';
$count=0;$wire=[];
function cardCheck($ok,string $message):void{global $count;$count++;if(!$ok)throw new RuntimeException($message);}
function cardReject(callable $fn,string $message):void{try{$fn();}catch(DomainException $e){cardCheck(true,$message);return;}cardCheck(false,$message);}
$transport=static function($environment,$method,$path,$payload,$secret)use(&$wire){$wire[]=[$environment,$method,$path,$payload];return ['id'=>'pay_synthetic','status'=>'CONFIRMED'];};
$client=new Asaas\CompanyClient('sandbox','synthetic-api-key',$transport);
$legacy=['creditCardToken'=>'synthetic-token-only'];$modern=['cardType'=>'CREDIT','cardToken'=>'synthetic-token-only'];
foreach([['payWithCreditCard',$legacy],['payWithCard',$modern],['payWithCard',array_replace($modern,['cardType'=>'VOUCHER'])]] as [$operation,$payload]){
 $result=$client->request('POST','payments/pay_synthetic/'.$operation,$payload);
 cardCheck($result['status']==='CONFIRMED','transport preserves provider observation, not native settlement');
 cardCheck(end($wire)===['sandbox','POST','payments/pay_synthetic/'.$operation,$payload],'exact contract keys and endpoint');
}
$before=count($wire);
foreach([
 ['payWithCard',$legacy],['payWithCreditCard',$modern],['payWithCard',array_replace($modern,['cardType'=>'DEBIT'])],
 ['payWithCard',array_replace($modern,['cardToken'=>[]])],['payWithCreditCard',$legacy+['creditCard'=>[]]],
 ['payWithCreditCard',$legacy+['remoteIp'=>'127.0.0.1']],['payWithCard',$modern+['cvv'=>'']],
 ['payWithCard',array_replace($modern,['cardToken'=>"invalid\ntoken"])],['payWithCard',[]],
] as [$operation,$payload])cardReject(fn()=>$client->request('POST','payments/pay_synthetic/'.$operation,$payload),'mixed/unknown/raw card fields denied');
cardReject(fn()=>$client->request('GET','payments/pay_synthetic/payWithCard'),'GET cannot trigger card payment');
cardCheck(count($wire)===$before,'invalid contracts never reach transport');
$attempts=0;$uncertain=new Asaas\CompanyClient('sandbox','synthetic-api-key',static function()use(&$attempts){$attempts++;throw new RuntimeException('synthetic timeout');});
try{$uncertain->request('POST','payments/pay_synthetic/payWithCard',$modern);cardCheck(false,'timeout must fail');}catch(RuntimeException $e){cardCheck($e->getMessage()==='asaas_company_transport_unconfirmed','timeout exposes no token/provider exception');}
cardCheck($attempts===1,'nonidempotent payment POST is never automatically repeated');
echo 'Asaas token API contracts: '.$count.' checks; simulated transport only, no native card workflow or settlement claimed.'.PHP_EOL;
