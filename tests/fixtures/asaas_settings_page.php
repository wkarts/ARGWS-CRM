<?php
// Render the production view with the existing isolated financial fixtures.
ob_start();
require dirname(__DIR__).'/companies/asaas_checkout_views.php';
ob_end_clean();
$data['checkout_ready']=true;
$data['card_payments_ready']=true;$data['card_request_key']=str_repeat('a',64);$data['card_payments']=[];
$cardFixture=$data['origins'][0];$cardFixture=array_replace($cardFixture,['id'=>90001,'invoice_id'=>90001,'account_id'=>'synthetic-card-account','environment'=>'sandbox','billing_type'=>'CREDIT_CARD','state'=>'issued','receipt'=>null,'remote_id'=>'pay_synthetic_card','last_code'=>'','remote_operation'=>null,'hosted_publication'=>null]);$cardFixture['proof']['status']='PENDING';unset($cardFixture['proof']['checkout_url']);$data['origins'][]=$cardFixture;
$data['legacy_review']=['bound'=>false,'issuer_id'=>$b,'account_id'=>'synthetic-legacy-account','environment'=>'sandbox','invoice_id'=>90002,'account_row'=>1,'remote_id'=>'pay_synthetic_legacy','amount'=>'40.00','receivable'=>'100.00','billing_type'=>'PIX','status'=>'RECEIVED','review_hash'=>str_repeat('b',64)];
echo $harness->render($data);
