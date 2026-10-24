<?php
require __DIR__.'/../modules/asaas/libraries/Asaas/CompanyInstruments.php';
require __DIR__.'/../modules/asaas/libraries/Asaas/CompanyClient.php';
function instrumentCheck($ok,$label){if(!$ok)throw new RuntimeException($label);}
function instrumentReject(callable $call){try{$call();}catch(DomainException $e){return;}throw new RuntimeException('expected invalid instrument');}
$pix=['payload'=>'000201synthetic-only-fixture-not-payable','expirationDate'=>'2030-12-31 23:59:59','encodedImage'=>'ignored'];
instrumentCheck(!isset(\Asaas\CompanyInstruments::parse('PIX',$pix)['encodedImage']),'image never returned');
foreach([['payload'=>"bad\ntext"],['expirationDate'=>'2000-01-01 00:00:00'],['expirationDate'=>'2030-02-30 10:00:00'],['payload'=>str_repeat('x',2049)]] as $bad)instrumentReject(fn()=>\Asaas\CompanyInstruments::parse('PIX',array_replace($pix,$bad)));
$boleto=['identificationField'=>str_repeat('1',47),'barCode'=>str_repeat('1',44),'nossoNumero'=>'123'];instrumentCheck(\Asaas\CompanyInstruments::parse('BOLETO',$boleto)['bar_code']===$boleto['barCode'],'bounded bank code');instrumentReject(fn()=>\Asaas\CompanyInstruments::parse('BOLETO',array_replace($boleto,['barCode'=>'<script>'])));
$calls=[];$client=new \Asaas\CompanyClient('sandbox','synthetic-only',function($env,$method,$path,$payload)use(&$calls){$calls[]=$path;instrumentCheck($method==='GET'&&$payload===null,'instrument is empty-body GET');return ['fixture'=>true];});
foreach(['payments/pay_1/pixQrCode','payments/pay_1/identificationField'] as $path)$client->request('GET',$path);
foreach(['payments/pay_1/../refund','payments/pay_1/pixQrCode?extra=1','https://elsewhere.invalid'] as $path)instrumentReject(fn()=>$client->request('GET',$path));
instrumentCheck(count($calls)===2,'invalid path never reaches transport');echo "Asaas plain-text instruments and allowlisted transport fixture passed.\n";
