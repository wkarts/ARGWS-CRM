<?php
declare(strict_types=1);
require __DIR__.'/../modules/asaas/libraries/Asaas/AdminSettingsPolicy.php';
use Asaas\AdminSettingsPolicy as Policy;
$checks=0;
function check($ok,$message){global $checks;++$checks;if(!$ok)throw new RuntimeException($message);}
foreach (['0','1'] as $value){$data=['settings'=>['paymentmethod_asaas_sandbox'=>$value,'paymentmethod_asaas_debug'=>'1','paymentmethod_asaas_file_log_enabled'=>'0']];check(Policy::validate($data)===$data,'explicit environment preserved independently from diagnostics');}
foreach ([null,0,1,true,false,'',[],['0','1'],'production','sandbox','0.0','1.0',' 0','false'] as $value){try{Policy::validate(['settings'=>['paymentmethod_asaas_sandbox'=>$value]]);check(false,'invalid environment accepted');}catch(InvalidArgumentException $e){check(true,'invalid environment blocked');}}
$data=['settings'=>['paymentmethod_stripe_active'=>'1','paymentmethod_asaas_debug'=>'1']];check(Policy::validate($data)===$data,'unrelated settings preserved without inferred environment');
try{Policy::validate(['settings'=>['paymentmethod_asaas_webhook_secret'=>'synthetic']]);check(false,'legacy token changed');}catch(InvalidArgumentException $e){check(true,'generic token field blocked');}
$gateway=file_get_contents(__DIR__.'/../modules/asaas/libraries/Asaas_gateway.php');$settings=substr($gateway,strpos($gateway,'$this->setSettings'),strpos($gateway,'$this->provider =')-strpos($gateway,'$this->setSettings'));
check(!str_contains($settings,"'name' => 'webhook_secret'"),'saved legacy secret not exposed in generic settings');
check(str_contains($settings,"'input_type' => 'password'"),'API input masked');
echo "Asaas settings policy: {$checks} checks passed\n";
