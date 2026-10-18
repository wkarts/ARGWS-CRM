<?php
/** Compose actual contact origin, native template factory/lookup and SMTP config.
 * No recovery key is generated, no queue write and no SMTP side effect. */
require __DIR__.'/portal_mail_config.php';
require_once APPPATH.'services/CompanyEmailTemplates.php';
use app\services\PortalClientIdentity;
$start=$GLOBALS['essentialChecks'];
$idDefinition=$db->driver()==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
$db->query("CREATE TABLE {$p}emailtemplates(emailtemplateid {$idDefinition},type TEXT NOT NULL,slug VARCHAR(100) NOT NULL,language VARCHAR(40),name TEXT NOT NULL,subject TEXT NOT NULL,message TEXT NOT NULL,fromname TEXT NOT NULL,fromemail VARCHAR(100),plaintext INT NOT NULL DEFAULT 0,active INT NOT NULL DEFAULT 0,`order` INT NOT NULL)");
unset($db->data_cache['table_names']);
CompanyEmailTemplateSchema::ensure($db,$p);CompanyEmailTemplates::reconcile($db,$p);
foreach([41=>'A recovery',82=>'B recovery'] as $issuer=>$subject)$db->query("UPDATE {$p}emailtemplates SET subject=? WHERE issuer_id=? AND slug='contact-forgot-password' AND language='portuguese_br'",[$subject,$issuer]);
CompanySettingsRuntime::run($db,$p,82,12,function()use($db,$p,$decrypt){
 foreach([501=>[41,'A recovery','smtp-a.invalid'],502=>[82,'B recovery','smtp-b.invalid']] as $contact=>$expected){
  $origin=PortalClientIdentity::mailOrigin($db,$p,$contact);
  $template=CompanyEmailTemplates::findForIssuer($db,$p,$origin['issuer_id'],'contact-forgot-password','portuguese_br');
  $transport=CompanyMailConfig::build($db,$p,$origin['issuer_id'],$decrypt);
  essentialCheck($origin['issuer_id']===$expected[0] && $template->subject===$expected[1] && $transport['smtp_host']===$expected[2],'same email across contacts does not change explicit template/transport owner');
 }
 essentialCheck(CompanySettingsRuntime::issuerId()===82,'full metadata preparation leaves active B unchanged');
});
$db->query("UPDATE {$p}emailtemplates SET message='' WHERE issuer_id=41 AND slug='contact-forgot-password' AND language='portuguese_br'");
$origin=PortalClientIdentity::mailOrigin($db,$p,501);
essentialCheck(CompanyEmailTemplates::findForIssuer($db,$p,$origin['issuer_id'],'contact-forgot-password','portuguese_br')->issuer_id==41,'fallback stays with contact owner despite optional shared-client link');
echo 'Portal origin/template/transport: '.($GLOBALS['essentialChecks']-$start).' checks passed; reset, queue and delivery remain pending.'.PHP_EOL;
