<?php
// Reuse the existing synthetic databases and actual Clients controller adapter.
// HTTP routing/CSRF still requires authenticated browser integration.
require __DIR__.'/native_clients_controller.php';

$controller->output=new class {
    public string $body='';
    public array $headers=[];
    public string $contentType='';
    function set_header($value){$this->headers[]=$value;return $this;}
    function set_content_type($value){$this->contentType=$value;return $this;}
    function set_output($value){$this->body=$value;return $this;}
};
$db->query('UPDATE '.$p.'clients SET billing_street=? WHERE userid=?',['Synthetic billing B',$nativeB]);
$persisted=CompanyClientNativeFields::read($db,$p,82,(int)$nativeB);
essentialCheck(($persisted['billing_street']??null)==='Synthetic billing B','native profile reader consumes the synthetic client column');
$controller->input->post=[];
$controller->input->method='GET';
CompanyRequestScope::$issuer=41;
$controller->get_customer_billing_and_shipping_details($nativeA);
$billing=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck(count($billing)===1&&$billing[0]['billing_street']==='A billing','owner A receives its own billing address');
essentialCheck(in_array('Cache-Control: no-store',$controller->output->headers,true)&&$controller->output->contentType==='application/json','billing JSON is marked private and non-cacheable');
try{$controller->get_customer_billing_and_shipping_details($nativeB);throw new RuntimeException('foreign billing accepted');}
catch(NativeClientHttpError $error){essentialCheck($error->getCode()===403,'owner A cannot read B billing even with the direct identifier');}
CompanyRequestScope::$issuer=82;
$controller->get_customer_billing_and_shipping_details($nativeB);
$billing=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck(count($billing)===1&&$billing[0]['billing_street']==='Synthetic billing B','owner B sees its own distinct billing address');
try{$controller->get_customer_billing_and_shipping_details($nativeA);throw new RuntimeException('shared private billing accepted');}
catch(NativeClientHttpError $error){essentialCheck($error->getCode()===403,'shared identity cannot expose owner A private billing');}
$controller->input->method='HEAD';
$controller->get_customer_billing_and_shipping_details($nativeB);
$billing=json_decode($controller->output->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($billing[0]['billing_street']==='Synthetic billing B','HEAD enters the same ownership-checked reader');
foreach(['POST','PUT','PATCH','DELETE'] as $verb){
    $controller->input->method=$verb;
    try{$controller->get_customer_billing_and_shipping_details($nativeB);throw new RuntimeException('unexpected billing method accepted');}
    catch(NativeClientHttpError $error){essentialCheck($error->getCode()===405,'billing reader rejects '.$verb);}
}
$controller->input->method='GET';
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=82 AND staff_id=12 AND feature='customers' AND capability='view'");
try{$controller->get_customer_billing_and_shipping_details($nativeB);throw new RuntimeException('revoked billing accepted');}
catch(NativeClientHttpError $error){essentialCheck($error->getCode()===403,'revoked customer view cannot read billing');}
echo "Native customer billing controller ownership, methods and revocation passed.\n";
