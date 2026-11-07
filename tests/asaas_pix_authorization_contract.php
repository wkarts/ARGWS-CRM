<?php
declare(strict_types=1);
define('APPPATH',dirname(__DIR__).'/application/');
require dirname(__DIR__).'/modules/asaas/libraries/Asaas/CompanyPixAuthorizationContract.php';
require dirname(__DIR__).'/modules/asaas/libraries/Asaas/CompanyClient.php';
use Asaas\CompanyPixAuthorizationContract as Contract;
$n=0;
function check(bool $condition,string $message):void{global $n;if(!$condition)throw new RuntimeException($message);++$n;}
function denied(callable $action,string $message):void{try{$action();}catch(DomainException $e){check(true,$message);return;}throw new RuntimeException($message);}
$source=['customer_id'=>'cus_synthetic','contract_id'=>'crm_synthetic','frequency'=>'MONTHLY','start_date'=>'2026-11-10','amount'=>'105.53'];
$remote=['id'=>'auth_synthetic','customerId'=>'cus_synthetic','contractId'=>'crm_synthetic','frequency'=>'MONTHLY','startDate'=>'2026-11-10','status'=>'CREATED','paymentCreationMode'=>'MANUAL','retryPolicy'=>'NOT_ALLOWED','immediateQrCode'=>['conciliationIdentifier'=>'conciliation_synthetic','expirationDate'=>'2026-11-10T12:00:00-03:00'],'payload'=>str_repeat('0',30),'encodedImage'=>base64_encode('synthetic-image')];
$r=Contract::request($source);check($r['immediateQrCode']['originalValue']==='105.53'&&$r['paymentCreationMode']==='MANUAL'&&$r['retryPolicy']==='NOT_ALLOWED','exact amount and explicit manual creation');
foreach(Contract::FREQUENCIES as $frequency)check(Contract::request(array_replace($source,['frequency'=>$frequency]))['frequency']===$frequency,'documented frequency');
foreach(['customer_id'=>['',[],str_repeat('x',65),'cus/path'],'contract_id'=>['',str_repeat('x',36)],'frequency'=>['DAILY',null],'start_date'=>['2026-02-30',[],null],'amount'=>['0.00','-1.00','1e2','1.1',1.23]] as $key=>$values)foreach($values as $value)denied(fn()=>Contract::request(array_replace($source,[$key=>$value])),'invalid source '.$key);
$r=Contract::response($source,$remote);check($r['expires_at']==='2026-11-10 15:00:00'&&$r['qr']['payload']===$remote['payload'],'validated QR and UTC expiration');
foreach(['customerId'=>'cus_other','contractId'=>'other','frequency'=>'WEEKLY','startDate'=>'2026-11-11','id'=>'bad/id','status'=>'DONE','subscriptionId'=>'sub_other','paymentCreationMode'=>'SUBSCRIPTION','retryPolicy'=>'ALLOWED','immediateQrCode'=>'malformed','payload'=>"private\ncontrol",'encodedImage'=>'invalid%%%'] as $key=>$value)denied(fn()=>Contract::response($source,array_replace($remote,[$key=>$value])),'mismatch '.$key);
foreach(['2026-02-30T12:00:00','2026-11-10T24:00:00','2026-11-10T12:60:00','2026-11-10T12:00:60'] as $date){$bad=$remote;$bad['immediateQrCode']['expirationDate']=$date;denied(fn()=>Contract::response($source,$bad),'invalid expiration');}
foreach(['remote_id'=>'auth_other','conciliation_id'=>'other'] as $key=>$value)denied(fn()=>Contract::response(array_replace($source,[$key=>$value]),$remote),'immutable '.$key);
foreach(['CANCELLED','EXPIRED','REFUSED'] as $state){$old=array_replace($source,['status'=>$state]);denied(fn()=>Contract::response($old,$remote),'terminal cannot resurrect');$terminal=array_replace($remote,['status'=>$state]);check(Contract::response($old,$terminal)['qr']['payload']==='','terminal repeat drops QR');}
denied(fn()=>Contract::response(array_replace($source,['status'=>'ACTIVE']),$remote),'active cannot regress');
$active=Contract::response($source,array_replace($remote,['status'=>'ACTIVE']));check($active['status']==='ACTIVE'&&$active['qr']['payload']===''&&!isset($active['payment']),'activation never implies financial receipt');
$calls=[];$client=new Asaas\CompanyClient('sandbox','synthetic-secret',function($environment,$method,$path,$body)use(&$calls,$remote){$calls[]=[$environment,$method,$path,$body];return $remote;});
check($client->createPixAuthorization($source)===$remote&&count($calls)===1&&$calls[0][2]==='pix/automatic/authorizations','one typed sandbox transport call');
denied(fn()=>$client->request('POST','pix/automatic/authorizations',[]),'generic bypass denied');
denied(fn()=>$client->createPixAuthorization(array_replace($source,['amount'=>'-1.00'])),'invalid request rejected before transport');check(count($calls)===1,'no transport after invalid request');
$attempts=0;$failed=new Asaas\CompanyClient('sandbox','synthetic-secret',function()use(&$attempts){++$attempts;throw new RuntimeException('untrusted provider body');});
try{$failed->createPixAuthorization($source);throw new LogicException('expected uncertain result');}catch(RuntimeException $e){check($e->getMessage()==='asaas_company_transport_unconfirmed'&&$attempts===1,'timeout hidden and never retried');}
$initial=$source+['conciliation_id'=>'conciliation_synthetic','invoice_hash'=>'invoice_synthetic'];
$payment=['id'=>'pay_synthetic','pixTransaction'=>'tx_synthetic','customer'=>'cus_synthetic','billingType'=>'PIX','value'=>'105.53'];
$transaction=['id'=>'tx_synthetic','payment'=>'pay_synthetic','type'=>'CREDIT','conciliationIdentifier'=>'conciliation_synthetic'];
$identity=Contract::initialPayment($initial,$payment,$transaction);check($identity['payment_id']==='pay_synthetic'&&!isset($identity['status']),'initial identity without external reference is not settlement');
foreach(['customer'=>'cus_other','value'=>'105.52','billingType'=>'BOLETO','externalReference'=>'other','installment'=>'group_other','subscription'=>'sub_other','deleted'=>true,'pixTransaction'=>'tx_other'] as $key=>$value)denied(fn()=>Contract::initialPayment($initial,array_replace($payment,[$key=>$value]),$transaction),'initial payment mismatch '.$key);
foreach(['id'=>'tx_other','payment'=>'pay_other','type'=>'DEBIT','conciliationIdentifier'=>'other'] as $key=>$value)denied(fn()=>Contract::initialPayment($initial,$payment,array_replace($transaction,[$key=>$value])),'initial transaction mismatch '.$key);
denied(fn()=>Contract::initialPayment($initial+['payment_id'=>'pay_other'],$payment,$transaction),'initial immutable payment');
check(Contract::initialPayment($initial+['status'=>'REFUSED'],$payment,$transaction)===$identity,'refused recurring consent does not erase initial transaction identity');
echo "OK: {$n} Pix authorization contract checks; synthetic transport only, no enrollment or financial posting.\n";
