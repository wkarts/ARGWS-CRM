<?php
require __DIR__.'/native_invoice_draft_creation_tags.php';
$field=NativeCompanyResourceId::encode('company_custom_field',$db->query("SELECT id FROM {$p}company_custom_fields WHERE issuer_id=? AND slug='draft_line_metadata'",[$a])->row_array()['id']);
$writer=new CompanyInvoices($db,$p);$fields=new NativeCompanyCustomFields($db,$p);$tags=new NativeCompanyResourceTags($db,$p);$key=hash('sha256','issued-native-created-metadata');$created=0;$lineId=0;
$guard=function()use($writer,$fields,$tags,$db,$p,$a,$b,$field,$key,&$created,&$lineId){
 $row=$db->query('SELECT invoice_id FROM '.$p.'company_invoice_requests WHERE request_key=?',[$key])->row_array();if(!$row)return;$created=(int)$row['invoice_id'];
 $writer->assertCreatedInOwned($db,$p,$a,12,'invoice',$created);
 $lines=$writer->createdLineIdsInOwned($a,12,$created,$key);$lineId=$lines[0]['itemable_id'];
 $fields->saveCreatedInOwnedTransaction($a,12,'invoice',$created,[],$writer);
 $fields->saveCreatedInOwnedTransaction($a,12,'items',$lineId,[$field=>'Exact issued line metadata'],$writer);
 $tags->createInvoiceTagsInOwnedTransaction($a,12,$created,['Exact issued tag'],$writer);
 essentialThrows(fn()=>(new CompanyInvoices($db,$p))->assertCreatedInOwned($db,$p,$a,12,'invoice',$created),'company_invoice_creation_proof_required','another instance cannot create issued metadata');
 essentialThrows(fn()=>$writer->assertCreatedInOwned($db,$p,$b,12,'invoice',$created),'company_invoice_creation_proof_required','proof rejects other issuer');
 essentialThrows(fn()=>$writer->assertCreatedInOwned($db,$p,$a,7,'invoice',$created),'company_invoice_creation_proof_required','proof rejects other actor');
 essentialThrows(fn()=>$writer->assertCreatedInOwned($db,$p,$a,12,'invoice_item',9999999),'company_invoice_creation_proof_required','proof rejects unrelated item ID');
 essentialThrows(fn()=>$writer->assertCreatedInOwned($db,$p.'foreign_',$a,12,'invoice',$created),'company_invoice_creation_proof_required','proof rejects different prefix');
};
$id=$writer->create($a,12,$draftInput,$key,$guard,array_merge($fields->tableNames(),NativeCompanyResourceTags::tableNames()));
essentialCheck($created===$id&&$fields->values($a,12,'items',$lineId)[$field]==='Exact issued line metadata','create-only issued metadata uses actual itemable identity');
essentialCheck(array_column($tags->read($a,12,'invoice',$id)['tags'],'name')===['Exact issued tag'],'create-only issued tags persist without edit grant');
essentialThrows(fn()=>$writer->assertCreatedInOwned($db,$p,$a,12,'invoice',$id),'company_invoice_creation_proof_required','issued proof expires after compositor');
essentialThrows(fn()=>$writer->create($a,12,$draftInput,$key,$guard,array_merge($fields->tableNames(),NativeCompanyResourceTags::tableNames())),'company_invoice_creation_proof_required','replay does not fabricate create metadata authority');
essentialCheck($writer->create($a,12,$draftInput,$key)===$id,'original writer replay remains valid without metadata mutation');
echo 'Issued invoice private metadata composition: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
