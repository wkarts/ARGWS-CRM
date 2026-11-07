<?php
declare(strict_types=1);
require __DIR__.'/../modules/asaas/libraries/Asaas/CompanyClient.php';
$n=0;$calls=0;
$check=function(bool $ok,string $message)use(&$n){$n++;if(!$ok)throw new RuntimeException($message);};
$client=new \Asaas\CompanyClient('sandbox','synthetic_secret',function($env,$method,$path,$payload,$secret)use(&$calls,$check){$calls++;$check($env==='sandbox'&&$method==='GET'&&$payload===null,'read remains scoped and bodyless');return ['id'=>'synthetic'];});
foreach(['installments/grp_review','installments/grp_review/payments','installments/grp_review/payments?limit=100&offset=0'] as $path)$check($client->request('GET',$path)['id']==='synthetic','supported group reads');
foreach([['POST','installments/grp_review',[]],['DELETE','installments/grp_review',null],['PUT','installments/grp_review',[]],['GET','installments/grp_review/payments',['status'=>'RECEIVED']],['GET','https://foreign.example/installments/grp_review',null],['GET','installments/../wallets/',null],['GET','installments/grp_review/payments?limit=100&offset=1',null],['GET','installments/grp_review/payments?url=https://foreign.example',null]] as [$method,$path,$payload]){
 $before=$calls;$error='';try{$client->request($method,$path,$payload);}catch(DomainException $e){$error=$e->getMessage();}$check($error==='asaas_company_request_invalid'&&$calls===$before,'unsafe group request denied before transport');
}
$client=new \Asaas\CompanyClient('sandbox','synthetic_secret',function(){throw new RuntimeException('synthetic_sensitive_payload');});
$error='';try{$client->request('GET','installments/grp_review');}catch(RuntimeException $e){$error=$e->getMessage();}
$check($error==='asaas_company_transport_unconfirmed','transport error never echoes upstream body or secret');
$client=new \Asaas\CompanyClient('sandbox','synthetic_secret',function($env,$method,$path,$payload,$secret)use($check){$check($env==='sandbox'&&$method==='POST'&&$path==='payments','successor uses documented payments endpoint');$check(array_keys($payload)===['customer','billingType','installmentCount','totalValue','dueDate','externalReference']&&$payload['totalValue']==='105.53','total mode has no conflicting value/installmentValue or sensitive fields');return ['id'=>'synthetic'];});
$client->createInstallmentSuccessor('cus_a','PIX',2,'105.53','2027-01-05',str_repeat('a',64));
foreach([['CREDIT_CARD',2,'105.53'],['PIX',1,'105.53'],['PIX',101,'105.53'],['PIX',2,'0.00'],['PIX',2,'1.001']] as [$type,$count,$total]){$error='';try{$client->createInstallmentSuccessor('cus_a',$type,$count,$total,'2027-01-05',str_repeat('a',64));}catch(DomainException $e){$error=$e->getMessage();}$check($error==='asaas_installment_recreation_invalid','unsupported successor payload rejected before transport');}
echo "Installment transport: {$n} checks passed; no network.\n";
