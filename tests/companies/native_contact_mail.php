<?php
/** Actual SQLite/native template, token, queue and dispatch fences; no network. */
require dirname(__DIR__).'/settings/google_portal_mail.php';
require_once APPPATH.'services/CompanyNativeContactMail.php';
$start=$GLOBALS['essentialChecks'];
if($db->driver()==='sqlite')$db->query("CREATE TABLE {$p}company_native_mail_dispatch_fences(queue_id INTEGER PRIMARY KEY,issuer_id INT NOT NULL,kind TEXT NOT NULL,binding_hash TEXT NOT NULL,state TEXT NOT NULL,attempt_key TEXT,created_at TEXT NOT NULL,dispatched_at TEXT,completed_at TEXT,UNIQUE(issuer_id,queue_id))");else NativeCompanyMailDispatchFence::ensure($db,$p);
unset($db->data_cache['table_names']);
$configurator->save($a,7,1,'native',1);
$db->query("UPDATE {$p}emailtemplates SET active=1 WHERE issuer_id=? AND slug IN ('new-client-created','contact-set-password')",[$a]);
$contact=$db->query("SELECT userid FROM {$p}contacts WHERE id=101")->row();$client=(int)$contact->userid;
$service=new CompanyNativeContactMail($db,$p,$portalSecret);
$queue=static fn()=>IssuerTransaction::run($db,$p,$service->tables(),fn()=>$service->queueInOwned($a,7,$client,101,['new-client-created','contact-set-password'],$base,$decrypt));
$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n;
$queue();
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===$before+2,'native welcome/setup each queues once under owner');
$mails=$db->query("SELECT q.* FROM {$p}mail_queue q JOIN {$p}company_native_mail_dispatch_fences f ON f.queue_id=q.id ORDER BY q.id")->result_array();
$tokens=[];
foreach($mails as$row){$mail=(object)$row;$headers=unserialize($mail->headers,['allowed_classes'=>false]);preg_match('/#token=([a-f0-9]{64})/',$mail->message,$match);$tokens[]=$match[1]??'';
 essentialCheck(str_contains($mail->message,'/authentication/reset_password/0/101/native?issuer_id='.$a)&&!str_contains($mail->message,'/company_portal'),'native same-route setup URL, not parallel portal');
 CompanyNativeContactMail::assertQueueRouting($db,$p,$mail,$headers);
 $missing=$headers;unset($missing[CompanyNativeContactMail::HEADER]);$db->data_cache['table_names']=[];essentialThrows(fn()=>CompanyNativeContactMail::assertQueueRouting($db,$p,$mail,$missing),'company_contact_mail_denied','removing marker cannot bypass contact dispatch fence even with stale metadata cache');unset($db->data_cache['table_names']);
 $foreign=$headers;$foreign[CompanyNativeContactMail::HEADER]['issuer']=$b;essentialThrows(fn()=>CompanyNativeContactMail::assertQueueRouting($db,$p,$mail,$foreign),'company_contact_mail_denied','cross-company marker denied');
 $db->query("UPDATE {$p}mail_queue SET status='sending' WHERE id=?",[$mail->id]);$mail->status='sending';
 $permit=CompanyNativeContactMail::claimDispatch($db,$p,$mail,$headers,$portalSecret);essentialCheck(!$db->pdo->inTransaction()&&is_array($permit),'source proof and fence committed before sending permit');
 CompanyNativeContactMail::completeDispatch($db,$p,$permit,false);
 essentialThrows(fn()=>CompanyNativeContactMail::claimDispatch($db,$p,$mail,$headers,$portalSecret),'company_mail_dispatch_review_required','uncertain transport is never replayed');
}
essentialCheck(strlen($tokens[0])===64&&$tokens[0]===$tokens[1],'both welcome/setup share one valid token, not mutually revoked');
$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n;
$db->failOn='INSERT INTO '.$p.'company_native_mail_dispatch_fences';essentialThrows($queue,'issuer_storage_failed','late fence failure rolls back token/queue/receipt atomically');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===$before,'failed fence leaves no queued mail');
$queue();$row=$db->query("SELECT * FROM {$p}mail_queue ORDER BY id DESC LIMIT 1")->row_array();$mail=(object)$row;$headers=unserialize($mail->headers,['allowed_classes'=>false]);
$db->query("UPDATE {$p}mail_queue SET status='sending' WHERE id=?",[$mail->id]);$mail->status='sending';
$db->query("UPDATE {$p}mail_queue SET message=? WHERE id=?",['tampered',$mail->id]);essentialThrows(fn()=>CompanyNativeContactMail::claimDispatch($db,$p,$mail,$headers,$portalSecret),'company_contact_mail_denied','stale in-memory queue cannot hide persisted payload change');$db->query("UPDATE {$p}mail_queue SET message=? WHERE id=?",[$mail->message,$mail->id]);
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");essentialThrows(fn()=>CompanyNativeContactMail::claimDispatch($db,$p,$mail,$headers,$portalSecret),'company_contact_mail_denied','revoked initiating administrator cannot dispatch contact mail');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
essentialCheck($db->query("SELECT state FROM {$p}company_native_mail_dispatch_fences WHERE queue_id=?",[$mail->id])->row()->state==='prepared','denials consume no sending permit');
echo 'Native contact mail: '.($GLOBALS['essentialChecks']-$start).' checks passed; source templates/queue/token/dispatch tested, no SMTP.'.PHP_EOL;
