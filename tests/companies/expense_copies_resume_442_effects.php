<?php
/** Recreated V2 proof: late adapter audit must not commit corrupt canonical writer evidence. */
require __DIR__.'/expense_copies_resume_442_atomic.php';
$review=expenseCopyReview($b,$ib);$errors=['issuer_expense_copy_unconfirmed','company_expense_copy_stale','company_expense_copy_denied','issuer_storage_failed'];
$attacks=[
 ['journal amount',"UPDATE {$p}company_financial_events SET amount='999.99' WHERE issuer_id=NEW.issuer_id AND source_type='expense' AND source_id=CAST(%ID% AS CHAR) AND kind='expense_recorded'"],
 ['journal payload hash',"UPDATE {$p}company_financial_events SET payload_hash='".str_repeat('e',64)."' WHERE issuer_id=NEW.issuer_id AND source_type='expense' AND source_id=CAST(%ID% AS CHAR) AND kind='expense_recorded'"],
 ['journal record hash',"UPDATE {$p}company_financial_events SET record_hash='".str_repeat('f',64)."' WHERE issuer_id=NEW.issuer_id AND source_type='expense' AND source_id=CAST(%ID% AS CHAR) AND kind='expense_recorded'"],
 ['journal sequence',"UPDATE {$p}company_financial_events SET issuer_sequence=issuer_sequence+10000 WHERE issuer_id=NEW.issuer_id AND source_type='expense' AND source_id=CAST(%ID% AS CHAR) AND kind='expense_recorded'"],
 ['journal absent',"DELETE FROM {$p}company_financial_events WHERE issuer_id=NEW.issuer_id AND source_type='expense' AND source_id=CAST(%ID% AS CHAR) AND kind='expense_recorded'"],
 ['creation audit metadata',"UPDATE {$p}issuers_audit SET metadata_json='{}' WHERE issuer_id=NEW.issuer_id AND subject_id=%ID% AND event='company_expense_created'"],
 ['creation audit author',"UPDATE {$p}issuers_audit SET actor_id=12 WHERE issuer_id=NEW.issuer_id AND subject_id=%ID% AND event='company_expense_created'"],
 ['creation audit absent',"DELETE FROM {$p}issuers_audit WHERE issuer_id=NEW.issuer_id AND subject_id=%ID% AND event='company_expense_created'"],
 ['journal audit absent',"DELETE FROM {$p}issuers_audit WHERE issuer_id=NEW.issuer_id AND event='company_financial_event_recorded' AND metadata_json LIKE '%\"source_id\":\"' || %ID% || '\"%'"],
];
// MySQL does not permit rewriting the table whose INSERT trigger is currently executing.
// Those same-audit-table attacks run on the final separate copy receipt INSERT there.
foreach($attacks as$n=>[$name,$sql]){
 $table=$db->driver()==='mysql'?'company_expense_copies':'issuers_audit';$id=$table==='company_expense_copies'?'NEW.result_expense_id':'NEW.subject_id';$condition=$table==='company_expense_copies'?'1=1':"NEW.event='company_expense_copied'";
 if($name==='journal audit absent')$sql="DELETE FROM {$p}issuers_audit WHERE issuer_id=NEW.issuer_id AND event='company_financial_event_recorded' AND metadata_json LIKE ".($db->driver()==='mysql'?"CONCAT('%\"source_id\":\"',{$id},'\"%')":"'%\"source_id\":\"' || {$id} || '\"%'");
 $sql=str_replace('%ID%',$id,$sql);$before=expenseCopyState();expenseCopyTrigger($table,$condition,$sql);
 try{expenseCopyReject(fn()=>$copies->create($b,7,$ib,$review,$copyKey('v2-effects-'.$n)),$errors,'final canonical '.$name.' is confirmed before copy commit');}finally{$db->query('DROP TRIGGER '.$p.'expense_copy_late');}
 essentialCheck(expenseCopyState()===$before,'late '.$name.' rolls back costs/copy/native receipts/journal/audits exactly');
 $r=$copies->create($b,7,$ib,$review,$copyKey('v2-effects-'.$n));essentialCheck($copies->create($b,7,$ib,$review,$copyKey('v2-effects-'.$n))===$r,'same key recovers once after '.$name.' rollback');
}
$r=$copies->create($b,7,$ib,$review,$copyKey('v2-reader-effects'));
foreach(['amount','record_hash']as$n=>$field){$value=$field==='amount'?"'999.99'":"'".str_repeat('f',64)."'";$before=expenseCopyState();expenseCopyTrigger('issuers_audit',"NEW.event='company_expense_copy_viewed'","UPDATE {$p}company_financial_events SET {$field}={$value} WHERE issuer_id=NEW.issuer_id AND source_type='expense' AND source_id=CAST(NEW.subject_id AS CHAR) AND kind='expense_recorded'");try{expenseCopyReject(fn()=>$copies->receipt($b,7,$r['request_key']),$errors,'receipt reader confirms canonical journal after final view audit '.$field);}finally{$db->query('DROP TRIGGER '.$p.'expense_copy_late');}essentialCheck(expenseCopyState()===$before,'reader returns no DTO or corrupt journal '.$field);}
echo 'Expense copy442 V2 final canonical effects confirmed: '.$GLOBALS['essentialChecks'].' checks ('.$db->driver().').'.PHP_EOL;
