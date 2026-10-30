<?php
/** Each fault runs at the LAST source audit, with a nontransactional witness and exact rollback. */
require __DIR__.'/banco_brasil_tariff_mysql.php';
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,15,'banco_brasil','reconcile_tariff')",[$a]);
$witness=$p.'tariff_witness';$db->query("CREATE TABLE `$witness`(case_name varchar(64) NOT NULL,affected_rows int NOT NULL) ENGINE=MyISAM");
$faults=[
 'invoice_number'=>fn($i,$e)=>"UPDATE {$p}invoices SET number=number+1 WHERE id=$i",
 'invoice_subtotal'=>fn($i,$e)=>"UPDATE {$p}invoices SET subtotal=subtotal+0.01 WHERE id=$i",
 'native_item'=>fn($i,$e)=>"UPDATE {$p}itemable SET rate=rate+0.01 WHERE rel_id=$i AND rel_type='invoice'",
 'native_tax'=>fn($i,$e)=>"UPDATE {$p}item_tax SET taxrate=taxrate+0.01 WHERE rel_id=$i AND rel_type='invoice'",
 'issuer_counter'=>fn($i,$e)=>"UPDATE {$p}issuers_sequences SET next_number=next_number+1 WHERE issuer_id=$a AND series='INV'",
 'client_owner'=>fn($i,$e)=>"UPDATE {$p}issuers_clients SET owner_issuer_id=$b WHERE client_id=$ca",
 'registry_identity'=>fn($i,$e)=>"UPDATE {$p}issuers_registry SET legal_name='Changed after final source audit' WHERE id=$a",
 'current_actor'=>fn($i,$e)=>"UPDATE {$p}staff SET active=0 WHERE staffid=15",
 'current_grant'=>fn($i,$e)=>"DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=$a AND staff_id=15 AND feature='banco_brasil' AND capability='reconcile_tariff'",
 'connection_hash'=>fn($i,$e)=>"UPDATE {$p}bb_connections SET configuration_hash=REPEAT('0',64) WHERE id=$connection",
 'source_observation'=>fn($i,$e)=>"UPDATE {$p}bb_return_events SET observation_json=JSON_SET(observation_json,'$.fees','2.54','$.net_amount','102.99'),payload_hash=SHA2(observation_json,256) WHERE id=$e",
 'source_provenance'=>fn($i,$e)=>"UPDATE {$p}bb_return_files SET provenance_json=JSON_SET(provenance_json,'$.unexpected_after_audit','1') WHERE id=(SELECT return_file_id FROM {$p}bb_return_file_events WHERE return_event_id=$e LIMIT 1)",
 'source_receipt_time'=>fn($i,$e)=>"UPDATE {$p}bb_event_receipts SET created_at='2001-01-01 00:00:00' WHERE event_id=$e",
 'source_review_reason'=>fn($i,$e)=>"UPDATE {$p}bb_event_reviews SET reason='Changed after last audit' WHERE event_id=$e AND decision='tariff_credit_confirmed'",
 'native_payment_note'=>fn($i,$e)=>"UPDATE {$p}invoicepaymentrecords SET note='Changed after last audit' WHERE invoiceid=$i",
 'component_header'=>fn($i,$e)=>"UPDATE {$p}company_bank_receipt_components SET created_at='2001-01-01 00:00:00' WHERE invoice_id=$i",
];
$batchId=static fn($e)=>(int)$db->query("SELECT x.batch_id FROM {$p}bb_batch_origins x JOIN {$p}bb_event_links l ON l.origin_id=x.origin_id WHERE l.event_id=?",[$e])->row()->batch_id;
$originId=static fn($e)=>(int)$db->query("SELECT origin_id FROM {$p}bb_event_links WHERE event_id=?",[$e])->row()->origin_id;
$contractId=static fn($e)=>(int)$db->query("SELECT s.contract_id FROM {$p}bb_origin_snapshots s JOIN {$p}bb_event_links l ON l.origin_id=s.origin_id WHERE l.event_id=?",[$e])->row()->contract_id;
$faults+=[
 'batch_content_hash'=>fn($i,$e)=>"UPDATE {$p}bb_batches SET content_hash=REPEAT('0',64) WHERE id=".$batchId($e),
 'batch_content'=>fn($i,$e)=>"UPDATE {$p}bb_batches SET content=CONCAT('X',SUBSTRING(content,2)) WHERE id=".$batchId($e),
 'batch_content_resealed'=>fn($i,$e)=>"UPDATE {$p}bb_batches SET content=CONCAT('X',SUBSTRING(content,2)),content_hash=SHA2(content,256) WHERE id=".$batchId($e),
 'batch_header'=>fn($i,$e)=>"UPDATE {$p}bb_batches SET created_at='2001-01-01 00:00:00' WHERE id=".$batchId($e),
 'manual_submission_deleted'=>fn($i,$e)=>"DELETE FROM {$p}bb_batch_actions WHERE action='manual_submission_reported' AND batch_id=".$batchId($e),
 'manual_submission_reference'=>fn($i,$e)=>"UPDATE {$p}bb_batch_actions SET reference='Changed after last audit' WHERE action='manual_submission_reported' AND batch_id=".$batchId($e),
 'batch_origin_rebound'=>fn($i,$e)=>"UPDATE {$p}bb_batch_origins SET batch_id=(SELECT MIN(id) FROM {$p}bb_batches) WHERE origin_id=".$originId($e),
 'snapshot_resealed'=>fn($i,$e)=>"UPDATE {$p}bb_origin_snapshots SET snapshot_json=JSON_SET(snapshot_json,'$.unexpected_after_audit','1') WHERE origin_id=".$originId($e)."; UPDATE {$p}bb_origins SET payload_hash=(SELECT SHA2(snapshot_json,256) FROM {$p}bb_origin_snapshots WHERE origin_id=".$originId($e).") WHERE id=".$originId($e),
 'contract_header'=>fn($i,$e)=>"UPDATE {$p}bb_contract_declarations SET created_at='2001-01-01 00:00:00' WHERE id=".$contractId($e),
 'contract_resealed'=>fn($i,$e)=>"UPDATE {$p}bb_contract_declarations SET declaration_json=JSON_SET(declaration_json,'$.reference','Changed after last audit'),reference='Changed after last audit',declaration_hash=SHA2(declaration_json,256) WHERE id=".$contractId($e),
 'event_link_header'=>fn($i,$e)=>"UPDATE {$p}bb_event_links SET created_at='2001-01-01 00:00:00' WHERE event_id=$e",
 'file_link_header'=>fn($i,$e)=>"UPDATE {$p}bb_return_file_events SET created_at='2001-01-01 00:00:00' WHERE return_event_id=$e",
];
foreach(['company_provider_payment_recorded','company_bank_components_recorded','company_financial_event_recorded']as$coreEvent){
 foreach(['deleted'=>null,'actor_id'=>'7','created_at'=>"'2001-01-01 00:00:00'",'event'=>"'changed_after_source_audit'",'issuer_id'=>(string)$b,'subject_id'=>'0','metadata_json'=>"'{\"changed_after_source_audit\":true}'"]as$field=>$value){
  $faults['core_'.$coreEvent.'_'.$field]=static function($i,$e)use($p,$a,$coreEvent,$field,$value):string{
   $where="issuer_id=$a AND event='$coreEvent' AND ".($coreEvent==='company_financial_event_recorded'?"JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.source_type'))='payment' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.source_id'))=(SELECT id FROM {$p}invoicepaymentrecords WHERE invoiceid=$i)":"subject_id=$i");
   return$field==='deleted'?"DELETE FROM {$p}issuers_audit WHERE $where":"UPDATE {$p}issuers_audit SET `$field`=$value WHERE $where";
  };
 }
}
$snapshot=static function()use($db,$p):array{$result=[];foreach(['invoices','itemable','item_tax','issuers_invoice_documents','issuers_sequences','clients','issuers_clients','issuers_client_links','issuers_registry','staff','issuers_resource_permissions','invoicepaymentrecords','company_payment_receipts','company_bank_receipt_components','company_financial_events','issuers_audit','bb_connections','bb_return_events','bb_return_files','bb_event_receipts','bb_event_reviews','bb_audit','bb_origins','bb_origin_snapshots','bb_batches','bb_batch_origins','bb_contract_declarations','bb_batch_actions','bb_event_links','bb_return_file_events']as$t){$rows=$db->query('SELECT * FROM '.$p.$t)->result_array();$encoded=array_map(static fn($v)=>json_encode($v,JSON_THROW_ON_ERROR),$rows);sort($encoded,SORT_STRING);$result[$t]=hash('sha256',json_encode($encoded,JSON_THROW_ON_ERROR));}return$result;};
$resealWitness=static function(string$name,int$event)use($p,$batchId,$originId,$contractId):string{
 $expression=match($name){'batch_content_resealed'=>"(SELECT IF(content_hash=SHA2(content,256),1,0) FROM {$p}bb_batches WHERE id=".$batchId($event).")",'snapshot_resealed'=>"(SELECT IF(o.payload_hash=SHA2(s.snapshot_json,256),1,0) FROM {$p}bb_origins o JOIN {$p}bb_origin_snapshots s ON s.origin_id=o.id WHERE o.id=".$originId($event).")",'contract_resealed'=>"(SELECT IF(declaration_hash=SHA2(declaration_json,256),1,0) FROM {$p}bb_contract_declarations WHERE id=".$contractId($event).")",default=>null};
 return$expression===null?'':"INSERT INTO `{$p}tariff_witness` VALUES('$name-seal',$expression);";
};
$results=[];
foreach($faults as$name=>$mutation){$invoice=freshTariffOrigin('last-audit-'.$name);$event=importTariffCredit();$reconcile->link($a,7,$event);$before=$snapshot();$trigger=$p.'tariff_fault';$sql=$mutation($invoice,$event);$seal=$resealWitness($name,$event);$db->query("CREATE TRIGGER `$trigger` AFTER INSERT ON {$p}bb_audit FOR EACH ROW BEGIN IF NEW.action='assisted_tariff_credit_applied' THEN $sql; INSERT INTO `$witness` VALUES('$name',ROW_COUNT()); $seal END IF; END");
 try{$rejected=false;$error='';try{$apply($event,$proof,15);}catch(Throwable$e){$error=$e->getMessage();$rejected=true;}essentialCheck($rejected&&$error!=='issuer_storage_failed','Last-audit '.$name.' rejects the actual mutation rather than a fixture SQL failure');$hits=$db->query("SELECT * FROM `$witness` WHERE case_name=?",[$name])->result_array();essentialCheck(count($hits)===1&&(int)$hits[0]['affected_rows']>0,'Last-audit '.$name.' witness proves a real changed row');if($seal!==''){$sealRows=$db->query("SELECT * FROM `$witness` WHERE case_name=?",[$name.'-seal'])->result_array();essentialCheck(count($sealRows)===1&&(int)$sealRows[0]['affected_rows']===1,'Last-audit '.$name.' witness proves the altered bytes were internally resealed before rejection');}essentialCheck($snapshot()===$before,'Last-audit '.$name.' rolls back every captured native/source/journal byte');$results[$name]=['rejected'=>$rejected,'error'=>$error,'witness_hits'=>count($hits),'affected_rows'=>(int)$hits[0]['affected_rows'],'exact_rollback'=>true,'self_hash_verified'=>$seal!==''?true:null];}
 finally{$db->query("DROP TRIGGER IF EXISTS `$trigger`");}
 $retry=$apply($event,$proof,15);essentialCheck($retry>0&&$s->position($a,7,$invoice)['receivable']==='0.00','Identical '.$name.' form and evidence work after removing only the fault');
}
$auditFaults=['created_at'=>"'2001-01-01 00:00:00'",'connection_id'=>(string)$otherConnection,'actor_id'=>'7','resource_type'=>"'other_resource'",'resource_id'=>'0','action'=>"'different_tariff_action'",'issuer_id'=>(string)$b];
foreach($auditFaults as$field=>$value){$name='audit_'.$field;$invoice=freshTariffOrigin('audit-image-'.$field);$event=importTariffCredit();$reconcile->link($a,7,$event);$before=$snapshot();$trigger=$p.'tariff_audit_fault';$db->query("CREATE TRIGGER `$trigger` BEFORE INSERT ON {$p}bb_audit FOR EACH ROW BEGIN IF NEW.action='assisted_tariff_credit_applied' THEN SET NEW.$field=$value; INSERT INTO `$witness` VALUES('$name',IF(NEW.$field=$value,1,0)); END IF; END");
 try{$rejected=false;$error='';try{$apply($event,$proof,15);}catch(Throwable$e){$rejected=true;$error=$e->getMessage();}essentialCheck($rejected&&$error!=='issuer_storage_failed','Generated audit '.$field.' is checked against the intended image');$hits=$db->query("SELECT * FROM `$witness` WHERE case_name=?",[$name])->result_array();essentialCheck(count($hits)===1&&(int)$hits[0]['affected_rows']===1,'BEFORE audit '.$field.' witness confirms changed NEW field');essentialCheck($snapshot()===$before,'Changed audit '.$field.' rolls back all native/source/journal rows');$results[$name]=['rejected'=>$rejected,'error'=>$error,'witness_hits'=>count($hits),'affected_rows'=>1,'exact_rollback'=>true];}
 finally{$db->query("DROP TRIGGER IF EXISTS `$trigger`");}
 $retry=$apply($event,$proof,15);essentialCheck($retry>0&&$s->position($a,7,$invoice)['receivable']==='0.00','Same form succeeds after removing the audit-image fault '.$field);
}
if(($out=getenv('BB_TARIFF_BOUNDARY_RESULTS'))!==false)file_put_contents($out,json_encode($results,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
echo 'BB240 tariff final audit integrity: '.count($results).' real faults with witness/byte rollback/retry; '.$GLOBALS['essentialChecks'].' cumulative checks.'.PHP_EOL;
