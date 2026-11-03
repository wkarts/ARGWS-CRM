<?php
require __DIR__.'/native_contact_message.php';
require APPPATH.'services/NativeCompanyCustomerRegistration.php';
$registration=new NativeCompanyCustomerRegistration($db,$p,$portalSecret);$start=$GLOBALS['essentialChecks'];
$db->query("UPDATE {$p}clients SET active=0,registration_confirmed=0 WHERE userid=?",[$client]);$db->query("UPDATE {$p}contacts SET active=0 WHERE id=101");
$revision=$registration->revision($a,7,$client);$count=(int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n;
essentialThrows(fn()=>$registration->confirm($b,7,$client,$revision,$base,$decrypt),'company_client_access_denied','B cannot confirm A registration');
$db->failOn='INSERT INTO '.$p.'company_native_mail_dispatch_fences';essentialThrows(fn()=>$registration->confirm($a,7,$client,$revision,$base,$decrypt),'issuer_storage_failed','confirmation cannot commit without requested email fence');$db->failOn=null;
essentialCheck((int)$db->query("SELECT registration_confirmed FROM {$p}clients WHERE userid=?",[$client])->row()->registration_confirmed===0&&(int)$db->query("SELECT active FROM {$p}contacts WHERE id=101")->row()->active===0,'late notification failure rolls back both activation changes');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===$count,'late notification failure leaves no queued message');
$registration->confirm($a,7,$client,$revision,$base,$decrypt);
essentialCheck((int)$db->query("SELECT registration_confirmed FROM {$p}clients WHERE userid=?",[$client])->row()->registration_confirmed===1&&(int)$db->query("SELECT active FROM {$p}contacts WHERE id=101")->row()->active===1,'native confirmation activates exact owned customer and primary contact');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===$count+1,'native confirmation queues exactly one source-bound message');
essentialThrows(fn()=>$registration->confirm($a,7,$client,$revision,$base,$decrypt),'company_client_stale','replay cannot requeue confirmation');
$db->query("UPDATE {$p}staff SET admin=0 WHERE staffid=7");$db->query("DELETE FROM {$p}issuers_staff WHERE issuer_id=? AND staff_id=7",[$a]);essentialThrows(fn()=>$registration->revision($a,7,$client),'company_client_access_denied','revoked administrator cannot inspect confirmation revision');
echo 'Native customer registration: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
