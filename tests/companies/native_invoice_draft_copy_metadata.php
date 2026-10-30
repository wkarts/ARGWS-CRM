<?php
require __DIR__.'/native_invoice_draft_creation_tags.php';
require_once APPPATH.'services/NativeCompanyInvoiceDraftCopy.php';
$itemField=NativeCompanyResourceId::encode('company_custom_field',$db->query('SELECT id FROM '.$p.'company_custom_fields WHERE issuer_id=? AND slug=?',[$a,'draft_line_metadata'])->row()->id);
$fieldDef=NativeCompanyCustomFields::definition(['fieldto'=>'invoice','name'=>'Copy invoice field','type'=>'input','active'=>1]);
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'invoice','invoice_copy_field',json_encode($fieldDef,JSON_THROW_ON_ERROR)]);$invoiceField=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
$itemId=(int)$db->query("SELECT id FROM {$p}itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id LIMIT 1",[$branded])->row()->id;
IssuerTransaction::run($db,$p,array_unique(array_merge($fields->tableNames(),['invoices','itemable'])),function()use($fields,$a,$branded,$invoiceField,$itemId,$itemField){$fields->saveInOwnedTransaction($a,7,'invoice',$branded,[$invoiceField=>'Invoice copy value']);$fields->saveInOwnedTransaction($a,7,'items',$itemId,[$itemField=>'Line copy value']);});
$sourceTags=$tags->read($a,7,'invoice',$branded);IssuerTransaction::run($db,$p,array_merge(NativeCompanyResourceTags::tableNames(),['invoices']),fn()=>$tags->replaceInvoiceInOwnedTransaction($a,7,$branded,$sourceTags['revision'],['Copied tag']));
$source=$copy->preview($a,7,$branded);$review=['source_revision'=>$source['revision'],'date'=>'2026-12-15','due_date'=>'2027-01-15','note'=>$source['note'],'confirm_amounts'=>'1'];$key=hash('sha256','native-copy-with-metadata');
$service=new NativeCompanyInvoiceDraftCopy($db,$p);$canonical=$service->copy($a,7,$branded,$review,$key);$draftId=NativeCompanyResourceId::decode('invoice',$canonical)['source_id'];$draft=$creator->document($a,7,$draftId);
essentialCheck($draft['state']==='open'&&$draft['invoice_id']===null&&$draft['payload']['items']===$source['items'],'original copy composition creates private draft with exact items');
essentialCheck($fields->values($a,7,'invoice',$canonical)[$invoiceField]==='Invoice copy value'&&array_column($tags->read($a,7,'invoice',$canonical)['tags'],'name')===['Copied tag'],'invoice custom value and tags are copied');
$line=$db->query('SELECT id FROM '.$p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE draft_id=? AND draft_revision=1 ORDER BY position',[$draftId])->row_array();$canonicalLine=NativeCompanyResourceId::encode('company_invoice_draft_line',$line['id']);
essentialCheck($fields->values($a,7,'items',$canonicalLine)[$itemField]==='Line copy value','line custom field is copied to exact persisted draft line');
essentialCheck($service->copy($a,7,$branded,$review,$key)===$canonical,'same copy request returns exact draft without duplicate metadata');
$sourceTags=$tags->read($a,7,'invoice',$branded);IssuerTransaction::run($db,$p,array_merge(NativeCompanyResourceTags::tableNames(),['invoices']),fn()=>$tags->replaceInvoiceInOwnedTransaction($a,7,$branded,$sourceTags['revision'],['Source changed later']));
essentialCheck($service->copy($a,7,$branded,$review,$key)===$canonical&&array_column($tags->read($a,7,'invoice',$canonical)['tags'],'name')===['Copied tag'],'replay uses frozen metadata even if source tags change later');
essentialThrows(fn()=>$service->copy($b,7,$branded,$review,hash('sha256','foreign-native-copy')),'company_invoice_access_denied','foreign company cannot copy source metadata');
$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n;$db->failOn='INSERT INTO '.$p.'issuers_audit';
try{essentialThrows(fn()=>$service->copy($a,7,$branded,$review,hash('sha256','native-copy-rollback')),'issuer_storage_failed','audit failure aborts complete draft composition');}finally{$db->failOn=null;}
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n===$before,'failed metadata copy leaves no draft');
$db->query('UPDATE '.$p.'invoices SET recurring=1 WHERE id=?',[$branded]);
try{essentialThrows(fn()=>$service->copy($a,7,$branded,$review,hash('sha256','native-copy-recurring')),'native_invoice_copy_recurring_required','unsupported recurring source cannot silently lose recurrence');}finally{$db->query('UPDATE '.$p.'invoices SET recurring=0 WHERE id=?',[$branded]);}
$issued=$creator->issue($a,7,$draftId,1,hash('sha256','native-copy-issue'));
$issuedLine=(int)$db->query("SELECT id FROM {$p}itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id LIMIT 1",[$issued])->row()->id;
essentialCheck($fields->values($a,7,'invoice',$issued)[$invoiceField]==='Invoice copy value'&&$fields->values($a,7,'items',$issuedLine)[$itemField]==='Line copy value'&&array_column($tags->read($a,7,'invoice',$issued)['tags'],'name')===['Copied tag'],'later issuance transfers exact draft metadata and tags');
echo 'Native draft copy: exact invoice/item metadata, tags, source isolation and replay passed.'.PHP_EOL;
