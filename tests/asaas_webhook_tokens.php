<?php
/** Legacy receiver recovery uses the same native cipher; this fixture never reaches a provider. */
require __DIR__.'/asaas_webhook_security.php';
[$gw,$a,$db,$i]=webhookFixture();
$store=$a->webhookIntegrations();$original=$db->tables['tblasaas_webhook_integrations'][0];
$db->tables['tblmodules'][0]['installed_version']='1.5.3';
foreach([fn()=>$store->createVerified('blocked',['issuer_id'=>null,'provider'=>'asaas','account_id'=>'wallet_original','environment'=>'sandbox']),fn()=>$store->rotateToken(1),fn()=>$store->revealToken(1),fn()=>$a->registerWebhookIntegration('blocked','sandbox','wallet_original')] as $operation){
    try{$operation();check(false,'native154 required before new administrative capability');}catch(DomainException $error){check($error->getMessage()==='asaas_module_154_required','schema alone does not enable new administrative capability');}
}
check($db->tables['tblasaas_webhook_integrations'][0]===$original&&\Asaas\Sdk\Http\Client::$calls===[],'failed native tracking cannot rotate, reveal or contact provider');
check(receive($a,$i,'evt_existing_hash_before154')['http_status']===200,'existing hash authentication remains available before native154');
$db->tables['tblmodules'][0]['installed_version']='1.5.4';
$original=$db->tables['tblasaas_webhook_integrations'][0];
$revealed=$store->revealToken(1);check($revealed['one_time_token']===$i['one_time_token']&&!empty($revealed['revealed']),'protected recovery returns original token');
check($db->tables['tblasaas_webhook_integrations'][0]===$original,'recovery does not rotate or rewrite receiver');
$listing=$store->listForAdmin();check($listing[0]['token_recoverable']&&!isset($listing[0]['token_hash'])&&!isset($listing[0]['token_cipher']),'listing strips all secret representations');
check(!str_contains(json_encode($listing),$i['one_time_token']),'GET DTO cannot hydrate token');
check(end($db->tables['tblasaas_webhook_admin_audit'])['action']==='token_revealed','recovery audit contains action only');
$admin=false;throws(fn()=>$store->revealToken(1),'non-admin recovery denied');throws(fn()=>$store->rotateToken(1),'non-admin rotation denied');$admin=true;
$db->failInsert='tblasaas_webhook_admin_audit';throws(fn()=>$store->revealToken(1),'missing audit prevents secret response');$db->failInsert=null;
foreach(['account_id'=>'another','environment'=>'production','public_id'=>str_repeat('0',32),'issuer_id'=>2] as $field=>$value){$db->tables['tblasaas_webhook_integrations'][0][$field]=$value;throws(fn()=>$store->revealToken(1),'transplanted '.$field.' cannot reveal token');$db->tables['tblasaas_webhook_integrations'][0]=$original;}
$db->tables['tblasaas_webhook_integrations'][0]['token_cipher']=null;throws(fn()=>$store->revealToken(1),'legacy hash-only receiver requires explicit regeneration');check($db->tables['tblasaas_webhook_integrations'][0]['token_hash']===$original['token_hash'],'legacy recovery never silently rotates');
$rotated=$store->rotateToken(1);check($rotated['one_time_token']!==$i['one_time_token'],'regeneration invalidates predecessor');check($store->revealToken(1)['one_time_token']===$rotated['one_time_token'],'regenerated token is recoverable');
check(\Asaas\Sdk\Http\Client::$calls===[],'token management never calls provider');
foreach(['issuer_id'=>2,'provider'=>'other-provider'] as $field=>$value){$row=$db->tables['tblasaas_webhook_integrations'][0];$db->tables['tblasaas_webhook_integrations'][0][$field]=$value;$snapshot=$db->tables;throws(fn()=>$store->disable(1),'disable rejects foreign '.$field);check($db->tables===$snapshot,'foreign disable leaves receiver and audit untouched');$db->tables['tblasaas_webhook_integrations'][0]=$row;}
throws(fn()=>$store->disable(999),'disable rejects unknown receiver ID');
$store->disable(1);$disabledSnapshot=$db->tables;$store->disable(1);check($db->tables===$disabledSnapshot,'repeated disable is a no-op with no duplicate audit');throws(fn()=>$store->revealToken(1),'disabled receiver does not expose token');
echo "Legacy webhook encrypted recovery: additional recovery, ACL, tamper, hash-only and rotation checks passed.\n";
