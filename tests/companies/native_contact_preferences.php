<?php
require dirname(__DIR__).'/settings/company_settings.php';
require APPPATH.'services/NativeCompanyContactPreferences.php';
$start=$GLOBALS['essentialChecks'];$prefs=new NativeCompanyContactPreferences($db,$p);
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$seed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
preg_match("/\([0-9]+, 'default_contact_permissions', '([^']+)', [01]\)/",$seed,$match);
essentialCheck(isset($match[1])&&stripslashes($match[1])===NativeCompanyContactPreferences::DEFAULT_PERMISSIONS,'public versioned seed proves exact default permissions');
$helper=file_get_contents(APPPATH.'helpers/clients_helper.php');essentialCheck(str_contains($helper,"apply_filters('automatic_calling_codes_enabled', true)"),'native factory calling-code default is true before hooks');
$initial=$prefs->read(41,7);essentialCheck($initial['permissions']===[1,2,3,4,5,6]&&$initial['calling']===true,'named company uses proven factory defaults only');
$prefs->save(41,7,$initial['revision'],['default_contact_permissions'=>'a:1:{i:0;s:1:"1";}','automatic_calling_codes'=>'0']);
essentialCheck($prefs->read(41,7)['permissions']===[1]&&!$prefs->read(41,7)['calling']&&$prefs->read(82,7)['permissions']===[1,2,3,4,5,6],'A preference never changes B');
settingThrows(fn()=>$prefs->save(41,7,$initial['revision'],['automatic_calling_codes'=>'1']),'company_contact_preferences_stale','stale native settings cannot overwrite');
foreach(['O:8:"stdClass":0:{}','a:1:{i:0;s:1:"7";}','a:2:{i:0;s:1:"1";i:1;i:1;}','a:1:{i:2;i:1;}','a:1:{i:0;a:0:{}}']as$bad)settingThrows(fn()=>NativeCompanyContactPreferences::permissions($bad),'company_contact_preferences_invalid','malformed/object/duplicate/out-of-range defaults denied');
settingThrows(fn()=>NativeCompanyContactPreferences::prepare(['automatic_calling_codes'=>'true']),'company_contact_preferences_invalid','calling setting is strict bool wire value');
echo 'Native contact preferences: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
