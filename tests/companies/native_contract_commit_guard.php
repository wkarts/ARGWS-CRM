<?php
/** Real storage triggers prove confirmation survives the last durable receipt, before COMMIT. */
require __DIR__.'/native_contract_metadata.php';
$guardStart=$GLOBALS['essentialChecks'];
$guardInput=array_replace($contractInput,['name'=>'Commit guarded contract','body'=>'Public guarded body','due_date'=>null,'native_metadata'=>['version'=>1,'description'=>'Private guarded description']]);
$guardDraft=$contracts->create($a,7,$guardInput,$metadataKey('guard-draft'));
$checkpoint=static function()use($db,$p):array{$rows=[];foreach(['company_contracts',CompanyContractMetadataSchema::TABLE,'company_custom_field_values','company_contract_requests','company_contract_counters','issuers_audit']as$table)$rows[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return$rows;};
$lateFailure=static function(string$operation,string$mutation,callable$write,string$expected,string$label)use($db,$p,$checkpoint):void{
    $trigger=$p.'contract_late_receipt';$before=$checkpoint();
    if($db->driver()==='sqlite')$ddl='CREATE TRIGGER '.$trigger.' AFTER INSERT ON '.$p."company_contract_requests WHEN NEW.operation='$operation' BEGIN $mutation; END";
    else$ddl='CREATE TRIGGER '.$trigger.' AFTER INSERT ON '.$p."company_contract_requests FOR EACH ROW BEGIN IF NEW.operation='$operation' THEN $mutation; END IF; END";
    $db->query($ddl);
    try{essentialThrows($write,$expected,$label);essentialCheck($checkpoint()===$before,$label.' rolls back all parent, metadata, projection, audit and receipt rows');}
    finally{$db->query('DROP TRIGGER IF EXISTS '.$trigger);}
};
$lateFailure('contract_created','UPDATE '.$p.CompanyContractMetadataSchema::TABLE." SET data_json='{}' WHERE issuer_id=NEW.issuer_id AND contract_id=NEW.result_id",fn()=>$contracts->create($a,7,$guardInput,$metadataKey('guard-create')),'native_contract_metadata_unconfirmed','creation rejects metadata altered by the final receipt trigger');
$lateFailure('contract_updated','UPDATE '.$p."company_custom_field_values SET value='late replacement' WHERE issuer_id=NEW.issuer_id AND source_kind='company_contract' AND source_id=NEW.result_id",fn()=>$contracts->update($a,7,$guardDraft,1,$guardInput,$metadataKey('guard-update')),'native_contract_metadata_unconfirmed','draft edit rejects a projection altered after private readback');
$lateFailure('contract_activate','UPDATE '.$p.CompanyContractMetadataSchema::TABLE.' SET frozen=0 WHERE issuer_id=NEW.issuer_id AND contract_id=NEW.result_id',fn()=>$contracts->transition($a,7,$guardDraft,1,'activate',$metadataKey('guard-activate')),'native_contract_metadata_unconfirmed','activation confirms the final frozen companion after receipt insertion');
$lateFailure('contract_copy','UPDATE '.$p."company_contracts SET body='late source replacement' WHERE issuer_id=NEW.issuer_id AND id=".$id,fn()=>$contracts->transition($a,7,$id,3,'copy',$metadataKey('guard-copy')),'issuer_operation_write_unconfirmed','copy confirms its unchanged issued source after the new draft receipt');
$lateFailure('contract_renewed','UPDATE '.$p."company_custom_field_values SET value='late source field replacement' WHERE issuer_id=NEW.issuer_id AND source_kind='company_contract' AND source_id=".$id,fn()=>$contracts->renew($a,7,$id,3,'2029-01-01','',$metadataKey('guard-renew')),'native_contract_metadata_unconfirmed','renewal confirms source private projections as well as its successor');
$saved=$contracts->create($a,7,$guardInput,$metadataKey('guard-create'));
$contracts->update($a,7,$guardDraft,1,$guardInput,$metadataKey('guard-update'));
$contracts->transition($a,7,$guardDraft,2,'activate',$metadataKey('guard-activate-after-retry'));
essentialCheck($saved>0&&$contracts->getForAdmin($a,7,$guardDraft)['status']==='active','failed verification clears its private expectation frame and permits clean retry after trigger removal');
echo 'Native contract final receipt/metadata confirmation: '.($GLOBALS['essentialChecks']-$guardStart).' added / '.$GLOBALS['essentialChecks'].' cumulative PASS ('.$db->driver().').'.PHP_EOL;
