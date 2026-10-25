<?php
require __DIR__.'/native_invoice_table_factory.php';
require_once APPPATH.'services/NativeCompanyInvoiceAgents.php';
$agents=new NativeCompanyInvoiceAgents($db,$p);$choices=$agents->choices($a,7);
essentialCheck(in_array(7,array_column($choices,'staffid'),true),'persisted master/active member is eligible without staff directory permission');
foreach($choices as$row)essentialCheck(array_keys($row)===['staffid','firstname','lastname'],'editor agent catalogue contains no private profile/email fields');
essentialCheck($agents->validate($a,7,0)===null,'native unassigned agent remains explicit absence');
essentialThrows(fn()=>$agents->validate($a,7,999999),'native_invoice_agent_denied','unknown candidate is not accepted as sales agent');
$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');
try{essentialThrows(fn()=>$agents->choices($a,7),'native_invoice_editor_denied','inactive actor cannot reuse cached editor catalogue');}finally{$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');}
echo 'Native invoice eligible agents: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
