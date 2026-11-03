<?php
require __DIR__.'/native_invoice_draft_lines.php';
require_once APPPATH.'services/NativeCompanyCustomFields.php';
NativeCompanyCustomFieldSchema::ensure($db,$p);$fields=new NativeCompanyCustomFields($db,$p);
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'items','name'=>'Synthetic private line metadata','type'=>'input','active'=>1]);$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'items','draft_line_metadata',json_encode($definition,JSON_THROW_ON_ERROR)]);$field=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
$private=$drafts->create($a,7,$draftInput,hash('sha256','draft-line-fields'));
$line=$db->query('SELECT id FROM '.$p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE issuer_id=? AND draft_id=? AND draft_revision=1 ORDER BY position',[$a,$private])->row_array();$canonical=NativeCompanyResourceId::encode('company_invoice_draft_line',$line['id']);
$tables=array_unique(array_merge($drafts->tables(),$fields->tableNames()));
IssuerTransaction::run($db,$p,$tables,fn()=>$fields->saveInOwnedTransaction($a,7,'items',$canonical,[$field=>'Private line <script> data']));
essentialCheck($fields->values($a,7,'items',$canonical)[$field]==='Private line <script> data','real current draft-line custom field uses distinct persisted source');
essentialThrows(fn()=>$fields->values($b,7,'items',$canonical),'native_custom_field_denied','foreign draft line never reads metadata');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,$fields->tableNames(),fn()=>$fields->saveInOwnedTransaction($a,7,'items',$canonical,[$field=>'bad'])),'issuer_owned_transaction_required','private line writes require entire owned draft boundary');
$drafts->change($a,7,$private,1,$draftInput,hash('sha256','draft-line-fields-next'));
essentialThrows(fn()=>$fields->values($a,7,'items',$canonical),'native_custom_field_denied','old revision line ID is not silently accepted as new editable line');
$old=$db->query("SELECT value FROM {$p}company_custom_field_values WHERE issuer_id=? AND source_kind='company_invoice_draft_line' AND source_id=?",[$a,$line['id']])->row_array();essentialCheck($old['value']==='Private line <script> data','revision change retains historical field rather than deleting or silently moving it');
echo 'Actual draft-line custom fields: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$current=$db->query('SELECT id FROM '.$p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE issuer_id=? AND draft_id=? AND draft_revision=2 ORDER BY position',[$a,$private])->row_array();$currentCanonical=NativeCompanyResourceId::encode('company_invoice_draft_line',$current['id']);
IssuerTransaction::run($db,$p,$tables,fn()=>$fields->saveInOwnedTransaction($a,7,'items',$currentCanonical,[$field=>'Explicit current metadata']));
$issuedFields=$drafts->issue($a,7,$private,2,hash('sha256','draft-line-fields-issued'));
$mapping=$db->query('SELECT itemable_id FROM '.$p.NativeCompanyInvoiceDraftLinesSchema::TRANSFERS.' WHERE draft_line_id=?',[$current['id']])->row_array();
essentialCheck($fields->values($a,7,'items',(int)$mapping['itemable_id'])[$field]==='Explicit current metadata','exact mapped issued line receives real current draft metadata');
$audits=$db->query("SELECT metadata_json FROM {$p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_invoice_line_fields_transferred'",[$a,$private])->result_array();essentialCheck(count($audits)===count($draftInput['items']),'every source/target metadata handoff has durable audit receipt');
$beforeFields=$db->query('SELECT * FROM '.$p.'company_custom_field_values ORDER BY issuer_id,source_kind,source_id,field_id')->result_array();$drafts->issue($a,7,$private,2,hash('sha256','draft-line-fields-issued'));essentialCheck($db->query('SELECT * FROM '.$p.'company_custom_field_values ORDER BY issuer_id,source_kind,source_id,field_id')->result_array()===$beforeFields,'issue replay does not reapply or overwrite line metadata');
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12",[$a]);$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'customers','view')",[$a]);
$createOnly=$drafts->create($a,12,$draftInput,hash('sha256','line-fields-createonly'));$createdLine=$db->query('SELECT id FROM '.$p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE issuer_id=? AND draft_id=? ORDER BY position',[$a,$createOnly])->row_array();$createdCanonical=NativeCompanyResourceId::encode('company_invoice_draft_line',$createdLine['id']);
IssuerTransaction::run($db,$p,$tables,fn()=>$fields->saveInOwnedTransaction($a,7,'items',$createdCanonical,[$field=>'Reviewed for create-only issue']));
$createOnlyInvoice=$drafts->issue($a,12,$createOnly,1,hash('sha256','line-fields-createonly-issue'));essentialCheck($createOnlyInvoice>0,'creation proof permits metadata handoff without granting invoice edit to create-only actor');
echo 'Draft to issued line metadata proof: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$failureDraft=$drafts->create($a,7,$draftInput,hash('sha256','line-fields-failure'));$failureLine=$db->query('SELECT id FROM '.$p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE issuer_id=? AND draft_id=? ORDER BY position',[$a,$failureDraft])->row_array();$failureCanonical=NativeCompanyResourceId::encode('company_invoice_draft_line',$failureLine['id']);
IssuerTransaction::run($db,$p,$tables,fn()=>$fields->saveInOwnedTransaction($a,7,'items',$failureCanonical,[$field=>'Survives failed handoff']));
if($db->driver()==='sqlite'){
 $db->query("CREATE TRIGGER ignore_transferred_line_fields BEFORE INSERT ON {$p}company_custom_field_values WHEN NEW.source_kind='invoice_item' BEGIN SELECT RAISE(IGNORE); END");
 $financialBefore=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
 essentialThrows(fn()=>$drafts->issue($a,7,$failureDraft,1,hash('sha256','line-fields-failure-issue')),'issuer_storage_failed','ignored target metadata insert cannot acknowledge issue');
 essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$financialBefore&&$drafts->document($a,7,$failureDraft)['state']==='open','metadata failure rolls back invoice, mapping and draft state');
 $db->query('DROP TRIGGER ignore_transferred_line_fields');essentialCheck($drafts->issue($a,7,$failureDraft,1,hash('sha256','line-fields-failure-issue'))>0,'same explicit issue retry transfers fields after transient write fault');
}
echo 'Atomic line metadata handoff rollback: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
