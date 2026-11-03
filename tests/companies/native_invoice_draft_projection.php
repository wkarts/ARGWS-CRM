<?php
require __DIR__.'/invoice_drafts.php';
require_once APPPATH.'services/NativeCompanyInvoiceDraftProjection.php';
NativeCompanyInvoiceDraftProjectionSchema::ensure($db,$p);
$projection=new NativeCompanyInvoiceDraftProjection($db,$p);$projection->backfill();$projection->backfill();
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$id=$drafts->create($a,7,$draftInput,hash('sha256','projection-create'));
$derived=IssuerTransaction::run($db,$p,$drafts->tables(),fn()=>$projection->assertInOwned($id));
$money=CompanyInvoiceLines::calculate($draftInput['items'],array_intersect_key($draftInput,array_flip(['discount_mode','discount_value','discount_type','adjustment'])));
essentialCheck($derived['state']==='open'&&$derived['revision']===1&&$derived['total']===$money['total'],'new private draft projects canonical exact amounts inside original write transaction');
$changed=array_replace($draftInput,['note'=>'Synthetic revised projection']);$drafts->change($a,7,$id,1,$changed,hash('sha256','projection-change'));
$after=IssuerTransaction::run($db,$p,$drafts->tables(),fn()=>$projection->assertInOwned($id));essentialCheck($after['revision']===2&&$after['source_hash']!==$derived['source_hash'],'draft edit atomically replaces projected source hash and revision');
$db->query('UPDATE '.$p.'company_invoice_draft_projection SET total=total+1 WHERE id=?',[$id]);
essentialThrows(fn()=>$drafts->document($a,7,$id),'issuer_invoice_draft_projection_unconfirmed','corrupt derived amount blocks runtime instead of being trusted');
$projection->backfill();essentialCheck($drafts->document($a,7,$id)['revision']===2,'explicit proven-source backfill repairs derived corruption without changing source');
$db->failOn='UPDATE '.$p.'company_invoice_draft_projection';try{essentialThrows(fn()=>$drafts->change($a,7,$id,2,$draftInput,hash('sha256','projection-rollback')),'issuer_storage_failed','projection failure rolls back complete original draft mutation');}finally{$db->failOn=null;}
essentialCheck($drafts->document($a,7,$id)['revision']===2,'projection failure leaves prior original revision intact');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'projection create/edit/backfill never reserves number or issues invoice');
echo 'Draft projection writer/backfill: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$allIds=$db->query('SELECT id FROM '.$p.'company_invoice_drafts ORDER BY id')->result_array();$first=(int)$allIds[0]['id'];$last=(int)end($allIds)['id'];
$db->query('UPDATE '.$p.'company_invoice_draft_projection SET total=total+1 WHERE id=?',[$first]);$corruptProjection=$db->query('SELECT * FROM '.$p.'company_invoice_draft_projection WHERE id=?',[$first])->row_array();
$originalSource=$db->query('SELECT payload_json FROM '.$p.'company_invoice_drafts WHERE id=?',[$last])->row_array()['payload_json'];
$db->query('UPDATE '.$p.'company_invoice_drafts SET payload_json=? WHERE id=?',['{}',$last]);
try{essentialThrows(fn()=>$projection->backfill(),'issuer_invoice_draft_projection_unconfirmed','corrupt original source stops backfill rather than guessing amounts');}finally{$db->query('UPDATE '.$p.'company_invoice_drafts SET payload_json=? WHERE id=?',[$originalSource,$last]);}
essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_draft_projection WHERE id=?',[$first])->row_array()===$corruptProjection,'failed backfill rolls back earlier repair, never partially completes data stage');$projection->backfill();
$cancelId=$drafts->create($a,7,$draftInput,hash('sha256','projection-cancel'));$drafts->change($a,7,$cancelId,1,null,hash('sha256','projection-cancel-action'));
$cancelProjection=IssuerTransaction::run($db,$p,$drafts->tables(),fn()=>$projection->assertInOwned($cancelId));essentialCheck($cancelProjection['state']==='cancelled','private cancellation synchronizes projection and remains unissued');
$issueId=$drafts->create($a,7,$draftInput,hash('sha256','projection-issue'));$issuedInvoice=$drafts->issue($a,7,$issueId,1,hash('sha256','projection-issue-action'));
$issueProjection=IssuerTransaction::run($db,$p,$drafts->tables(),fn()=>$projection->assertInOwned($issueId));essentialCheck($issueProjection['state']==='issued'&&$drafts->document($a,7,$issueId)['invoice_id']===$issuedInvoice,'explicit issue atomically changes projection state, no duplicate open draft');
echo 'Draft projection corrupt-source rollback/cancel/issue: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
