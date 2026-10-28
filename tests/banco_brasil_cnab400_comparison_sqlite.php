<?php
/** Real production ACL, transaction, parser projection and invoice snapshot against SQLite.
 * SQLite does not prove InnoDB locks or the real driver's concurrent behavior. */
declare(strict_types=1);
require __DIR__.'/companies/invoices.php';
if($db->driver()!=='sqlite')throw new RuntimeException('SQLite fixture only');
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Reconciliation.php';
$db->query("INSERT INTO {$p}modules(module_name,installed_version,active) VALUES('banco_brasil',?,1)",[\BancoBrasil\ModuleRelease::VERSION]);
$db->query("DROP TABLE IF EXISTS {$p}bb_origins");
foreach([
 "CREATE TABLE {$p}bb_origins(id INTEGER PRIMARY KEY,invoice_id INTEGER,issuer_id INTEGER,connection_id INTEGER,scope_key TEXT,provider TEXT,channel TEXT,remote_id TEXT,control_reference TEXT,layout_binding_id INTEGER,payload_hash TEXT,account_id TEXT,environment TEXT,agreement_number TEXT)",
 "CREATE TABLE {$p}bb_origin_snapshots(origin_id INTEGER,layout_binding_id INTEGER,snapshot_json TEXT)",
 "CREATE TABLE {$p}bb_batch_origins(batch_id INTEGER,origin_id INTEGER)",
 "CREATE TABLE {$p}bb_batches(id INTEGER,issuer_id INTEGER,connection_id INTEGER,scope_key TEXT,generation_state TEXT,delivery_state TEXT,layout_id TEXT,layout_source_hash TEXT,file_sequence INTEGER,content BLOB,content_hash TEXT)",
 "CREATE TABLE {$p}bb_return_events(id INTEGER PRIMARY KEY,issuer_id INTEGER,connection_id INTEGER,scope_key TEXT,event_key TEXT,payload_hash TEXT,remote_id TEXT,control_reference TEXT,occurrence TEXT,observation TEXT,layout_binding_id INTEGER,observation_json TEXT)",
 "CREATE TABLE {$p}bb_return_files(id INTEGER PRIMARY KEY,issuer_id INTEGER,connection_id INTEGER,scope_key TEXT,content_hash TEXT,provenance_json TEXT)",
 "CREATE TABLE {$p}bb_return_file_events(return_file_id INTEGER,return_event_id INTEGER,issuer_id INTEGER,connection_id INTEGER,scope_key TEXT,layout_binding_id INTEGER)"
] as $ddl)if(!$db->query($ddl))throw new RuntimeException('fixture DDL failed');
$invoiceRow=$db->query("SELECT id,issuer_id,total,duedate FROM {$p}invoices WHERE id=?",[$branded])->row_array();
$account='001:00452-3:000000123873-X';$agreement='1234567';
$bankAgreement=new \BancoBrasil\Agreement(new \BancoBrasil\Scope($a,10,$account,'sandbox',$agreement),'12345678000195','Invoice A','019','17');
$scope=$bankAgreement->scope->key();$key=hash('sha256','synthetic event');$control='CRM'.$branded;
$projection=['layout'=>\BancoBrasil\LayoutCatalog::CNAB400,'channel'=>'cnab400','provider'=>'banco_brasil','scope_key'=>$scope,'our_number'=>$bankAgreement->ourNumber('1'),'control'=>$control,'event_key'=>$key,'occurrence'=>'06','observation'=>'settlement_reported','document'=>$control,'nominal_amount'=>\BancoBrasil\Fields::amount(\app\services\InvoicePaymentWriter::minorUnits($invoiceRow['total'])),'paid_amount'=>'10.00','nature'=>'01','fees'=>'0.00','other_expenses'=>'0.00','discount_interest'=>'0.00','iof'=>'0.00','abatement'=>'0.00','discount'=>'0.00','interest'=>'0.00','other_credits'=>'0.00','unused_abatement'=>'0.00','due_date'=>$invoiceRow['duedate'],'financial_effect'=>'not_applied'];
$json=json_encode($projection,JSON_THROW_ON_ERROR);
$db->query("INSERT INTO {$p}bb_return_events VALUES(71,?,10,?,?,?,?,?,?,?,17,?)",[$a,$scope,$key,hash('sha256',$json),$projection['our_number'],$control,'06','settlement_reported',$json]);
$db->query("INSERT INTO {$p}bb_return_files VALUES(9,?,10,?,?,?)",[$a,$scope,hash('sha256','synthetic file'),'{"source":"fixture"}']);
$db->query("INSERT INTO {$p}bb_return_file_events VALUES(9,71,?,10,?,17)",[$a,$scope]);
$service=new \BancoBrasil\Reconciliation($db,$p);
$before=[];foreach(['invoices','invoicepaymentrecords','issuers_invoice_documents','bb_origins','bb_return_events','bb_return_files','bb_return_file_events'] as $table)$before[$table]=$db->query("SELECT * FROM {$p}{$table}")->result_array();
$result=$service->compareCnab400($a,7,71,$branded);
essentialCheck($result['all_equal']===true&&$result['origin_matches_invoice']===false&&$result['identity_authority']==='not_established'&&$result['financial_effect']==='not_applied','real source comparison records no inferred origin, payment or bank authenticity');
essentialThrows(fn()=>$service->compareCnab400($b,7,71,$branded),'bb_event_unavailable','foreign company event cannot be read');
essentialThrows(fn()=>$service->compareCnab400($a,12,71,$branded),'bb_reconciliation_access_denied','staff without BB reconcile permission cannot compare');
essentialThrows(fn()=>$service->compareCnab400($a,7,71,$bound),'bb400_comparison_invoice_unavailable','foreign invoice denied even for master');
$db->query("UPDATE {$p}bb_return_events SET observation_json='{}' WHERE id=71");
essentialThrows(fn()=>$service->compareCnab400($a,7,71,$branded),'bb_event_corrupt','tampered payload fails hash verification');
$db->query("UPDATE {$p}bb_return_events SET observation_json=? WHERE id=71",[$json]);
foreach($before as $table=>$rows)essentialCheck($db->query("SELECT * FROM {$p}{$table}")->result_array()===$rows,'comparison preserves '.$table);
$connectionData=['id'=>10,'account_id'=>$account,'environment'=>'sandbox','agreement_number'=>$agreement,'issuer_tax_id'=>'12345678000195','issuer_name'=>'Invoice A','variation'=>'019','wallet'=>'17'];
$snapshot=['connection'=>$connectionData,'beneficiary_name'=>'Invoice A','title'=>['sequence'=>'1','control'=>$control,'document'=>$control,'due_date'=>$invoiceRow['duedate']],'invoice'=>['id'=>$branded]];
$snapshotJson=json_encode($snapshot,JSON_THROW_ON_ERROR);
$db->query("INSERT INTO {$p}bb_origins(id,invoice_id,issuer_id,connection_id,scope_key,provider,channel,remote_id,control_reference,layout_binding_id,payload_hash,account_id,environment,agreement_number) VALUES(80,?,?,10,?,'banco_brasil','cnab400',?,?,17,?,?,?,?)",[$branded,$a,$scope,$projection['our_number'],$control,hash('sha256',$snapshotJson),$account,'sandbox',$agreement]);
$db->query("INSERT INTO {$p}bb_origin_snapshots VALUES(80,17,?)",[$snapshotJson]);
$db->query("INSERT INTO {$p}bb_batch_origins VALUES(90,80)");
$db->query("INSERT INTO {$p}bb_batches VALUES(90,?,10,?,'generated','not_submitted',?,?,1,'',?)",[$a,$scope,\BancoBrasil\LayoutCatalog::CNAB400,hash('sha256','synthetic source'),hash('sha256','')]);
$exactBefore=$db->query("SELECT * FROM {$p}bb_origins")->result_array();
$matched=$service->compareCnab400($a,7,71,$branded);
essentialCheck($matched['origin_matches_invoice']===true&&$matched['financial_effect']==='not_applied','matching immutable origin is identified without link or payment');
essentialCheck($db->query("SELECT * FROM {$p}bb_origins")->result_array()===$exactBefore,'matching origin remains untouched');
$db->query("UPDATE {$p}bb_batches SET generation_state='awaiting_layout' WHERE id=90");
essentialCheck($service->compareCnab400($a,7,71,$branded)['origin_matches_invoice']===false,'reserved 400 source without a generated file never claims a match');
$db->query("UPDATE {$p}bb_batches SET generation_state='generated' WHERE id=90");
$db->query("UPDATE {$p}bb_origin_snapshots SET snapshot_json='{}' WHERE origin_id=80");
$altered=$service->compareCnab400($a,7,71,$branded);
essentialCheck($altered['origin_matches_invoice']===false,'corrupt snapshot never claims origin match');
echo 'CNAB400 invoice comparison: real ACL/owned SQLite transaction and no-write checks passed; no MySQL concurrency or bank authentication claimed.'.PHP_EOL;
