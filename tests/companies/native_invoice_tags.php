<?php
require __DIR__.'/native_work_tags.php';require_once APPPATH.'services/NativeCompanyResourceTags.php';
$db->query('ALTER TABLE '.$p.'company_work_tags DROP COLUMN unexpected');
$db->query('DROP TABLE '.$p.'tags');$db->query('CREATE TABLE '.$p.'tags(id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL)');$db->query('ALTER TABLE '.$p.'taggables ADD tag_order INT NOT NULL DEFAULT 0');
$db->query('ALTER TABLE '.$p.'invoices ADD number INT NOT NULL DEFAULT 1');
$db->query('INSERT INTO '.$p.'invoices(id,issuer_id,number) VALUES(81,?,81),(82,?,82),(83,NULL,83),(84,1,84)',[$a,$b]);
// The shared historical fixture has a proven NULL-owned customer9; financial tag references require it.
$db->query('UPDATE '.$p.'invoices SET clientid=9 WHERE id=83');
$tags=new NativeCompanyResourceTags($db,$p);$tables=array_merge(NativeCompanyResourceTags::tableNames(),['invoices']);
$save=static fn($issuer,$invoice,$expected,$labels)=>IssuerTransaction::run($db,$p,$tables,fn()=>$tags->replaceInvoiceInOwnedTransaction($issuer,7,$invoice,$expected,$labels));
$empty=$tags->read($a,7,'invoice',81);$changed=$save($a,81,$empty['revision'],['Priority','Cliente A']);
essentialCheck(array_column($changed['tags'],'name')===['Priority','Cliente A']&&array_column($changed['tags'],'order')===[0,1]&&$changed['lineage']==='owned','owned invoice tags preserve native order and explicit lineage');
essentialCheck($tags->read($b,7,'invoice',82)['tags']===[],'other company invoice never inherits labels');
essentialThrows(fn()=>$tags->read($b,7,'invoice',81),'native_resource_reference_denied','guessed invoice from A is denied in B');
essentialThrows(fn()=>$save($a,81,$empty['revision'],['Stale']),'native_tags_stale','stale tags cannot overwrite current labels');
essentialThrows(fn()=>$tags->replaceInvoiceInOwnedTransaction($a,7,81,$changed['revision'],[]),'issuer_owned_transaction_required','plain caller cannot mutate tags outside invoice transaction');
$db->query('UPDATE '.$p.'invoices SET clientid=99999999 WHERE id=83');
try{essentialThrows(fn()=>$tags->read(1,7,'invoice',83),'native_resource_reference_denied','legacy tag read rejects absent customer before labels');}
finally{$db->query('UPDATE '.$p.'invoices SET clientid=9 WHERE id=83');}
$legacy=$tags->read(1,7,'invoice',83);$legacySaved=$save(1,83,$legacy['revision'],['Historical']);
essentialCheck($legacySaved['lineage']==='legacy'&&count($db->query('SELECT * FROM '.$p.'taggables WHERE rel_id=83')->result_array())===1,'primary legacy invoice keeps native taggables, no adoption');
$primaryOwned=$tags->read(1,7,'invoice',84);$primarySaved=$save(1,84,$primaryOwned['revision'],['Owned primary']);
essentialCheck($primarySaved['lineage']==='owned'&&$db->query('SELECT * FROM '.$p.'taggables WHERE rel_id=84')->result_array()===[],'named primary tags stay in owned store');
essentialThrows(fn()=>$tags->read($a,7,'invoice',83),'native_resource_reference_denied','secondary cannot read primary NULL invoice tags');
$beforeInvoice=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();$beforeOwned=$db->query('SELECT * FROM '.$p.'company_work_tags ORDER BY id')->result_array();$beforeLinks=$db->query('SELECT * FROM '.$p.'company_work_tag_links ORDER BY tag_id')->result_array();$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$save($a,81,$changed['revision'],['New label']),'issuer_storage_failed','audit failure rolls back catalog and links together');$db->failOn=null;
essentialCheck($beforeOwned===$db->query('SELECT * FROM '.$p.'company_work_tags ORDER BY id')->result_array()&&$beforeLinks===$db->query('SELECT * FROM '.$p.'company_work_tag_links ORDER BY tag_id')->result_array(),'failed write leaves labels and order unchanged');
essentialCheck($beforeInvoice===$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array(),'tag writer never changes financial invoice fields');
$projectId=NativeCompanyResourceId::encode('company_project',1);$projectTags=$tags->read($a,7,'project',$projectId);
$projectTables=array_merge(NativeCompanyResourceTags::tableNames(),['company_projects']);
$projectSaved=IssuerTransaction::run($db,$p,$projectTables,fn()=>$tags->replaceInOwnedTransaction($a,7,'project',$projectId,$projectTags['revision'],['Project scoped']));
essentialCheck(array_column($projectSaved['tags'],'name')===['Project scoped']&&$tags->read($a,7,'invoice',81)===$changed,'generic resource tag compositor does not overwrite invoice tags');
essentialThrows(fn()=>$tags->read($a,7,'project',1),'native_resource_reference_denied','raw legacy project id cannot impersonate owned project namespace');
echo "Native invoice tags: {$GLOBALS['essentialChecks']} cumulative checks passed.\n";
