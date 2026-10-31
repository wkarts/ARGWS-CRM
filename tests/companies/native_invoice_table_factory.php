<?php
require __DIR__.'/native_invoice_read.php';
require_once APPPATH.'services/NativeCompanyInvoiceTableFactory.php';
$db->query('UPDATE '.$p."staff SET firstname='Synthetic',lastname='Agent' WHERE staffid=7");
$db->query('UPDATE '.$p.'invoices SET sale_agent=7 WHERE id=? AND issuer_id=?',[$branded,$a]);
$factory=new NativeCompanyInvoiceTableFactory($db,$p);$data=$factory->data($a,7);$table=$factory->make($a,7);
essentialCheck($table->contextIdentity()===['issuer_id'=>$a,'actor_id'=>7]&&count($table->rules())===12,'single native factory returns real issuer-bound table');
essentialCheck($data['options']['sale_agent']===[['value'=>'7','label'=>'Synthetic Agent']],'sale-agent catalogue uses issuer membership and actual assigned invoices');
essentialCheck(array_column($data['options']['year'],'value')===['2026','2025'],'year catalogue reflects persisted invoice dates, not current-year dummy');
essentialCheck(in_array('company_manual',array_column($data['options']['made_payment_by'],'value'),true),'payment filter inventory preserves actual canonical receipt mode');
essentialCheck(array_unique(array_column($data['payment_modes'],'basis'))===['recorded_receipt_filter'],'filter catalogue never claims gateway/editor eligibility');
$modeBefore=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
$foreignData=$factory->data($b,7);
essentialCheck($foreignData['options']['sale_agent']===[],'real empty B assignment inventory is not inherited from A');
essentialThrows(fn()=>$factory->make($a,99999),'company_invoice_access_denied','factory rejects revoked or missing actor');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array()===$modeBefore,'factory never mutates/provider-calls payment data');
echo 'Native invoice table/facts catalogue factory: '.$GLOBALS['essentialChecks']." cumulative checks PASS.\n";
