<?php
require __DIR__.'/native_contact_mail.php';
CompanyCustomerAdminSchema::ensure($db,$p);$start=$GLOBALS['essentialChecks'];
foreach(['firstname','lastname']as$field)if(!$db->field_exists($field,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$field.' varchar(191)');
$db->query("INSERT INTO {$p}staff(staffid,active,admin,firstname,lastname) VALUES(51,1,0,'Assigned','Mail')");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,51,1)",[$a]);
$access=new CompanyCustomerAccess($db,$p);$access->assign($a,7,$client,[51],$access->assignments($a,7,$client)['revision']);
IssuerTransaction::run($db,$p,$service->tables(),fn()=>$service->queueInOwned($a,51,$client,101,['contact-set-password'],$base,$decrypt));
$mail=(object)$db->query("SELECT * FROM {$p}mail_queue ORDER BY id DESC LIMIT 1")->row_array();$headers=unserialize($mail->headers,['allowed_classes'=>false]);$db->query("UPDATE {$p}mail_queue SET status='sending' WHERE id=?",[$mail->id]);$mail->status='sending';
$access->assign($a,7,$client,[],$access->assignments($a,7,$client)['revision']);
essentialThrows(fn()=>CompanyNativeContactMail::claimDispatch($db,$p,$mail,$headers,$portalSecret),'company_contact_mail_denied','revoking responsible before SMTP denies the assigned mail');
essentialCheck($db->query("SELECT state FROM {$p}company_native_mail_dispatch_fences WHERE queue_id=?",[$mail->id])->row()->state==='prepared','revoked grant does not consume or send prepared mail');
$access->assign($a,7,$client,[51],$access->assignments($a,7,$client)['revision']);$permit=CompanyNativeContactMail::claimDispatch($db,$p,$mail,$headers,$portalSecret);essentialCheck(is_array($permit),'active exact assignment can claim its own contact mail');CompanyNativeContactMail::completeDispatch($db,$p,$permit,false);
echo 'Assigned contact mail: '.($GLOBALS['essentialChecks']-$start).' checks passed, no SMTP.'.PHP_EOL;
