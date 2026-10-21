<?php
require __DIR__.'/banco_brasil_local_noncash_regressions_v3.php';
function v3CompleteState():array{global$db,$p;$state=[];foreach($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ? ORDER BY TABLE_NAME',[$p.'%'])->result_array()as$row){$table=$row['TABLE_NAME'];$state[$table]=$db->query('SELECT * FROM `'.$table.'`')->result_array();}return$state;}
$matrix=[];
foreach(['declaration','restoration']as$operation){
 $f=v3Observed('faults-'.$operation);$id=$f['invoice'];$declaration=null;if($operation==='restoration')$declaration=$writer->declareCompanyLocalNoncash($a,7,$localSource($f));
 $mutations=[
 'nominal'=>"UPDATE {$p}invoices SET total=total+0.01 WHERE id=$id",
 'number'=>"UPDATE {$p}invoices SET number=number+1 WHERE id=$id",
 'status'=>"UPDATE {$p}invoices SET status=5 WHERE id=$id",
 'note'=>"UPDATE {$p}invoices SET clientnote=CONCAT(clientnote,' late') WHERE id=$id",
 'date'=>"UPDATE {$p}invoices SET date='2026-10-18' WHERE id=$id",
 'due_date'=>"UPDATE {$p}invoices SET duedate='2026-12-31' WHERE id=$id",
 'discount'=>"UPDATE {$p}invoices SET discount_total=discount_total+0.01 WHERE id=$id",
 'legal_snapshot'=>"UPDATE {$p}issuers_invoice_documents SET legal_identity_json='{}' WHERE invoice_id=$id",
 'recipient_snapshot'=>"UPDATE {$p}issuers_invoice_documents SET recipient_identity_json='{}' WHERE invoice_id=$id",
 'formatted_number'=>"UPDATE {$p}issuers_invoice_documents SET formatted_number=CONCAT(formatted_number,'x') WHERE invoice_id=$id",
 'line_description'=>"UPDATE {$p}itemable SET description=CONCAT(description,' late') WHERE rel_type='invoice' AND rel_id=$id",
 'line_rate'=>"UPDATE {$p}itemable SET rate=rate+0.01 WHERE rel_type='invoice' AND rel_id=$id",
 'tax_rate'=>"UPDATE {$p}item_tax SET taxrate=taxrate+0.01 WHERE rel_type='invoice' AND rel_id=$id",
 'actor_active'=>"UPDATE {$p}staff SET active=0 WHERE staffid=7",
 'issuer_active'=>"UPDATE {$p}issuers_registry SET active=0 WHERE id=$a",
 'new_membership'=>"INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES($a,7,1)",
 'current_client'=>"UPDATE {$p}clients SET company=CONCAT(company,' late') WHERE userid=$ca",
 'installation_identity'=>"UPDATE {$p}issuers_installation_identity SET legacy_catalog_hash=REPEAT('f',64) WHERE id=1",
 'sequence'=>"UPDATE {$p}issuers_sequences SET next_number=next_number+1 WHERE issuer_id=$a AND series='INV'",
 'currency'=>"UPDATE {$p}currencies SET symbol=CONCAT(symbol,'x') WHERE id=1",
 'local_evidence'=>"UPDATE {$p}company_local_noncash_declarations SET payload_json=CONCAT(payload_json,' ') WHERE invoice_id=$id",
 'local_actor'=>"UPDATE {$p}company_local_noncash_declarations SET created_by=8 WHERE invoice_id=$id",
 ];
 // Prove every target mutation is executable and affects a stored image before using it as an adversarial last-audit fault.
 foreach($mutations as$label=>$sql){
  $before=v3CompleteState();$db->trans_begin();$db->query($sql);$changed=v3CompleteState()!==$before;$db->trans_rollback();
  // A declaration row is generated inside the operation, so these two mutations have no row before a first declaration.
  essentialCheck($changed||$operation==='declaration'&&in_array($label,['local_evidence','local_actor'],true),$operation.' '.$label.' target SQL is valid and changes its intended image');
  $trigger=$p.'v3_last_audit_fault';$db->query("CREATE TRIGGER $trigger AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_financial_event_recorded' AND NEW.issuer_id=$a THEN $sql; END IF; END");
  $rejected=false;try{if($operation==='declaration')$writer->declareCompanyLocalNoncash($a,7,$localSource($f));else$writer->restoreCompanyLocalNoncash($a,7,$id,$declaration,$restoreInput,$localSource($f));}catch(Throwable$e){$rejected=true;$code=$e->getMessage();if(!$e instanceof DomainException&&!preg_match('/^issuer_[a-z_]+$/D',$code))throw$e;}
  essentialCheck($rejected,$operation.' '.$label.' mutation after LAST journal audit cannot commit');essentialCheck(v3CompleteState()===$before,$operation.' '.$label.' mutation and complete native/local/journal/audit frame roll back');$db->query('DROP TRIGGER '.$trigger);$matrix[]=['operation'=>$operation,'fault'=>$label,'rejected'=>$code,'rollback'=>true];$db->queries=[];
 }
 if($operation==='declaration')$writer->declareCompanyLocalNoncash($a,7,$localSource($f));else$writer->restoreCompanyLocalNoncash($a,7,$id,$declaration,$restoreInput,$localSource($f));essentialCheck($s->position($a,7,$id)['receivable']===($operation==='declaration'?'0.00':'105.53'),$operation.' same key succeeds once all last-audit faults removed');
}
essentialCheck(count($matrix)===44,'44 distinct last-audit rollback faults completed');if($file=getenv('NONCASH_V3_FAULT_RESULTS'))file_put_contents($file,json_encode($matrix,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'NEW Noncash V3 44 exact last-audit faults: '.$GLOBALS['essentialChecks'].' cumulative checks.'.PHP_EOL;
