<?php
/** Real issuer ACL/storage/transactions, SQLite or disposable CI MySQL. No bank access. */
require dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanySettingsRuntime.php';
require_once APPPATH.'../modules/banco_brasil/libraries/BancoBrasil/ChannelPreferences.php';
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;
IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$primary=(new IssuerResourceAccess($db,$p))->primaryId();
$db->query("INSERT INTO {$p}issuers_registry(id,legal_name,tax_id,active,settings_json,created_by,created_at,identity_status) VALUES(41,'Synthetic A','12345678000195',1,'{}',7,'2026-10-18','complete'),(82,'Synthetic B','52998224725',1,'{}',7,'2026-10-18','complete')");
$db->query("INSERT INTO {$p}staff VALUES(12,1,0,NULL),(13,1,1,NULL),(14,1,0,NULL)");
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(41,12,1),(41,14,1)");
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(41,12,'banco_brasil','view'),(41,12,'banco_brasil','manage'),(41,14,'banco_brasil','view')");
function channelThrows(callable $op,string $expected,string $label):void{$actual=null;try{$op();}catch(Throwable $error){$actual=$error->getMessage();}essentialCheck($actual===$expected,$label.' ('.var_export($actual,true).')');}
$channels=new BancoBrasil\ChannelPreferences($db,$p);
$legacy=essentialLegacy($f);
foreach([$primary,41,82]as$i){$state=$channels->page($i,7);essentialCheck(!$state['api']&&!$state['cnab']&&!$state['configured'],'no inferred activation, including installation company');}
$original=(new CompanySettingsStorage($db,$p));
CompanySettingsRuntime::run($db,$p,41,7,fn()=>$original->mutate(41,7,'update','smtp_password','SYNTHETIC-PRIVATE-SETTING'));
$beforeDoc=json_decode($db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=41")->row()->settings_json,true,64,JSON_THROW_ON_ERROR);
$state=$channels->page(41,12);$stale=$state['revision'];
$state=$channels->setEnabled(41,12,'api',true,$state['revision']);
essentialCheck($state['api']&&!$state['cnab']&&$state['configured'],'API enabled independently by scoped operator');
channelThrows(fn()=>$channels->setEnabled(41,12,'cnab',true,$stale),'bb_channel_stale','old form revision cannot overwrite settings');
$state=$channels->setEnabled(41,12,'cnab',true,$state['revision']);
essentialCheck($state['api']&&$state['cnab'],'API and CNAB coexist');
$state=$channels->setEnabled(41,12,'api',false,$state['revision']);
essentialCheck(!$state['api']&&$state['cnab'],'disabling API preserves CNAB');
essentialCheck(!$channels->page(82,7)['api']&&!$channels->page(82,7)['cnab'],'company B stays disabled');
$afterDoc=json_decode($db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=41")->row()->settings_json,true,64,JSON_THROW_ON_ERROR);unset($afterDoc['bb_payment_channels']);
essentialCheck($afterDoc===$beforeDoc&&$original->get(41,'smtp_password')==='SYNTHETIC-PRIVATE-SETTING','channel edit preserves unrelated private settings');
channelThrows(fn()=>$channels->page(82,12),'bb_channel_denied','A membership grants no B access');
channelThrows(fn()=>$channels->page(41,13),'bb_channel_denied','unlinked global admin has no implicit access');
channelThrows(fn()=>$channels->setEnabled(41,14,'api',true,$state['revision']),'bb_channel_denied','view-only actor cannot toggle channels');
channelThrows(fn()=>CompanySettingsRuntime::run($db,$p,41,7,fn()=>$channels->page(82,7)),'bb_channel_denied','selected request cannot read a different company');
$auditBefore=$db->query("SELECT * FROM {$p}issuers_audit ORDER BY id")->result_array();
essentialCheck($channels->setEnabled(41,12,'api',false,$state['revision'])===$state,'identical update is idempotent');
essentialCheck($db->query("SELECT * FROM {$p}issuers_audit ORDER BY id")->result_array()===$auditBefore,'idempotent request adds no duplicate audit');
$db->failOn='INSERT INTO '.$p.'issuers_audit';
channelThrows(fn()=>$channels->setEnabled(41,12,'api',true,$state['revision']),'issuer_storage_failed','audit failure rolls back channel update');$db->failOn=null;
essentialCheck($channels->page(41,12)===$state,'rollback preserves switches and revision');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12");
channelThrows(fn()=>$channels->setEnabled(41,12,'cnab',false,$state['revision']),'bb_channel_denied','revoked membership blocks previously opened form');
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");
$valid=$db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=41")->row()->settings_json;
foreach(['null','{"format":1,"revision":1,"api":1,"cnab":false}','{"format":1,"revision":1,"api":true,"cnab":false,"extra":true}']as$bad){
 $db->query("UPDATE {$p}issuers_registry SET settings_json=? WHERE id=41",['{"bb_payment_channels":'.$bad.'}']);
 channelThrows(fn()=>$channels->page(41,12),'bb_channel_configuration_invalid','corrupt activation fails closed');
}
$db->query("UPDATE {$p}issuers_registry SET settings_json=? WHERE id=41",[$valid]);
essentialCheck(essentialLegacy($f)===$legacy,'channel changes never touch legacy global settings');
echo 'BB independent channels: '.$GLOBALS['essentialChecks'].' checks passed ('.$db->driver().'). No provider call.'.PHP_EOL;
