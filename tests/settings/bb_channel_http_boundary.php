<?php
require __DIR__.'/../banco_brasil_admin.php';
$GLOBALS['currentCompanyId']=1;$ci->input->posts=[];$ci->input->verb='GET';
try{$controller->save_channel_preferences();throw new LogicException('GET accepted');}catch(BbHttp $e){check($e->getMessage()==='405','channel preferences GET never writes');}
$ci->input->verb='POST';$ci->input->posts=['issuer_id'=>'1','csrf_bb'=>'invalid'];
try{$controller->save_channel_preferences();throw new LogicException('CSRF accepted');}catch(BbHttp $e){check($e->getMessage()==='403','channel preferences reject invalid CSRF');}
$ci->input->posts=['issuer_id'=>'2','csrf_bb'=>$csrf];
rejects(fn()=>$controller->save_channel_preferences(),'channel preferences reject a foreign company before writer');
echo "BB preferences actual controller GET/POST, CSRF and foreign company boundaries passed.\n";
