<?php
/** New V3 regressions: an AFTER audit trigger must never turn an unconfirmed frame into success. */
require (getenv('SALES_BOUNDARY_TEST_ROOT')?:__DIR__).'/sales_mutations_setup.php';
$mk=static fn(string$v):string=>hash('sha256','sales-v3-boundary:'.$v);
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12',[$a]);
foreach([['proposals','view'],['proposals','edit'],['proposals','create'],['proposals','delete'],['customers','view'],['invoices','create']]as[$feature,$cap])if(!(int)$db->query('SELECT COUNT(*) n FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature=? AND capability=?',[$a,$feature,$cap])->row()->n)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$a,$feature,$cap]);
$tables=['options','company_sales_documents','company_sales_revisions','company_sales_copy_requests','issuers_sequences','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','clients','issuers_clients','issuers_client_links','currencies','company_currency_units','issuers_audit','invoicepaymentrecords','credits','company_payment_receipts'];
$snapshot=static function()use($db,$p,$tables):array{$out=[];foreach($tables as$t){$rows=$db->query('SELECT * FROM '.$p.$t)->result_array();usort($rows,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$out[$t]=$rows;}return$out;};
$cases=[
 ['create','company_sales_created','number'],['create','company_sales_creation_recorded','creator'],['create','company_sales_creation_recorded','sequence'],['create','company_sales_creation_recorded','context'],
 ['copy','company_sales_copied','number'],['copy','company_sales_copied','creator'],['copy','company_sales_copied','source_state'],['copy','company_sales_copied','source_reference'],['copy','company_sales_copied','source_number'],['copy','company_sales_copied','sequence'],['copy','company_sales_copied','context'],['copy','company_sales_copied','client'],['copy','company_sales_copied','view'],['copy','company_sales_copied','customers'],['copy','company_sales_copied','receipt_source'],['copy','company_sales_copied','receipt_result'],['copy','company_sales_copied','receipt_reason'],['copy','company_sales_copied','currency'],
 ['edit','company_sales_revised','source_state'],['edit','company_sales_revised','creator'],['edit','company_sales_revised','sequence'],['edit','company_sales_revised','context'],['edit','company_sales_revised','client'],['edit','company_sales_revised','view'],['edit','company_sales_revised','customers'],['edit','company_sales_revised','receipt_reason'],
 ['decide','company_sales_accepted','source_state'],['decide','company_sales_accepted','sequence'],['decide','company_sales_accepted','context'],['decide','company_sales_accepted','client'],['decide','company_sales_accepted','view'],
 ['claim','company_sales_conversion_claimed','source_state'],['claim','company_sales_conversion_claimed','source_reference'],['claim','company_sales_conversion_claimed','context'],['claim','company_sales_conversion_claimed','sequence'],['claim','company_sales_conversion_claimed','view'],
 ['convert','company_sales_converted','invoice_link'],['convert','company_sales_converted','source_number'],['convert','company_sales_converted','sequence'],['convert','company_sales_converted','context'],['convert','company_sales_converted','client'],['convert','company_sales_converted','view'],['convert','company_sales_converted','invoice_total'],['convert','company_sales_converted','invoice_receipt'],['convert','company_sales_converted','invoice_snapshot'],['convert','company_sales_converted','invoice_item'],['convert','company_sales_converted','invoice_tax'],['convert','company_sales_converted','invoice_journal'],
 ['pdf','company_sales_pdf_downloaded','source_state'],['pdf','company_sales_pdf_downloaded','sequence'],['pdf','company_sales_pdf_downloaded','context'],['pdf','company_sales_pdf_downloaded','view'],['decide','company_sales_accepted','global_option'],['pdf','company_sales_pdf_downloaded','global_option'],
];
foreach($cases as$index=>[$action,$event,$fault]){
 $owner=$a;$actor=12;$client=$ca;$payload=array_replace($salesInput,['client_id'=>$client]);$source=$sales->create($owner,7,'proposals',$payload,$mk('source-'.$index));$raw=$db->query('SELECT * FROM '.$p.'company_sales_documents WHERE id=?',[$source])->row_array();
 if(in_array($action,['claim','convert'],true)){$sales->decide($owner,$actor,$source,'accepted');$review=$sales->document($owner,$actor,$source);}elseif(in_array($action,['edit','copy'],true))$review=$sales->review($owner,$actor,$source,$action);else$review=$sales->document($owner,$actor,$source);
 $sql=match($fault){
  'number'=>"UPDATE {$p}company_sales_documents SET number=number+1000 WHERE id=NEW.subject_id",
  'creator'=>"UPDATE {$p}company_sales_documents SET actor_id=".($action==='edit'?12:7)." WHERE id=NEW.subject_id",
  'source_state'=>"UPDATE {$p}company_sales_documents SET state='cancelled' WHERE id={$source}",
  'source_reference'=>"UPDATE {$p}company_sales_documents SET client_id={$cb} WHERE id={$source}",
  'source_number'=>"UPDATE {$p}company_sales_documents SET number=number+1000 WHERE id={$source}",
  'sequence'=>"UPDATE {$p}issuers_sequences SET next_number=next_number+100 WHERE issuer_id={$owner} AND series='PROP'",
  'context'=>"UPDATE {$p}issuers_registry SET legal_name='Final audit changed context' WHERE id={$owner}",
  'client'=>"UPDATE {$p}clients SET active=0 WHERE userid={$client}",
  'view'=>"DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id={$owner} AND staff_id={$actor} AND feature='proposals' AND capability='view'",
  'customers'=>"DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id={$owner} AND staff_id={$actor} AND feature='customers' AND capability='view'",
  'global_option'=>"UPDATE {$p}options SET value='Changed during final audit' WHERE name='companyname'",
  'currency'=>"UPDATE {$p}currencies SET name='USD' WHERE id=1",
  'receipt_source'=>"UPDATE {$p}company_sales_copy_requests SET source_document_id=NEW.subject_id WHERE result_document_id=NEW.subject_id",
  'receipt_result'=>"UPDATE {$p}company_sales_copy_requests SET result_document_id={$source} WHERE result_document_id=NEW.subject_id",
  'receipt_reason'=>$action==='edit'?"UPDATE {$p}company_sales_revisions SET reason='Final audit changed reason' WHERE document_id=NEW.subject_id":"UPDATE {$p}company_sales_copy_requests SET review_json='{}' WHERE result_document_id=NEW.subject_id",
  'invoice_link'=>"UPDATE {$p}company_sales_documents SET invoice_id=42 WHERE id=NEW.subject_id",
  'invoice_total'=>"UPDATE {$p}invoices SET total=total+1 WHERE id=(SELECT invoice_id FROM {$p}company_sales_documents WHERE id=NEW.subject_id)",
  'invoice_receipt'=>"UPDATE {$p}company_invoice_requests SET input_hash=REPEAT('f',64) WHERE invoice_id=(SELECT invoice_id FROM {$p}company_sales_documents WHERE id=NEW.subject_id)",
  'invoice_item'=>"UPDATE {$p}itemable SET rate=rate+1 WHERE rel_type='invoice' AND rel_id=(SELECT invoice_id FROM {$p}company_sales_documents WHERE id=NEW.subject_id)",
  'invoice_tax'=>"UPDATE {$p}item_tax SET taxrate=taxrate+1 WHERE rel_type='invoice' AND rel_id=(SELECT invoice_id FROM {$p}company_sales_documents WHERE id=NEW.subject_id)",
  'invoice_journal'=>"UPDATE {$p}company_financial_events SET amount=amount+1 WHERE issuer_id={$owner} AND source_type='invoice' AND source_id=(SELECT invoice_id FROM {$p}company_sales_documents WHERE id=NEW.subject_id)",
  'invoice_snapshot'=>"UPDATE {$p}issuers_invoice_documents SET legal_identity_json='{}' WHERE invoice_id=(SELECT invoice_id FROM {$p}company_sales_documents WHERE id=NEW.subject_id)",
 };
 if($db->driver()==='sqlite')$sql=str_replace("REPEAT('f',64)","'".str_repeat('f',64)."'",$sql);
 $before=$snapshot();$invoiceBefore=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();$key=$mk('operation-'.$index);$trigger=$p.'sales_final_boundary';$db->query($db->driver()==='mysql'?"CREATE TRIGGER {$trigger} AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='{$event}' THEN {$sql}; END IF; END":"CREATE TRIGGER {$trigger} AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='{$event}' BEGIN {$sql}; END");
 $error=null;try{try{match($action){'create'=>$sales->create($owner,$actor,'proposals',$payload,$key),'copy'=>$sales->copy($owner,$actor,$source,$review['payload'],['source_revision'=>$review['source_revision'],'destination_revision'=>$review['destination_revision'],'reason'=>'Final boundary copy','copy_confirmed'=>'1'],$key),'edit'=>$sales->edit($owner,$actor,$source,array_replace($review['payload'],['note'=>'Final boundary revision']),['source_revision'=>$review['source_revision'],'reason'=>'Final boundary edit','edit_confirmed'=>'1'],$key),'decide'=>$sales->decide($owner,$actor,$source,'accepted',$review['revision_token']),'claim','convert'=>$sales->convert($owner,$actor,$source,$review['revision_token']),'pdf'=>$sales->confirmPdf($owner,$actor,$source,$review['revision_token'])};}catch(Throwable$e){$error=$e->getMessage();}}finally{$db->query('DROP TRIGGER '.$trigger);}
 echo 'BOUNDARY '.$action.'/'.$fault.' '.($error??'UNEXPECTED_SUCCESS').PHP_EOL;
 essentialCheck(in_array($error,['issuer_sales_write_unconfirmed','issuer_sales_mutation_unconfirmed','issuer_sales_mutation_corrupt','company_sales_denied','company_sales_origin_changed','company_sales_recipient_denied','company_currency_catalog_changed'],true),$action.'/'.$fault.' rejects the final audit mutation');
 if($action!=='convert'){essentialCheck($snapshot()===$before,$action.'/'.$fault.' rolls back every source, ledger, sequence, reference, grant and audit row');essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$invoiceBefore,$action.'/'.$fault.' has no financial document effects');}
 else{
  $after=$snapshot();$saved=$sales->document($owner,$actor,$source);$receipt=$db->query('SELECT * FROM '.$p.'company_invoice_requests WHERE request_key=?', [hash('sha256','company-sales-invoice:'.$owner.':'.$source)])->row_array();$invoice=(int)$receipt['invoice_id'];
  essentialCheck($saved['state']==='converting'&&(int)$saved['invoice_id']===0&&$invoice>0&&$invoice!==42,'final link failure preserves resumable claim and separately committed canonical receipt');
  foreach(['company_sales_revisions','company_sales_copy_requests','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','clients','issuers_clients','issuers_client_links','currencies','company_currency_units','invoicepaymentrecords','credits','company_payment_receipts']as$t)essentialCheck($after[$t]===$before[$t],$action.'/'.$fault.' preserves '.$t);
  essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'final conversion fault cannot adopt or alter native NULL invoice');
  $canonical=(new CompanyInvoices($db,$p))->document($owner,7,$invoice)->data();essentialCheck($canonical['totals']['total']==='105.53','canonical financial result remains validated after final link rejection');$number=$db->query('SELECT * FROM '.$p.'issuers_sequences ORDER BY issuer_id,series')->result_array();$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
  essentialCheck($sales->convert($owner,$actor,$source,$review['revision_token'])===$invoice,'same accepted revision resumes exact canonical invoice after removing fault');essentialCheck($sales->convert($owner,$actor,$source)===$invoice&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$count&&$db->query('SELECT * FROM '.$p.'issuers_sequences ORDER BY issuer_id,series')->result_array()===$number,'conversion retries never duplicate invoice or sequence');
 }
}
echo 'Sales V3 final boundary: '.count($cases).' real audit-trigger variants; '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().').'.PHP_EOL;
