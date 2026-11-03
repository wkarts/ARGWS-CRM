<?php
require __DIR__.'/staff_self.php';require_once APPPATH.'vendor/autoload.php';
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12');$page=$self->page(41,12);$google=new PragmaRX\Google2FA\Google2FA();$secret=$google->generateSecretKey();$code=$google->getCurrentOtp($secret);$encrypt=fn($value)=>'synthetic-encrypted:'.base64_encode($value);
settingThrows(fn()=>$self->twoFactor(41,12,$page['revision'],'wrong','google',$secret,$code,$encrypt),'native_staff_old_password_invalid','2FA enrollment requires current identity password');
settingThrows(fn()=>$self->twoFactor(41,12,$page['revision'],'synthetic-new-password','google',$secret,'not-code',$encrypt),'native_staff_two_factor_invalid','UI verification cannot bypass server TOTP check');
$revision=$self->twoFactor(41,12,$page['revision'],'synthetic-new-password','google',$secret,$code,$encrypt);$row=$db->query('SELECT google_auth_secret,two_factor_auth_enabled FROM '.$p.'staff WHERE staffid=12')->row_array();essentialCheck((int)$row['two_factor_auth_enabled']===2&&$row['google_auth_secret']===$encrypt($secret),'real bundled Google2FA validates proof and only ciphertext is persisted');
essentialCheck(!isset($self->page(41,12)['member']->google_auth_secret),'personal form does not project stored TOTP ciphertext');
settingThrows(fn()=>$self->twoFactor(41,12,$revision,'wrong','off',null,null,$encrypt),'native_staff_old_password_invalid','disabling authentication factor requires reauthentication');
$after=$self->twoFactor(41,12,$revision,'synthetic-new-password','off',null,null,$encrypt);essentialCheck((int)$self->page(41,12)['member']->two_factor_auth_enabled===0&&$after!==$revision,'same original off mode commits only personal authentication state');
echo "Personal 2FA: six real SQLite/bundled-TOTP checks; encryption deterministic fixture, no SMTP.\n";
if(!$db->table_exists($p.'emailtemplates'))$db->query('CREATE TABLE '.$p.'emailtemplates(emailtemplateid INTEGER PRIMARY KEY,issuer_id INT NULL,slug TEXT,active INT)');
$db->query('INSERT INTO '.$p.'emailtemplates(emailtemplateid,issuer_id,slug,active) VALUES(987,41,?,1)',['two-factor-authentication']);
settingThrows(fn()=>$self->twoFactor(41,12,$after,'synthetic-new-password','email',null,null,$encrypt),'native_staff_two_factor_email_unavailable','tenant template cannot enable global authentication email factor');
$db->query('INSERT INTO '.$p.'emailtemplates(emailtemplateid,issuer_id,slug,active) VALUES(988,NULL,?,1)',['two-factor-authentication']);
$revision=$self->twoFactor(41,12,$after,'synthetic-new-password','email',null,null,$encrypt);essentialCheck((int)$self->page(41,12)['member']->two_factor_auth_enabled===1,'global login template enables same original email factor without tenant transport');
$revoke=function($value)use($db,$p,$encrypt){$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12');return$encrypt($value);};
settingThrows(fn()=>$self->twoFactor(41,12,$revision,'synthetic-new-password','google',$secret,$google->getCurrentOtp($secret),$revoke),'issuer_staff_denied','revocation during preparation prevents authentication change');
echo "Personal 2FA: three additional installation-template/revocation checks.\n";
