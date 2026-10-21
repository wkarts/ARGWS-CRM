<?php
/** Native428 additive portal metadata/replay; never infers publication from old data. */
require __DIR__.'/portal_operations_cleanup.php';
require __DIR__.'/native_427.php';
require_once APPPATH.'services/CompanyContracts.php';require_once APPPATH.'services/CompanyTickets.php';require_once APPPATH.'services/CompanyProjects.php';require_once APPPATH.'services/CompanyProjectExtras.php';require_once APPPATH.'services/CompanyOperationFiles.php';
// These records and BLOBs predate428. Native fixtures use the real schema and writers.
foreach(['firstname','lastname'] as $field)if(!in_array($field,$db->list_fields($p.'staff'),true))$db->query('ALTER TABLE '.$p.'staff ADD '.$field.' varchar(50)');
$db->query("UPDATE {$p}staff SET firstname='Native',lastname='Fixture' WHERE staffid=7");
$nativeContracts=new CompanyContracts($db,$p);$nativeTickets=new CompanyTickets($db,$p);$nativeProjects=new CompanyProjects($db,$p);$nativeFiles=new CompanyOperationFiles($db,$p);$nativeExtras=new CompanyProjectExtras($db,$p);$nativeKey=fn($s)=>hash('sha256','native428-'.$s);$nativePdf="%PDF-1.4\nNative428 private original\n%%EOF\n";
foreach([[$a,$ca],[$b,$cb]] as [$owner,$client]){
 $contract=$nativeContracts->create($owner,7,['client_id'=>$client,'name'=>'Private before428','body'=>'Unpublished contract text','start_date'=>'2026-10-19','due_date'=>'2026-11-19'],$nativeKey('contract-'.$owner));$nativeContracts->transition($owner,7,$contract,1,'activate',$nativeKey('activate-'.$owner));$nativeFiles->upload($owner,7,'contract',$contract,2,$nativeKey('contract-file-'.$owner),'native.pdf',$nativePdf);
 $ticket=$nativeTickets->create($owner,7,['client_id'=>$client,'assignee_id'=>7,'subject'=>'Private before428','body'=>'Unpublished support','priority'=>'normal'],$nativeKey('ticket-'.$owner));$nativeTickets->reply($owner,7,$ticket,1,'Internal message before428',$nativeKey('reply-'.$owner));$nativeFiles->upload($owner,7,'ticket',$ticket,2,$nativeKey('ticket-file-'.$owner),'native.pdf',$nativePdf);
 $project=$nativeProjects->create($owner,7,['client_id'=>$client,'responsible_id'=>7,'name'=>'Private before428','description'=>'Unpublished project','start_date'=>'2026-10-19','due_date'=>'2026-11-19'],$nativeKey('project-'.$owner));$nativeExtras->upload($owner,7,'project',$project,1,$nativeKey('project-file-'.$owner),'native.pdf',$nativePdf);
}
$nativePreserved=[];$nativeTables=['invoices'=>'id','invoicepaymentrecords'=>'id','company_financial_events'=>'evidence_key','company_contracts'=>'id','company_tickets'=>'id','company_projects'=>'id','company_ticket_replies'=>'id','company_contract_files'=>'file_key','company_ticket_files'=>'file_key','company_project_files'=>'file_key','company_contract_requests'=>'request_key','company_ticket_requests'=>'request_key','company_project_requests'=>'request_key'];
foreach($nativeTables as $table=>$order)$nativePreserved[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.$order)->result_array();
// The current writer fixture needs428 metadata while seeding. Remove only its empty
// reversal child before the native body to prove real creation and no backfill.
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_bank_receipt_reversals')->row()->n===0,'native fixture has no declared reversals');
$db->query('DROP TABLE '.$p.'company_bank_receipt_reversals');
require_once APPPATH.'migrations/428_version_428.php';$m428=new Migration_Version_428();$m428->db=$db;$m428->up();$m428->up();unset($db->data_cache['table_names']);
require __DIR__.'/bank_reversal_schema_contract.php';assertBankReversalSchemaContract($db,$p);
CompanyPortalOperationsSchema::assertValid($db,$p);$portalDefinitions=CompanyPortalOperationsSchema::definitions($p);essentialCheck(count($portalDefinitions)===8,'428 creates exactly eight private publication tables');
foreach($portalDefinitions as $table=>$definition){essentialCheck($db->table_exists($p.$table),'native428 publication table exists');essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.$table)->row()->n===0,'native428 and replay never publish old parents children replies or files');}
foreach($nativePreserved as $table=>$rows)essentialCheck($rows===$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.$nativeTables[$table])->result_array(),'native428 replay preserves original '.$table.' bytes including snapshots and BLOBs');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'428 preserves native NULL owner invoice byte values');
// Real MySQL metadata validation covers every new column, engine, PK, key and FK.
$db->query('ALTER TABLE '.$p.'company_portal_projects MODIFY visible varchar(8) NOT NULL');
essentialThrows(fn()=>CompanyPortalOperationsSchema::assertValid($db,$p),'issuer_portal_operations_schema_invalid','428 refuses incompatible persisted visibility column');
essentialThrows(fn()=>$m428->up(),'issuer_portal_operations_schema_invalid','native428 replay propagates incompatible schema failure without silent repair');
$db->query('ALTER TABLE '.$p.'company_portal_projects MODIFY visible tinyint NOT NULL');CompanyPortalOperationsSchema::assertValid($db,$p);
$db->failOn='CREATE TABLE IF NOT EXISTS `'.$p.'company_portal_contracts`';essentialThrows(fn()=>$m428->up(),'issuer_storage_failed','native428 propagates DDL failure to native runner');$db->failOn=null;$m428->up();CompanyPortalOperationsSchema::assertValid($db,$p);
foreach($nativePreserved as $table=>$rows)essentialCheck($rows===$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.$nativeTables[$table])->result_array(),'failed schema and replay leave originals unchanged '.$table);
echo 'Native428 actual additive schema/replay/initial privacy/DDL failure/unchanged originals: '.$GLOBALS['essentialChecks'].' cumulative checks passed. Runner tracking failures are exercised separately by native_migration_tracking, not inferred from this body fixture.'.PHP_EOL;
