<?php
require __DIR__.'/projects_operations.php';
$tables=CompanyProjects::ownedReferenceTables();
$before=$db->query('SELECT * FROM '.$p.'company_projects ORDER BY id')->result_array();
$expected=$ps->get($a,7,$pa);
$actual=IssuerTransaction::run($db,$p,$tables,fn()=>$ps->getInOwnedTransaction($a,7,$pa));
essentialCheck($actual===$expected,'owned writer composes exactly the same project reader result');
foreach([[$b,7,$pa],[$a,999999,$pa],[$a,7,999999]]as[$owner,$actor,$project]){
    essentialThrows(fn()=>IssuerTransaction::run($db,$p,$tables,fn()=>$ps->getInOwnedTransaction($owner,$actor,$project)),
        'company_project_access_denied','composed read rejects foreign/missing/unauthorized project');
}
essentialThrows(fn()=>$ps->getInOwnedTransaction($a,7,$pa),'issuer_owned_transaction_required','plain call cannot manufacture owned transaction authority');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,array_diff($tables,['company_projects']),fn()=>$ps->getInOwnedTransaction($a,7,$pa)),
    'issuer_owned_transaction_required','owner must declare project table participation');
$db->trans_begin();
try{essentialThrows(fn()=>$ps->getInOwnedTransaction($a,7,$pa),'issuer_owned_transaction_required','raw caller transaction is never accepted');}
finally{$db->trans_rollback();}
essentialCheck($db->query('SELECT * FROM '.$p.'company_projects ORDER BY id')->result_array()===$before,'composed reference never mutates project rows');
echo 'Native invoice project reference owned composition: '.$GLOBALS['essentialChecks']." cumulative checks PASS.\n";
