<?php
require __DIR__.'/native_invoice_cancel_controller.php';
require_once APPPATH.'services/CompanyLedgerReports.php';
$reopenRevision=$s->revision($a,7,$fresh);$oldCount=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_financial_events')->row()->n;
$s->reopen($a,7,$fresh,$reopenRevision);
essentialCheck($s->document($a,7,$fresh)->data()['status']===1,'reopen restores proven local status without issuing another invoice');
$newRevision=$s->revision($a,7,$fresh);essentialCheck($newRevision!==$reopenRevision,'reopen advances lifecycle CAS');
$s->reopen($a,7,$fresh,$reopenRevision);
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_financial_events')->row()->n===$oldCount+1,'same actor retry keeps one immutable reopening event');
$events=$db->query("SELECT * FROM ".$p."company_financial_events WHERE issuer_id=? AND source_type='invoice' AND source_id=? ORDER BY issuer_sequence",[$a,(string)$fresh])->result_array();
$legs=new ReflectionMethod(CompanyLedgerReports::class,'legs');$sum=0;$journal=new CompanyFinancialJournal($db,$p);
foreach($events as $event)foreach($legs->invoke(null,$journal->decode($event))as$leg)if($leg['metric']==='invoice_after_changes')$sum+=$leg['amount'];
essentialCheck($sum===CompanyInvoiceAmounts::minor((string)$db->query('SELECT total FROM '.$p.'invoices WHERE id=?',[$fresh])->row()->total),'journal cancel/reopen legs restore receivable magnitude without duplicating nominal issue');
$s->change($a,7,$fresh,$newRevision);$secondCancel=$s->revision($a,7,$fresh);$s->reopen($a,7,$fresh,$secondCancel);
essentialCheck($s->document($a,7,$fresh)->data()['status']===1,'second cancellation cycle retains unique proof and does not collide with original evidence');
essentialThrows(fn()=>$s->reopen($a,7,$fresh,$reopenRevision),'company_invoice_stale','old cycle replay cannot accept a newer lifecycle');
essentialThrows(fn()=>$s->reopen($b,7,$fresh,$secondCancel),'company_invoice_access_denied','same actor cross-company reopen denied');
$s->change($a,7,$fresh,$s->revision($a,7,$fresh));$cancelled=$s->revision($a,7,$fresh);
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$s->reopen($a,7,$fresh,$cancelled),'issuer_storage_failed','late audit failure rolls back reopen');$db->failOn=null;
essentialCheck($s->document($a,7,$fresh)->data()['status']===5&&$s->revision($a,7,$fresh)===$cancelled,'audit failure leaves cancelled state and CAS unchanged');
$s->reopen($a,7,$fresh,$cancelled);
essentialCheck($s->document($a,7,$fresh)->data()['status']===1,'retry after failed audit succeeds with original cancellation revision');
echo 'Native invoice reopen: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$s->change($a,7,$fresh,$s->revision($a,7,$fresh));$cancelled=$s->revision($a,7,$fresh);
$ci->input->posts=['revision'=>$cancelled];$ci->input->verb='GET';$controller->unmark_as_cancelled($fresh);
essentialCheck($ci->output->status===403,'native reopen GET denied');
$ci->input->verb='POST';$ci->output->status=200;$controller->unmark_as_cancelled($fresh);
essentialCheck($ci->output->status===200&&json_decode($ci->output->body,true)['success']===true&&$s->document($a,7,$fresh)->data()['status']===1,'native same URL POST composes reopen proof writer');
$s->change($a,7,$fresh,$s->revision($a,7,$fresh));$cancelled=$s->revision($a,7,$fresh);
foreach(['bb_origins','asaas_payment_origins','efi_company_origins']as$table){
 $db->table_exists($p.$table); // Populate a stale negative framework catalogue deliberately.
 $db->query('CREATE TABLE '.$p.$table.'(id int PRIMARY KEY,invoice_id int NOT NULL)');
 $db->query('INSERT INTO '.$p.$table.' VALUES(1,?)',[$fresh]);
 essentialThrows(fn()=>$s->reopen($a,7,$fresh,$cancelled),'company_invoice_provider_review_required','provider origin '.$table.' vetoes reopening even without a cash payment');
 $db->query('DROP TABLE '.$p.$table);unset($db->data_cache['table_names']);
}
$metadataSql=$db->driver()==='sqlite'?'SELECT name AS table_name FROM sqlite_master':'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES';
$db->failOn=$metadataSql;essentialThrows(fn()=>$s->reopen($a,7,$fresh,$cancelled),'issuer_storage_failed','provider catalogue failure is not mistaken for absent providers');$db->failOn=null;
$beforeFailure=$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_id,issuer_sequence')->result_array();
$db->failOn='INSERT INTO '.$p.'company_financial_events';essentialThrows(fn()=>$s->reopen($a,7,$fresh,$cancelled),'issuer_storage_failed','late journal failure rolls back earlier status and audit writes');$db->failOn=null;
essentialCheck($s->revision($a,7,$fresh)===$cancelled&&$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_id,issuer_sequence')->result_array()===$beforeFailure,'late journal failure preserves all history and original CAS');
essentialThrows(fn()=>$s->reopen($a,7,$fresh,str_repeat('f',64)),'company_invoice_stale','wrong revision never restores cancellation');
CompanyRequestScope::$csrf=false;$ci->input->posts=['revision'=>$cancelled];$controller->unmark_as_cancelled($fresh);essentialCheck($ci->output->status===403,'native reopen rejects invalid framework/captured CSRF');CompanyRequestScope::$csrf=true;
$db->query('UPDATE '.$p.'company_financial_events SET payload_hash=? WHERE issuer_id=? AND source_id=? AND kind=?',[str_repeat('0',64),$a,(string)$fresh,'invoice_cancelled']);
essentialThrows(fn()=>$s->reopen($a,7,$fresh,$cancelled),'company_financial_event_invalid','corrupt cancellation proof cannot reopen an invoice');
essentialCheck($s->document($a,7,$fresh)->data()['status']===5,'proof failure leaves cancellation intact');
echo 'Native reopen HTTP boundary double/proof negatives: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$unproven=$s->create($a,7,$cancelInput,hash('sha256','native-reopen-unproven'));
$db->query('UPDATE '.$p.'invoices SET status=5 WHERE id=?',[$unproven]);
essentialThrows(fn()=>$s->reopen($a,7,$unproven,$s->revision($a,7,$unproven)),'company_invoice_reopen_review_required','a cancelled status alone never fabricates canonical cancellation proof');
essentialThrows(fn()=>$s->reopen($a,7,$main,$s->revision($a,7,$main)),'company_invoice_immutable','receipt history is independently fenced before reopening or replay');
$rev=$s->revision($a,7,$unproven);$db->pdo->beginTransaction();try{essentialThrows(fn()=>$s->reopen($a,7,$unproven,$rev),'issuer_outer_transaction_not_allowed','reopen cannot commit or borrow a plain caller transaction');}finally{$db->pdo->rollBack();}
echo 'Native reopen full negative set: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
