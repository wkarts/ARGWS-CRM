<?php
/** Real origin resolver + real company mail configuration, synthetic storage/decryption. No SMTP or queue dispatch. */
require __DIR__.'/native_settings.php';
require APPPATH.'services/PortalClientIdentity.php';
require APPPATH.'services/CompanyMailConfig.php';
use app\services\PortalClientIdentity;
$start=$GLOBALS['essentialChecks'];
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$db->query("ALTER TABLE {$p}clients ADD COLUMN active INT DEFAULT 1");
$db->query("INSERT INTO {$p}clients(userid,company,active) VALUES(101,'A customer',1),(202,'B customer',1)");
$db->query("CREATE TABLE {$p}contacts(id INT PRIMARY KEY,userid INT,active INT,email VARCHAR(255))");
$db->query("INSERT INTO {$p}contacts VALUES(501,101,1,'same@example.invalid'),(502,202,1,'same@example.invalid')");
$db->query("INSERT INTO {$p}issuers_clients(client_id,owner_issuer_id,tax_id,created_by,created_at) VALUES(101,41,'synthetic-a',7,'2026-10-18 00:00:00'),(202,82,'synthetic-b',7,'2026-10-18 00:00:00')");
$db->query("INSERT INTO {$p}issuers_client_links(client_id,issuer_id,linked_by,created_at) VALUES(101,82,7,'2026-10-18 00:00:00')");
foreach([41=>'a',82=>'b'] as $issuer=>$letter)foreach(['mail_engine'=>'phpmailer','email_protocol'=>'smtp','smtp_host'=>'smtp-'.$letter.'.invalid','smtp_port'=>'587','smtp_password'=>'synthetic-'.$letter,'smtp_email'=>$letter.'@example.invalid'] as $name=>$value)$store->mutate($issuer,7,'update',$name,$value,null);
$decrypt=static fn($value)=>'decoded:'.$value;
foreach([501=>[41,'a'],502=>[82,'b']] as $contact=>$expected){
 $origin=PortalClientIdentity::mailOrigin($db,$p,$contact);
 $config=CompanyMailConfig::build($db,$p,$origin['issuer_id'],$decrypt);
 essentialCheck($origin['issuer_id']===$expected[0] && $config['smtp_host']==='smtp-'.$expected[1].'.invalid','explicit portal contact binds correct A/B transport');
 essentialCheck($config['smtp_pass']==='decoded:synthetic-'.$expected[1],'portal origin does not reuse other company credentials');
}
CompanySettingsRuntime::run($db,$p,82,12,function()use($db,$p,$decrypt){
 $origin=PortalClientIdentity::mailOrigin($db,$p,501);
 essentialCheck($origin['issuer_id']===41 && CompanyMailConfig::build($db,$p,$origin['issuer_id'],$decrypt)['smtp_host']==='smtp-a.invalid','active administrative B cannot replace contact A mail origin');
 essentialCheck(CompanySettingsRuntime::issuerId()===82,'origin resolution leaves interactive selection untouched');
});
essentialCheck(!CompanySettingsRuntime::active(),'public metadata/config lookup does not create administrative scope');
echo 'Portal origin/config integration: '.($GLOBALS['essentialChecks']-$start).' checks passed; template, reset and dispatch integration pending.'.PHP_EOL;
