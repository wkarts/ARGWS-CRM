<?php
declare(strict_types=1);
require __DIR__.'/purchases_fixture.php';
$x=purchaseOrder($a,12,'faults');
$receipt=$svc->receive($a,12,$x['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'3.000']]],$key('fault-receipt'));
$input=['order_id'=>$x['order'],'receipt_ids'=>[$receipt],'vendor_number'=>'FAULT-1','document_date'=>$today,'due_date'=>$today,'category_id'=>$categories[$a]];
foreach (['company_purchase_documents','company_purchase_allocations','company_expenses','company_financial_events','company_expense_requests','company_purchase_payables','company_purchase_commands'] as $table) {
    $before=purchaseSnapshot(); $needle='INSERT INTO '.(in_array($table,['company_expenses','company_financial_events','company_expense_requests'],true)?$p.$table:'`'.$p.$table.'`');
    $db->fail=$needle;
    purchaseThrows(fn()=>$svc->postDocument($a,12,$input,$key('fail-'.$table)),'issuer_storage_failed','real transaction failure at '.$table);
    $db->fail=null; purchaseCheck(purchaseSnapshot()===$before,'zero delta across cost/AP/audit/allocations after failure at '.$table);
}
foreach (['company_purchase_documents','company_purchase_allocations','company_purchase_payables','company_purchase_commands'] as $table) {
    $before=purchaseSnapshot(); $db->ignore='INSERT INTO `'.$p.$table.'`';
    purchaseThrows(fn()=>$svc->postDocument($a,12,$input,$key('ignored-'.$table)),'issuer_purchase_write_unconfirmed','ignored write cannot confirm '.$table);
    $db->ignore=null; purchaseCheck(purchaseSnapshot()===$before,'ignored '.$table.' leaves zero durable delta');
}
foreach (['company_expense_created','company_financial_event_recorded'] as $eventName) {
    $before=purchaseSnapshot(); $fired=false;
    $db->observer=static function ($sql,$args,$conn) use ($p,$eventName,&$fired): void {
        if (!$fired&&str_starts_with($sql,'INSERT INTO '.$p.'company_expense_requests')) { $fired=true; $conn->pdo->exec("UPDATE {$p}issuers_audit SET actor_id=14,metadata_json='{}' WHERE event='".$eventName."'"); }
    };
    purchaseThrows(fn()=>$svc->postDocument($a,12,$input,$key('tampered-'.$eventName)),'issuer_purchase_integrity_invalid','canonical audit mutated after native writer caught: '.$eventName);
    $db->observer=null; purchaseCheck($fired&&purchaseSnapshot()===$before,'audit mutation is observed and all financial participants roll back');
}
foreach (['expense','receipt','grant'] as $target) {
    $before=purchaseSnapshot(); $fired=false;
    $db->observer=static function ($sql,$args,$conn) use ($p,$a,$target,&$fired): void {
        if (!$fired&&str_starts_with($sql,'INSERT INTO `'.$p.'company_purchase_commands`')) {
            $fired=true;
            if ($target==='expense') { $conn->pdo->exec("UPDATE {$p}company_expenses SET total_cents=total_cents+1"); }
            elseif ($target==='receipt') { $conn->pdo->exec("UPDATE {$p}company_purchase_receipts SET base_cents=base_cents+1"); }
            else { $conn->pdo->exec("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id={$a} AND staff_id=12 AND feature='purchases' AND capability='approve'"); }
        }
    };
    $caught=null; try { $svc->postDocument($a,12,$input,$key('late-'.$target)); } catch (RuntimeException|DomainException $e) { $caught=$e->getMessage(); }
    purchaseCheck($target==='grant'?$caught==='company_purchase_denied':in_array($caught,['issuer_purchase_integrity_invalid','issuer_purchase_write_unconfirmed'],true),'semantic integrity or an earlier exact frame rejects late '.$target.' corruption ('.$caught.')');
    $db->observer=null; purchaseCheck($fired&&purchaseSnapshot()===$before,'late '.$target.' mutation leaves zero delta');
}
$payable=$svc->postDocument($a,12,$input,$key('success-after-faults')); purchaseCheck((int)$svc->get($a,12,'payable',$payable)['amount_cents']===3303,'fresh retry after failures produces one correct obligation');
$command=$db->query('SELECT * FROM '.$p.'company_purchase_commands WHERE request_key=?',[$key('success-after-faults')])->row_array();
$db->query('UPDATE '.$p.'company_purchase_commands SET result_json=? WHERE id=?',[(string)($payable+1),$command['id']]);
purchaseThrows(fn()=>$svc->postDocument($a,12,$input,$key('success-after-faults')),'issuer_purchase_receipt_invalid','stored receipt result cannot point to another identity');
$db->query('UPDATE '.$p.'company_purchase_commands SET result_json=? WHERE id=?',[$command['result_json'],$command['id']]);
purchaseCheck($svc->postDocument($a,12,$input,$key('success-after-faults'))===$payable,'valid original receipt remains replayable');
$triggerOrder=purchaseOrder($b,12,'native-trigger'); $triggerReceipt=$svc->receive($b,12,$triggerOrder['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'3.000']]],$key('native-trigger-receipt'));
$before=purchaseSnapshot();
$db->query("CREATE TRIGGER {$p}purchase_late_expense_audit AFTER INSERT ON {$p}company_expense_requests FOR EACH ROW BEGIN UPDATE {$p}issuers_audit SET actor_id=14,metadata_json='{}' WHERE issuer_id=NEW.issuer_id AND subject_id=NEW.result_id AND event='company_expense_created'; END");
purchaseThrows(fn()=>purchaseDocument($b,12,$triggerOrder['order'],[$triggerReceipt],'native-trigger'),'issuer_purchase_integrity_invalid','real SQL AFTER trigger cannot falsify canonical cost audit after its native writer');
purchaseCheck(purchaseSnapshot()===$before,'native SQL trigger leaves zero delta in document allocation expense journal AP command and audit'); $db->query('DROP TRIGGER '.$p.'purchase_late_expense_audit');
$before=purchaseSnapshot(); $db->trans_begin(); purchaseThrows(fn()=>$svc->createRequest($a,12,['title'=>'outer','needed_date'=>$today,'currency'=>'BRL','lines'=>[['description'=>'x','unit'=>'UN','quantity'=>'1.000']]],$key('outer')),'issuer_outer_transaction_not_allowed','writer never commits external transaction'); $db->trans_rollback(); purchaseCheck(purchaseSnapshot()===$before,'external transaction refusal leaves zero delta');
echo 'Purchases fault/integrity: '.$GLOBALS['essentialChecks'].' checks passed on real mysql'.PHP_EOL;
