<?php
require __DIR__.'/../settings/resume_439_support.php';
require_once APPPATH.'services/NativeCompanyMailSettings.php';
resume439Schema(CompanyGoogleMailOAuthSchema::class);resume439Schema(CompanyMicrosoftMailOAuthSchema::class);resume439Schema(CompanyPortalMailSchema::class);
$mail=new NativeCompanyMailSettings($db,$p);$start=$GLOBALS['essentialChecks'];$before=$mail->page(41,12);$other=$mail->page(82,12);$values=$before['values'];
$values['smtp_email']='issuer-a@example.invalid';$values['smtp_host']='smtp.example.invalid';$values['smtp_password']='synthetic-smtp-secret';$values['google_mail_client_id']='synthetic-client.apps.googleusercontent.com';$values['google_mail_client_secret']='synthetic-google-secret';$values['microsoft_mail_client_id']='22222222-2222-4222-8222-222222222222';$values['microsoft_mail_azure_tenant_id']='11111111-1111-4111-8111-111111111111';$values['microsoft_mail_client_secret']='synthetic-microsoft-secret';
$after=$mail->save(41,12,$before['revision'],$values,$seal439,$open439);
essentialCheck($after['values']['smtp_password']===''&&$after['values']['google_mail_client_secret']===''&&$after['values']['microsoft_mail_client_secret']==='','GET DTO never returns provider or SMTP secrets');
essentialCheck($after['secret_configured']===['smtp'=>true,'google'=>true,'microsoft'=>true],'configured flags do not require decrypting credentials');
essentialCheck($mail->page(82,12)===$other,'all changes are isolated from issuer B');
essentialCheck($after['google_revision']===1&&$after['microsoft_revision']===1,'both actual provider configuration writers participate in same Settings transaction');
$stored=(new CompanySettingsStorage($db,$p))->get(41,'smtp_password');essentialCheck($stored!==$values['smtp_password']&&$open439($stored)===$values['smtp_password'],'SMTP password cipher comes from explicit encryptor');
$keep=$after['values'];$same=$mail->save(41,12,$after['revision'],$keep,$seal439,$open439);essentialCheck($same===$after,'blank secret fields preserve configured ciphertext and do not rotate accounts');
settingThrows(fn()=>$mail->save(41,12,$before['revision'],$keep,$seal439,$open439),'native_mail_settings_stale','stale whole-form revision denied');
$bad=$keep;$bad['email_protocol']='sendmail';settingThrows(fn()=>$mail->save(41,12,$after['revision'],$bad,$seal439,$open439),'native_mail_settings_invalid','named issuer cannot use server-global sendmail identity');
$bad=$keep;$bad['bcc_emails']="safe@example.invalid\r\nBcc: other@example.invalid";settingThrows(fn()=>$mail->save(41,12,$after['revision'],$bad,$seal439,$open439),'native_mail_settings_invalid','header injection rejected');
$bad=$keep;$bad['smtp_password']='changed';settingThrows(fn()=>$mail->save(41,12,$after['revision'],$bad,fn($v)=>$v,$open439),'company_settings_encryption_failed','plaintext encryptor rejected');
$changed=$keep;$changed['smtp_host']='changed.example.invalid';$changed['google_mail_client_secret']='rotated-synthetic';$changed['microsoft_mail_client_secret']='rotated-synthetic';
$db->query("CREATE TRIGGER native_mail_settings_failure BEFORE UPDATE ON {$p}company_microsoft_mail_accounts BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$mail->save(41,12,$after['revision'],$changed,$seal439,$open439),'issuer_microsoft_mail_write_unconfirmed','unconfirmed final provider update fails atomic settings transaction');$db->query('DROP TRIGGER native_mail_settings_failure');
essentialCheck($mail->page(41,12)===$after,'SMTP and earlier Google rotation both rollback if Microsoft confirmation fails');
$encryptCalls=0;$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature=? AND capability=?',['settings','edit']);
settingThrows(fn()=>$mail->save(41,12,$after['revision'],$values,function($v)use(&$encryptCalls){$encryptCalls++;return 'cipher';},$open439),'native_mail_settings_denied','revoked edit authorization before secret processing');essentialCheck($encryptCalls===0,'no encryption/decryption/network work under revoked authority');
$db->pdo->beginTransaction();try{settingThrows(fn()=>(new CompanyGoogleMailOAuth($db,$p))->saveInOwnedTransaction(41,7,1,$values['google_mail_client_id'],'',$seal439,$open439),'issuer_owned_transaction_required','raw PDO transaction is not composition authority');}finally{$db->pdo->rollBack();}
echo 'Native mail Settings: '.($GLOBALS['essentialChecks']-$start).' checks passed, actual SQLite writers with synthetic encryptors; no provider requests, OAuth UI still separate.'.PHP_EOL;
