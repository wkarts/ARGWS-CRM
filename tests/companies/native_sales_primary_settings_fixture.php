<?php
// Existing estimate rows and native options belong only to the proven installation company.
if(!$db->table_exists($p.'estimates'))$db->query('CREATE TABLE '.$p.'estimates(id int NOT NULL PRIMARY KEY,number int NOT NULL)');
$db->query('INSERT INTO '.$p.'estimates(id,number) VALUES(?,?)',[701,75]);
foreach(['next_estimate_number'=>'80','estimate_prefix'=>'LEGACY-'] as $name=>$value){
    $db->query('DELETE FROM '.$p.'options WHERE name=?',[$name]);
    $db->query('INSERT INTO '.$p.'options(name,value) VALUES(?,?)',[$name,$value]);
}
$legacyEstimateRows=$db->query('SELECT * FROM '.$p.'estimates ORDER BY id')->result_array();
$ownedSalesCounters=$db->query('SELECT * FROM '.$p.'issuers_sequences ORDER BY issuer_id,series')->result_array();
$secondaryBefore=[$forms->page($a,7,'estimates'),$forms->page($b,7,'estimates')];
$primaryPage=$forms->page($principal,7,'estimates');
essentialCheck($primaryPage['values']['next_estimate_number']==='80'&&$primaryPage['values']['estimate_prefix']==='LEGACY-','principal original form reads historical options and counter without replacing its source');
$primaryValues=$primaryPage['values'];$primaryValues['next_estimate_number']='90';$primaryValues['estimate_prefix']='PRIMARY-';
$primarySaved=$forms->save($principal,7,'estimates',$primaryPage['revision'],$primaryValues);
essentialCheck($primarySaved['values']===$primaryValues,'principal can save the same original estimate settings form');
essentialCheck($db->query('SELECT value FROM '.$p.'options WHERE name=?',['next_estimate_number'])->row()->value==='90','principal retains the original next_estimate_number option');
essentialCheck($db->query('SELECT * FROM '.$p.'issuers_sequences ORDER BY issuer_id,series')->result_array()===$ownedSalesCounters,'principal settings never rewrite owned sales counters');
essentialCheck([$forms->page($a,7,'estimates'),$forms->page($b,7,'estimates')]===$secondaryBefore,'principal settings do not change either secondary company');
essentialCheck($db->query('SELECT * FROM '.$p.'estimates ORDER BY id')->result_array()===$legacyEstimateRows,'historical estimates are never rewritten or renumbered');
essentialThrows(fn()=>$forms->save($principal,7,'estimates',$primaryPage['revision'],$primaryValues),'native_sales_settings_stale','principal rejects an obsolete full-form revision');
$regression=$primaryValues;$regression['next_estimate_number']='74';
essentialThrows(fn()=>$forms->save($principal,7,'estimates',$primarySaved['revision'],$regression),'native_sales_sequence_regression','principal cannot move counter backwards into historical document numbers');
$principalProposal=$forms->page($principal,7,'proposals');$principalProposalValues=$principalProposal['values'];$principalProposalValues['proposal_number_prefix']='PRIMARY-P-';
essentialCheck($forms->save($principal,7,'proposals',$principalProposal['revision'],$principalProposalValues)['values']===$principalProposalValues,'principal proposal settings also use the official form and native options');
$atomicPrimary=$primaryValues;$atomicPrimary['next_estimate_number']='95';$atomicPrimary['estimate_prefix']='ROLLBACK-';
$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$forms->save($principal,7,'estimates',$primarySaved['revision'],$atomicPrimary),'issuer_storage_failed','principal counter and form both require confirmed audit');
$db->failOn=null;
essentialCheck($forms->page($principal,7,'estimates')===$primarySaved,'failed principal audit rolls back counter and settings');
essentialThrows(fn()=>$forms->page($principal,12,'estimates'),'native_sales_settings_denied','restricted staff cannot read principal sales configuration');
