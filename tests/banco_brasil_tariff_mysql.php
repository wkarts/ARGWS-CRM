<?php
/** Real core writer and complete CNAB240 provenance; synthetic independent review only. */
require __DIR__.'/banco_brasil_origination_mysql.php';
require_once __DIR__.'/support/BbPdoDatabase.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ModuleStore.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ReviewedTariffReceipt.php';
$nativeDb=new BbPdoDatabase($db->pdo);$store=new \BancoBrasil\ModuleStore($nativeDb,$p);$connectionRow=$db->query("SELECT * FROM {$p}bb_connections WHERE id=?",[$connection])->row_array();$agreement=\BancoBrasil\ModuleStore::agreement($connectionRow);$reconcile=new \BancoBrasil\Reconciliation($db,$p);
function tariffCreditReturn(array $replace=[]):string
{
    global $file,$origin,$agreement;
    $v=$replace+['paid'=>'105.53','fee'=>'2.53','net'=>'103.00','charges'=>'0.00','discount'=>'0.00','abatement'=>'0.00','iof'=>'0.00','expenses'=>'0.00','credits'=>'0.00','code'=>'06'];
    $lines=explode("\r\n",substr($file['content'],0,-2));$h=substr_replace($lines[0],'2',142,1);$lot=substr_replace($lines[1],'T',8,1);
    $t=(new \BancoBrasil\Record())->put(1,17,'0010001300001T '.$v['code'])->put(18,22,$agreement->branch)->put(23,23,$agreement->branchDigit)->put(24,35,$agreement->account)->put(36,36,$agreement->accountDigit)->put(38,57,str_pad($origin['remote_id'],20))->put(58,58,'7')->put(59,73,str_pad('CRM'.$origin['invoice_id'],15))->put(74,81,'19112026')->put(82,96,str_pad('10553',15,'0',STR_PAD_LEFT))->put(106,130,str_pad($origin['control_reference'],25))->put(131,132,'09')->put(199,213,str_pad((string)\BancoBrasil\Fields::cents($v['fee']),15,'0',STR_PAD_LEFT))->put(214,223,str_repeat(' ',10));
    $u=(new \BancoBrasil\Record())->put(1,17,'0010001300002U '.$v['code']);
    foreach([[18,32,'charges'],[33,47,'discount'],[48,62,'abatement'],[63,77,'iof'],[78,92,'paid'],[93,107,'net'],[108,122,'expenses'],[123,137,'credits']]as[$from,$to,$field])$u->put($from,$to,str_pad((string)\BancoBrasil\Fields::cents($v[$field]),15,'0',STR_PAD_LEFT));
    $u->put(138,145,'19102026')->put(146,153,'19102026');return implode("\r\n",[$h,$lot,(string)$t,(string)$u,$lines[4],$lines[5]])."\r\n";
}
function importTariffCredit(array $amounts=[]):int
{
    global $store,$connectionRow,$binding,$db,$p;
    $result=$store->importReturn($connectionRow,7,tariffCreditReturn($amounts),$binding);return (int)$db->query("SELECT return_event_id FROM {$p}bb_return_file_events WHERE return_file_id=?",[$result['id']])->row()->return_event_id;
}
function freshTariffOrigin(string $identity):int
{
    global $s,$a,$input,$bank,$connection,$binding,$title,$file,$origin,$db,$p;
    $invoice=$s->create($a,7,$input,hash('sha256','tariff-invoice-'.$identity));$batch=$bank->generate($a,7,$connection,$binding,'2026-10-19',[array_replace($title,['invoice_id'=>$invoice])],hash('sha256','tariff-batch-'.$identity));$file=$bank->download($a,7,$batch);$bank->reportTransmission($a,7,$batch,'SYNTHETIC TARIFF REVIEW '.$identity);$origin=$db->query("SELECT * FROM {$p}bb_origins WHERE invoice_id=?",[$invoice])->row_array();return $invoice;
}
$proof=['credit_confirmed'=>'1','tariff_policy_confirmed'=>'1','reason'=>'Synthetic independently checked full principal and explicit tariff','reference'=>'SYNTHETIC TARIFF STATEMENT'];
$apply=fn($event,$evidence,$actor=7,$issuer=null)=>$writer->recordCompanyProviderReceipt($issuer??$a,$actor,new \BancoBrasil\ReviewedTariffReceipt($event,$evidence));
$initialFile=$file;$initialOrigin=$origin;$event=importTariffCredit();$invoice=(int)$origin['invoice_id'];
essentialCheck($s->payments($a,7,$invoice)===[],'Tariff-bearing upload is only an observation until independent review');
essentialThrows(fn()=>$apply($event,$proof),'bb_event_explicit_link_required','A tariff cannot bypass the explicit source link');
essentialThrows(fn()=>new \BancoBrasil\ReviewedTariffReceipt($event,array_diff_key($proof,['tariff_policy_confirmed'=>true])),'bb_independent_credit_confirmation_required','New basis requires its own positive confirmation');
essentialThrows(fn()=>new \BancoBrasil\ReviewedTariffReceipt($event,$proof+['allow_fee'=>true]),'bb_independent_credit_confirmation_required','No arbitrary bypass flag is accepted');
$reconcile->link($a,7,$event);
essentialThrows(fn()=>$apply($event,$proof,7,$b),'bb_event_unavailable','Known event ID cannot cross company ownership');
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(15,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,15,1)",[$a]);foreach([['banco_brasil','view'],['banco_brasil','reconcile'],['invoices','view'],['payments','create']]as[$feature,$cap])$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,15,?,?)",[$a,$feature,$cap]);
essentialThrows(fn()=>$apply($event,$proof,15),'bb_reconciliation_access_denied','Older reconcile grant does not imply new tariff authority');
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,15,'banco_brasil','reconcile_tariff')",[$a]);
foreach([['net'=>'102.99'],['iof'=>'0.01'],['expenses'=>'0.01'],['credits'=>'0.01'],['charges'=>'0.01'],['discount'=>'0.01'],['abatement'=>'0.01'],['paid'=>'105.52'],['fee'=>'0.00','net'=>'105.53']]as$case=>$change){freshTariffOrigin('invalid-'.$case);$unsupported=importTariffCredit($change);$reconcile->link($a,7,$unsupported);try{$apply($unsupported,$proof);throw new LogicException('Unsupported tariff equation accepted');}catch(DomainException $e){essentialCheck(str_starts_with($e->getMessage(),'company_bank_'),'Unsupported amounts remain observations before the native writer');}}
$file=$initialFile;$origin=$initialOrigin;essentialCheck($s->payments($a,7,$invoice)===[],'All unsupported equations leave the original nominal balance intact');
$db->failOn='INSERT INTO '.$p.'bb_event_receipts';essentialThrows(fn()=>$apply($event,$proof),'issuer_storage_failed','Late source receipt failure rolls back cash, components and journal');$db->failOn=null;
essentialCheck($s->payments($a,7,$invoice)===[]&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_bank_receipt_components WHERE invoice_id=?",[$invoice])->row()->n===0,'Failed source proof leaves no financial side effects');
$payment=$apply($event,$proof);$position=$s->position($a,7,$invoice);$payments=$s->payments($a,7,$invoice);$component=$position['components'][$payment];
essentialCheck($position['received']==='105.53'&&$position['receivable']==='0.00'&&$position['noncash_reductions']==='0.00','Full principal received and debt closed without inventing a discount or cash deficit');
essentialCheck($component['fees']==='2.53'&&$component['net_observed']==='103.00'&&$component['paid']==='105.53','Immutable component evidence keeps gross105.53, tariff2.53 and bank net103 separate');
essentialCheck((string)$db->query("SELECT amount FROM {$p}invoicepaymentrecords WHERE id=?",[$payment])->row()->amount==='105.53'&&$s->document($a,7,$invoice)->data()['status']===2,'Native writer persists full customer payment and paid invoice');
essentialCheck($apply($event,$proof)===$payment,'Exact retry returns the canonical receipt without recalculating paid debt');
essentialCheck($apply($event,$proof,15)===$payment,'Current newly authorized operator may replay without changing the original author');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$invoice])->row()->n===1,'No duplicate financial receipt on replay');
essentialThrows(fn()=>$apply($event,array_replace($proof,['reference'=>'CHANGED PROOF'])),'bb_event_receipt_conflict','Captured independent evidence is immutable');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=15 AND feature='banco_brasil' AND capability='reconcile_tariff'",[$a]);essentialThrows(fn()=>$apply($event,$proof,15),'bb_reconciliation_access_denied','Current tariff revocation also blocks an old request replay');
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ReceiptReversalOverview.php';$overview=(new \BancoBrasil\ReceiptReversalOverview($db,$p))->forIssuer($a,7);essentialCheck($overview[$event]['basis']===CompanyBankComponentsData::TARIFF_BASIS&&$overview[$event]['fees']==='2.53'&&!$overview[$event]['reversed'],'Reconciliation GUI reads preserved tariff basis without offering a fictional reversal');
echo 'BB240 tariff canonical SQL: '.$GLOBALS['essentialChecks'].' cumulative checks; full gross principal, distinct tariff/net, provenance, isolated grants and immutable replay.'.PHP_EOL;
