<?php
declare(strict_types=1);
define('ASAAS_FIXTURES_ONLY',true);
require __DIR__.'/asaas_payment_origin_integration.php';
use Asaas\Sdk\Http\Client;
function get_currency($id){return $id===1?(object)['name'=>'BRL']:null;}
function get_invoice_total_left_to_pay($id,$total){return (float)$total;}
function cardFixture(): array {
    [$gw,$adapter,$db]=fixture();profile_option('sandbox','active','1');profile_option('sandbox','card_only','1');
    $db->tables['tblinvoices'][0]['allowed_payment_modes']=serialize(['asaas']);$db->tables['tblinvoices'][0]['currency']=1;
    $db->tables['tblasaas_customers_map']=[['client_id'=>10,'asaas_customer_id'=>'cus_original']];
    return [$gw,$adapter,$db,(object)$db->tables['tblinvoices'][0]];
}
function cardPayment($type='CREDIT_CARD'): array {return payment()+['billingType'=>$type,'invoiceUrl'=>'https://sandbox.asaas.com/i/hosted_test','creditCardToken'=>'NEVER_PROPAGATE_PROVIDER_TOKEN','creditCard'=>['number'=>'NEVER_PERSIST_PAN','ccv'=>'NEVER_PERSIST_CVV'],'creditCardHolderInfo'=>['name'=>'NEVER_PERSIST_HOLDER'],'unknownFutureSecret'=>'NEVER_PERSIST_FUTURE'];}
foreach (['card','creditCard','creditCardToken','creditCardHolderInfo','unknown'] as $field) {
    [$gw,$adapter,$db,$invoice]=cardFixture();$result=json_decode($gw->charge_credit_card(['invoice'=>$invoice,$field=>null]),true);
    check(isset($result['errors'])&&Client::$calls===[],'unexpected/raw field rejected before provider');
}
[$gw,$adapter,$db,$invoice]=cardFixture();$result=json_decode($gw->charge_credit_card(['invoice'=>$invoice,'installmentCount'=>2]),true);
check(isset($result['errors'])&&Client::$calls===[],'installments blocked before provider');
foreach (['CREDIT_CARD','UNDEFINED','BOLETO'] as $type) {
    [$gw,$adapter,$db,$invoice]=cardFixture();known($adapter,$db);$GLOBALS['options']['paymentmethod_asaas_sandbox']='0';
    Client::$responses=[wallet(),cardPayment($type)];$result=json_decode($gw->charge_credit_card(['invoice'=>$invoice]),true);
    check(array_column(Client::$calls,0)===['GET','GET'],'existing origin read only for '.$type);
    check(array_column(Client::$calls,3)===['sandbox','sandbox'],'origin sandbox wins over new global production');
    check($type==='BOLETO'?isset($result['errors']):isset($result['invoiceUrl']),'canonical billing type required');
    check(!str_contains(json_encode($result),'NEVER_PROPAGATE_PROVIDER_TOKEN'),'provider token scrubbed');
    check(count($db->tables['tblasaas_payment_origins'])===1&&$db->tables['tblinvoicepaymentrecords']===[],'no duplicate origin or direct settlement');
}
foreach (['map','receipt','issuer','pending','currency'] as $case) {
    [$gw,$adapter,$db,$invoice]=cardFixture();
    if($case==='map')$db->tables['tblasaas_payments_map'][]=['invoice_id'=>1,'asaas_payment_id'=>'legacy'];
    if($case==='receipt')$db->tables['tblinvoicepaymentrecords'][]=['invoiceid'=>1,'amount'=>1];
    if($case==='currency')$db->tables['tblinvoices'][0]['currency']=2;
    if($case==='issuer')$db->tables['tblinvoices'][0]['issuer_id']=2;
    if($case==='pending')$adapter->originStore()->reserve($db->tables['tblinvoices'][0],['provider'=>'asaas','account_id'=>'wallet_original','environment'=>'sandbox','channel'=>'api']);
    $result=json_decode($gw->charge_credit_card(['invoice'=>$invoice]),true);
    check(isset($result['errors'])&&Client::$calls===[],'unsafe issuance blocked: '.$case);
}
[$gw,$adapter,$db,$invoice]=cardFixture();Client::$responses=[wallet(),customer(),cardPayment()];
$result=json_decode($gw->charge_credit_card(['invoice'=>$invoice,'installmentCount'=>1]),true);
check(isset($result['invoiceUrl']),'hosted charge created with verified wallet/customer');
check(array_column(Client::$calls,0)===['GET','GET','POST'],'single new issuance');
check(count($db->tables['tblasaas_payment_origins'])===1&&$adapter->originStore()->byInvoice(1)['remote_id']==='pay_original','origin persisted before redirect');
check($db->tables['tblinvoicepaymentrecords']===[],'creation never settles');
foreach (['NEVER_PROPAGATE_PROVIDER_TOKEN','NEVER_PERSIST_PAN','NEVER_PERSIST_CVV','NEVER_PERSIST_HOLDER','NEVER_PERSIST_FUTURE'] as $secret) check(!str_contains(json_encode($db->tables),$secret),'no sensitive provider field ever persisted');
$cache=json_decode($db->tables['tblasaas_payments_map'][0]['payload_cache'],true);
check(array_diff(array_keys($cache),['id','status','value','dueDate','billingType','externalReference','customer'])===[],'cache explicit flat allowlist');
Client::$responses=[wallet(),cardPayment()];$result=json_decode($gw->charge_credit_card(['invoice'=>$invoice]),true);
check(isset($result['invoiceUrl'])&&count(array_filter(Client::$calls,fn($call)=>$call[0]==='POST'))===1,'repeat request only reuses canonical existing payment');
[$gw,$adapter,$db,$invoice]=cardFixture();Client::$responses=[wallet(),customer(),new Asaas\Sdk\Exception\TransportException('synthetic timeout')];
$result=json_decode($gw->charge_credit_card(['invoice'=>$invoice]),true);
check(isset($result['errors'])&&$adapter->originStore()->byInvoice(1)['state']==='review_required','uncertain issuance enters review');
$count=count(Client::$calls);$gw->charge_credit_card(['invoice'=>$invoice]);check(count(Client::$calls)===$count,'uncertain POST never blindly reissued');
echo "Asaas hosted gateway: {$checks} checks passed\n";
