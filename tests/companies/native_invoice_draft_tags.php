<?php
require __DIR__.'/native_invoice_draft_references.php';
require_once APPPATH.'services/NativeCompanyResourceTags.php';
$tags=new NativeCompanyResourceTags($db,$p);$initial=$tags->read($a,7,'invoice',$encoded);
$tables=array_values(array_unique(array_merge(NativeCompanyResourceTags::tableNames(),$drafts->tables())));
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$updated=IssuerTransaction::run($db,$p,$tables,fn()=>$tags->replaceInOwnedTransaction($a,7,'invoice',$encoded,$initial['revision'],['Synthetic private tag']));
essentialCheck(array_column($updated['tags'],'name')===['Synthetic private tag'],'draft tag write composes under complete owned boundary');
$link=$db->query('SELECT source_kind,source_id FROM '.$p.'company_work_tag_links WHERE issuer_id=? AND source_kind=?',[$a,'company_invoice_draft'])->row_array();
essentialCheck($link['source_kind']==='company_invoice_draft'&&(int)$link['source_id']===$private,'tag association retains separate private source, no issued raw-ID alias');
essentialThrows(fn()=>$tags->read($b,7,'invoice',$encoded),'company_invoice_draft_unavailable','foreign issuer cannot read draft tags');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,NativeCompanyResourceTags::tableNames(),fn()=>$tags->replaceInOwnedTransaction($a,7,'invoice',$encoded,$updated['revision'],[])),'issuer_outer_transaction_not_allowed','partial owned boundary cannot impersonate complete draft authority');
$db->failOn='INSERT INTO '.$p.'issuers_audit';try{essentialThrows(fn()=>IssuerTransaction::run($db,$p,$tables,fn()=>$tags->replaceInOwnedTransaction($a,7,'invoice',$encoded,$updated['revision'],['Rollback tag'])),'issuer_storage_failed','late tag audit failure rolls back private links');}finally{$db->failOn=null;}
essentialCheck($tags->read($a,7,'invoice',$encoded)===$updated,'rollback retains exact prior private tag revision');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'tag editing never emits a draft or changes invoice ledger');
echo 'Native private draft tags: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
