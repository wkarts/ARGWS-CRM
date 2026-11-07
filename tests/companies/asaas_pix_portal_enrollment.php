<?php
require __DIR__.'/asaas_pix_portal_controller.php';
$start=$GLOBALS['essentialChecks'];
$detail->encryption=new class($dec){function __construct(private $decrypt){}function decrypt($value){return($this->decrypt)($value);}};
$detail->load=new class{function library($name){if($name!=='encryption')throw new LogicException('unexpected library');}};
(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($detail->session,$b,$sessions[$b]);$detail->input->get=['issuer_id'=>(string)$b];$detail->router->class='invoice';$detail->router->method='index';$detail->input->verb='POST';
essentialCheck(PortalRequestScope::prepare($detail)&&PortalRequestScope::resolve($detail),'private enrollment controller scope restored');
foreach(['success','timeout','missing'] as $scenario){
 $invoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','native-pix-enroll-'.$scenario));$origin=$asaas->reservePixAuthorization($b,7,$invoice,$accounts[$b],'cus_b',$s->revision($b,7,$invoice),hash('sha256','native-pix-enroll-key-'.$scenario),'MONTHLY',$input['date']);$review=$consent->review($portal,$sessions[$b],$b,$invoice);$consent->accept($portal,$sessions[$b],$b,$invoice,$review['revision'],true);$invoiceHash=$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$invoice])->row()->hash;
 $posts=0;$reply=null;$nativeTransport=function($env,$method,$path,$payload,$secret)use($mock,$scenario,&$posts,&$reply){
  if($method==='POST'&&$path==='pix/automatic/authorizations'){$posts++;$reply=['id'=>'auth_native_'.$scenario,'customerId'=>$payload['customerId'],'contractId'=>$payload['contractId'],'frequency'=>$payload['frequency'],'startDate'=>$payload['startDate'],'status'=>'CREATED','immediateQrCode'=>['conciliationIdentifier'=>'native_'.$scenario,'expirationDate'=>'2030-01-01T12:00:00-03:00'],'payload'=>'000201synthetic-private-qr-no-payment','encodedImage'=>''];if($scenario!=='success')throw new RuntimeException('synthetic timeout');return $reply;}
  if(str_starts_with($path,'pix/automatic/authorizations?'))return ['data'=>$scenario==='missing'?[]:[$reply],'hasMore'=>false,'totalCount'=>$scenario==='missing'?0:1];
  if($path==='pix/automatic/authorizations/auth_native_'.$scenario)return $reply;return $mock($env,$method,$path,$payload,$secret);
 };
 $detail->input->post=['native_payment_action'=>'asaas_pix_enroll','pix_consent_revision'=>$review['revision'],'_native_portal_csrf'=>PortalRequestScope::csrfToken($detail)];
 $http=new NativePortalInvoiceDetailHttp($detail,$nativeTransport);$render=function()use($http,$invoice,$invoiceHash){ob_start();try{$http->index((string)$invoice,$invoiceHash);return ob_get_contents();}finally{ob_end_clean();}};
 $html=$render();if($scenario==='success'&&($file=getenv('ASAAS_PIX_ENROLLMENT_HTML'))!==false&&$file!=='')file_put_contents($file,$html);$again=$render();essentialCheck($posts===1,'native enrollment sends once despite repeat '.$scenario);
 essentialCheck($scenario==='missing'?str_contains($html,'Código indisponível'):str_contains($html,'<svg role="img"')&&str_contains($html,'native-pix-payload')&&str_contains($html,'000201synthetic-private-qr-no-payment'),'private delivery accurately reflects canonical recovery '.$scenario);
 essentialCheck(!str_contains(json_encode($detail->session->data),'synthetic-private-qr')&&!str_contains(json_encode($db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=?',[$origin])->result_array()),'synthetic-private-qr'),'no QR in session or journals '.$scenario);
 essentialCheck(!$db->query('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row(),'QR delivery never fakes receipt '.$scenario);
}
echo 'Native Pix enrollment: '.($GLOBALS['essentialChecks']-$start).' additional MySQL/controller checks; provider simulated.'.PHP_EOL;
