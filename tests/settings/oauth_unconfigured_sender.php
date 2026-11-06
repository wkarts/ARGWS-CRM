<?php
/** Real disposable SQLite storage; no HTTP/SMTP client is invoked. */
require __DIR__.'/native_settings.php';
require_once APPPATH.'services/CompanyGoogleMailOAuth.php';
require_once APPPATH.'services/CompanyMicrosoftMailOAuth.php';
$db->query('UPDATE '.$p.'issuers_registry SET active=1 WHERE id=82');
foreach([CompanyGoogleMailOAuthSchema::class,CompanyMicrosoftMailOAuthSchema::class] as $schema){
    foreach($schema::definitions() as $table=>$fields){
        $parts=[];foreach($fields as $key=>$type)$parts[]=$key.' '.$type.' NOT NULL';
        $parts[]='PRIMARY KEY('.array_key_first($fields).')';
        $db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).')');
    }
}
unset($db->data_cache['table_names']);
$encrypt=fn($v)=>'sealed:'.base64_encode($v);$decrypt=fn($v)=>base64_decode(substr($v,7),true);
foreach(['google'=>new CompanyGoogleMailOAuth($db,$p),'microsoft'=>new CompanyMicrosoftMailOAuth($db,$p)] as $provider=>$oauth){
    if(isset($argv[1])&&$argv[1]!==$provider)continue;
    foreach([$primary,41,82] as $issuer){
        $store->mutate($issuer,7,'update','smtp_email','',null);
        $before=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array();
        $page=$oauth->view($issuer,7);
        essentialCheck($page['sender_email']===''&&$page['status']==='unconfigured','unconfigured '.$provider.' page opens for issuer '.$issuer);
        essentialCheck($before===$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array(),'GET has no audit or configuration side effects');
        $save=fn($rev)=>$provider==='google'?$oauth->save($issuer,7,$rev,'syntheticclient.apps.googleusercontent.com','synthetic-secret',$encrypt,$decrypt):$oauth->save($issuer,7,$rev,'11111111-1111-4111-8111-111111111111','22222222-2222-4222-8222-222222222222','synthetic-secret',$encrypt,$decrypt);
        essentialThrows(fn()=>$save(0),'company_'.$provider.'_mail_sender_required','write still requires own valid sender');
        $store->mutate($issuer,7,'update','smtp_email','sender-'.$issuer.'@example.invalid',null);
        $save(0);$configured=$oauth->view($issuer,7);
        essentialCheck($configured['revision']===1&&$configured['sender_email']==='sender-'.$issuer.'@example.invalid','valid own configuration saved');
        essentialThrows(fn()=>$save(0),'company_'.$provider.'_mail_stale','stale revision denied');
    }
    essentialThrows(fn()=>$oauth->view(82,14),'company_'.$provider.'_mail_access_denied','foreign reader denied');
    essentialCheck($oauth->view(41,7)['sender_email']==='sender-41@example.invalid','A retains its sender after B save');
}
echo "Both OAuth configuration readers support incomplete setup; writes, ACL, revisions and isolation preserved. No provider calls.\n";
