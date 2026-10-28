<?php
require __DIR__.'/calendar_event_mail.php';
require_once APPPATH.'vendor/autoload.php';
require_once APPPATH.'services/CompanyGoogleMailOAuthTokenProvider.php';
require_once APPPATH.'services/NativeCompanyEventMailSource.php';
foreach(CompanyGoogleMailOAuthSchema::definitions()as$table=>$fields){$parts=[];foreach($fields as$key=>$type)$parts[]=$key.' '.$type.' NOT NULL';$parts[]='PRIMARY KEY('.array_key_first($fields).')';$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).')');}
unset($db->data_cache['table_names']);
$encrypt=fn($v)=>'sealed:'.base64_encode($v);$decrypt=fn($v)=>str_starts_with($v,'sealed:')?base64_decode(substr($v,7),true):$v;$clock=time();$hook=null;$requests=0;
$oauth=new CompanyGoogleMailOAuth($db,$p,new CompanyGoogleMailOAuthClient(function($url,$method,$form,$bearer)use($db,&$requests,&$hook){essentialCheck(!$db->pdo->inTransaction(),'OAuth transport outside transaction');$requests++;if($hook){$f=$hook;$hook=null;$f();}return $url===CompanyGoogleMailOAuthClient::TOKEN?['token_type'=>'Bearer','expires_in'=>3600,'scope'=>CompanyGoogleMailOAuthClient::MAIL_SCOPE,'access_token'=>'synthetic-event-access','refresh_token'=>'synthetic-event-refresh']:['sub'=>'synthetic-event-sub','email'=>'from@example.test','email_verified'=>true];}),function()use(&$clock){return$clock;});
$oauth->save(41,7,0,'event-client.apps.googleusercontent.com','synthetic-secret',$encrypt,$decrypt);$url=$oauth->begin(41,7,1,'https://crm.example.invalid/admin/settings/google_mail_oauth',$encrypt);parse_str(parse_url($url,PHP_URL_QUERY),$q);$oauth->callback(41,7,$q['state'],'synthetic-code',$encrypt,$decrypt);
(new CompanyPortalMailConfig($db,$p))->save(41,7,0,'google',1);
$events->save(41,7,array_replace($input,['title'=>'OAuth event']));$result=$worker->run('2026-10-25 13:00:00','https://example.test/crm',$decrypt);essentialCheck($result['notified']===1,'event queues with explicitly selected Google account');
$mail=$db->query('SELECT * FROM '.$p.'mail_queue ORDER BY id DESC LIMIT 1')->row();$headers=unserialize($mail->headers,['allowed_classes'=>false]);
$source=new NativeCompanyEventMailSource($db,$p,$mail,$headers);$provider=new CompanyGoogleMailOAuthTokenProvider($oauth,41,$oauth->view(41,7)['epoch'],'from@example.test',$encrypt,$decrypt,$source);
essentialCheck(base64_decode($provider->getOauth64())==="user=from@example.test\x01auth=Bearer synthetic-event-access\x01\x01",'actual provider accepts concrete event source');
$clock+=3700;$hook=function()use($db,$p,$headers){$db->query('UPDATE '.$p.'company_events SET revision=revision+1 WHERE eventid=?',[$headers[NativeCompanyEventMail::HEADER]['event']]);};
settingThrows(fn()=>$provider->getOauth64(),'native_event_mail_stale','source changed during OAuth refresh cannot release refreshed credential');
echo "Calendar event Google OAuth: real provider/source refresh and intervening revocation passed; synthetic HTTP only.\n";
