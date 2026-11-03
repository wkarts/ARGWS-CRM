<?php
require __DIR__.'/native_invoice_commands.php';
$ids=[];foreach([['visible',0],['hidden',1]]as[$name,$private]){$definition=NativeCompanyCustomFields::definition(['fieldto'=>'invoice','name'=>'Synthetic invoice '.$name,'type'=>'input','active'=>1,'only_admin'=>$private]);$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'invoice','invoice_transfer_'.$name,json_encode($definition,JSON_THROW_ON_ERROR)]);$ids[$name]=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());}
$meta=['invoice_fields'=>[$ids['visible']=>'Visible original',$ids['hidden']=>'Hidden original'],'line_fields'=>[[],[]],'tags'=>['First original','Second original']];
$draft=$commands->createDraft($a,7,$draftInput,$meta,hash('sha256','header-transfer-source'));$drafts=new CompanyInvoiceDrafts($db,$p);$issueKey=hash('sha256','header-transfer-issue');
$source=$db->query("SELECT * FROM {$p}company_custom_field_values WHERE source_kind='company_invoice_draft' AND source_id=?",[$draft['draft_id']])->result_array();
$invoice=$drafts->issue($a,12,$draft['draft_id'],1,$issueKey);
essentialCheck($fields->values($a,7,'invoice',$invoice)[$ids['visible']]==='Visible original'&&$fields->values($a,7,'invoice',$invoice)[$ids['hidden']]==='Hidden original','issued header preserves exact visible and hidden draft fields');
essentialCheck(!array_key_exists($ids['hidden'],$fields->values($a,12,'invoice',$invoice)),'issuing actor does not gain hidden metadata visibility');
essentialCheck(array_column($tags->read($a,12,'invoice',$invoice)['tags'],'name')===['First original','Second original'],'draft tags transfer explicitly in original order');
essentialCheck($db->query("SELECT * FROM {$p}company_custom_field_values WHERE source_kind='company_invoice_draft' AND source_id=?",[$draft['draft_id']])->result_array()===$source,'issued metadata transfer does not rewrite historical draft');
$before=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array();essentialCheck($drafts->issue($a,12,$draft['draft_id'],1,$issueKey)===$invoice&&$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array()===$before,'issue replay validates metadata receipt without reapplying fields or tags');
$issued=$drafts->document($a,12,$draft['draft_id']);$receipt=$issued['issue']['metadata_transfer']??null;
essentialCheck(is_array($receipt)&&$receipt['audit_id']>0&&preg_match('/^[a-f0-9]{64}$/D',$receipt['hash'])===1,'issued immutable result includes verifiable metadata transfer receipt');
$audit=$db->query('SELECT metadata_json FROM '.$p.'issuers_audit WHERE id=?',[$receipt['audit_id']])->row_array();$db->query('UPDATE '.$p.'issuers_audit SET metadata_json=? WHERE id=?',['{}',$receipt['audit_id']]);
try{essentialThrows(fn()=>$drafts->issue($a,12,$draft['draft_id'],1,$issueKey),'issuer_native_invoice_metadata_transfer_unconfirmed','tampered transfer audit blocks replay');}finally{$db->query('UPDATE '.$p.'issuers_audit SET metadata_json=? WHERE id=?',[$audit['metadata_json'],$receipt['audit_id']]);}
$retryDraft=$commands->createDraft($a,7,$draftInput,$meta,hash('sha256','header-transfer-rollback-source'));$retryKey=hash('sha256','header-transfer-rollback-issue');
if($db->driver()==='sqlite'){
 $before=[];foreach(['invoices','issuers_sequences','company_custom_field_values','company_work_tag_links','company_invoice_requests']as$table)$before[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
 $db->query("CREATE TRIGGER reject_header_transfer BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='native_invoice_draft_metadata_transferred' BEGIN SELECT RAISE(ABORT,'synthetic receipt refusal'); END");
 essentialThrows(fn()=>$drafts->issue($a,12,$retryDraft['draft_id'],1,$retryKey),'issuer_storage_failed','late metadata receipt refusal rolls back complete issue');$db->query('DROP TRIGGER reject_header_transfer');
 foreach($before as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'metadata failure preserves exact '.$table.' state');
 essentialCheck($drafts->document($a,7,$retryDraft['draft_id'])['state']==='open','metadata failure leaves draft open for exact retry');
 $retry=$drafts->issue($a,12,$retryDraft['draft_id'],1,$retryKey);essentialCheck($fields->values($a,7,'invoice',$retry)[$ids['hidden']]==='Hidden original','retry transfers hidden metadata only after complete successful issue');
}
$tables=NativeCompanyInvoiceDraftMetadata::tables($db,$p);$db->query('ALTER TABLE '.$p.'company_work_tags RENAME TO '.$p.'held_company_work_tags');
try{essentialThrows(fn()=>NativeCompanyInvoiceDraftMetadata::tables($db,$p),'native_invoice_metadata_schema_incomplete','partial tag schema cannot silently drop transfer');}finally{$db->query('ALTER TABLE '.$p.'held_company_work_tags RENAME TO '.$p.'company_work_tags');}
essentialCheck(NativeCompanyInvoiceDraftMetadata::tables($db,$p)===$tables,'restored metadata schema census matches');
echo 'Draft header fields and ordered tags atomic issue transfer: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
