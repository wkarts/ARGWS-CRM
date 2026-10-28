<?php
require_once __DIR__.'/../application/services/CompanyRegistryLookup.php';
$n=0;function checkLookup($condition,$label){global $n;if(!$condition){throw new RuntimeException($label);}++$n;}
function rejectsLookup(callable $fn){try{$fn();}catch(Throwable $e){return true;}return false;}
checkLookup(CompanyRegistryLookup::request('cnpj','11.222.333/0001-81')['url']==='https://www.receitaws.com.br/v1/cnpj/11222333000181','fixed provider and normalized ID');
checkLookup(CompanyRegistryLookup::request('cep','01001-000')['url']==='https://viacep.com.br/ws/01001000/json/','fixed CEP URL');
foreach(['11111111111111','11222333000182','https://internal/','52998224725',[]] as $bad){checkLookup(rejectsLookup(fn()=>CompanyRegistryLookup::lookup('cnpj',$bad,fn()=>throw new RuntimeException('must not run'))),'invalid CNPJ');}
foreach(['123','123456789','01001000?host=internal'] as $bad){checkLookup(rejectsLookup(fn()=>CompanyRegistryLookup::request('cep',$bad)),'invalid CEP');}
$fixture=['cnpj'=>'11.222.333/0001-81','nome'=>'Empresa sintética','fantasia'=>'Loja de teste','logradouro'=>'Rua de teste','numero'=>'10','bairro'=>'Centro','municipio'=>'Cidade teste','uf'=>'SP','cep'=>'01001-000','telefone'=>'','email'=>'not-imported@example.invalid','status'=>'OK'];
$result=CompanyRegistryLookup::lookup('cnpj','11222333000181',fn()=>json_encode($fixture));
checkLookup($result['fields']['company_trade_name']==='Loja de teste','name mapping');
checkLookup(!isset($result['fields']['email'])&&!isset($result['fields']['company_state_registration'])&&!isset($result['fields']['invoice_company_phonenumber']),'missing and untrusted fields omitted');
checkLookup(CompanyRegistryLookup::lookup('cnpj','11222333000181',fn()=>json_encode(['cnpj'=>'11222333000181']))['fields']===['invoice_company_country_code'=>'BR'],'partial response supported');
foreach(['null','{bad','{"status":"ERROR"}','{"cnpj":"12345678000195"}',str_repeat('x',131073)] as $bad){checkLookup(rejectsLookup(fn()=>CompanyRegistryLookup::lookup('cnpj','11222333000181',fn()=>$bad)),'provider failure');}
$cep=CompanyRegistryLookup::lookup('cep','01001000',fn()=>json_encode(['cep'=>'01001-000','logradouro'=>'Rua sintética','uf'=>'SP','localidade'=>'Cidade teste','bairro'=>'Centro']));
checkLookup($cep['fields']['invoice_company_city']==='Cidade teste','CEP mapping');
checkLookup(rejectsLookup(fn()=>CompanyRegistryLookup::lookup('cep','01001000',fn()=>' {"erro":true}')),'CEP not found');
checkLookup(rejectsLookup(fn()=>CompanyRegistryLookup::lookup('cep','01001000',fn()=>throw new RuntimeException('timeout'))),'transport timeout');
echo "Registry lookup: $n checks passed; fixtures only, no network.\n";
