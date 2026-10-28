<?php
require __DIR__.'/settings/native_settings.php';
require_once APPPATH.'services/NativeCompanyUploadPreferences.php';
require_once APPPATH.'services/CompanySettingsCatalog.php';
$fields=NativeCompanyUploadPreferences::fields();
foreach($fields as$key){$value=NativeCompanyUploadPreferences::defaultValue($key);essentialCheck(CompanySettingsCatalog::defaultValue('upload_preferences',$key)===$value,'catalog delegates exact native defaults');$seed=file_get_contents(dirname(__DIR__).'/install/database.sql');essentialCheck(str_contains($seed,"'{$key}', '{$value}'"),'public native default provenance');}
$checks=$GLOBALS['essentialChecks'];
foreach(['.php','.svg','.jpg,.jpg','jpg','.jpg,*.pdf','.jpg, .pdf',".jpg\n",'.exe']as$value)essentialThrows(fn()=>NativeCompanyUploadPreferences::prepare(['allowed_files'=>$value]),'native_upload_option_invalid','unsafe/ambiguous extension rejected');
foreach(['0','-1','1.5','1025','01','']as$value)essentialThrows(fn()=>NativeCompanyUploadPreferences::prepare(['media_max_file_size_upload'=>$value]),'native_upload_option_invalid','invalid size rejected');
essentialCheck(NativeCompanyUploadPreferences::maxBytes(['media_max_file_size_upload'=>'10'])===10485760,'native MiB converted without float');
essentialCheck(CompanySettingsCatalog::prepare('upload_preferences',['allowed_files'=>'.pdf,.png'])===['allowed_files'=>'.pdf,.png'],'validated explicit extensions retained');
echo 'Native upload preferences: '.($GLOBALS['essentialChecks']-$checks).' additional checks passed; actual upload MIME/storage validation is separate.'.PHP_EOL;

$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$store=new CompanySettingsStorage($db,$p);$store->updateBatch(41,7,['allowed_files'=>'.pdf','media_max_file_size_upload'=>'2']);
$a=NativeCompanyUploadPreferences::read($db,$p,41,7);$b=NativeCompanyUploadPreferences::read($db,$p,82,7);
essentialCheck($a['allowed_files']==='.pdf'&&NativeCompanyUploadPreferences::maxBytes($a)===2097152,'company override read exactly');
essentialCheck($b['allowed_files']===NativeCompanyUploadPreferences::defaultValue('allowed_files')&&$b['media_max_file_size_upload']==='10','B never inherits A');
$db->query("UPDATE {$p}issuers_registry SET active=0 WHERE id=41");
settingThrows(fn()=>NativeCompanyUploadPreferences::read($db,$p,41,7),'native_upload_denied','revoked company cannot read preference');
echo "Native upload PDO A/B and revocation passed.\n";
