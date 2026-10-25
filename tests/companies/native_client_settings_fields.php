<?php
require dirname(__DIR__).'/settings/company_settings.php';
require APPPATH.'services/NativeCompanyCustomFields.php';
NativeCompanyCustomFieldSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$fields=new NativeCompanyCustomFields($db,$p);$start=$GLOBALS['essentialChecks'];
foreach([41,82]as$i)foreach(['customers','contacts']as$to){$d=NativeCompanyCustomFields::definition(['fieldto'=>$to,'name'=>'Owned '.$i.' '.$to,'type'=>'input']);$db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)",[$i,$to,$to.'_'.$i,json_encode($d)]);}
$rows=$fields->definitionsForClientSettings(41,12);essentialCheck(count($rows)===1&&$rows[0]['name']==='Owned 41 customers','settings-view only reads customer definitions in its issuer');
settingThrows(fn()=>$fields->definitions(41,12,'customers'),'native_custom_field_denied','settings catalog never grants ordinary customer definition access');
essentialCheck(count($fields->definitionsForClientSettings(82,12))===1,'B explicit settings ACL resolves B catalog only');
settingThrows(fn()=>$fields->definitionsForClientSettings(41,14),'native_custom_field_denied','membership without settings-view denied');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='settings' AND capability='view'");
settingThrows(fn()=>$fields->definitionsForClientSettings(41,12),'native_custom_field_denied','settings ACL revocation denied');
echo 'Native Clients settings custom-field catalog: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
