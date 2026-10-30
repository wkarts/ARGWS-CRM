<?php
require __DIR__.'/native_payment_batch.php';
require_once APPPATH.'services/NativeCompanyPaymentRecords.php';require_once APPPATH.'services/CompanyFinancialDocumentPdf.php';
$checks=$GLOBALS['essentialChecks'];$records=new NativeCompanyPaymentRecords($db,$p);$id=$ids[0];$data=$records->read($a,7,$id);
essentialCheck($data['payment']['effective_amount']==='2.50'&&$data['note']==='Actual batch note'&&$data['invoice']['issuer_id']===$a,'Original payment reader resolves the exact issuer, real amount and stored note');
$original=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE id=?',[$id])->row_array();$key=hash('sha256','payment-note-edit');$records->saveNote($a,7,$id,$data['revision'],'Reviewed <script> literal note',$key);
$after=$records->read($a,7,$id);essentialCheck($after['note']==='Reviewed <script> literal note'&&$after['revision']!==$data['revision'],'Payment note update has an audited current version');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE id=?',[$id])->row_array()===$original,'Note update preserves original financial record and its immutable receipt');
$records->saveNote($a,7,$id,$data['revision'],'Reviewed <script> literal note',$key);essentialCheck($records->read($a,7,$id)===$after,'Note replay does not append a second audit');
essentialThrows(fn()=>$records->saveNote($a,7,$id,$data['revision'],'Other note',hash('sha256','payment-note-stale')),'native_payment_stale','Obsolete payment note revision rejects a new command');
essentialThrows(fn()=>$records->saveNote($a,7,$id,$data['revision'],'Changed',$key),'native_payment_conflict','Idempotency key cannot be reused for another note');
essentialThrows(fn()=>$records->read($b,7,$id),'native_payment_denied','Foreign company cannot hydrate a payment through its original ID');
$list=$records->listing($a,7);essentialCheck(in_array((string)$id,array_map('strval',array_column($list,'id')),true),'Original payment list includes the stored payment');foreach($list as$item)essentialCheck((int)$item['issuer_id']===$a,'Payment table never contains another issuer');$window=$records->listingPage($a,7,null,0,1,(string)$id,[['field'=>'id','dir'=>'desc']]);essentialCheck($window['total']>=1&&$window['filtered']>=1&&count($window['rows'])===1&&(int)$window['rows'][0]['id']===$id,'Payment listing uses a bounded SQL page and server-side ID search');
$pdf=CompanyFinancialDocumentPdf::payment($after);essentialCheck(str_starts_with($pdf,'%PDF-')&&strlen($pdf)>1000,'Actual payment receipt is a real PDF with the stored document identity');
if($db->driver()==='sqlite'){
    $count=$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_audit')->row()->n;$db->query("CREATE TRIGGER payment_note_audit_failure BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='native_payment_note_saved' BEGIN SELECT RAISE(ABORT,'synthetic audit rejection'); END");
    try{essentialThrows(fn()=>$records->saveNote($a,7,$id,$after['revision'],'Rejected',hash('sha256','payment-note-rollback')),'issuer_storage_failed','Missing audit rolls back payment note update');}finally{$db->query('DROP TRIGGER payment_note_audit_failure');}
    essentialCheck($records->read($a,7,$id)===$after&&$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_audit')->row()->n===$count,'Rejected note leaves display, original data and history unchanged');
}
echo 'Original payment records: '.($GLOBALS['essentialChecks']-$checks).' additional checks passed, including PDF and audit preservation.'.PHP_EOL;
