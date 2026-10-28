<?php
require __DIR__.'/native_invoice_read.php';
require_once APPPATH.'services/NativeCompanyPaymentModes.php';
NativeCompanyPaymentModesSchema::ensure($db,$p);
require_once APPPATH.'services/NativeCompanyPaymentNoticeSchema.php';
if(!$db->table_exists($p.'mail_queue'))$db->query('CREATE TABLE '.$p.'mail_queue(id INT NOT NULL PRIMARY KEY)'.($db->driver()==='mysql'?' ENGINE=InnoDB':''));
if($db->driver()==='mysql'){require_once APPPATH.'migrations/475_version_475.php';$upgrade=new Migration_Version_475();$upgrade->db=$db;$upgrade->up();$upgrade->up();}else{$db->query('CREATE TABLE '.$p.'company_payment_notice_intents(request_key CHAR(64) PRIMARY KEY, issuer_id INT NOT NULL, actor_id INT NOT NULL, invoice_id INT NOT NULL, payment_id INT NOT NULL UNIQUE, email_requested TINYINT NOT NULL,sms_requested TINYINT NOT NULL,email_status VARCHAR(16) NOT NULL,sms_status VARCHAR(16) NOT NULL,created_at DATETIME NOT NULL)');$db->query('CREATE TABLE '.$p.'company_payment_notice_deliveries(queue_id INT PRIMARY KEY,issuer_id INT NOT NULL,notice_key CHAR(64) NOT NULL,delivery_key CHAR(64) NOT NULL UNIQUE,contact_id INT NOT NULL,UNIQUE(notice_key,contact_id))');}
unset($db->data_cache['table_names']);
// Mode administration verifies every participant's storage engine, including
// these tables which are unused by this fixture's create operation.
foreach(['expenses','company_invoice_drafts'] as $table)if(!$db->table_exists($p.$table))$db->query('CREATE TABLE '.$p.$table.'(id INT NOT NULL PRIMARY KEY)'.($db->driver()==='mysql'?' ENGINE=InnoDB':''));
unset($db->data_cache['table_names']);
$catalog=new NativeCompanyPaymentModes($db,$p);
$input=['name'=>'Synthetic transfer','description'=>'','active'=>1,'show_on_pdf'=>1,'selected_by_default'=>0,'invoices_only'=>1,'expenses_only'=>0];
$mode=$catalog->save($a,7,$input);$other=$catalog->save($b,7,array_replace($input,['name'=>'Foreign']));
$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([$mode['id']]),$branded]);
require_once APPPATH.'services/NativeCompanyInvoicePaymentForm.php';$form=new NativeCompanyInvoicePaymentForm($db,$p);$data=$form->read($a,7,$branded);
essentialCheck(array_column($data['payment_modes'],'id')===[$mode['id']]&&$data['balance']['receivable']==='105.53'&&strlen($data['request_key'])===64,'native payment form contains only selected company modes, actual balance and fresh retry key');
essentialThrows(fn()=>$form->read($b,7,$branded),'company_invoice_access_denied','foreign company cannot hydrate original payment form');
$writer=new \app\services\InvoicePaymentWriter($db);$payment=['amount'=>'5.00','date'=>'2026-10-19','reference'=>'selected-transfer','note'=>'Synthetic'];$key=hash('sha256','selected-mode-payment');
essentialThrows(fn()=>$writer->recordCompanyManualWithMode($b,7,$branded,$payment,$key,$other['id'],$other['revision'],true),'native_payment_mode_unavailable','foreign issuer cannot write the invoice');
essentialThrows(fn()=>$writer->recordCompanyManualWithMode($a,7,$branded,$payment,$key,$other['id'],$other['revision'],true),'native_payment_mode_denied','foreign offline mode cannot be used');
essentialThrows(fn()=>$writer->recordCompanyManualWithMode($a,7,$branded,$payment,$key,$mode['id'],str_repeat('0',64),true),'native_payment_mode_stale','outdated mode revision rejected');
essentialThrows(fn()=>$writer->recordCompanyManualWithMode($a,7,$branded,$payment,$key,$mode['id'],$mode['revision'],true,str_repeat('0',64)),'company_invoice_stale','invoice revision is checked again inside the financial transaction');
$db->failOn='INSERT INTO '.$p.'issuers_audit';
try{essentialThrows(fn()=>$writer->recordCompanyManualWithMode($a,7,$branded,$payment,$key,$mode['id'],$mode['revision'],true),'issuer_storage_failed','late audit failure rolls back selected payment');}finally{$db->failOn=null;}
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$branded])->row()->n===0,'failed audit leaves no payment');
if($db->driver()==='sqlite'){
 $db->query("CREATE TRIGGER selected_mode_late_change AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='company_manual_payment_recorded' BEGIN UPDATE {$p}company_payment_modes SET active=0 WHERE id={$mode['source_id']}; END");
 try{essentialThrows(fn()=>$writer->recordCompanyManualWithMode($a,7,$branded,$payment,$key,$mode['id'],$mode['revision'],true),'native_payment_mode_unavailable','mode revoked after audit aborts payment');}finally{$db->query('DROP TRIGGER selected_mode_late_change');}
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$branded])->row()->n===0&&(int)$db->query('SELECT active FROM '.$p.'company_payment_modes WHERE id=?',[$mode['source_id']])->row()->active===1,'late mode change and receipt roll back together');
 $db->query("CREATE TRIGGER notice_late_change AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='company_payment_notice_recorded' BEGIN UPDATE {$p}company_payment_notice_intents SET email_requested=0 WHERE invoice_id={$branded}; END");
 try{essentialThrows(fn()=>$writer->recordCompanyManualWithMode($a,7,$branded,$payment,$key,$mode['id'],$mode['revision'],true),'native_payment_notice_conflict','notice tampering after audit aborts payment');}finally{$db->query('DROP TRIGGER notice_late_change');}
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$branded])->row()->n===0&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_payment_notice_intents')->row()->n===0,'notice mutation, audit and payment roll back together');
}
$id=$writer->recordCompanyManualWithMode($a,7,$branded,$payment,$key,$mode['id'],$mode['revision'],true);
$stored=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE id=?',[$id])->row_array();
essentialCheck((string)$stored['paymentmode']===$mode['id']&&$stored['paymentmethod']===$mode['name'],'actual payment stores selected company mode and name');
essentialCheck((int)$db->query('SELECT email_requested FROM '.$p.'company_payment_notice_intents WHERE request_key=?',[$key])->row()->email_requested===1&&$db->query('SELECT email_status FROM '.$p.'company_payment_notice_intents WHERE request_key=?',[$key])->row()->email_status==='needs_review','notification intent is recorded without claiming it was queued or delivered');
essentialThrows(fn()=>$writer->recordCompanyManualWithMode($a,7,$branded,$payment,$key,$mode['id'],$mode['revision'],false),'native_payment_notice_conflict','notification choice cannot change on replay');
essentialCheck($writer->recordCompanyManualWithMode($a,7,$branded,$payment,$key,$mode['id'],$mode['revision'],true)===$id,'retry returns same payment');
$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([]),$branded]);
essentialThrows(fn()=>$writer->recordCompanyManualWithMode($a,7,$branded,$payment,hash('sha256','other-key'),$mode['id'],$mode['revision'],true),'native_payment_mode_unavailable','mode must be allowed by the invoice');
require_once APPPATH.'services/NativeCompanyInvoicePaymentAction.php';
$formInvoice=$s->create($a,7,$legacyInput,hash('sha256','selected-form-invoice'));$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([$mode['id']]),$formInvoice]);
$formData=$form->read($a,7,$formInvoice);$wire=['invoiceid'=>(string)$formInvoice,'amount'=>'4.00','date'=>'2026-10-19','paymentmode'=>$mode['id'],'transactionid'=>'form-reference','note'=>'Synthetic','do_not_send_email_template'=>'on','_native_payment_key'=>$formData['request_key'],'_native_payment_mode_revision'=>$mode['revision'],'_native_invoice_revision'=>$formData['revision']];
$action=new NativeCompanyInvoicePaymentAction($db,$p);$originalNote=$db->query('SELECT clientnote FROM '.$p.'invoices WHERE id=?',[$formInvoice])->row()->clientnote;$db->query('UPDATE '.$p.'invoices SET clientnote=? WHERE id=?',['stale form',$formInvoice]);
essentialThrows(fn()=>$action->submit($a,7,$wire,'https://crm.example.invalid',fn($v)=>$v),'company_invoice_stale','stale invoice form cannot record a new payment');
$db->query('UPDATE '.$p.'invoices SET clientnote=? WHERE id=?',[$originalNote,$formInvoice]);
$formResult=$action->submit($a,7,$wire,'https://crm.example.invalid',fn($v)=>$v);
essentialCheck($formResult['payment_id']>0&&$formResult['email_status']==='suppressed'&&$formResult['sms_status']==='suppressed','native form action records actual selected payment and respects opt-out');
essentialCheck($action->submit($a,7,$wire,'https://crm.example.invalid',fn($v)=>$v)['payment_id']===$formResult['payment_id'],'native form retry preserves same payment');
essentialThrows(fn()=>$action->submit($b,7,$wire,'https://crm.example.invalid',fn($v)=>$v),'company_invoice_access_denied','native form action refuses foreign company');
echo 'Native selected offline payment mode: owned source, revision, persisted mode, replay and audit rollback passed.'.PHP_EOL;
