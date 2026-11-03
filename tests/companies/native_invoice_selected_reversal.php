<?php
require __DIR__.'/credits_operations.php';
require_once APPPATH.'services/NativeCompanyPaymentModes.php';require_once APPPATH.'services/NativeCompanyPaymentNoticeSchema.php';
preg_match('/CREATE TABLE `tblpayment_modes` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$matches);$ddl=str_replace('`tblpayment_modes`','`'.$p.'payment_modes`',$matches[0]);$ddl=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$ddl);if($db->driver()==='sqlite')$ddl=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$ddl);$db->query($ddl);
foreach(['expenses','company_invoice_drafts','mail_queue']as$table)if(!$db->table_exists($p.$table))$db->query('CREATE TABLE '.$p.$table.'(id INT NOT NULL PRIMARY KEY)'.($db->driver()==='mysql'?' ENGINE=InnoDB':''));
unset($db->data_cache['table_names']);NativeCompanyPaymentModesSchema::ensure($db,$p);
if($db->driver()==='mysql')NativeCompanyPaymentNoticeSchema::ensure($db,$p);else{$db->query('CREATE TABLE '.$p.'company_payment_notice_intents(request_key CHAR(64) PRIMARY KEY,issuer_id INT NOT NULL,actor_id INT NOT NULL,invoice_id INT NOT NULL,payment_id INT NOT NULL UNIQUE,email_requested TINYINT NOT NULL,sms_requested TINYINT NOT NULL,email_status VARCHAR(16) NOT NULL,sms_status VARCHAR(16) NOT NULL,created_at DATETIME NOT NULL)');$db->query('CREATE TABLE '.$p.'company_payment_notice_deliveries(queue_id INT PRIMARY KEY,issuer_id INT NOT NULL,notice_key CHAR(64) NOT NULL,delivery_key CHAR(64) NOT NULL UNIQUE,contact_id INT NOT NULL,UNIQUE(notice_key,contact_id))');}
unset($db->data_cache['table_names']);
$catalog=new NativeCompanyPaymentModes($db,$p);$mode=$catalog->save($a,7,['name'=>'Synthetic transfer','description'=>'','active'=>1,'show_on_pdf'=>1,'selected_by_default'=>0,'invoices_only'=>1,'expenses_only'=>0]);
$invoiceId=$s->create($a,7,$input,hash('sha256','mode-reversal-invoice'));$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([$mode['id']]),$invoiceId]);
$payInput=['amount'=>'10.00','date'=>'2026-10-19','reference'=>'mode-payment','note'=>'Synthetic'];$paymentKey=hash('sha256','mode-reversal-payment');$pid=$writer->recordCompanyManualWithMode($a,7,$invoiceId,$payInput,$paymentKey,$mode['id'],$mode['revision'],false);
$undo=['invoice_id'=>$invoiceId,'payment_id'=>$pid,'amount'=>'3.00','date'=>'2026-10-19','reference'=>'mode-reversal-1','reason'=>'Synthetic correction'];$reverseKey=hash('sha256','mode-reversal-1');
essentialThrows(fn()=>$writer->moveCompanyCredit($b,7,'manual_reverse',$undo,$reverseKey),'company_credit_access_denied','another company cannot reverse selected payment');
$intent=$db->query('SELECT * FROM '.$p.'company_payment_notice_intents WHERE request_key=?',[$paymentKey])->row_array();$db->query('DELETE FROM '.$p.'company_payment_notice_intents WHERE request_key=?',[$paymentKey]);
try{essentialThrows(fn()=>$writer->moveCompanyCredit($a,7,'manual_reverse',$undo,$reverseKey),'company_credit_invalid_reversal','numeric payment mode without owned selection intent cannot authorize reversal');}
finally{$db->query('INSERT INTO '.$p.'company_payment_notice_intents (`'.implode('`,`',array_keys($intent)).'`) VALUES('.implode(',',array_fill(0,count($intent),'?')).')',array_values($intent));}
$writer->moveCompanyCredit($a,7,'manual_reverse',$undo,$reverseKey);$position=$s->position($a,7,$invoiceId);
essentialCheck($position['received']==='7.00'&&$position['received_original']==='10.00'&&$position['received_manual_reversed']==='3.00','selected offline payment reverses without losing its real mode or original history');
essentialCheck($writer->moveCompanyCredit($a,7,'manual_reverse',$undo,$reverseKey)===$reverseKey,'selected reversal is idempotent');
$db->failOn='INSERT INTO '.$p.'issuers_audit';$undo['amount']='7.00';$undo['reference']='mode-reversal-2';try{essentialThrows(fn()=>$writer->moveCompanyCredit($a,7,'manual_reverse',$undo,hash('sha256','mode-reversal-2')),'issuer_storage_failed','failed audit rolls back remaining reversal');}finally{$db->failOn=null;}
essentialCheck($s->position($a,7,$invoiceId)['received']==='7.00','failed reversal retains balance');
$writer->moveCompanyCredit($a,7,'manual_reverse',$undo,hash('sha256','mode-reversal-2'));
essentialCheck($s->position($a,7,$invoiceId)['received']==='0.00','full selected payment reversal retains history');
essentialThrows(fn()=>$writer->recordCompanyManualWithMode($a,7,$invoiceId,$payInput,$paymentKey,$mode['id'],$mode['revision'],false),'company_payment_reversed','old form cannot resurrect reversed receipt');
echo 'Selected offline mode reversal: provenance, exact balance, replay and rollback passed.'.PHP_EOL;
