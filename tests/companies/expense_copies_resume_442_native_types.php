<?php
/** Native mysqli returns numeric fields as strings; receipts must keep canonical semantic types. */
require __DIR__.'/expense_copies_resume_442_effects.php';
$review=expenseCopyReview($b,$ib);$key=$copyKey('native-driver-transition');$first=$copies->create($b,7,$ib,$review,$key);
$db->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,true);
$decoded=(new CompanyFinancialJournal($db,$p))->decode($db->query("SELECT * FROM {$p}company_financial_events WHERE source_type='expense' AND kind='expense_recorded' LIMIT 1")->row_array());
essentialCheck(is_string($decoded['issuer_id'])&&is_string($decoded['actor_id']),'actual driver stringifies decoded raw IDs as native mysqli does');
essentialCheck($copies->create($b,7,$ib,$review,$key)===$first,'driver string fetch keeps the original copy receipt identity');
$receipt=$copies->receipt($b,7,$key);essentialCheck($receipt['expense_id']===$first['expense_id'],'receipt created with integer fetches remains readable with native string fetches');
$review=expenseCopyReview($b,$ib);$newKey=$copyKey('native-string-positive');$new=$copies->create($b,7,$ib,$review,$newKey);essentialCheck($new['expense_id']!==$first['expense_id'],'new canonical copy succeeds while all database numeric fields are returned as strings');
$before=expenseCopyState();essentialCheck($copies->create($b,7,$ib,$review,$newKey)===$new&&expenseCopyState()===$before,'string-fetch idempotence creates no duplicate expense journal receipt or audit');
$receipt=$copies->receipt($b,7,$newKey);essentialCheck($receipt['expense_id']===$new['expense_id'],'string-fetch receipt remains authorized and confirmed');
$svc->change($b,7,$new['expense_id'],$svc->get($b,7,$new['expense_id'])['revision_token'],$copyKey('native-string-cancel'));
$before=expenseCopyState();essentialCheck($copies->create($b,7,$ib,$review,$newKey)===$new&&expenseCopyState()===$before,'cancelled new cost is not resurrected under string fetch');
$db->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,false);essentialCheck($copies->receipt($b,7,$newKey)['expense_id']===$new['expense_id'],'canonical receipt written with string driver stays valid after integer fetch transition');
echo 'Expense copy442 native numeric driver types: '.$GLOBALS['essentialChecks'].' checks ('.$db->driver().').'.PHP_EOL;
