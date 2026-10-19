<?php
/** The production wire decoder must retain decimal tokens before any monetary decision. */
require __DIR__.'/asaas_positive.php';
$exactStart=$GLOBALS['essentialChecks'];
$raw='{"value":9999999999999.99,"small":99.99999999999999,"exponential":1e2,"text":"value 99.99999999999999 and \\"escaped\\"","nested":{"amount":0.01},"deleted":false}';
// Build JSON escaped string natively; numeric tokens stay literal and are never built as floats.
$raw='{"value":9999999999999.99,"small":99.99999999999999,"exponential":1e2,"text":'.json_encode('value 99.99999999999999 and "escaped"').' ,"nested":{"amount":0.01},"deleted":false}';
$decoded=\Asaas\CompanyClient::decodeExact($raw);
essentialCheck($decoded['value']==='9999999999999.99'&&$decoded['small']==='99.99999999999999','DECIMAL15,2 and adversarial excess precision remain exact wire lexemes');
essentialCheck($decoded['exponential']==='1e2'&&$decoded['nested']['amount']==='0.01'&&$decoded['deleted']===false,'exponents/nested numbers and boolean types are preserved distinctly');
essentialCheck($decoded['text']==='value 99.99999999999999 and "escaped"','numeric text and escaped quotes are never rewritten');
$money=new ReflectionMethod(\Asaas\CompanyService::class,'money');
essentialCheck($money->invoke(null,$decoded['value'])==='9999999999999.99','largest supported nominal decimal remains exact');
foreach([$decoded['small'],$decoded['exponential'],99.99999999999999] as $bad)essentialThrows(fn()=>$money->invoke(null,$bad),'asaas_company_amount_requires_review','fractional/exponential or already-rounded float must never authorize a receipt');
essentialThrows(fn()=>\Asaas\CompanyClient::decodeExact('{"value":1,"value":2}'),'asaas_company_duplicate_key','duplicate monetary keys cannot shadow evidence');
// Exercise raw response through the actual client then service conflict boundary.
[$wireInvoice,$wireOrigin,$wireProof,$wireRemote]=asaasFreshCredit($b,$customerB,'cus_b','raw-wire-precision');
$wireMock=function($environment,$method,$path,$payload,$secret)use($mock,$responses,$wireRemote){
 if($path==='payments/'.$wireRemote){$response=$responses[$wireRemote];$response['value']='EXACT_NUMBER_TOKEN';return str_replace('"EXACT_NUMBER_TOKEN"','105.52999999999999',json_encode($response,JSON_THROW_ON_ERROR));}
 return $mock($environment,$method,$path,$payload,$secret);
};
essentialThrows(fn()=>$asaas->query($b,7,$wireOrigin,$dec,$wireMock),'asaas_company_response_conflict','production raw client preserves excess precision so service rejects a rounded nominal match');
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($wireOrigin,$wireProof)),'asaas_company_receipt_requires_review','prior valid proof cannot bypass raw precision conflict');
echo 'Company Asaas exact JSON: '.($GLOBALS['essentialChecks']-$exactStart).' added checks; '.$GLOBALS['essentialChecks'].' cumulative passed.'.PHP_EOL;
