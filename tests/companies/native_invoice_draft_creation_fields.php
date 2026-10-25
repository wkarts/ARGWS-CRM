<?php
require __DIR__.'/native_invoice_draft_line_fields.php';
$creator=new CompanyInvoiceDrafts($db,$p);$creationKey=hash('sha256','private-creation-fields');$createdId=null;$createdLineId=null;
$callback=function()use($creator,$db,$p,$a,$fields,$field,$creationKey,&$createdId,&$createdLineId){
 $row=$db->query('SELECT id FROM '.$p.'company_invoice_drafts WHERE issuer_id=? AND origin_key=?',[$a,$creationKey])->row_array();if(!$row)return;$createdId=(int)$row['id'];
 $line=$db->query('SELECT id FROM '.$p.NativeCompanyInvoiceDraftLinesSchema::TABLE.' WHERE issuer_id=? AND draft_id=? AND draft_revision=1 ORDER BY position',[$a,$createdId])->row_array();$createdLineId=(int)$line['id'];
 $fields->saveCreatedInOwnedTransaction($a,12,'items',$createdLineId,[$field=>'Created by actual create-only actor'],$creator);
 $creator->assertCreatedInOwned($db,$p,$a,12,'company_invoice_draft',$createdId);
 essentialThrows(fn()=>(new CompanyInvoiceDrafts($db,$p))->assertCreatedInOwned($db,$p,$a,12,'company_invoice_draft',$createdId),'company_invoice_draft_creation_proof_required','different draft writer cannot impersonate creator');
};
$id=$creator->create($a,12,$draftInput,$creationKey,$callback,$fields->tableNames());
essentialCheck($id===$createdId&&$fields->values($a,12,'items',NativeCompanyResourceId::encode('company_invoice_draft_line',$createdLineId))[$field]==='Created by actual create-only actor','actual draft creation callback stores metadata without edit grant');
essentialThrows(fn()=>$creator->assertCreatedInOwned($db,$p,$a,12,'company_invoice_draft',$id),'company_invoice_draft_creation_proof_required','draft proof expires outside compositor');
essentialThrows(fn()=>$creator->create($a,12,$draftInput,$creationKey,$callback,$fields->tableNames()),'company_invoice_draft_creation_proof_required','replay cannot manufacture new creation authority');
essentialCheck($creator->create($a,12,$draftInput,$creationKey)===$id,'normal durable draft replay remains intact');
echo 'Private draft create-only metadata proof: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
