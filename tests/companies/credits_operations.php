<?php
/** The actual native payment writer, exact ledger/projection, and issuer boundaries. */
require __DIR__.'/invoices.php';
require_once APPPATH.'services/CompanyCreditSchema.php';
foreach(['creditnotes','creditnote_refunds'] as $name){
 preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$m);if(!$m)throw new RuntimeException('native fixture missing '.$name);
 $sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$m[0]);$sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
 if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}$db->query($sql);
}
$db->query("INSERT INTO {$p}creditnotes(id,clientid,number,datecreated,date,currency,subtotal,total,discount_type,include_shipping) VALUES(4200,9999,999,?,'2026-10-19',1,7,7,'after_tax',0)",[date('Y-m-d H:i:s')]);$legacyCredit=$db->query("SELECT * FROM {$p}creditnotes WHERE id=4200")->row_array();
if($db->driver()==='mysql'){CompanyCreditSchema::ensure($db,$p);CompanyCreditSchema::ensure($db,$p);}else{
 $db->query("ALTER TABLE {$p}creditnotes ADD issuer_id int unsigned NULL DEFAULT NULL");
 $db->query("CREATE TABLE {$p}company_credit_documents(credit_id int PRIMARY KEY,issuer_id int NOT NULL,actor_id int NOT NULL,request_key varchar(64) UNIQUE,input_hash varchar(64),number int,identity_json text,recipient_json text,items_json text,created_at datetime,UNIQUE(issuer_id,number))");
 $db->query("CREATE TABLE {$p}company_credit_entries(request_key varchar(64) PRIMARY KEY,issuer_id int NOT NULL,actor_id int,kind varchar(20),credit_id int NULL,invoice_id int NULL,native_id int,parent_key varchar(64) NULL,amount decimal(15,2),input_hash varchar(64),reference_hash varchar(64),reference varchar(191),book_date date,reason text,created_at datetime,UNIQUE(issuer_id,kind,reference_hash))");
}
unset($db->data_cache['table_names']);
if($db->driver()==='mysql'){require_once APPPATH.'migrations/424_version_424.php';$migration=new Migration_Version_424();$migration->db=$db;$migration->up();$migration->up();essentialCheck(true,'native migration424 creates and replays all owned schemas on real InnoDB');}
$legacyNow=$db->query("SELECT * FROM {$p}creditnotes WHERE id=4200")->row_array();essentialCheck($legacyNow['issuer_id']===null,'migration preserves native legacy credit NULL owner');unset($legacyNow['issuer_id']);essentialCheck($legacyNow===$legacyCredit,'legacy native credit byte values unchanged by additive migration');

$ci=['client_id'=>$ca,'currency'=>1,'date'=>'2026-10-19','items'=>[['description'=>'Credit exact <script>','qty'=>'1.00','rate'=>'100.00']],'note'=>'Credit evidence'];
$ck=fn($v)=>hash('sha256','credits-'.$v);
$credit=$writer->issueCompanyCredit($a,7,$ci,$ck('issue'));
essentialCheck($writer->issueCompanyCredit($a,7,$ci,$ck('issue'))===$credit,'credit issue retry returns durable document');
essentialThrows(fn()=>$writer->issueCompanyCredit($b,7,$ci,$ck('cross')),'company_credit_access_denied','credit recipient A cannot be read for B');
$creditB=$writer->issueCompanyCredit($b,7,array_replace($ci,['client_id'=>$cb]),$ck('issueB'));
essentialCheck((int)$db->query("SELECT number FROM {$p}creditnotes WHERE id=?",[$creditB])->row()->number===1,'B has independent CREDIT sequence');
$invoice=$s->create($a,7,array_replace($input,['items'=>[['description'=>'Invoice for credit','qty'=>'1.00','rate'=>'100.00']]]),$ck('invoice'));
$move=['credit_id'=>$credit,'invoice_id'=>$invoice,'amount'=>'30.00','date'=>'2026-10-19','reference'=>'credit-apply-1','reason'=>'Customer adjustment'];
$entry=$writer->moveCompanyCredit($a,7,'apply',$move,$ck('apply'));
essentialCheck($writer->moveCompanyCredit($a,7,'apply',$move,$ck('apply'))===$entry,'apply retry writes only one ledger/native row');
essentialCheck((int)$s->document($a,7,$invoice)->data()['status']===3,'credit makes invoice partially paid');
essentialThrows(fn()=>$writer->moveCompanyCredit($a,7,'apply',$move,$ck('applyfresh')),'company_credit_reference_conflict','same credit evidence cannot duplicate under fresh key');
essentialThrows(fn()=>$writer->moveCompanyCredit($b,7,'apply',$move,$ck('crossapply')),'company_credit_access_denied','cross issuer credit/invoice denied');
essentialThrows(fn()=>$writer->moveCompanyCredit($a,7,'apply',array_replace($move,['amount'=>'70.01','reference'=>'excess']),$ck('excess')),'company_credit_exceeds_balance','credit or invoice excess denied');
$undo=array_replace($move,['parent_key'=>$entry,'amount'=>'10.00','reference'=>'undo1']);$writer->moveCompanyCredit($a,7,'unapply',$undo,$ck('undo1'));
essentialCheck(\app\services\InvoicePaymentWriter::minorUnits($db->query("SELECT amount FROM {$p}credits WHERE credit_id=?",[$credit])->row()->amount)===2000,'partial unapply retains projection and immutable original');
$refund=['credit_id'=>$credit,'amount'=>'25.00','date'=>'2026-10-19','reference'=>'refund-evidence','reason'=>'Recorded external refund evidence only'];$refundKey=$writer->moveCompanyCredit($a,7,'refund',$refund,$ck('refund'));
$rr=array_replace($refund,['parent_key'=>$refundKey,'amount'=>'5.00','reference'=>'refund-undo']);$writer->moveCompanyCredit($a,7,'refund_reverse',$rr,$ck('refundundo'));
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_credit_entries WHERE credit_id=?",[$credit])->row()->n===4,'refund reversal keeps all four immutable entries');
essentialThrows(fn()=>$writer->voidCompanyCredit($a,7,$credit,$ck('void')),'company_credit_has_history','void cannot erase previous operations even with reversals');
$untouched=$writer->issueCompanyCredit($a,7,$ci,$ck('voidable'));$writer->voidCompanyCredit($a,7,$untouched,$ck('voidableaction'));$writer->voidCompanyCredit($a,7,$untouched,$ck('voidableaction'));
essentialCheck((int)$db->query("SELECT status FROM {$p}creditnotes WHERE id=?",[$untouched])->row()->status===3,'unused credit void retains document and is retryable');
$payment=['amount'=>'20.00','date'=>'2026-10-19','reference'=>'manual-credit-reverse','note'=>'Manual evidence'];$paymentKey=$ck('manual');$pay=$writer->recordCompanyManual($a,7,$invoice,$payment,$paymentKey);
$reverse=['invoice_id'=>$invoice,'payment_id'=>$pay,'amount'=>'7.00','date'=>'2026-10-19','reference'=>'manualreverse1','reason'=>'Correction'];$writer->moveCompanyCredit($a,7,'manual_reverse',$reverse,$ck('manualreverse1'));
essentialCheck(\app\services\InvoicePaymentWriter::minorUnits($db->query("SELECT amount FROM {$p}invoicepaymentrecords WHERE id=?",[$pay])->row()->amount)===1300,'partial manual reversal retains remaining native amount');
essentialThrows(fn()=>$writer->recordCompanyManual($a,7,$invoice,$payment,$paymentKey),'company_payment_reversed','old receipt retry cannot resurrect reversed payment');
$reverse['amount']='13.00';$reverse['reference']='manualreverse2';$writer->moveCompanyCredit($a,7,'manual_reverse',$reverse,$ck('manualreverse2'));
essentialCheck(\app\services\InvoicePaymentWriter::minorUnits($db->query("SELECT amount FROM {$p}invoicepaymentrecords WHERE id=?",[$pay])->row()->amount)===0,'full remainder reversal preserves zero projection and receipt history');
$before=$db->query("SELECT COUNT(*) n FROM {$p}company_credit_entries")->row()->n;$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$writer->moveCompanyCredit($a,7,'refund',array_replace($refund,['reference'=>'rollback']),$ck('rollback')),'issuer_storage_failed','audit failure rolls back native refund and ledger');$db->failOn=null;
essentialCheck($db->query("SELECT COUNT(*) n FROM {$p}company_credit_entries")->row()->n===$before,'failed credit operation leaves immutable ledger unchanged');
$db->query("UPDATE {$p}credits SET amount='19.99' WHERE credit_id=?",[$credit]);
essentialThrows(fn()=>$writer->moveCompanyCredit($a,7,'refund',array_replace($refund,['reference'=>'tampered']),$ck('tampered')),'company_credit_balance_review','projection/ledger divergence fails closed before write');
$db->query("UPDATE {$p}credits SET amount='20.00' WHERE credit_id=?",[$credit]);
$principal=(new IssuerResourceAccess($db,$p))->primaryId();essentialThrows(fn()=>$writer->moveCompanyCredit($principal,7,'refund',$refund,$ck('principal')),'company_credit_access_denied','principal cannot adopt positive credit');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyBefore,'legacy NULL invoice remains byte-preserved');
echo 'Credits/application/refunds/manual reversals: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). Local accounting only; no external payment/refund.'.PHP_EOL;
