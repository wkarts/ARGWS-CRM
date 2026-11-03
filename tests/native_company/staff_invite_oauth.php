<?php
require __DIR__.'/staff_invite_creation.php';require_once APPPATH.'services/NativeCompanyStaffInviteMailSource.php';
$start=$GLOBALS['essentialChecks'];$configurator->save($a,7,$configurator->page($a,7)['revision'],'google',1);
[$id,$mail,$headers]=$createInvite('oauth');$source=new NativeCompanyStaffInviteMailSource($db,$p,$mail,$headers,$secret);$epoch=$oauth->view($a,7)['epoch'];
$provider=new CompanyGoogleMailOAuthTokenProvider($oauth,$a,$epoch,'a@example.invalid',$encrypt,$decrypt,$source);$provider->assertReady();essentialCheck(base64_decode($provider->getOauth64())==="user=a@example.invalid\x01auth=Bearer synthetic-access\x01\x01",'real OAuth provider accepts exact creation invitation source');
$clock+=3700;$googleEmail='a@example.invalid';$hook=function()use($db,$p,$a,$id){$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=?',[$a,$id]);};
essentialThrows(fn()=>$provider->getOauth64(),'native_staff_invite_denied','creation membership revoked while refresh yields blocks credential release');
echo 'Staff invitation concrete OAuth source: '.($GLOBALS['essentialChecks']-$start).' checks; provider HTTP synthetic, no SMTP.'.PHP_EOL;
