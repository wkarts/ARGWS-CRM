<?php
/** Recreated441 regression: native GUI reader must confirm parent and bytes after its own audit. */
require __DIR__.'/expenses_operations.php';
require_once APPPATH.'services/CompanyExpenseFiles.php';
$resumeExpenseFiles=new CompanyExpenseFiles($db,$p);
foreach([$a,$b] as $resumeOwner){
 $resumeCategory=$svc->saveCategory($resumeOwner,7,'Native441 file proof '.$resumeOwner,hash('sha256','resume441-file-category-'.$resumeOwner));
 $resumeExpense=$svc->create($resumeOwner,7,array_replace($input,['category_id'=>$resumeCategory,'client_id'=>0,'project_id'=>0]),hash('sha256','resume441-file-source-'.$resumeOwner));
 $resumeBytes="%PDF-1.4\nSynthetic private native441 file\n%%EOF\n";$resumeFile=hash('sha256','resume441-native-file-'.$resumeOwner);
 $resumeExpenseFiles->upload($resumeOwner,7,$resumeExpense,$resumeFile,'native-proof.pdf',$resumeBytes);
 essentialCheck($resumeExpenseFiles->download($resumeOwner,7,$resumeExpense,$resumeFile)['content']===$resumeBytes,'native GUI reader delivers own positive private PDF A/B');
 essentialThrows(fn()=>$resumeExpenseFiles->download($resumeOwner===$a?$b:$a,7,$resumeExpense,$resumeFile),'company_expense_denied','native GUI reader rejects foreign parent and file A/B');
 $resumeBefore=[];foreach(['company_expenses','company_expense_files','company_expense_requests','company_financial_events','issuers_audit'] as $resumeTable)$resumeBefore[$resumeTable]=$db->query('SELECT * FROM '.$p.$resumeTable.' ORDER BY 1')->result_array();
 $resumeMutation='UPDATE '.$p.'company_expense_files SET active=0 WHERE expense_id=NEW.subject_id';
 $db->query($db->driver()==='mysql'?"CREATE TRIGGER {$p}resume441_download AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_expense_file_downloaded' THEN {$resumeMutation}; END IF; END":"CREATE TRIGGER {$p}resume441_download AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='company_expense_file_downloaded' BEGIN {$resumeMutation}; END");
 try{essentialThrows(fn()=>$resumeExpenseFiles->download($resumeOwner,7,$resumeExpense,$resumeFile),'company_expense_denied','native GUI reader refuses bytes hidden during its own final audit');}
 finally{$db->query('DROP TRIGGER '.$p.'resume441_download');}
 foreach($resumeBefore as $resumeTable=>$resumeRows)essentialCheck($db->query('SELECT * FROM '.$p.$resumeTable.' ORDER BY 1')->result_array()===$resumeRows,'native late denial rolls back audit/file/cost/journal '.$resumeTable);
 essentialCheck($resumeExpenseFiles->download($resumeOwner,7,$resumeExpense,$resumeFile)['content']===$resumeBytes,'native same file remains available after rolled back audit mutation');
}
echo 'Recreated441 native file positive/cross-owner/final-audit denial/rollback: '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().').'.PHP_EOL;
