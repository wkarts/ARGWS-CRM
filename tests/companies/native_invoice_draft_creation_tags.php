<?php
require __DIR__.'/native_invoice_draft_creation_fields.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
require_once APPPATH.'services/NativeCompanyResourceTags.php';
$tags=new NativeCompanyResourceTags($db,$p);$creator=new CompanyInvoiceDrafts($db,$p);$key=hash('sha256','draft-create-tags-proof');$taggedId=0;
$guard=function()use($db,$p,$a,$key,$tags,$creator,&$taggedId){$row=$db->query('SELECT id FROM '.$p.'company_invoice_drafts WHERE issuer_id=? AND origin_key=?',[$a,$key])->row_array();if(!$row)return;$taggedId=(int)$row['id'];$result=$tags->createDraftTagsInOwnedTransaction($a,12,$taggedId,['Synthetic create-only tag'],$creator);essentialCheck(array_column($result['tags'],'name')===['Synthetic create-only tag'],'real tag writer returns original native tag shape under private draft proof');};
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();$id=$creator->create($a,12,$draftInput,$key,$guard,NativeCompanyResourceTags::tableNames());
$canonical=NativeCompanyResourceId::encode('company_invoice_draft',$id);essentialCheck($id===$taggedId&&array_column($tags->read($a,12,'invoice',$canonical)['tags'],'name')===['Synthetic create-only tag'],'create-only draft retains own persisted tags');
essentialThrows(fn()=>$tags->createDraftTagsInOwnedTransaction($a,12,$id,['Outside callback'],$creator),'company_invoice_draft_creation_proof_required','expired creator cannot grant arbitrary tag edit');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'private tag composition never allocates invoice or financial state');
echo 'Private draft create-only tag composition: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
