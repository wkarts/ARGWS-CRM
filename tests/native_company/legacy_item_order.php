<?php
require __DIR__.'/invoice_native_files.php';require_once APPPATH.'services/NativeCompanyLegacyItemOrder.php';
$legacyOrder=new NativeCompanyLegacyItemOrder($db,$p);
$db->query("INSERT INTO {$p}itemable(id,rel_id,rel_type,description,long_description,qty,rate,unit,item_order) VALUES(99101,42,'invoice','Legacy one','',1,10,'',1),(99102,42,'invoice','Legacy two','',1,20,'',2)");
$all=$db->query("SELECT id FROM {$p}itemable WHERE rel_id=42 AND rel_type='invoice' ORDER BY id")->result_array();$ids=array_reverse(array_map('intval',array_column($all,'id')));$pairs=[];foreach($ids as$o=>$id)$pairs[]=[$id,$o+1];
$invoiceBefore=$db->query("SELECT * FROM {$p}invoices WHERE id=42")->row_array();
$legacyOrder->save($principal,7,'invoice',$pairs);essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->row_array()===$invoiceBefore,'legacy order preserves invoice values');
essentialCheck(array_map('intval',array_column($db->query("SELECT id FROM {$p}itemable WHERE rel_id=42 AND rel_type='invoice' ORDER BY item_order")->result_array(),'id'))===$ids,'legacy complete set ordered within exact parent');
$foreign=$db->query("SELECT id FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY id",[$ia])->row_array();
essentialThrows(fn()=>$legacyOrder->save($principal,7,'invoice',[[(int)$foreign['id'],1]]),'native_legacy_order_denied','principal legacy endpoint cannot alter named invoice item');
essentialThrows(fn()=>$legacyOrder->save($a,7,'invoice',$pairs),'native_legacy_order_denied','named context cannot use legacy writer');
essentialThrows(fn()=>$legacyOrder->save($principal,7,'invoice',[[99101,1]]),'native_legacy_order_denied','partial item set denied');
$reverse=array_reverse($ids);$changed=[];foreach($reverse as$o=>$id)$changed[]=[$id,$o+1];
if($db->driver()==='sqlite'){$db->query("CREATE TRIGGER legacy_order_ignore BEFORE UPDATE ON {$p}itemable BEGIN SELECT RAISE(IGNORE); END");essentialThrows(fn()=>$legacyOrder->save($principal,7,'invoice',$changed),'issuer_storage_failed','ignored legacy update cannot announce success');$db->query('DROP TRIGGER legacy_order_ignore');}
echo "Native legacy item-order boundary 6 checks PASS\n";

$historicalClient=(int)$invoiceBefore['clientid'];
if(!$db->table_exists($p.'leads'))$db->query('CREATE TABLE '.$p.'leads(id INT PRIMARY KEY,client_id INT,issuer_id INT NULL)');
foreach(['estimate'=>'estimates','proposal'=>'proposals','credit_note'=>'creditnotes']as$type=>$table){
    if(!$db->table_exists($p.$table))$db->query('CREATE TABLE '.$p.$table.'(id INT PRIMARY KEY,clientid INT,rel_id INT,rel_type VARCHAR(30),issuer_id INT NULL)');
    unset($db->data_cache['table_names']);
    if($type==='proposal')$db->query('INSERT INTO '.$p.$table.'(id,rel_id,rel_type) VALUES(9981,?,?)',[$historicalClient,'customer']);
    else $db->query('INSERT INTO '.$p.$table.'(id,clientid'.($type==='credit_note'?',issuer_id':'').') VALUES(9981,?'.($type==='credit_note'?',NULL':'').')',[$historicalClient]);
    $next=99200+array_search($type,['estimate','proposal','credit_note'],true)*2;
    foreach([0,1]as$n)$db->query("INSERT INTO {$p}itemable(id,rel_id,rel_type,description,long_description,qty,rate,unit,item_order) VALUES(?,9981,?,'Legacy','','1','1','',?)",[$next+$n,$type,$n+1]);
    $legacyOrder->save($principal,7,$type,[[$next+1,1],[$next,2]]);
    essentialCheck((int)$db->query("SELECT item_order FROM {$p}itemable WHERE id=?",[$next])->row_array()['item_order']===2,'legacy '.$type.' resolves native parent before ordering');
}
echo "Native legacy estimate/proposal/credit-note order 3 checks PASS\n";
$beforeOrder=$db->query("SELECT * FROM {$p}itemable WHERE rel_id=42 AND rel_type='invoice' ORDER BY id")->result_array();
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$legacyOrder->save($principal,7,'invoice',$changed),'issuer_storage_failed','audit failure rolls back historical ordering');$db->failOn=null;
essentialCheck($beforeOrder===$db->query("SELECT * FROM {$p}itemable WHERE rel_id=42 AND rel_type='invoice' ORDER BY id")->result_array(),'audit failure preserves every historical item byte');
essentialThrows(fn()=>$legacyOrder->save($principal,12,'invoice',$pairs),'native_legacy_order_denied','named editor is not installation-company historical authority');
echo "Native legacy order audit rollback/ACL 3 checks PASS\n";
