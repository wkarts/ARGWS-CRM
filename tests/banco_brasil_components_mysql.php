<?php
/** Canonical writer + CNAB T/U local policy; synthetic independent evidence, no live bank calls. */
require __DIR__.'/banco_brasil_origination_mysql.php';
require_once __DIR__.'/support/BbPdoDatabase.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ModuleStore.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ReviewedComponentsReceipt.php';
$nativeDb=new BbPdoDatabase($db->pdo);$store=new \BancoBrasil\ModuleStore($nativeDb,$p);
$connectionRow=$db->query("SELECT * FROM {$p}bb_connections WHERE id=?",[$connection])->row_array();$agreement=\BancoBrasil\ModuleStore::agreement($connectionRow);$reconcile=new \BancoBrasil\Reconciliation($db,$p);
function componentsCreditReturn(string $paid,string $charge='0.00',string $discount='0.00',string $abatement='0.00',string $fee='0.00',string $iof='0.00',?string $net=null,string $code='06'):string
{
    global $file,$origin,$agreement;
    $lines=explode("\r\n",substr($file['content'],0,-2));$h=substr_replace($lines[0],'2',142,1);$lot=substr_replace($lines[1],'T',8,1);
    $t=(new \BancoBrasil\Record())->put(1,17,'0010001300001T '.$code)->put(18,22,$agreement->branch)->put(23,23,$agreement->branchDigit)->put(24,35,$agreement->account)->put(36,36,$agreement->accountDigit)->put(38,57,str_pad($origin['remote_id'],20))->put(58,58,'7')->put(59,73,str_pad('CRM'.$origin['invoice_id'],15))->put(74,81,'19112026')->put(82,96,str_pad('10553',15,'0',STR_PAD_LEFT))->put(106,130,str_pad($origin['control_reference'],25))->put(131,132,'09')->put(199,213,str_pad((string)\BancoBrasil\Fields::cents($fee),15,'0',STR_PAD_LEFT))->put(214,223,str_repeat(' ',10));
    $u=(new \BancoBrasil\Record())->put(1,17,'0010001300002U '.$code);
    foreach([[18,32,$charge],[33,47,$discount],[48,62,$abatement],[63,77,$iof],[78,92,$paid],[93,107,$net??$paid]] as [$from,$to,$amount])$u->put($from,$to,str_pad((string)\BancoBrasil\Fields::cents($amount),15,'0',STR_PAD_LEFT));
    $u->put(108,137,str_repeat('0',30))->put(138,145,'19102026')->put(146,153,'19102026');
    return implode("\r\n",[$h,$lot,(string)$t,(string)$u,$lines[4],$lines[5]])."\r\n";
}
function importComponentsCredit(...$amounts):int
{
    global $store,$connectionRow,$binding,$db,$p;
    $result=$store->importReturn($connectionRow,7,componentsCreditReturn(...$amounts),$binding);
    return (int)$db->query("SELECT return_event_id FROM {$p}bb_return_file_events WHERE return_file_id=?",[$result['id']])->row()->return_event_id;
}
$proof=['credit_confirmed'=>'1','component_policy_confirmed'=>'1','reason'=>'Explicit synthetic local equation and independently checked aggregate components','reference'=>'SYNTHETIC STATEMENT COMPONENTS'];
$apply=fn($event,$evidence,$actor=7)=>$writer->recordCompanyProviderReceipt($a,$actor,new \BancoBrasil\ReviewedComponentsReceipt($event,$evidence));
$e1=importComponentsCredit('95.00','5.00','10.00');
essentialCheck($s->payments($a,7,$origin['invoice_id'])===[],'component-bearing upload never creates financial effects');
essentialThrows(fn()=>$apply($e1,$proof),'bb_event_explicit_link_required','component policy cannot bypass exact source link');
essentialThrows(fn()=>new \BancoBrasil\ReviewedComponentsReceipt($e1,array_diff_key($proof,['component_policy_confirmed'=>true])),'bb_independent_credit_confirmation_required','local decomposition policy must be explicitly declared');
essentialThrows(fn()=>new \BancoBrasil\ReviewedComponentsReceipt($e1,array_replace($proof,['credit_confirmed'=>'0'])),'bb_independent_credit_confirmation_required','component declaration does not authenticate or independently confirm a bank credit');
$reconcile->link($a,7,$e1);
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(15,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,15,1)",[$a]);foreach([['banco_brasil','view'],['banco_brasil','reconcile'],['invoices','view'],['payments','create']] as [$feature,$cap])$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,15,?,?)",[$a,$feature,$cap]);
essentialThrows(fn()=>$apply($e1,$proof,15),'bb_reconciliation_access_denied','an older reconcile permission does not grant component policy authority');
// Distinct explicit observations keep unsupported net deductions observational.
foreach([[['1.01','0.00','0.00','0.00','0.10'],'company_bank_components_net_contract_required'],[['1.02','0.00','0.00','0.00','0.00','0.10'],'company_bank_components_net_contract_required'],[['1.03','0.00','0.00','0.00','0.00','0.00','1.02'],'company_bank_components_balance_invalid'],[['1.04','1.04'],'company_bank_components_cash_invalid']] as [$amounts,$error]){$unsupported=importComponentsCredit(...$amounts);$reconcile->link($a,7,$unsupported);essentialThrows(fn()=>$apply($unsupported,$proof),$error,'unsupported net/zero-cash semantics never reach native payment writer');}
essentialCheck($s->payments($a,7,$origin['invoice_id'])===[],'unsupported fee/IOF/net/cash observations leave original nominal balance intact');
$db->failOn='INSERT INTO '.$p.'company_bank_receipt_components';essentialThrows(fn()=>$apply($e1,$proof),'issuer_storage_failed','component ledger failure rolls back native cash payment and status');$db->failOn=null;
essentialCheck($s->payments($a,7,$origin['invoice_id'])===[],'failed component ledger leaves no false native receipt');
$db->failOn='INSERT INTO '.$p.'bb_event_receipts';essentialThrows(fn()=>$apply($e1,$proof),'issuer_storage_failed','source receipt failure rolls back component ledger too');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_bank_receipt_components WHERE invoice_id=?",[$origin['invoice_id']])->row()->n===0,'failed source proof leaves no component artifact');
$payment1=$apply($e1,$proof);$captured=$db->query("SELECT * FROM {$p}company_bank_receipt_components WHERE payment_id=?",[$payment1])->row_array();
essentialCheck($captured['cash_principal']==='90.00'&&$captured['discount']==='10.00'&&$captured['charges_aggregate']==='5.00'&&$captured['principal_extinguished']==='100.00','native principal cash and noncash extinction and aggregate charges remain distinct');
essentialCheck((string)$db->query("SELECT amount FROM {$p}invoicepaymentrecords WHERE id=?",[$payment1])->row()->amount==='90.00','native payment never pretends the discounted principal was received cash');
essentialCheck($s->document($a,7,$origin['invoice_id'])->data()['status']===3,'a confirmed partial principal extinction keeps the exact remaining balance open');
essentialCheck($apply($e1,$proof)===$payment1,'component retry uses captured prior balance rather than deriving a different post-write DTO');
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,15,'banco_brasil','reconcile_components')",[$a]);
essentialCheck($apply($e1,$proof,15)===$payment1&&(int)$db->query("SELECT created_by FROM {$p}company_bank_receipt_components WHERE payment_id=?",[$payment1])->row()->created_by===7,'new authorized actor can replay same captured proof without rewriting its original actor');
essentialThrows(fn()=>$apply($e1,array_replace($proof,['reference'=>'different independent evidence'])),'bb_event_receipt_conflict','component evidence is immutable after application');
$e2=importComponentsCredit('3.00','0.00','0.00','2.53');$reconcile->link($a,7,$e2);$payment2=$apply($e2,$proof);
essentialCheck($payment2!==$payment1&&$s->document($a,7,$origin['invoice_id'])->data()['status']===2,'remaining cash3 plus noncash abatimento2.53 extinguishes105.53 exactly');
essentialCheck($apply($e1,$proof)===$payment1,'a closed invoice still accepts an exact earlier receipt retry');
$payments=$db->query("SELECT amount FROM {$p}invoicepaymentrecords WHERE invoiceid=? ORDER BY id",[$origin['invoice_id']])->result_array();essentialCheck(array_column($payments,'amount')===['90.00','3.00'],'cashprincipal remains93 while principal extinguished105.53 and aggregatecharge5 stay distinct');
$db->query("UPDATE {$p}company_bank_receipt_components SET payload_hash=? WHERE payment_id=?",[str_repeat('0',64),$payment1]);essentialThrows(fn()=>$apply($e1,$proof),'company_bank_components_receipt_review','mutated component proof cannot be hidden by idempotent retry');$db->query("UPDATE {$p}company_bank_receipt_components SET payload_hash=? WHERE payment_id=?",[$captured['payload_hash'],$payment1]);
$reverse=importComponentsCredit('0.00','0.00','0.00','0.00','0.00','0.00',null,'44');$reconcile->link($a,7,$reverse);essentialThrows(fn()=>$apply($e1,$proof),'bb_event_adverse_history_requires_review','later bank adverse observation freezes prior component retry without deleting cash or reductions');
essentialCheck(count($s->payments($a,7,$origin['invoice_id']))===2,'adverse event does not silently reverse native receipts');
echo 'BB explicit components: '.$GLOBALS['essentialChecks'].' cumulative actual SQL checks; local equation and synthetic bank evidence, no bank homologation.'.PHP_EOL;
