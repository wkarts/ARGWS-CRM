<?php
/** Synthetic company payment email queue; never invokes SMTP. */
require __DIR__.'/invoice_delivery.php';
require_once APPPATH.'services/NativeCompanyPaymentNoticeSchema.php';
if($db->driver()==='mysql'){NativeCompanyPaymentNoticeSchema::ensure($db,$p);NativeCompanyPaymentNoticeSchema::ensure($db,$p);}else{
 $db->query('CREATE TABLE '.$p.'company_payment_notice_intents(request_key CHAR(64) PRIMARY KEY,issuer_id INT NOT NULL,actor_id INT NOT NULL,invoice_id INT NOT NULL,payment_id INT NOT NULL UNIQUE,email_requested TINYINT NOT NULL,sms_requested TINYINT NOT NULL,email_status VARCHAR(16) NOT NULL,sms_status VARCHAR(16) NOT NULL,created_at DATETIME NOT NULL)');
 $db->query('CREATE TABLE '.$p.'company_payment_notice_deliveries(queue_id INT PRIMARY KEY,issuer_id INT NOT NULL,notice_key CHAR(64) NOT NULL,delivery_key CHAR(64) NOT NULL UNIQUE,contact_id INT NOT NULL,UNIQUE(notice_key,contact_id))');
}
unset($db->data_cache['table_names']);
$ordinary=$s->create($a,7,$input,hash('sha256','ordinary-after-payment-migration'));$ordinaryKey=hash('sha256','ordinary-after-payment-migration-queue');
$ordinaryQueue=$delivery->queue($a,7,$ordinary,101,$s->revision($a,7,$ordinary),$ordinaryKey,$base,$decrypt);$ordinaryMail=$db->query('SELECT * FROM '.$p.'mail_queue WHERE id=?',[$ordinaryQueue])->row_array();$delivery->assertDispatch((object)$ordinaryMail,$ordinaryKey);
essentialCheck(!isset(unserialize($ordinaryMail['headers'],['allowed_classes'=>false])[CompanyInvoiceDelivery::PAYMENT_HEADER]),'normal invoice notification remains valid after the payment migration');
$invoice=$s->create($a,7,$input,hash('sha256','notice-payment-invoice'));$paymentKey=hash('sha256','notice-payment');$writer=new \app\services\InvoicePaymentWriter($db);
$payment=$writer->recordCompanyManual($a,7,$invoice,['amount'=>'5.00','date'=>'2026-10-19','reference'=>'notice-reference','note'=>'Synthetic'],$paymentKey);
$db->query('INSERT INTO '.$p.'company_payment_notice_intents VALUES(?,?,?,?,?,1,1,?,?,?)',[$paymentKey,$a,7,$invoice,$payment,'needs_review','unavailable',gmdate('Y-m-d H:i:s')]);
$template=$db->query("SELECT emailtemplateid FROM {$p}emailtemplates WHERE issuer_id=? AND slug='invoice-payment-recorded'",[$a])->row_array();if(!$template)throw new RuntimeException('payment template missing');
$db->query("UPDATE {$p}emailtemplates SET active=1,subject='Synthetic payment {payment_total}',message='Payment {payment_total} on {payment_date} for {invoice_number}.' WHERE emailtemplateid=?",[$template['emailtemplateid']]);
$revision=$s->revision($a,7,$invoice);
if($db->driver()==='sqlite'){
 $before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'mail_queue')->row()->n;
 $db->query("CREATE TRIGGER notice_late_tamper AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='company_invoice_notification_queued' BEGIN UPDATE {$p}mail_queue SET email='wrong@example.invalid' WHERE id=(SELECT MAX(id) FROM {$p}mail_queue); END");
 try{essentialThrows(fn()=>$delivery->queuePaymentNotice($a,7,$invoice,101,$revision,$paymentKey,$base,$decrypt),'issuer_invoice_delivery_unconfirmed','late queue tampering rolls back notification');}finally{$db->query('DROP TRIGGER notice_late_tamper');}
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'mail_queue')->row()->n===$before&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_payment_notice_deliveries')->row()->n===0,'late tampering leaves no queue or payment binding');
 $selectedTemplate=CompanyEmailTemplates::findForIssuer($db,$p,$a,'invoice-payment-recorded','portuguese_br');
 $db->query("CREATE TRIGGER notice_late_template AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='company_invoice_notification_queued' BEGIN UPDATE {$p}emailtemplates SET active=0 WHERE emailtemplateid={$selectedTemplate->emailtemplateid}; END");
 try{essentialThrows(fn()=>$delivery->queuePaymentNotice($a,7,$invoice,101,$revision,$paymentKey,$base,$decrypt),'company_invoice_delivery_template_required','late payment template revocation aborts notification');}finally{$db->query('DROP TRIGGER notice_late_template');}
 essentialCheck((int)$db->query('SELECT active FROM '.$p.'emailtemplates WHERE emailtemplateid=?',[$selectedTemplate->emailtemplateid])->row()->active===1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'mail_queue')->row()->n===$before,'late template revocation and queued notice roll back together');
}
$queue=$delivery->queuePaymentNotice($a,7,$invoice,101,$revision,$paymentKey,$base,$decrypt);
essentialCheck($delivery->queuePaymentNotice($a,7,$invoice,101,$revision,$paymentKey,$base,$decrypt)===$queue,'same payment/contact notice queues once');
$mail=$db->query('SELECT * FROM '.$p.'mail_queue WHERE id=?',[$queue])->row_array();$headers=unserialize($mail['headers'],['allowed_classes'=>false]);$deliveryKey=hash('sha256','payment-notice:'.$paymentKey.':101');
essentialCheck(str_contains($mail['message'],'5.00')&&str_contains($mail['message'],'2026-10-19')&&$headers[CompanyInvoiceDelivery::PAYMENT_HEADER]===$paymentKey,'correct payment-specific template and origin marker');
$delivery->assertDispatch((object)$mail,$deliveryKey);
$db->query('UPDATE '.$p.'contacts SET invoice_emails=0 WHERE id=101');
try{essentialThrows(fn()=>$delivery->assertDispatch((object)$mail,$deliveryKey),'company_invoice_delivery_denied','revoked invoice email preference stops payment notice before transport');}finally{$db->query('UPDATE '.$p.'contacts SET invoice_emails=1 WHERE id=101');}
$selectedTemplate=CompanyEmailTemplates::findForIssuer($db,$p,$a,'invoice-payment-recorded','portuguese_br');
$db->query('UPDATE '.$p.'emailtemplates SET active=0 WHERE emailtemplateid=?',[$selectedTemplate->emailtemplateid]);
try{essentialThrows(fn()=>$delivery->assertDispatch((object)$mail,$deliveryKey),'company_invoice_delivery_template_required','disabled company payment template stops worker');}finally{$db->query('UPDATE '.$p.'emailtemplates SET active=1 WHERE emailtemplateid=?',[$selectedTemplate->emailtemplateid]);}
essentialThrows(fn()=>$delivery->queuePaymentNotice($b,7,$invoice,202,$revision,$paymentKey,$base,$decrypt),'company_invoice_delivery_denied','foreign company cannot queue payment');
essentialThrows(fn()=>$delivery->queuePaymentNotice($a,7,$invoice,202,$revision,$paymentKey,$base,$decrypt),'company_invoice_delivery_denied','foreign contact denied');
unset($headers[CompanyInvoiceDelivery::PAYMENT_HEADER]);$forged=(object)$mail;$forged->headers=serialize($headers);
essentialThrows(fn()=>$delivery->assertDispatch($forged,$deliveryKey),'company_payment_notice_denied','worker rejects missing payment provenance marker');
$db->query('UPDATE '.$p.'company_payment_notice_intents SET email_requested=0 WHERE request_key=?',[$paymentKey]);
essentialThrows(fn()=>$delivery->assertDispatch((object)$mail,$deliveryKey),'company_payment_notice_denied','worker rechecks notice consent before transport');
$db->query('UPDATE '.$p.'company_payment_notice_intents SET email_requested=1 WHERE request_key=?',[$paymentKey]);
$db->query('DELETE FROM '.$p.'mail_queue WHERE id=?',[$queue]);
essentialCheck($delivery->queuePaymentNotice($a,7,$invoice,101,$revision,$paymentKey,$base,$decrypt)===$queue&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'mail_queue WHERE id=?',[$queue])->row()->n===0,'queue cleanup preserves receipt and retry cannot send another notification');
// Full original-form action with an owned offline mode and actual queued email.
require_once APPPATH.'services/NativeCompanyPaymentModes.php';require_once APPPATH.'services/NativeCompanyInvoicePaymentAction.php';
if(!$db->table_exists($p.'payment_modes')){preg_match('/CREATE TABLE `tblpayment_modes` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$matches);$ddl=str_replace('`tblpayment_modes`','`'.$p.'payment_modes`',$matches[0]);$ddl=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$ddl);if($db->driver()==='sqlite')$ddl=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$ddl);$db->query($ddl);}
foreach(['expenses','company_invoice_drafts']as$table)if(!$db->table_exists($p.$table))$db->query('CREATE TABLE '.$p.$table.'(id INT NOT NULL PRIMARY KEY)'.($db->driver()==='mysql'?' ENGINE=InnoDB':''));
unset($db->data_cache['table_names']);NativeCompanyPaymentModesSchema::ensure($db,$p);
$catalog=new NativeCompanyPaymentModes($db,$p);$mode=$catalog->save($a,7,['name'=>'Synthetic form method','description'=>'','active'=>1,'show_on_pdf'=>1,'selected_by_default'=>0,'invoices_only'=>1,'expenses_only'=>0]);
$formInvoice=$s->create($a,7,$input,hash('sha256','notice-form-invoice'));$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([$mode['id']]),$formInvoice]);$form=(new NativeCompanyInvoicePaymentForm($db,$p))->read($a,7,$formInvoice);
$wire=['invoiceid'=>(string)$formInvoice,'amount'=>'7.00','date'=>'2026-10-19','paymentmode'=>$mode['id'],'transactionid'=>'form-mail-reference','note'=>'Synthetic','_native_payment_key'=>$form['request_key'],'_native_payment_mode_revision'=>$mode['revision'],'_native_invoice_revision'=>$form['revision']];
$action=new NativeCompanyInvoicePaymentAction($db,$p);$result=$action->submit($a,7,$wire,$base,$decrypt);
essentialCheck($result['payment_id']>0&&$result['email_status']==='queued'&&$result['email_queued']===1&&$result['sms_status']==='unavailable','original form records payment and queues one issuer-owned payment email, while reporting SMS unavailable');
essentialCheck($action->submit($a,7,$wire,$base,$decrypt)===$result,'form retry does not duplicate payment or notice');
echo 'Payment notification queue: payment template, company/contact provenance, idempotency and worker revocation passed; no external delivery.'.PHP_EOL;
