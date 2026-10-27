<?php
/** Real native ACL/transactions and isolated MySQL/MariaDB; no bank or network. */
require __DIR__.'/banco_brasil_origination_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ReturnRejections.php';
$rejectionStart=$GLOBALS['essentialChecks'];
// Reuse shipped synthetic codec vectors without overwriting the composed issuer/invoice variables.
(static function(){ob_start();try{require __DIR__.'/banco_brasil_cnab240.php';require __DIR__.'/banco_brasil_cnab400.php';}finally{ob_end_clean();}})();
$rejectionStore=new BancoBrasil\ModuleStore($db,$p);
$rejectionArchive=new BancoBrasil\ReturnRejections($db,$p);
$rejectionConnection=$rejectionStore->connection($connection,$a);
$rejectionAgreement=BancoBrasil\ModuleStore::agreement($rejectionConnection);
$rejection400Binding=$rejectionStore->registerLayout($rejectionConnection,7,BancoBrasil\LayoutCatalog::CNAB400);
$rejection400=bb400Fixture($rejectionAgreement);
$rejection240=returnBytes($rejectionAgreement,explode("\r\n",substr($file['content'],0,-2)));
$rejectionState=static function()use($db,$p){
    $out=[];foreach(['bb_return_files','bb_return_originals','bb_return_events','bb_return_file_events','bb_origins','bb_batches','bb_origin_snapshots','invoices','issuers_invoice_documents','invoicepaymentrecords','credits','company_payment_receipts','company_financial_events','bb_event_links','bb_event_reviews','bb_event_receipts']as$table)
    if($db->table_exists($p.$table))$out[$table]=hash('sha256',json_encode($db->query('SELECT * FROM '.$p.$table.' ORDER BY 1')->result_array(),JSON_THROW_ON_ERROR));
    return$out;
};
$rejectionRows=static fn()=>$db->query('SELECT * FROM '.$p.'bb_return_rejections ORDER BY id')->result_array();
$rejectionAudit=static fn()=>$db->query('SELECT * FROM '.$p.'bb_audit ORDER BY id')->result_array();
$beforeRejected=$rejectionState();
$rejectedProfile=$rejectionStore->receiveReturn($rejectionConnection,7,$rejection400,$binding);
essentialCheck($rejectedProfile['rejected']&&!$rejectedProfile['duplicate']&&$rejectedProfile['financial_effect']==='not_applied','wrong 240 profile retains a refused400 upload without a successful import');
essentialCheck($rejectionState()===$beforeRejected,'refusal changes no accepted file/event/origin/invoice/payment or financial journal');
$rejectedRow=$rejectionRows()[0];
essentialCheck($rejectedRow['content']===$rejection400&&$rejectedRow['content_hash']===hash('sha256',$rejection400)&&(int)$rejectedRow['byte_count']===strlen($rejection400),'rejected bytes/hash/length retained exactly in a separate private ledger');
essentialCheck((int)$rejectedRow['issuer_id']===$a&&(int)$rejectedRow['connection_id']===$connection&&(int)$rejectedRow['layout_binding_id']===$binding,'rejected file is bound to the explicitly selected company/account/profile, never inferred from untrusted headers');
$beforeReplay=[$rejectionRows(),$rejectionAudit(),$rejectionState()];
$rejectedAgain=$rejectionStore->receiveReturn($rejectionConnection,7,$rejection400,$binding);
essentialCheck($rejectedAgain['duplicate']&&$rejectedAgain['id']===$rejectedProfile['id']&&[$rejectionRows(),$rejectionAudit(),$rejectionState()]===$beforeReplay,'same rejection reimport is exactly read-only, including audit and originals');
$accepted400=$rejectionStore->receiveReturn($rejectionConnection,7,$rejection400,$rejection400Binding);
essentialCheck(empty($accepted400['rejected'])&&!$accepted400['duplicate']&&$accepted400['events']===1,'corrected400 profile accepts the original despite its earlier separate rejection');
essentialCheck($rejectionRows()===$beforeReplay[0],'acceptance preserves the earlier refusal and its diagnostic unchanged');
$accepted400Replay=$rejectionStore->receiveReturn($rejectionConnection,7,$rejection400,$rejection400Binding);
essentialCheck($accepted400Replay['duplicate']&&$accepted400Replay['id']===$accepted400['id'],'accepted400 replay deduplicates independently of rejected uploads');
$accepted240=$rejectionStore->receiveReturn($rejectionConnection,7,$rejection240,$binding);
essentialCheck(empty($accepted240['rejected'])&&$accepted240['events']===1,'native authorized240 upload still follows the accepted-file pipeline');
$beforeInvalid=$rejectionState();
$invalid240=str_replace("\r\n","\n",$rejection240);
$invalidReceipt=$rejectionStore->receiveReturn($rejectionConnection,7,$invalid240,$binding);
essentialCheck($invalidReceipt['rejected']&&$invalidReceipt['reason_code']==='bb_return_encoding_invalid','invalid240 encoding has a closed diagnostic, never a payment');
$privateOriginal=$rejectionArchive->download($a,7,$invalidReceipt['id']);
essentialCheck($privateOriginal['content']===$invalid240&&$privateOriginal['hash']===hash('sha256',$invalid240),'authorized private download returns the exact invalid original');
$wrongAccount=bb400Mutate($rejection400,0,27,'9999');
$wrongReceipt=$rejectionStore->receiveReturn($rejectionConnection,7,$wrongAccount,$rejection400Binding);
essentialCheck($wrongReceipt['rejected']&&$wrongReceipt['reason_code']==='bb_return_account_mismatch','foreign account header is diagnosed without looking up or selecting another company');
$wrongWidth=substr_replace($rejection400,'',0,1);
$widthReceipt=$rejectionStore->receiveReturn($rejectionConnection,7,$wrongWidth,$rejection400Binding);
$widthRow=$db->query('SELECT record_index FROM '.$p.'bb_return_rejections WHERE id=?',[$widthReceipt['id']])->row_array();
essentialCheck((int)$widthRow['record_index']===1,'known physical-width rejection identifies the first actual line');
$lineFlood=str_repeat("\r\n",intdiv(16*1024*1024,242)+1);
$lineReceipt=$rejectionStore->receiveReturn($rejectionConnection,7,$lineFlood,$binding);
essentialCheck($lineReceipt['rejected']&&$lineReceipt['reason_code']==='bb_return_record_limit','malformed excessive line count is rejected before an unbounded explode');
essentialCheck($rejectionState()===$beforeInvalid,'all invalid240/400/account/line-limit operations preserve accepted and financial records');
$beforeForeign=[$rejectionRows(),$rejectionAudit(),$rejectionState()];
essentialThrows(fn()=>$rejectionArchive->download($b,7,$invalidReceipt['id']),'bb_return_rejection_unavailable','even installation master cannot fetch A file through B company scope');
essentialCheck($rejectionArchive->recent($b,7)===[],'B listing exposes no A diagnostics or file metadata');
essentialThrows(fn()=>$rejectionStore->receiveReturn($rejectionConnection,7,$invalid240,$otherBinding),'bb_layout_binding_mismatch','another account binding is refused before parser retention');
essentialCheck([$rejectionRows(),$rejectionAudit(),$rejectionState()]===$beforeForeign,'foreign company/account refusals have no writes');

// Real active member with exactly the archive capabilities, not a global admin.
$rejectionActor=8123;
$db->query('INSERT INTO '.$p.'staff(staffid,active,admin) VALUES(?,1,0)',[$rejectionActor]);
$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,?,1)',[$a,$rejectionActor]);
foreach(['view','import','download']as$cap)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,?,?)',[$a,$rejectionActor,'banco_brasil',$cap]);
essentialCheck($rejectionArchive->download($a,$rejectionActor,$invalidReceipt['id'])['content']===$invalid240,'explicit member download capability works without global admin');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=? AND feature=? AND capability=?',[$a,$rejectionActor,'banco_brasil','import']);
$beforeRevoked=[$rejectionRows(),$rejectionAudit(),$rejectionState()];
essentialThrows(fn()=>$rejectionStore->receiveReturn($rejectionConnection,$rejectionActor,$invalid240.'x',$binding),'bb_return_rejection_denied','prepared upload loses authority after import permission is revoked');
essentialCheck([$rejectionRows(),$rejectionAudit(),$rejectionState()]===$beforeRevoked,'revoked prepared upload stores no bytes, audit or financial effect');
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,?,?)',[$a,$rejectionActor,'banco_brasil','import']);
$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=?',[$rejectionActor]);
essentialThrows(fn()=>$rejectionArchive->download($a,$rejectionActor,$invalidReceipt['id']),'bb_return_rejection_denied','inactive member cannot retrieve a retained original');
$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=?',[$rejectionActor]);

$beforeFault=[$rejectionRows(),$rejectionAudit(),$rejectionState()];
$db->query("CREATE TRIGGER rejection_corrupt_original BEFORE INSERT ON {$p}bb_return_rejections FOR EACH ROW SET NEW.content='corrupt'");
try{essentialThrows(fn()=>$rejectionStore->receiveReturn($rejectionConnection,7,$invalid240.'corrupt-trigger',$binding),'bb_return_rejection_corrupt','last original-byte mutation rejects the transaction');}
finally{$db->query('DROP TRIGGER rejection_corrupt_original');}
essentialCheck([$rejectionRows(),$rejectionAudit(),$rejectionState()]===$beforeFault,'corrupt retention rolls back the entire private row/audit without accepted or financial changes');
$db->failOn='INSERT INTO '.$p.'bb_audit';
try{essentialThrows(fn()=>$rejectionStore->receiveReturn($rejectionConnection,7,$invalid240.'audit-fault',$binding),'issuer_storage_failed','audit failure rejects rather than claiming retention');}finally{$db->failOn=null;}
essentialCheck([$rejectionRows(),$rejectionAudit(),$rejectionState()]===$beforeFault,'audit failure leaves no orphan private original');
$db->query('CREATE TABLE '.$p.'rejection_witness(hit INT NOT NULL) ENGINE=MyISAM');
$db->query("CREATE TRIGGER rejection_revoke_actor AFTER INSERT ON {$p}bb_audit FOR EACH ROW BEGIN IF NEW.action='return_rejected_archived' AND NEW.actor_id={$rejectionActor} THEN INSERT INTO {$p}rejection_witness VALUES(1); UPDATE {$p}staff SET active=0 WHERE staffid={$rejectionActor}; END IF; END");
try{essentialThrows(fn()=>$rejectionStore->receiveReturn($rejectionConnection,$rejectionActor,$invalid240.'revoke-at-audit',$binding),'bb_return_rejection_denied','current authority is rechecked after the actual final archive audit');}
finally{$db->query('DROP TRIGGER rejection_revoke_actor');}
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'rejection_witness')->row()->n===1,'independent witness proves actual last-audit revocation was reached');
$db->query('DROP TABLE '.$p.'rejection_witness');
essentialCheck((int)$db->query('SELECT active FROM '.$p.'staff WHERE staffid=?',[$rejectionActor])->row()->active===1&&[$rejectionRows(),$rejectionAudit(),$rejectionState()]===$beforeFault,'last-audit refusal rolls back archive/audit/identity and preserves accepted/financial state');

$db->query('UPDATE '.$p.'bb_return_rejections SET content=? WHERE id=?',['corrupt',$invalidReceipt['id']]);
try{essentialThrows(fn()=>$rejectionArchive->download($a,7,$invalidReceipt['id']),'bb_return_rejection_corrupt','stored content mismatch refuses download');}
finally{$db->query('UPDATE '.$p.'bb_return_rejections SET content=? WHERE id=?',[$invalid240,$invalidReceipt['id']]);}
$channelBefore=$channels->page($a,7);
$channelOff=$channels->setEnabled($a,7,'cnab',false,$channelBefore['revision']);
essentialCheck($channelOff['api']&&count($rejectionArchive->recent($a,7))>=4&&$rejectionArchive->download($a,7,$invalidReceipt['id'])['content']===$invalid240,'CNAB disabled preserves API enabled and access to rejected-file history');
$channels->setEnabled($a,7,'cnab',$channelBefore['cnab'],$channelOff['revision']);
$ddlBefore=count($db->queries);$schemaRows=$rejectionRows();
BancoBrasil\ReturnRejectionSchema::upgrade($db,$p);BancoBrasil\ReturnRejectionSchema::upgrade($db,$p);
essentialCheck(!array_filter(array_slice($db->queries,$ddlBefore),static fn($q)=>preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i',$q[0]))&&$rejectionRows()===$schemaRows,'schema replay performs no DDL or row mutation after real retained files');
$db->query('ALTER TABLE '.$p.'bb_return_rejections DROP INDEX bb_return_rejection_identity');
try{essentialThrows(fn()=>BancoBrasil\ReturnRejectionSchema::upgrade($db,$p),'bb_schema_incompatible','weak rejection identity is never certified or repaired silently');}
finally{$db->query('ALTER TABLE '.$p.'bb_return_rejections ADD UNIQUE KEY bb_return_rejection_identity(scope_key,layout_binding_id,content_hash,reason_code)');}
essentialCheck($rejectionRows()===$schemaRows,'invalid schema validation preserves all private originals and diagnostics');
echo 'BB rejected return originals: '.($GLOBALS['essentialChecks']-$rejectionStart).' actual MySQL/MariaDB checks; no bank calls or financial operations.'.PHP_EOL;
