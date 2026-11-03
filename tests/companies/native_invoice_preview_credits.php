<?php
require __DIR__.'/native_invoice_selected_reversal.php';
require_once APPPATH.'services/CompanyCredits.php';
$credits=new CompanyCredits($db,$p);$before=$db->query('SELECT * FROM '.$p.'credits ORDER BY id')->result_array();$applications=$credits->appliedToInvoice($a,7,$invoice);
essentialCheck(count($applications)===1&&$applications[0]['credit_id']===$credit&&$applications[0]['invoice_id']===$invoice&&$applications[0]['amount']==='20.00'&&$applications[0]['credit_number']!=='','preview retains native credit ID, exact reconciled remaining amount and owned number');
essentialThrows(fn()=>$credits->appliedToInvoice($b,7,$invoice),'company_invoice_access_denied','another issuer cannot inspect applied credit');
essentialCheck($db->query('SELECT * FROM '.$p.'credits ORDER BY id')->result_array()===$before,'preview credit read never mutates native applications');
echo 'Native invoice applied-credit preview: ownership, ledger and native credit ID verified.'.PHP_EOL;
