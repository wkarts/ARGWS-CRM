<?php
/** Uploaded-file evidence stays unverified; only explicit operator-confirmed credits use the core writer. */
require __DIR__.'/banco_brasil_origination_mysql.php';
require_once __DIR__.'/support/BbPdoDatabase.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ModuleStore.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ReviewedReceipt.php';
$nativeDb=new BbPdoDatabase($db->pdo);$store=new \BancoBrasil\ModuleStore($nativeDb,$p);
$connectionRow=$db->query("SELECT * FROM {$p}bb_connections WHERE id=?",[$connection])->row_array();$agreement=\BancoBrasil\ModuleStore::agreement($connectionRow);
function syntheticCreditReturn(string $paid,string $fee='0.00',string $code='06',?string $number=null):string
{
    global $file,$origin,$agreement;
    $lines=explode("\r\n",substr($file['content'],0,-2));$h=substr_replace($lines[0],'2',142,1);$lot=substr_replace($lines[1],'T',8,1);
    $t=(new \BancoBrasil\Record())->put(1,17,'0010001300001T '.$code)->put(18,22,$agreement->branch)->put(23,23,$agreement->branchDigit)->put(24,35,$agreement->account)->put(36,36,$agreement->accountDigit)->put(38,57,str_pad($number??$origin['remote_id'],20))->put(58,58,'7')->put(59,73,str_pad('CRM'.$origin['invoice_id'],15))->put(74,81,'19112026')->put(82,96,str_pad('10553',15,'0',STR_PAD_LEFT))->put(106,130,str_pad($origin['control_reference'],25))->put(131,132,'09')->put(199,213,str_pad((string)\BancoBrasil\Fields::cents($fee),15,'0',STR_PAD_LEFT))->put(214,223,str_repeat(' ',10));
    $money=str_pad((string)\BancoBrasil\Fields::cents($paid),15,'0',STR_PAD_LEFT);
    $u=(new \BancoBrasil\Record())->put(1,17,'0010001300002U '.$code)->put(18,77,str_repeat('0',60))->put(78,92,$money)->put(93,107,$money)->put(108,137,str_repeat('0',30))->put(138,145,'19102026')->put(146,153,'19102026');
    return implode("\r\n",[$h,$lot,(string)$t,(string)$u,$lines[4],$lines[5]])."\r\n";
}
function importCredit(string $paid,string $fee='0.00',string $code='06',?string $number=null):int
{
    global $store,$connectionRow,$binding,$db,$p;
    $result=$store->importReturn($connectionRow,7,syntheticCreditReturn($paid,$fee,$code,$number),$binding);
    return (int)$db->query("SELECT return_event_id FROM {$p}bb_return_file_events WHERE return_file_id=?",[$result['id']])->row()->return_event_id;
}
$reconcile=new \BancoBrasil\Reconciliation($db,$p);$e1=importCredit('40.00');
$evidence=['credit_confirmed'=>'1','reason'=>'Synthetic bank credit independently reviewed','reference'=>'SYNTHETIC STATEMENT A'];
$apply=fn($event,$proof)=>$writer->recordCompanyProviderReceipt($a,7,new \BancoBrasil\ReviewedReceipt($event,$proof));
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$origin['invoice_id']])->row()->n===0,'return import is observational even after exact origin exists');
essentialThrows(fn()=>$apply($e1,$evidence),'bb_event_explicit_link_required','canonical writer refuses an unlinked uploaded observation');
essentialThrows(fn()=>new \BancoBrasil\ReviewedReceipt($e1,array_replace($evidence,['credit_confirmed'=>'0'])),'bb_independent_credit_confirmation_required','file code06 cannot replace explicit independent bank credit confirmation');
essentialThrows(fn()=>$reconcile->link($b,7,$e1),'bb_event_unavailable','cross company numeric event ID denied');
essentialCheck($reconcile->link($a,7,$e1)===(int)$origin['id']&&$reconcile->link($a,7,$e1)===(int)$origin['id'],'exact scope/number/control/document link is durable and idempotent');
$db->failOn='INSERT INTO '.$p.'bb_event_receipts';
essentialThrows(fn()=>$apply($e1,$evidence),'issuer_storage_failed','provider receipt failure rolls back native payment and invoice status');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$origin['invoice_id']])->row()->n===0,'failed receipt leaves no false invoice payment');
$db->trans_begin();
essentialThrows(fn()=>$apply($e1,$evidence),'issuer_outer_transaction_not_allowed','provider writer never joins a caller transaction');
essentialCheck($db->pdo->inTransaction(),'outer transaction ownership is preserved on rejection');$db->trans_rollback();
$trigger=$p.'freeze_financial_state';$db->query("CREATE TRIGGER {$trigger} BEFORE UPDATE ON {$p}bb_return_events FOR EACH ROW SET NEW.financial_state=OLD.financial_state");
essentialThrows(fn()=>$apply($e1,$evidence),'issuer_bb_receipt_unconfirmed','ignored successful financial marker update cannot report applied payment');
$db->query('DROP TRIGGER '.$trigger);
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$origin['invoice_id']])->row()->n===0,'ignored event marker rolls back native receipt and status');
$payment1=$apply($e1,$evidence);
essentialCheck($apply($e1,$evidence)===$payment1,'reviewed event retry inserts payment once');
essentialCheck($db->query("SELECT status FROM {$p}invoices WHERE id=?",[$origin['invoice_id']])->row()->status==3,'confirmed partial credit leaves remaining invoice balance');
essentialCheck(importCredit('40.00')===$e1,'reimport deduplicates event already financially applied');
$e2=importCredit('65.53');$reconcile->link($a,7,$e2);$payment2=$apply($e2,$evidence);
essentialCheck($payment2!==$payment1&&$db->query("SELECT status FROM {$p}invoices WHERE id=?",[$origin['invoice_id']])->row()->status==2,'distinct confirmed remaining credit completes exact total');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$origin['invoice_id']])->row()->n===2,'two bank credits create exactly two native receipts');
essentialThrows(fn()=>$apply($e1,array_replace($evidence,['reference'=>'different evidence'])),'bb_event_receipt_conflict','evidence cannot be overwritten after financial application');
$feeEvent=importCredit('1.00','0.10');$reconcile->link($a,7,$feeEvent);
essentialThrows(fn()=>$apply($feeEvent,$evidence),'bb_event_adjustments_require_review','fees are not silently subtracted from invoice principal');
$unmatched=importCredit('2.00','0.00','06','12345670000000999');
essentialThrows(fn()=>$reconcile->link($a,7,$unmatched),'bb_event_origin_unmatched','matching amount or control alone cannot link a different Nosso Numero');
$review=['decision'=>'unmatched','reason'=>'Synthetic unmatched bank identifier','reference'=>'SYNTHETIC REVIEW'];$reconcile->review($a,7,$unmatched,$review);$reconcile->review($a,7,$unmatched,$review);
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}bb_event_reviews WHERE event_id=?",[$unmatched])->row()->n===1,'divergence review reason/evidence is persisted without duplicate history');
$reversal=importCredit('0.00','0.00','44');$reconcile->link($a,7,$reversal);
essentialThrows(fn()=>$apply($reversal,$evidence),'bb_event_requires_specialist_review','cheque reversal is never misclassified as receipt');
essentialThrows(fn()=>$apply($e2,$evidence),'bb_event_adverse_history_requires_review','later adverse event cannot be hidden by replaying old settlement');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$origin['invoice_id']])->row()->n===2,'adverse/unmatched/fee events do not fabricate or erase payments');
echo 'Assisted BB reconciliation: '.$GLOBALS['essentialChecks'].' cumulative actual MySQL checks; synthetic evidence, no bank authentication or homologation.'.PHP_EOL;
