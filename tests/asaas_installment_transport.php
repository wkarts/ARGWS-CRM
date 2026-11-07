<?php
declare(strict_types=1);
require __DIR__.'/../modules/asaas/libraries/Asaas/CompanyClient.php';
$n=0;$calls=0;
$check=function(bool $ok,string $message)use(&$n){$n++;if(!$ok)throw new RuntimeException($message);};
$client=new \Asaas\CompanyClient('sandbox','synthetic_secret',function($env,$method,$path,$payload,$secret)use(&$calls,$check){$calls++;$check($env==='sandbox'&&$method==='GET'&&$payload===null,'read remains scoped and bodyless');return ['id'=>'synthetic'];});
foreach(['installments/grp_review','installments/grp_review/payments'] as $path)$check($client->request('GET',$path)['id']==='synthetic','supported group reads');
foreach([['POST','installments/grp_review',[]],['DELETE','installments/grp_review',null],['PUT','installments/grp_review',[]],['GET','installments/grp_review/payments',['status'=>'RECEIVED']],['GET','https://foreign.example/installments/grp_review',null],['GET','installments/../wallets/',null],['GET','installments/grp_review/payments?url=https://foreign.example',null]] as [$method,$path,$payload]){
 $before=$calls;$error='';try{$client->request($method,$path,$payload);}catch(DomainException $e){$error=$e->getMessage();}$check($error==='asaas_company_request_invalid'&&$calls===$before,'unsafe group request denied before transport');
}
$client=new \Asaas\CompanyClient('sandbox','synthetic_secret',function(){throw new RuntimeException('synthetic_sensitive_payload');});
$error='';try{$client->request('GET','installments/grp_review');}catch(RuntimeException $e){$error=$e->getMessage();}
$check($error==='asaas_company_transport_unconfirmed','transport error never echoes upstream body or secret');
echo "Installment transport: {$n} checks passed; no network.\n";
