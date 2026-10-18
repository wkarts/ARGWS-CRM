<?php
require __DIR__ . '/company_settings.php';
require APPPATH . 'services/CompanyMailConfig.php';
$start = $GLOBALS['essentialChecks'];
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$decrypt = static fn($value) => 'decoded:' . $value;
foreach ([41 => 'a', 82 => 'b'] as $issuer => $letter) {
    foreach (['mail_engine'=>'phpmailer', 'email_protocol'=>'smtp', 'smtp_host'=>"smtp-{$letter}.invalid", 'smtp_port'=>'587', 'smtp_password'=>"synthetic-{$letter}", 'smtp_email'=>"{$letter}@example.invalid"] as $name=>$value) {
        $store->mutate($issuer,7,'update',$name,$value,null);
    }
}
$a = CompanyMailConfig::build($db,$p,41,$decrypt);
$b = CompanyMailConfig::build($db,$p,82,$decrypt);
essentialCheck($a['smtp_host']==='smtp-a.invalid' && $b['smtp_host']==='smtp-b.invalid','explicit origin selects independent transport');
essentialCheck($a['smtp_pass']==='decoded:synthetic-a' && $b['smtp_pass']==='decoded:synthetic-b','credentials never cross companies');
essentialCheck(!CompanySettingsRuntime::active(),'background configuration does not create interactive context');
CompanySettingsRuntime::run($db,$p,82,12,function()use($db,$p,$decrypt){
    $job = CompanyMailConfig::build($db,$p,41,$decrypt);
    essentialCheck($job['smtp_host']==='smtp-a.invalid','persisted job origin is independent from active company');
    essentialCheck(CompanySettingsRuntime::issuerId()===82,'background lookup preserves current request');
});
$store->mutate(82,7,'delete','smtp_password','',null);
essentialCheck(CompanyMailConfig::build($db,$p,82,$decrypt)['smtp_pass']==='','missing secret never falls back to principal or previous transport');
settingThrows(fn()=>CompanyMailConfig::build($db,$p,41,fn()=>false),'company_mail_secret_unavailable','decryption failure is closed');
$store->mutate(82,7,'update','email_protocol','mail',null);
settingThrows(fn()=>CompanyMailConfig::build($db,$p,82,$decrypt),'company_mail_configuration_required','secondary cannot use shared server mail transport');
$store->mutate(82,7,'update','email_protocol','smtp',null);
$store->mutate(82,7,'update','smtp_port','70000',null);
settingThrows(fn()=>CompanyMailConfig::build($db,$p,82,$decrypt),'company_mail_configuration_required','invalid SMTP port rejected');
$store->mutate(82,7,'update','smtp_port','587',null);
foreach (['email_protocol'=>'google','google_mail_client_id'=>'synthetic-id','google_mail_client_secret'=>'synthetic-secret','google_mail_refresh_token'=>'synthetic-refresh'] as $name=>$value) {
    $store->mutate(82,7,'update',$name,$value,null);
}
$b=CompanyMailConfig::build($db,$p,82,$decrypt);
essentialCheck($b['client_id']==='synthetic-id' && $b['client_secret']==='decoded:synthetic-secret','OAuth account is scoped');
essentialCheck($a['client_id']==='' && $a['refresh_token']==='' && $a['dkim_private']==='','reused transport receives explicit OAuth and DKIM resets');
$db->query("UPDATE {$p}issuers_registry SET active=0 WHERE id=82");
settingThrows(fn()=>CompanyMailConfig::build($db,$p,82,$decrypt),'company_settings_issuer_unavailable','inactive company cannot obtain mail configuration');
echo 'Company mail config: '.($GLOBALS['essentialChecks']-$start).' additional checks passed.'.PHP_EOL;
