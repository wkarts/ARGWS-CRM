<?php
require __DIR__.'/settings/native_settings.php';
require_once APPPATH.'services/CompanySettingsCatalog.php';
$seed=file_get_contents(dirname(__DIR__).'/install/database.sql');
foreach(['dateformat'=>['localization','Y-m-d|%Y-%m-%d'],'time_format'=>['localization','24'],'invoice_due_after'=>['documents','30']]as$key=>[$group,$default]){
    essentialCheck(CompanySettingsCatalog::defaultValue($group,$key)===$default,'native public default '.$key);
    essentialCheck(str_contains($seed,"'".$key."', '".$default."'"),'default matches public installation schema snapshot');
}
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id IN(41,82)");
$store=new CompanySettingsStorage($db,$p);
$store->mutate(41,12,'update','invoice_due_after','0');$store->mutate(82,12,'update','invoice_due_after','15');
essentialCheck($store->get(41,'invoice_due_after')==='0'&&$store->get(82,'invoice_due_after')==='15','explicit defaults remain isolated including zero');
foreach(['-1','1e3','10000','1.5',"30\n"]as$bad)settingThrows(fn()=>CompanySettingsCatalog::prepare('documents',['invoice_due_after'=>$bad]),'company_settings_invalid_input','invalid due interval rejected');
essentialCheck(CompanySettingsCatalog::prepare('documents',['invoice_due_after'=>'0'])===['invoice_due_after'=>'0'],'zero means no automatic due date');
echo "Native public settings defaults passed.\n";
