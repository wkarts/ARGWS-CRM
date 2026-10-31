<?php
require __DIR__.'/invoices.php';
$reorder=$input;$reorder['client_id']=$ca;$reorder['items'][]=['description'=>'Second synthetic line','qty'=>'1.00','rate'=>'7.50'];$id=$s->create($a,7,$reorder,hash('sha256','native-item-order'));
$items=$db->query("SELECT * FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$id])->result_array();$ids=array_map('intval',array_column($items,'id'));$revision=$s->revision($a,7,$id);$money=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$id])->row_array();$journal=$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_sequence')->result_array();
$next=$s->reorderItems($a,7,$id,$revision,array_reverse($ids));essentialCheck($next!==$revision&&$next===$s->revision($a,7,$id),'real item order changes content revision');
essentialCheck(array_map('intval',array_column($db->query("SELECT id FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$id])->result_array(),'id'))===array_reverse($ids),'same actual item IDs reordered without copying or creating lines');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$id])->row_array()===$money&&$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_sequence')->result_array()===$journal,'presentation reorder leaves money/status/number and entire financial journal byte-identical');
essentialThrows(fn()=>$s->reorderItems($a,7,$id,$revision,$ids),'company_invoice_stale','stale content revision rejected');
essentialThrows(fn()=>$s->reorderItems($b,7,$id,$next,$ids),'company_invoice_access_denied','cross-company item order denied');
essentialThrows(fn()=>$s->reorderItems($a,7,$id,$next,[$ids[0]]),'company_invoice_item_scope_denied','partial item list cannot drop hidden or unrelated items');
essentialThrows(fn()=>$s->reorderItems($a,7,$id,$next,[$ids[0],$ids[0]]),'company_invoice_invalid_input','duplicated IDs rejected');
essentialThrows(fn()=>$s->reorderItems($a,7,$id,$next,[$ids[0],999999]),'company_invoice_item_scope_denied','foreign item cannot be smuggled into owned invoice');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$s->reorderItems($a,7,$id,$next,$ids),'issuer_storage_failed','late reorder audit failure rolls back order');$db->failOn=null;essentialCheck($s->revision($a,7,$id)===$next,'audit rollback keeps previous line order and CAS');
essentialCheck($s->reorderItems($a,7,$id,$next,array_reverse($ids))===$next,'already-current order is a no-op with current revision');
echo 'Native invoice exact item-order writer: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$db->table_exists($p.'bb_origins');$db->query('CREATE TABLE '.$p.'bb_origins(id int PRIMARY KEY,invoice_id int NOT NULL)');$db->query('INSERT INTO '.$p.'bb_origins VALUES(1,?)',[$id]);
essentialThrows(fn()=>$s->reorderItems($a,7,$id,$next,$ids),'company_invoice_provider_review_required','stale table cache cannot hide provider origin from presentation writer');$db->query('DROP TABLE '.$p.'bb_origins');unset($db->data_cache['table_names']);
if($db->driver()==='sqlite'){
 $db->query('CREATE TRIGGER native_reorder_ignore BEFORE UPDATE ON '.$p.'itemable BEGIN SELECT RAISE(IGNORE); END');
 essentialThrows(fn()=>$s->reorderItems($a,7,$id,$next,$ids),'issuer_invoice_write_unconfirmed','ignored item update cannot acknowledge a reordered document');$db->query('DROP TRIGGER native_reorder_ignore');
 essentialCheck($s->revision($a,7,$id)===$next,'ignored update rolls back full reorder');
}
$db->pdo->beginTransaction();try{essentialThrows(fn()=>$s->reorderItems($a,7,$id,$next,$ids),'issuer_outer_transaction_not_allowed','item ordering cannot borrow caller transaction');}finally{$db->pdo->rollBack();}
echo 'Native item-order fences/readback: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
