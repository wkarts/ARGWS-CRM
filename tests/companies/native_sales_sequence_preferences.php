<?php
// Actual company sales allocator with synthetic disposable invoice/sales data.
require __DIR__.'/sales.php';
require_once APPPATH.'services/NativeCompanySalesSequencePreferences.php';
$sequence = new NativeCompanySalesSequencePreferences($db, $p);
$start = $GLOBALS['essentialChecks'];
$estimateA = $sequence->read($a, 7, 'estimates');
$estimateB = $sequence->read($b, 7, 'estimates');
$proposalA = $sequence->read($a, 7, 'proposals');
$documents = $db->query('SELECT * FROM '.$p.'company_sales_documents ORDER BY id')->result_array();
$next = max(1000, (int)$estimateA['next_number'] + 1);
$advanced = $sequence->save($a, 7, 'estimates', (string)$next, $estimateA['revision']);
essentialCheck($advanced['next_number'] === (string)$next, 'real EST allocator advances for company A');
essentialCheck($sequence->read($b, 7, 'estimates') === $estimateB && $sequence->read($a, 7, 'proposals') === $proposalA, 'other company and other series stay unchanged');
essentialThrows(fn()=>$sequence->save($a, 7, 'estimates', (string)($next+1), $estimateA['revision']), 'native_sales_sequence_stale', 'stale setting cannot replace real series');
essentialThrows(fn()=>$sequence->save($a, 7, 'estimates', (string)($next-1), $advanced['revision']), 'native_sales_sequence_regression', 'counter cannot reuse historical numbers');
essentialThrows(fn()=>$sequence->save($a, 7, 'estimates', '1e4', $advanced['revision']), 'native_sales_sequence_invalid', 'numeric coercion rejected');
essentialThrows(fn()=>$sequence->save($a, 7, 'estimates', '2147483648', $advanced['revision']), 'native_sales_sequence_unemittable', 'counter cannot exceed document INT capacity');
essentialThrows(fn()=>$sequence->read($a, 7, 'invoices'), 'native_sales_sequence_invalid', 'other resource series denied');
essentialThrows(fn()=>$sequence->read($a, 12, 'estimates'), 'native_sales_sequence_denied', 'actor without permission denied');
essentialThrows(fn()=>$sequence->read($principal, 7, 'estimates'), 'native_sales_sequence_denied', 'principal native allocator is not replaced');
essentialCheck($sequence->save($a, 7, 'estimates', (string)$next, $advanced['revision']) === $advanced, 'unchanged save is idempotent');
essentialCheck($db->query('SELECT * FROM '.$p.'company_sales_documents ORDER BY id')->result_array() === $documents, 'counter setting never renumbers issued documents');
// The same allocator is consumed by the existing actual CompanySales writer.
$created = $sales->create($a, 7, 'estimates', array_replace($salesInput, ['client_id'=>$ca]), hash('sha256', 'native-estimate-advanced-counter'));
$issued = $sales->document($a, 7, $created);
essentialCheck((int)$issued['number'] === $next, 'next actual estimate uses the advanced issuer sequence');
essentialCheck($sequence->read($a, 7, 'estimates')['next_number'] === (string)($next + 1), 'writer and setting share the same monotonic allocator');
// Audit rejection rolls back the counter; never accept a partial write.
$current = $sequence->read($a, 7, 'estimates');
if ($db->driver() === 'mysql') {
    $db->query("CREATE TRIGGER {$p}native_sales_sequence_audit_failure BEFORE INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='native_sales_sequence_advanced' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'; END IF; END");
} else {
    $db->query("CREATE TRIGGER {$p}native_sales_sequence_audit_failure BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='native_sales_sequence_advanced' BEGIN SELECT RAISE(IGNORE); END");
}
essentialThrows(fn()=>$sequence->save($a, 7, 'estimates', (string)($next+5), $current['revision']), 'issuer_storage_failed', 'failed audit aborts whole update');
$db->query('DROP TRIGGER '.$p.'native_sales_sequence_audit_failure');
essentialCheck($sequence->read($a, 7, 'estimates') === $current, 'failed audit restores sequence');
$db->query("UPDATE {$p}issuers_sequences SET next_number=1 WHERE issuer_id=? AND series='EST'", [$a]);
essentialThrows(fn()=>$sequence->read($a, 7, 'estimates'), 'issuer_storage_failed', 'conflict with already issued estimate is not silently repaired');
echo 'Native sales sequence preferences: '.($GLOBALS['essentialChecks']-$start)." added checks. HTTP/form wiring is not enabled.\n";
