<?php
require __DIR__.'/banco_brasil_local_noncash_faults_v3.php';
// The synthetic adapter retains every query and JSON parameter; release that diagnostic buffer between independent cases.
$db->queries=[];$fundsMatrix=[];
foreach(['manual_receipt','credit_apply','credit_unapply','manual_reverse']as$kind){
 $extra=[];if($kind==='credit_apply')$extra=['credit_id'=>$realCredit];elseif($kind==='credit_unapply'){[$parent,$source]=v3Funds('fault-unapply-parent','credit_apply','1.00',['credit_id'=>$realCredit]);$writer->recordCompanyRestoredLocalFunds($a,7,$source);$extra=['credit_id'=>$realCredit,'parent_key'=>$parent];}elseif($kind==='manual_reverse'){[$parent,$source]=v3Funds('fault-reverse-parent','manual_receipt','1.00');$payment=$writer->recordCompanyRestoredLocalFunds($a,7,$source);$extra=['payment_id'=>$payment];}
 [$key,$ignored,$review]=v3Funds('last-audit-'.$kind,$kind,'0.25',$extra);$id=$v3['invoice'];$journalKey=hash('sha256',($kind==='manual_receipt'?'company-journal:receipt-recorded:':'company-journal:credit-entry:').$key);
 $mutations=[
 'nominal'=>"UPDATE {$p}invoices SET total=total+0.01 WHERE id=$id",
 'status'=>"UPDATE {$p}invoices SET status=5 WHERE id=$id",
 'native_note'=>"UPDATE {$p}invoices SET clientnote=CONCAT(clientnote,' funds-late') WHERE id=$id",
 'actor_active'=>"UPDATE {$p}staff SET active=0 WHERE staffid=7",
 'issuer_active'=>"UPDATE {$p}issuers_registry SET active=0 WHERE id=$a",
 'old_declaration'=>"UPDATE {$p}company_local_noncash_declarations SET payload_json=CONCAT(payload_json,' ') WHERE invoice_id=$id",
 'old_restoration'=>"UPDATE {$p}company_local_noncash_restorations SET payload_json=CONCAT(payload_json,' ') WHERE invoice_id=$id",
 'native_projection'=>in_array($kind,['manual_receipt','manual_reverse'],true)?"UPDATE {$p}invoicepaymentrecords SET amount=amount+0.01 WHERE invoiceid=$id":"UPDATE {$p}credits SET amount=amount+0.01 WHERE invoice_id=$id",
 'new_proof'=>"UPDATE {$p}company_local_noncash_settlements SET payload_json=CONCAT(payload_json,' ') WHERE request_key='$key'",
 'current_query'=>"UPDATE {$p}bb_api_operations SET response_hash=REPEAT('f',64) WHERE id=".$v3['query'],
 'journal'=>"UPDATE {$p}company_financial_events SET amount=amount+0.01 WHERE evidence_key='$journalKey'",
 ];
 foreach($mutations as$label=>$sql){$before=v3CompleteState();$db->trans_begin();$db->query($sql);$changed=v3CompleteState()!==$before;$db->trans_rollback();essentialCheck($changed||in_array($label,['new_proof','journal'],true),$kind.' '.$label.' actual target mutation is valid and changes intended existing image');$trigger=$p.'v3_funds_last';$db->query("CREATE TRIGGER $trigger AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_local_independent_funds_recorded' AND NEW.issuer_id=$a THEN $sql; END IF; END");$rejected=false;try{$writer->recordCompanyRestoredLocalFunds($a,7,new \BancoBrasil\RestoredLocalNoncashFunds($id,$v3['origin'],$v3['query'],$key,$kind,$review));}catch(Throwable$e){$rejected=true;$code=$e->getMessage();if(!$e instanceof DomainException&&!preg_match('/^issuer_[a-z_]+$/D',$code))throw$e;}essentialCheck($rejected,$kind.' '.$label.' mutation after LAST own proof audit cannot commit');essentialCheck(v3CompleteState()===$before,$kind.' '.$label.' entire native/local/ACL/source/journal/audit rolls back');$db->query('DROP TRIGGER '.$trigger);$fundsMatrix[]=['kind'=>$kind,'fault'=>$label,'rejected'=>$code,'rollback'=>true];$db->queries=[];}
 $writer->recordCompanyRestoredLocalFunds($a,7,new \BancoBrasil\RestoredLocalNoncashFunds($id,$v3['origin'],$v3['query'],$key,$kind,$review));essentialCheck(true,$kind.' same key succeeds after eleven final-audit faults removed');
}
essentialCheck(count($fundsMatrix)===44,'44 additional independent-funds final-audit faults completed');if($file=getenv('NONCASH_V3_FUNDS_FAULT_RESULTS'))file_put_contents($file,json_encode($fundsMatrix,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'NEW Noncash V3 44 additional native-funds final-audit faults: '.$GLOBALS['essentialChecks'].' cumulative checks.'.PHP_EOL;
