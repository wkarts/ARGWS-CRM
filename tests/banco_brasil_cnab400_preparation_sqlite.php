<?php
/** Native invoice and ACL on SQLite: no provider or real bank calls. */
declare(strict_types=1);
require __DIR__.'/companies/invoices.php';
if($db->driver()!=='sqlite')throw new RuntimeException('SQLite fixture only');
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Cnab400Preparation.php';
$db->query("INSERT INTO {$p}modules(module_name,installed_version,active) VALUES('banco_brasil',?,1)",[\BancoBrasil\ModuleRelease::VERSION]);
foreach([
 "CREATE TABLE {$p}bb_connections(id INTEGER PRIMARY KEY,issuer_id INTEGER,provider TEXT,account_id TEXT,environment TEXT,agreement_number TEXT,wallet TEXT,variation TEXT,issuer_tax_id TEXT,issuer_name TEXT,identity_hash TEXT,configuration_hash TEXT)",
 "CREATE TABLE {$p}bb_layout_bindings(id INTEGER PRIMARY KEY,issuer_id INTEGER,connection_id INTEGER,scope_key TEXT,layout_id TEXT,profile_hash TEXT,connection_hash TEXT,remittance_source_hash TEXT,return_source_hash TEXT,date_window TEXT,declared_currency TEXT)",
 "CREATE TABLE {$p}bb_cnab_preparations(id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER NOT NULL,connection_id INTEGER NOT NULL,layout_binding_id INTEGER NOT NULL,invoice_id INTEGER NOT NULL,scope_key TEXT NOT NULL,request_key TEXT NOT NULL,request_hash TEXT NOT NULL,source_hash TEXT NOT NULL,source_json TEXT NOT NULL,origin_id INTEGER DEFAULT NULL,batch_id INTEGER DEFAULT NULL,approval_json TEXT DEFAULT NULL,approval_hash TEXT DEFAULT NULL,approved_by INTEGER DEFAULT NULL,approved_at TEXT DEFAULT NULL,created_by INTEGER NOT NULL,created_at TEXT NOT NULL,UNIQUE(issuer_id,request_key),UNIQUE(origin_id),UNIQUE(batch_id))",
 "CREATE TABLE {$p}bb_audit(id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER,connection_id INTEGER,actor_id INTEGER,action TEXT,resource_type TEXT,resource_id INTEGER,created_at TEXT)"
] as $ddl)if(!$db->query($ddl))throw new RuntimeException('fixture DDL failed');
$account='001:00452-3:000000123873-X';$environment='sandbox';$agreement='1234567';$wallet='17';$variation='019';$tax='12345678000195';$name='Invoice A';$connection=10;
$identity=hash('sha256',json_encode([$a,$account,$environment,$agreement,$wallet,$variation],JSON_THROW_ON_ERROR));
$config=hash('sha256',json_encode([$identity,$tax,$name],JSON_THROW_ON_ERROR));
$db->query("INSERT INTO {$p}bb_connections VALUES(10,?,'banco_brasil',?,?,?,?,?,?,?,?,?)",[$a,$account,$environment,$agreement,$wallet,$variation,$tax,$name,$identity,$config]);
$scope=(new \BancoBrasil\Scope($a,$connection,$account,$environment,$agreement))->key();$catalog=\BancoBrasil\LayoutCatalog::get(\BancoBrasil\LayoutCatalog::CNAB400);
$db->query("INSERT INTO {$p}bb_layout_bindings VALUES(17,?,10,?,?,?,?,?,?,?,?)",[$a,$scope,\BancoBrasil\LayoutCatalog::CNAB400,\BancoBrasil\LayoutCatalog::fingerprint(\BancoBrasil\LayoutCatalog::CNAB400),$config,$catalog['remittance_sha256'],$catalog['return_sha256'],$catalog['date_window'],$catalog['currency']]);
$channels=new \BancoBrasil\ChannelPreferences($db,$p);$previous=$channels->page($a,7);$channels->setEnabled($a,7,'cnab',true,$previous['revision']);
$service=new \BancoBrasil\Cnab400Preparation($db,$p);$key=hash('sha256','fixture preparation');
$invoiceBefore=$db->query("SELECT * FROM {$p}invoices WHERE id=?",[$branded])->row_array();$paymentsBefore=$db->query("SELECT * FROM {$p}invoicepaymentrecords")->result_array();
$prepared=$service->prepare($a,7,10,17,$branded,$key);
essentialCheck($prepared['state']==='prepared_not_issued'&&!$prepared['stale']&&!$prepared['bank_number_reserved']&&!$prepared['remittance_generated']&&$prepared['financial_effect']==='not_applied','preparation is a scoped immutable source, never bank issuance');
essentialCheck($service->prepare($a,7,10,17,$branded,$key)===$prepared,'same idempotency key and input return same preparation');
essentialCheck($service->recover($a,7,$prepared['id'])===$prepared,'recovery returns the exact persisted preparation');
essentialThrows(fn()=>$service->prepare($a,7,10,17,$id,$key),'bb400_preparation_request_conflict','same request key cannot switch invoice');
essentialThrows(fn()=>$service->prepare($a,12,10,17,$branded,hash('sha256','unauthorized')),'bb400_preparation_access_denied','actor without module generate cannot prepare');
essentialThrows(fn()=>$service->recover($b,7,$prepared['id']),'bb400_preparation_unavailable','other company cannot recover private preparation');
essentialThrows(fn()=>$service->prepare($b,7,10,17,$bound,hash('sha256','cross issuer')),'bb_channel_disabled','other company has independent disabled CNAB channel');
$db->query("UPDATE {$p}invoices SET duedate='2026-12-20' WHERE id=?",[$branded]);
$stale=$service->recover($a,7,$prepared['id']);essentialCheck($stale['stale']===true&&$stale['source_hash']===$prepared['source_hash'],'changed live invoice preserves original source and marks it stale');
$db->query("UPDATE {$p}invoices SET status=2 WHERE id=?",[$branded]);
essentialCheck($service->recover($a,7,$prepared['id'])['stale']===true,'paid or closed invoice remains recoverable only as stale source');
essentialCheck($service->prepare($a,7,10,17,$branded,$key)['stale']===true,'idempotent retry cannot turn a paid invoice into a new source');
$db->query("UPDATE {$p}invoices SET status=? WHERE id=?",[$invoiceBefore['status'],$branded]);
$db->query("UPDATE {$p}invoices SET duedate=? WHERE id=?",[$invoiceBefore['duedate'],$branded]);
essentialCheck($service->recover($a,7,$prepared['id'])['stale']===false,'restored exact source matches immutable preparation');
$original=$db->query("SELECT source_json FROM {$p}bb_cnab_preparations WHERE id=?",[$prepared['id']])->row()->source_json;
$db->query("UPDATE {$p}bb_cnab_preparations SET source_json='{}' WHERE id=?",[$prepared['id']]);
essentialThrows(fn()=>$service->recover($a,7,$prepared['id']),'bb400_preparation_corrupt','tampered source hash denied');
$db->query("UPDATE {$p}bb_cnab_preparations SET source_json=? WHERE id=?",[$original,$prepared['id']]);
$count=(int)$db->query("SELECT COUNT(*) n FROM {$p}bb_cnab_preparations")->row()->n;
$db->failOn='INSERT INTO '.$p.'bb_audit';essentialThrows(fn()=>$service->prepare($a,7,10,17,$branded,hash('sha256','rollback')),'issuer_storage_failed','audit failure rolls back preparation');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}bb_cnab_preparations")->row()->n===$count,'failed audit leaves no partial preparation');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=?",[$branded])->row_array()===$invoiceBefore&&$db->query("SELECT * FROM {$p}invoicepaymentrecords")->result_array()===$paymentsBefore,'prepare/recover never change invoice or payment');
// The next stage permanently reserves a shared sequence and binds the same invoice.
foreach([
 "CREATE TABLE {$p}bb_number_reservations(bank_namespace TEXT PRIMARY KEY,issuer_id INTEGER,beneficiary_tax_id TEXT,first_number INTEGER,last_number INTEGER,next_number INTEGER,first_file INTEGER,next_file INTEGER)",
 "CREATE TABLE {$p}bb_contract_declarations(id INTEGER PRIMARY KEY,issuer_id INTEGER,connection_id INTEGER,layout_binding_id INTEGER,declaration_hash TEXT,declaration_json TEXT)",
 "CREATE TABLE {$p}bb_origins(id INTEGER PRIMARY KEY AUTOINCREMENT,invoice_id INTEGER,issuer_id INTEGER,connection_id INTEGER,account_id TEXT,environment TEXT,agreement_number TEXT,provider TEXT,channel TEXT,layout_binding_id INTEGER,remote_id TEXT,control_reference TEXT,scope_key TEXT,state TEXT,active_slot INTEGER,payload_hash TEXT,created_at TEXT,UNIQUE(invoice_id,active_slot),UNIQUE(scope_key,channel,remote_id),UNIQUE(scope_key,control_reference))",
 "CREATE TABLE {$p}bb_batches(id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER,connection_id INTEGER,scope_key TEXT,idempotency_key TEXT,payload_hash TEXT,layout_id TEXT,layout_source_hash TEXT,layout_binding_id INTEGER,file_sequence INTEGER,content_hash TEXT,content BLOB,generation_state TEXT,delivery_state TEXT,financial_state TEXT,created_by INTEGER,created_at TEXT,UNIQUE(scope_key,idempotency_key),UNIQUE(scope_key,file_sequence))",
 "CREATE TABLE {$p}bb_batch_actions(id INTEGER PRIMARY KEY AUTOINCREMENT,batch_id INTEGER,issuer_id INTEGER,actor_id INTEGER,action TEXT,reference TEXT,created_at TEXT,UNIQUE(batch_id,action))",
 "CREATE TABLE {$p}bb_origin_snapshots(origin_id INTEGER PRIMARY KEY,layout_binding_id INTEGER,contract_id INTEGER,snapshot_json TEXT)",
 "CREATE TABLE {$p}bb_batch_origins(batch_id INTEGER,origin_id INTEGER UNIQUE,PRIMARY KEY(batch_id,origin_id))"
] as $ddl)if(!$db->query($ddl))throw new RuntimeException('reservation fixture DDL failed');
$agreementObject=new \BancoBrasil\Agreement(new \BancoBrasil\Scope($a,10,$account,'sandbox',$agreement),$tax,$name,$variation,$wallet);
$namespace=\BancoBrasil\Origination::bankNamespace($agreementObject);
$db->query("INSERT INTO {$p}bb_number_reservations VALUES(?,?,?,?,?,?,?,?)",[$namespace,$a,$tax,100,199,100,1,1]);
$declaration=['beneficiary_name'=>'INVOICE A','connection_hash'=>$config,'profile_hash'=>\BancoBrasil\LayoutCatalog::fingerprint(\BancoBrasil\LayoutCatalog::CNAB400),'first_number'=>100,'last_number'=>199,'first_file'=>1,'interest_exempt'=>true,'exclusive_range'=>true,'test_exchange'=>true,'reference'=>'SYNTHETIC','verification'=>'operator_declaration_not_bank_verification'];
$declarationJson=json_encode($declaration,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
$db->query("INSERT INTO {$p}bb_contract_declarations VALUES(1,?,10,17,?,?)",[$a,hash('sha256',$declarationJson),$declarationJson]);
essentialThrows(fn()=>$service->reserve($a,7,$prepared['id'],'0'),'bb400_reservation_confirmation_required','explicit operator confirmation required');
$reserved=$service->reserve($a,7,$prepared['id'],'1');
essentialCheck($reserved['bank_number_reserved']&&$reserved['state']==='reserved_awaiting_layout'&&!$reserved['remittance_generated']&&$reserved['financial_effect']==='not_applied','origin is reserved without invented remittance or payment');
essentialCheck($service->reserve($a,7,$prepared['id'],'1')===$reserved&&$service->recover($a,7,$prepared['id'])===$reserved,'reservation and recovery are idempotent');
essentialCheck((int)$db->query("SELECT next_number FROM {$p}bb_number_reservations WHERE bank_namespace=?",[$namespace])->row()->next_number===101,'shared counter advances exactly once');
$origin=$db->query("SELECT * FROM {$p}bb_origins WHERE id=?",[$reserved['origin_id']])->row_array();$batch=$db->query("SELECT * FROM {$p}bb_batches WHERE id=?",[$reserved['batch_id']])->row_array();
essentialCheck((int)$origin['invoice_id']===$branded&&$origin['channel']==='cnab400'&&$origin['remote_id']===$agreementObject->ourNumber('100'),'reserved origin is explicitly linked to the scoped invoice and 17-digit bank number');
essentialCheck($batch['generation_state']==='awaiting_layout'&&$batch['content']===''&&$batch['financial_state']==='not_applied','reserved batch cannot be represented as an issued file');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=?",[$branded])->row_array()===$invoiceBefore&&$db->query("SELECT * FROM {$p}invoicepaymentrecords")->result_array()===$paymentsBefore,'reserving source never mutates invoice or payments');
$duplicateDraft=$service->prepare($a,7,10,17,$branded,hash('sha256','duplicate-source'));
$beforeDuplicate=$db->query("SELECT next_number,next_file FROM {$p}bb_number_reservations WHERE bank_namespace=?",[$namespace])->row_array();
essentialThrows(fn()=>$service->reserve($a,7,$duplicateDraft['id'],'1'),'bb_invoice_already_bound','a second draft cannot reserve the same invoice');
essentialCheck($db->query("SELECT next_number,next_file FROM {$p}bb_number_reservations WHERE bank_namespace=?",[$namespace])->row_array()===$beforeDuplicate,'rejected duplicate never consumes another number');
$rollbackInvoice=$s->create($a,7,$input,hash('sha256','cnab400-reservation-rollback-invoice'));
$rollbackDraft=$service->prepare($a,7,10,17,$rollbackInvoice,hash('sha256','cnab400-reservation-rollback-source'));
$beforeRollback=$db->query("SELECT next_number,next_file FROM {$p}bb_number_reservations WHERE bank_namespace=?",[$namespace])->row_array();
$db->failOn='INSERT INTO '.$p.'bb_audit';essentialThrows(fn()=>$service->reserve($a,7,$rollbackDraft['id'],'1'),'issuer_storage_failed','failed reservation audit rolls back the complete origin');$db->failOn=null;
essentialCheck($db->query("SELECT next_number,next_file FROM {$p}bb_number_reservations WHERE bank_namespace=?",[$namespace])->row_array()===$beforeRollback,'failed reservation preserves both shared counters');
essentialCheck($db->query("SELECT * FROM {$p}bb_origins WHERE invoice_id=?",[$rollbackInvoice])->result_array()===[]&&!$service->recover($a,7,$rollbackDraft['id'])['bank_number_reserved'],'failed reservation leaves neither origin nor partially linked preparation');
essentialThrows(fn()=>$service->generate($a,7,$prepared['id'],'2026-10-19',['payer_name'=>'PRIVATE A','payer_address'=>'SYNTHETIC STREET','payer_district'=>'CENTRO','payer_zip'=>'40000000','payer_city'=>'SALVADOR','payer_state'=>'BA','species'=>'02','acceptance'=>'N']),'bb400_homologation_required','generation remains blocked until explicit operator declaration');
$approvalInput=['currency_reference'=>'Synthetic currency evidence','interest_reference'=>'Synthetic exemption evidence','homologation_reference'=>'Synthetic homologation','currency_confirmed'=>'1','interest_confirmed'=>'1','homologation_confirmed'=>'1'];
essentialThrows(fn()=>$service->declareApproval($a,7,$prepared['id'],array_replace($approvalInput,['homologation_confirmed'=>'0'])),'bb400_approval_required','unconfirmed declaration cannot unlock generation');
$db->query("CREATE TRIGGER bb400_approval_late_change AFTER INSERT ON {$p}bb_audit WHEN NEW.action='cnab400_homologation_declared' BEGIN UPDATE {$p}invoices SET duedate='2027-01-01' WHERE id={$branded}; END");
try{essentialThrows(fn()=>$service->declareApproval($a,7,$prepared['id'],$approvalInput),'issuer_bb_write_unconfirmed','late invoice mutation aborts approval');}finally{$db->query('DROP TRIGGER bb400_approval_late_change');}
essentialCheck(!$service->recover($a,7,$prepared['id'])['homologation_declared'],'late approval mutation leaves no declaration');
$approved=$service->declareApproval($a,7,$prepared['id'],$approvalInput);
essentialCheck($approved['homologation_declared']===true&&!$approved['remittance_generated'],'explicit synthetic declaration is retained without generating remittance');
essentialCheck($service->declareApproval($a,7,$prepared['id'],$approvalInput)===$approved,'same declaration is idempotent');
essentialThrows(fn()=>$service->declareApproval($a,7,$prepared['id'],array_replace($approvalInput,['currency_reference'=>'different'])),'bb400_approval_conflict','declaration cannot be silently replaced');
$approvalRow=$db->query("SELECT approval_json,approval_hash FROM {$p}bb_cnab_preparations WHERE id=?",[$prepared['id']])->row_array();$approvalPolicy=json_decode($approvalRow['approval_json'],true,32,JSON_THROW_ON_ERROR);essentialCheck($approvalPolicy['currency_field']===\BancoBrasil\LayoutCatalog::CNAB400_BRL_CURRENCY_FIELD,'approval stores only the canonical BRL wire field');
$forgedApproval=$approvalPolicy;$forgedApproval['currency_field']='AA';$forgedApprovalJson=json_encode($forgedApproval,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$db->query("UPDATE {$p}bb_cnab_preparations SET approval_json=?,approval_hash=? WHERE id=?",[$forgedApprovalJson,hash('sha256',$forgedApprovalJson),$prepared['id']]);essentialThrows(fn()=>$service->recover($a,7,$prepared['id']),'bb400_approval_corrupt','rehashed arbitrary currency field is rejected');$db->query("UPDATE {$p}bb_cnab_preparations SET approval_json=?,approval_hash=? WHERE id=?",[$approvalRow['approval_json'],$approvalRow['approval_hash'],$prepared['id']]);
$candidateInput=['payer_name'=>'PRIVATE A','payer_address'=>'SYNTHETIC STREET','payer_district'=>'CENTRO','payer_zip'=>'40000000','payer_city'=>'SALVADOR','payer_state'=>'BA','species'=>'02','acceptance'=>'N'];
$beforeCandidate=$db->query("SELECT * FROM {$p}bb_batches WHERE id=?",[$reserved['batch_id']])->row_array();
$candidate=$service->candidate($a,7,$prepared['id'],'2026-10-19',$candidateInput);
essentialCheck($candidate['operational']===false&&$candidate['financial_effect']==='not_applied'&&$candidate['record_count']===3&&strlen($candidate['content_sha256'])===64,'400 candidate validates actual invoice and record widths without exposing unverified bytes');
essentialCheck(!isset($candidate['content'])&&$db->query("SELECT * FROM {$p}bb_batches WHERE id=?",[$reserved['batch_id']])->row_array()===$beforeCandidate,'candidate never stores or exposes non-homologated file');
$db->query("UPDATE {$p}invoices SET duedate='2026-12-25' WHERE id=?",[$branded]);
essentialThrows(fn()=>$service->candidate($a,7,$prepared['id'],'2026-10-19',$candidateInput),'bb400_preparation_stale','changed invoice cannot produce candidate from obsolete source');
$db->query("UPDATE {$p}invoices SET duedate=? WHERE id=?",[$invoiceBefore['duedate'],$branded]);
essentialThrows(fn()=>$service->candidate($b,7,$prepared['id'],'2026-10-19',$candidateInput),'bb_channel_disabled','candidate cannot cross company or its channel permission');
$db->query("CREATE TRIGGER bb400_generated_late_corrupt AFTER INSERT ON {$p}bb_audit WHEN NEW.action='cnab400_remittance_generated' BEGIN UPDATE {$p}bb_batches SET content='corrupt' WHERE id={$reserved['batch_id']}; END");
try{essentialThrows(fn()=>$service->generate($a,7,$prepared['id'],'2026-10-19',$candidateInput),'bb400_generated_source_corrupt','late generated file mutation aborts transaction');}finally{$db->query('DROP TRIGGER bb400_generated_late_corrupt');}
essentialCheck(!$service->recover($a,7,$prepared['id'])['remittance_generated'],'late file corruption leaves batch reserved');
$generated=$service->generate($a,7,$prepared['id'],'2026-10-19',$candidateInput);
essentialCheck($generated['remittance_generated']&&$generated['state']==='generated'&&$generated['financial_effect']==='not_applied','declared synthetic contract generates an invoice-bound file without payment');
essentialCheck($service->generate($a,7,$prepared['id'],'2026-10-19',$candidateInput)===$generated,'identical generation replay is idempotent');
$changedPayer=$candidateInput;$changedPayer['payer_address']='OTHER SYNTHETIC STREET';essentialThrows(fn()=>$service->generate($a,7,$prepared['id'],'2026-10-19',$changedPayer),'bb400_generation_conflict','changed input cannot overwrite generated bytes');
$db->query("UPDATE {$p}invoices SET duedate='2027-01-01' WHERE id=?",[$branded]);
essentialThrows(fn()=>$service->download($a,7,$prepared['id']),'bb400_preparation_stale','stale invoice cannot download previously generated file');
$db->query("UPDATE {$p}invoices SET duedate=? WHERE id=?",[$invoiceBefore['duedate'],$branded]);
$file400=$service->download($a,7,$prepared['id']);essentialCheck(strlen($file400['content'])===1206&&hash('sha256',$file400['content'])===$file400['hash'],'private 400 download returns exact verified 3 records and CRLF');
$validatedBatch=$db->query("SELECT * FROM {$p}bb_batches WHERE id=?",[$reserved['batch_id']])->row_array();$validatedSnapshot=json_decode($db->query("SELECT snapshot_json FROM {$p}bb_origin_snapshots WHERE origin_id=?",[$reserved['origin_id']])->row()->snapshot_json,true,64,JSON_THROW_ON_ERROR);
foreach([[0,20,'X'],[1,85,'02'],[1,157,'0000'],[1,161,'0000000000001'],[1,235,'@'],[1,394,'S']] as [$line,$position,$replacement]){$forgedLines=explode("\r\n",$validatedBatch['content']);$forgedLines[$line]=substr_replace($forgedLines[$line],$replacement,$position-1,strlen($replacement));$forgedBatch=$validatedBatch;$forgedBatch['content']=implode("\r\n",$forgedLines);$forgedBatch['content_hash']=hash('sha256',$forgedBatch['content']);essentialThrows(fn()=>\BancoBrasil\Cnab400GeneratedSource::validate($origin,$validatedSnapshot,$forgedBatch),'bb400_generated_source_corrupt','rehashed fixed-profile mutation rejected at record/position '.$line.'/'.$position);}
$service->reportTransmission($a,7,$prepared['id'],'SYNTHETIC TRANSMISSION');$service->reportTransmission($a,7,$prepared['id'],'SYNTHETIC TRANSMISSION');
essentialThrows(fn()=>$service->reportTransmission($a,7,$prepared['id'],'OTHER REFERENCE'),'bb_submission_report_conflict','transmission report is idempotent and cannot be rewritten');
essentialCheck($db->query("SELECT * FROM {$p}invoicepaymentrecords")->result_array()===$paymentsBefore,'generation, download and manual report never create a payment');
$originalReservation=$db->query("SELECT snapshot_json FROM {$p}bb_origin_snapshots WHERE origin_id=?",[$reserved['origin_id']])->row()->snapshot_json;
$forged=json_decode($originalReservation,true,64,JSON_THROW_ON_ERROR);$forged['title']['amount']='999.99';$forgedJson=json_encode($forged,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$forgedHash=hash('sha256',$forgedJson);
$db->query("UPDATE {$p}bb_origin_snapshots SET snapshot_json=? WHERE origin_id=?",[$forgedJson,$reserved['origin_id']]);$db->query("UPDATE {$p}bb_origins SET payload_hash=? WHERE id=?",[$forgedHash,$reserved['origin_id']]);$db->query("UPDATE {$p}bb_batches SET payload_hash=? WHERE id=?",[$forgedHash,$reserved['batch_id']]);
essentialThrows(fn()=>$service->recover($a,7,$prepared['id']),'bb400_reservation_corrupt','rehashed forged amount still mismatches immutable invoice source');
$db->query("UPDATE {$p}bb_origin_snapshots SET snapshot_json=? WHERE origin_id=?",[$originalReservation,$reserved['origin_id']]);$originalHash=hash('sha256',$originalReservation);$db->query("UPDATE {$p}bb_origins SET payload_hash=? WHERE id=?",[$originalHash,$reserved['origin_id']]);$db->query("UPDATE {$p}bb_batches SET payload_hash=? WHERE id=?",[$originalHash,$reserved['batch_id']]);
$db->query("UPDATE {$p}bb_origin_snapshots SET snapshot_json='{}' WHERE origin_id=?",[$reserved['origin_id']]);
essentialThrows(fn()=>$service->recover($a,7,$prepared['id']),'bb400_reservation_corrupt','tampered reserved snapshot is rejected');
echo 'CNAB400 preparation: immutable invoice source, idempotency, recovery and SQLite rollback passed. No MySQL concurrency or bank issuance claimed.'.PHP_EOL;
