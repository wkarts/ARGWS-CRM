<?php
require __DIR__.'/native_invoice_form_post.php';
// Exercise the actual official wire and writer, including failure after the money changed.
$writer=new CompanyInvoices($db,$p);$fields=new NativeCompanyCustomFields($db,$p);$tags=new NativeCompanyResourceTags($db,$p);
$invoice=$receipt['result']['invoice_id'];$line=$receipt['result']['line_ids'][0];
$edit=$wire;unset($edit['save_as_draft']);
$edit['_native_invoice_id']=(string)$invoice;$edit['_native_invoice_revision']=$writer->revision($a,7,$invoice);
$edit['_native_invoice_request_key']=hash('sha256','official-issued-edit');$edit['_native_invoice_tags_revision']=$tags->read($a,7,'invoice',$invoice)['revision'];
$edit['merge_current_invoice']=(string)$invoice;$edit['isedit']='';$edit['items']=['1'=>$edit['newitems']['7']+['itemid'=>(string)$line]];unset($edit['newitems']);
$edit['items']['1']['rate']='80';$edit['items']['1']['custom_fields']=['items'=>[$field=>'Preserved line identity']];$edit['subtotal']='80.00';$edit['total']='80.00';$edit['tags']='Edited invoice';
$updated=$form->update($a,7,$invoice,$edit);
essentialCheck($updated['result']['line_ids']===[$line]&&$writer->document($a,7,$invoice)->data()['totals']['total']==='80.00','official edit recalculates real totals without replacing retained item IDs');
essentialCheck($fields->values($a,7,'items',$line)[$field]==='Preserved line identity','item custom field is committed on the actual retained source');
essentialCheck(array_column($tags->read($a,7,'invoice',$invoice)['tags'],'name')===['Edited invoice'],'official edit persists invoice tags under their independent revision');
$tables=['invoices','itemable','item_tax','issuers_audit','company_invoice_native_commands','company_custom_field_values','company_resource_tags'];
$snapshot=function()use($db,$p,$tables){$out=[];foreach($tables as$table)if($db->table_exists($p.$table))$out[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return$out;};
$before=$snapshot();essentialCheck($form->update($a,7,$invoice,$edit)===$updated&&$snapshot()===$before,'same original edit POST is a durable no-write replay');
$bad=$edit;$bad['tags']='Conflicting replay';essentialThrows(fn()=>$form->update($a,7,$invoice,$bad),'native_invoice_command_conflict','divergent metadata cannot reuse the financial request');
$bad=$edit;$bad['_native_invoice_request_key']=hash('sha256','stale-official-edit');$bad['_native_invoice_tags_revision']=$updated['result']['tags_revision'];essentialThrows(fn()=>$form->update($a,7,$invoice,$bad),'company_invoice_stale','current tags do not bypass stale monetary revision');
$retry=$edit;$retry['_native_invoice_request_key']=hash('sha256','official-edit-rollback');$retry['_native_invoice_revision']=$updated['result']['revision'];$retry['_native_invoice_tags_revision']=$updated['result']['tags_revision'];$retry['items']['1']['rate']='70';$retry['subtotal']=$retry['total']='70.00';
$bad=$retry;$bad['items']['1']['itemid']='999999';essentialThrows(fn()=>$form->update($a,7,$invoice,$bad),'native_invoice_line_mapping_invalid','foreign or missing item identity cannot be overwritten');
essentialCheck($snapshot()===$before,'stale and malformed edits preserve financial, metadata and receipt tables');
$db->failOn='UPDATE '.$p.NativeCompanyInvoiceCommandSchema::TABLE;
essentialThrows(fn()=>$form->update($a,7,$invoice,$retry),'issuer_storage_failed','late command acknowledgement failure rejects the entire official edit');$db->failOn=null;
essentialCheck($snapshot()===$before,'late failure rolls back money, retained item data, tags and audit together');
$retried=$form->update($a,7,$invoice,$retry);essentialCheck($writer->document($a,7,$invoice)->data()['totals']['total']==='70.00'&&$retried['result']['line_ids']===[$line],'the same failed request can retry safely with original item identity');
essentialThrows(fn()=>$form->update($b,7,$invoice,$retry),'company_invoice_access_denied','foreign company cannot update the exact known invoice');
essentialThrows(fn()=>$form->update($a,12,$invoice,$retry),'native_invoice_editor_denied','create-only actor cannot edit an issued invoice');
essentialCheck($form->update($a,7,$invoice,$retry)===$retried,'post-denial replay retains the confirmed receipt');
echo 'Original issued invoice edit, metadata, ACL, stale/replay and rollback: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";

require_once APPPATH.'services/NativeCompanyInvoiceMergeCandidates.php';$candidates=(new NativeCompanyInvoiceMergeCandidates($db,$p))->forCustomer($a,7,(int)$record['clientid'],(string)$invoice);essentialCheck(!in_array((string)$invoice,array_map(static fn($r)=>(string)$r->id,$candidates['items']),true),'issued current invoice is excluded despite native numeric/string representation');
