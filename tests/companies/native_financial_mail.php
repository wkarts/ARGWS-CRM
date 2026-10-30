<?php
require __DIR__.'/native_payment_records.php';require_once APPPATH.'services/NativeCompanyFinancialMail.php';
if($db->driver()==='mysql'){CompanyPortalMailSchema::ensure($db,$p);NativeCompanyMailDispatchFence::ensure($db,$p);}else{
 foreach(CompanyPortalMailSchema::definitions()as$table=>$fields)if(!$db->table_exists($p.$table)){$parts=[];foreach($fields as$f=>$type)$parts[]=$f.' '.$type.' NOT NULL';$parts[]='PRIMARY KEY('.array_key_first($fields).')';$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).')');}
 foreach(NativeCompanyMailDispatchFence::definitions($p)as$sql)$db->query(str_replace('UNIQUE KEY queue_owner','UNIQUE',preg_replace('/ CHARACTER SET [a-z0-9]+ COLLATE [a-z0-9_]+/','',$sql)));
}unset($db->data_cache['table_names']);
$checks=$GLOBALS['essentialChecks'];$mailService=new NativeCompanyFinancialMail($db,$p);$preview=$mailService->preview($a,7,'payment',$id);$key=hash('sha256','manual-payment-pdf-mail');
essentialCheck(array_column($preview['recipients'],'id')===[101]||array_map('intval',array_column($preview['recipients'],'id'))===[101],'Payment mail uses own active contact with invoice permissions');
$q=$mailService->queue($a,7,'payment',$id,101,$preview['revision'],$key,$base,$decrypt);$mail=(object)$db->query('SELECT * FROM '.$p.'mail_queue WHERE id=?',[$q])->row_array();$headers=unserialize($mail->headers,['allowed_classes'=>false]);$files=unserialize(base64_decode($mail->attachments),['allowed_classes'=>false]);
essentialCheck(count($files)===1&&str_starts_with(base64_decode($files[0]['content']),'%PDF-')&&str_contains($mail->message,'2.50'),'Manual receipt queue contains actual frozen payment PDF and effective amount');
essentialCheck($mailService->queue($a,7,'payment',$id,101,$preview['revision'],$key,$base,$decrypt)===$q,'Explicit receipt replay queues once');$mailService->state($mail,$headers);
$altered=clone$mail;$altered->attachments='';essentialThrows(fn()=>$mailService->state($altered,$headers),'native_financial_mail_stale','Removing frozen receipt bytes is denied before dispatch');
$missing=$headers;unset($missing[NativeCompanyFinancialMail::HEADER]);essentialThrows(fn()=>NativeCompanyFinancialMail::assertQueueRouting($db,$p,$mail,$missing),'native_financial_mail_denied','A removed financial marker cannot downgrade a fenced envelope');
$db->query('UPDATE '.$p.'contacts SET invoice_emails=0 WHERE id=101');try{essentialThrows(fn()=>$mailService->state($mail,$headers),'native_financial_mail_denied','Contact preference is rechecked at dispatch');}finally{$db->query('UPDATE '.$p.'contacts SET invoice_emails=1 WHERE id=101');}
$noteState=$records->read($a,7,$id);$records->saveNote($a,7,$id,$noteState['revision'],'Changed after queue',hash('sha256','financial-note-revoke'));essentialThrows(fn()=>$mailService->state($mail,$headers),'native_financial_mail_stale','Changed source invalidates queued PDF before any external transport');
essentialThrows(fn()=>$mailService->preview($b,7,'payment',$id),'native_payment_denied','Foreign company cannot acquire payment mail authority');
$overdueInput=$input;$overdueInput['date']='2026-01-01';$overdueInput['due_date']='2026-01-10';$overdueInput['native_preferences']=['cancel_overdue_reminders'=>0];$overdue=$s->create($a,7,$overdueInput,hash('sha256','overdue-mail-invoice'));
$db->query("UPDATE {$p}emailtemplates SET active=1,subject='Overdue {invoice_number}',message='Due {invoice_duedate}, balance {invoice_total}, {invoice_link}' WHERE issuer_id=? AND slug='invoice-overdue-notice'",[$a]);
$notice=$mailService->preview($a,7,'overdue',$overdue);$q=$mailService->queue($a,7,'overdue',$overdue,101,$notice['revision'],hash('sha256','overdue-mail'),$base,$decrypt);$overdueMail=(object)$db->query('SELECT * FROM '.$p.'mail_queue WHERE id=?',[$q])->row_array();$overdueHeaders=unserialize($overdueMail->headers,['allowed_classes'=>false]);$mailService->state($overdueMail,$overdueHeaders);
essentialCheck(str_contains($overdueMail->message,'2026-01-10')&&$overdueMail->attachments==='','Overdue notice uses correct template and actual due date without a fake payment receipt');
$db->query('UPDATE '.$p.'invoices SET cancel_overdue_reminders=1 WHERE id=?',[$overdue]);try{essentialThrows(fn()=>$mailService->state($overdueMail,$overdueHeaders),'native_financial_mail_not_overdue','Paused overdue reminders block queued notice at dispatch');}finally{$db->query('UPDATE '.$p.'invoices SET cancel_overdue_reminders=0 WHERE id=?',[$overdue]);}
// The worker first atomically claims the *queue* row, changing pending -> sending.
// Never grant a transport permit directly from pending: the pre-send fence must
// remain prepared until the real worker has reserved the message.
essentialThrows(fn()=>NativeCompanyFinancialMail::claimDispatch($db,$p,$overdueMail,$overdueHeaders),'company_mail_dispatch_review_required','Pending notice cannot bypass worker queue reservation');
$pendingFence=$db->query('SELECT state,attempt_key FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=?',[$overdueMail->id])->row_array();
essentialCheck($pendingFence['state']==='prepared'&&$pendingFence['attempt_key']===null,'Rejected early dispatch leaves prepared fence and no attempt');
$db->query('UPDATE '.$p.'mail_queue SET status=? WHERE id=? AND issuer_id=? AND status=?',['sending',$overdueMail->id,$a,'pending']);
$claimedQueue=$db->query('SELECT status FROM '.$p.'mail_queue WHERE id=? AND issuer_id=?',[$overdueMail->id,$a])->row_array();
essentialCheck(($claimedQueue['status']??null)==='sending','Fixture follows the real worker queue-claim transition');
$overdueMail->status='sending';
$permit=NativeCompanyFinancialMail::claimDispatch($db,$p,$overdueMail,$overdueHeaders);essentialCheck($permit!==null,'Financial notice obtains one dispatch permit only after worker claim');NativeCompanyFinancialMail::completeDispatch($db,$p,$permit,false);
try{NativeCompanyFinancialMail::claimDispatch($db,$p,$overdueMail,$overdueHeaders);throw new RuntimeException('Replayed dispatch accepted');}catch(DomainException $error){essentialCheck(in_array($error->getMessage(),['native_financial_mail_stale','company_mail_dispatch_already_claimed','company_mail_fence_not_prepared'],true),'Failed dispatch cannot be automatically replayed: '.$error->getMessage());}
echo 'Safe financial mail: '.($GLOBALS['essentialChecks']-$checks).' additional checks passed; no SMTP/OAuth/bank call.'.PHP_EOL;
