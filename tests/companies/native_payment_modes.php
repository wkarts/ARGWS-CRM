<?php
require __DIR__.'/native_invoice_read.php';
require_once APPPATH.'services/NativeCompanyPaymentModes.php';
NativeCompanyPaymentModesSchema::ensure($db,$p);NativeCompanyPaymentModesSchema::ensure($db,$p);
$modes=new NativeCompanyPaymentModes($db,$p);$input=['name'=>'Synthetic bank transfer','description'=>"Synthetic line\nSecond <literal>",'active'=>1,'show_on_pdf'=>1,'selected_by_default'=>1,'invoices_only'=>1,'expenses_only'=>0];
$modeA=$modes->save($a,7,$input);$modeB=$modes->save($b,7,array_replace($input,['name'=>'B private mode']));
essentialCheck($modeA['source_kind']==='company_payment_mode'&&NativeCompanyResourceId::decode('payment_mode',$modeA['id'])['source_id']===$modeA['source_id'],'offline mode uses reserved namespace46 without changing raw storage ID');
essentialCheck(array_column($modes->listing($a,7,'invoices',false),'id')===[$modeA['id']],'invoice eligibility uses real owned active offline catalogue');
essentialCheck($modes->listing($a,7,'expenses',false)===[],'invoice-only mode is not silently offered for expense');
essentialThrows(fn()=>$modes->get($a,7,$modeB['id']),'native_payment_mode_denied','foreign owned mode denied despite same master actor');
essentialThrows(fn()=>$modes->get($a,7,$modeA['source_id']),'native_payment_mode_denied','raw equal ID cannot alias encoded owned mode');
essentialThrows(fn()=>$modes->get($a,7,'asaas'),'native_resource_id_invalid','provider string never coerced to offline ID');
$edited=$modes->save($a,7,array_replace($input,['name'=>'Reviewed offline mode']),$modeA['id'],$modeA['revision']);
essentialThrows(fn()=>$modes->save($a,7,$input,$modeA['id'],$modeA['revision']),'native_payment_mode_stale','stale mode revision cannot overwrite another tab');
$before=$db->query('SELECT * FROM '.$p.'company_payment_modes ORDER BY id')->result_array();$db->failOn='INSERT INTO '.$p.'issuers_audit';
try{essentialThrows(fn()=>$modes->save($a,7,$input),'issuer_storage_failed','late audit failure rolls back new mode');}finally{$db->failOn=null;}
essentialCheck($db->query('SELECT * FROM '.$p.'company_payment_modes ORDER BY id')->result_array()===$before,'late failure leaves existing catalogue intact');
if($db->driver()==='sqlite'){
 $db->query('CREATE TRIGGER '.$p.'ignore_mode BEFORE INSERT ON '.$p.'company_payment_modes BEGIN SELECT RAISE(IGNORE); END');
 try{essentialThrows(fn()=>$modes->save($a,7,$input),'issuer_storage_failed','real ignored insert is sanitized and cannot reuse stale insert ID');}
 finally{$db->query('DROP TRIGGER '.$p.'ignore_mode');}
}
$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([(string)$edited['id']]),$branded]);
essentialThrows(fn()=>$modes->delete($a,7,$edited['id'],$edited['revision']),'native_payment_mode_referenced','invoice mode selection vetoes delete under same issuer lock');
$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=?',[serialize([]),$branded]);
$modes->delete($a,7,$edited['id'],$edited['revision']);essentialThrows(fn()=>$modes->get($a,7,$edited['id']),'native_payment_mode_denied','unreferenced owned mode deleted without touching another issuer');
$primary=(new IssuerResourceAccess($db,$p))->primaryId();
$db->query('INSERT INTO '.$p.'payment_modes(name,description,active,show_on_pdf,selected_by_default,invoices_only,expenses_only) VALUES(?,?,1,1,0,0,0)',['Synthetic historical offline','Legacy description']);$legacyId=(int)$db->insert_id();
$legacy=$modes->get($primary,7,$legacyId);
$db->query('INSERT INTO '.$p.'invoicepaymentrecords(invoiceid,amount,paymentmode,date,transactionid,note,daterecorded) VALUES(42,1,?,?,?,?,?)',[(string)$legacyId,'2026-10-25','synthetic-mode-veto','','2026-10-25 00:00:00']);$legacyPayment=(int)$db->insert_id();
try{essentialThrows(fn()=>$modes->delete($primary,7,$legacyId,$legacy['revision']),'native_payment_mode_referenced','historical native receipt vetoes deletion of its offline mode');}finally{$db->query('DELETE FROM '.$p.'invoicepaymentrecords WHERE id=?',[$legacyPayment]);}
essentialCheck($legacy['source_kind']==='payment_mode'&&(string)$legacy['id']===(string)$legacyId,'primary historical offline mode keeps plain native ID');
essentialThrows(fn()=>$modes->get($b,7,$legacyId),'native_payment_mode_denied','secondary cannot hydrate primary legacy mode');
essentialThrows(fn()=>$modes->save($a,12,$input),'native_payment_mode_denied','non-administrator cannot configure payment modes');
NativeCompanyPaymentModesSchema::assertValid($db,$p);$db->query('ALTER TABLE '.$p.'company_payment_modes ADD unexpected INT NULL');
essentialThrows(fn()=>NativeCompanyPaymentModesSchema::assertValid($db,$p),'native_payment_modes_schema_invalid','schema drift fails closed');
echo 'Native offline modes namespace/ACL/revision/history/rollback: '.$GLOBALS['essentialChecks']." cumulative checks PASS. No provider calls or ledger mutation.\n";
