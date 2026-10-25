<?php
require __DIR__.'/staff_self_endpoint.php';require_once APPPATH.'vendor/autoload.php';
$db->query('UPDATE '.$p.'company_departments SET issuer_id=41 WHERE id=100');$ci->input->isPost=true;CompanyRequestScope::$csrf=true;
$before=$db->query('SELECT * FROM '.$p.'staff WHERE staffid=12')->row_array();$setup=$self->googleSetup(41,12,'https://crm.example.test/subpath/');
essentialCheck(preg_match('/^[A-Z2-7]{16,128}$/D',$setup['secret'])===1&&str_starts_with($setup['uri'],'otpauth://totp/'),'bundled TOTP creates actual provisioning URI for installation login identity');
essentialCheck($before===$db->query('SELECT * FROM '.$p.'staff WHERE staffid=12')->row_array(),'setup generation never installs or persists an unverified secret');
$google=new PragmaRX\Google2FA\Google2FA();$code=$google->getCurrentOtp($setup['secret']);$ci->input->data=['secret'=>$setup['secret'],'code'=>$code];essentialCheck(NativeCompanyStaffEndpoints::verifyGoogleSetup($ci),'same native verification endpoint checks real TOTP');
$wrong=$code==='000000'?'000001':'000000';$ci->input->data['code']=$wrong;essentialCheck(!NativeCompanyStaffEndpoints::verifyGoogleSetup($ci),'wrong code is not accepted');
CompanyRequestScope::$csrf=false;settingThrows(fn()=>NativeCompanyStaffEndpoints::verifyGoogleSetup($ci),'native_staff_csrf_denied','verification requires native mutation boundary');CompanyRequestScope::$csrf=true;$GLOBALS['selfSessionActor']=7;settingThrows(fn()=>NativeCompanyStaffEndpoints::verifyGoogleSetup($ci),'issuer_staff_denied','context cannot impersonate login identity');$GLOBALS['selfSessionActor']=12;
settingThrows(fn()=>$self->googleSetup(41,12,'https://user:pass@example.test/'),'native_staff_two_factor_invalid','provisioning issuer rejects credential-bearing base URL');
essentialCheck($before===$db->query('SELECT * FROM '.$p.'staff WHERE staffid=12')->row_array(),'verification does not mutate stored factor before final reauthenticated save');
echo "Personal Google setup: eight actual SQLite/bundled TOTP checks; Imagick PNG and browser not executed.\n";
