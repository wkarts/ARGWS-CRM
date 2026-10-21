<?php
/** Rebuilt119 API16 full reversal source. Independent declaration, never bank execution. */
declare(strict_types=1);
require __DIR__.'/banco_brasil_api16_components_mysql.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/Api16ReviewedExternalReversal.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiReceiptReversalOverview.php';
$reverse119=fn(int $issuer,int $actor,int $payment,array $proof):string=>$writer->recordCompanyProviderReversal($issuer,$actor,new \BancoBrasil\Api16ReviewedExternalReversal($payment,$proof));
$proof119=['external_reversal_confirmed'=>'1','full_reinstatement_confirmed'=>'1','returned_paid'=>'97.53','reinstated_principal'=>'105.53','date'=>$apiInput['date'],'reference'=>'SYNTHETIC COMPLETED EXTERNAL API16 REVERSAL','reason'=>'Independent synthetic completed reversal and full principal reinstatement; no bank instruction or authentication'];
$originalPayment119=$db->query("SELECT * FROM {$p}invoicepaymentrecords WHERE id=?",[$api16Payment])->result_array();
$originalComponents119=$db->query("SELECT * FROM {$p}company_bank_receipt_components WHERE payment_id=?",[$api16Payment])->result_array();
$originalReceipt119=$db->query("SELECT * FROM {$p}bb_api_receipts WHERE payment_id=?",[$api16Payment])->result_array();
$originalDoc119=$db->query("SELECT * FROM {$p}issuers_invoice_documents WHERE invoice_id=?",[$api16['invoice']])->result_array();
$httpBefore119=count($http);
essentialThrows(fn()=>$reverse119($a,7,$api16Payment,array_replace($proof119,['external_reversal_confirmed'=>'0'])),'company_bank_reversal_independent_confirmation_required','API16 credit receipt is not independent evidence of external reversal');
essentialThrows(fn()=>$reverse119($a,7,$api16Payment,array_replace($proof119,['full_reinstatement_confirmed'=>'0'])),'company_bank_reversal_independent_confirmation_required','cash return alone cannot silently reinstate noncash reductions');
essentialThrows(fn()=>$reverse119($a,7,$api16Payment,array_replace($proof119,['returned_paid'=>'92.53'])),'company_bank_reversal_full_amount_required','API16 full return includes received interest, not merely principal cash');
essentialThrows(fn()=>$reverse119($a,7,$api16Payment,array_replace($proof119,['reinstated_principal'=>'92.53'])),'company_bank_reversal_full_amount_required','API16 full reinstatement includes noncash extinction independently');
$future119=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->modify('+1 day')->format('Y-m-d');
essentialThrows(fn()=>$reverse119($a,7,$api16Payment,array_replace($proof119,['date'=>$future119])),'company_bank_reversal_date_requires_review','future bank operation cannot be declared completed');
essentialThrows(fn()=>$reverse119($b,7,$api16Payment,$proof119),'bb_api_reversal_original_unavailable','B cannot reverse A known native payment ID');
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,19,'payments','delete')",[$a]);
essentialThrows(fn()=>$reverse119($a,19,$api16Payment,$proof119),'bb_reconciliation_access_denied','existing payments/delete and reconcile grants do not imply API16 external reversal');
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,19,'banco_brasil','external_reverse_api_components')",[$a]);
$db->trans_begin();essentialThrows(fn()=>$reverse119($a,7,$api16Payment,$proof119),'issuer_outer_transaction_not_allowed','external reversal writer refuses caller transaction');essentialCheck($db->pdo->inTransaction(),'rejected caller transaction keeps its ownership');$db->trans_rollback();
foreach(['company_bank_receipt_reversals','company_financial_events','bb_audit'] as $table){
    $db->failOn='INSERT INTO '.$p.$table;essentialThrows(fn()=>$reverse119($a,7,$api16Payment,$proof119),'issuer_storage_failed','late '.$table.' failure rolls back source, reversal, balance and journal');$db->failOn=null;
    essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_bank_receipt_reversals WHERE payment_id=?",[$api16Payment])->row()->n===0&&$s->position($a,7,$api16['invoice'])['receivable']==='0.00','failed API16 reversal leaves completed balance and zero orphan reversals');
}
$key119=$reverse119($a,7,$api16Payment,$proof119);$position119=$s->position($a,7,$api16['invoice']);
essentialCheck(preg_match('/^[a-f0-9]{64}$/D',$key119)===1,'API16 reversal obtains durable canonical key');
essentialCheck($position119['received']==='0.00'&&$position119['received_reversed']==='92.53'&&$position119['receivable']==='105.53'&&$position119['noncash_reductions']==='0.00','API16 separate cash92.53 and noncash13 reversal reopens exactly105.53');
essentialCheck($db->query("SELECT * FROM {$p}invoicepaymentrecords WHERE id=?",[$api16Payment])->result_array()===$originalPayment119&&$db->query("SELECT * FROM {$p}company_bank_receipt_components WHERE payment_id=?",[$api16Payment])->result_array()===$originalComponents119&&$db->query("SELECT * FROM {$p}bb_api_receipts WHERE payment_id=?",[$api16Payment])->result_array()===$originalReceipt119,'every original native payment, bank receipt and component byte survives reversal');
essentialCheck($db->query("SELECT * FROM {$p}issuers_invoice_documents WHERE invoice_id=?",[$api16['invoice']])->result_array()===$originalDoc119,'reversal never rewrites original fiscal snapshot');
$captured119=$db->query("SELECT * FROM {$p}company_bank_receipt_reversals WHERE request_key=?",[$key119])->row_array();
essentialCheck($captured119['source_type']==='bb_api16_declared','API16 reversal provenance is explicit and distinct from CNAB240');
essentialCheck($reverse119($a,7,$api16Payment,$proof119)===$key119&&$reverse119($a,19,$api16Payment,$proof119)===$key119,'same proof replays idempotently for either explicitly authorized operator');
essentialCheck($db->query("SELECT * FROM {$p}company_bank_receipt_reversals WHERE request_key=?",[$key119])->row_array()===$captured119&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_financial_events WHERE kind='bank_external_reversal_declared' AND source_id=?",[(string)$api16Payment])->row()->n===1,'retry preserves first author and exactly one immutable reversal journal');
essentialThrows(fn()=>$reverse119($a,7,$api16Payment,array_replace($proof119,['reference'=>'changed independent proof'])),'company_bank_reversal_receipt_conflict','retry cannot overwrite prior reversal evidence');
$overview119=(new \BancoBrasil\ApiReceiptReversalOverview($db,$p))->forIssuer($a,7);essentialCheck($overview119[$api16Payment]['reversed']===true&&$overview119[$api16Payment]['invoice_id']===$api16['invoice'],'owned API overview reports separate declared reversal status');
essentialCheck(!isset((new \BancoBrasil\ApiReceiptReversalOverview($db,$p))->forIssuer($b,7)[$api16Payment]),'B overview does not disclose A reversed receipt');
// B's own API receipt uses its own company/account/proof and leaves A untouched.
$proofB119=array_replace($proof119,['returned_paid'=>'95.53','reference'=>'SYNTHETIC COMPLETED B API16 REVERSAL']);$keyB119=$reverse119($b,7,$b16Payment,$proofB119);$positionB119=$s->position($b,7,$b16Invoice);
essentialCheck(preg_match('/^[a-f0-9]{64}$/D',$keyB119)===1&&$keyB119!==$key119&&$positionB119['received']==='0.00'&&$positionB119['receivable']==='105.53','B positive reversal owns separate cash95.53/noncash10 and canonical identity');
essentialCheck($s->position($a,7,$api16['invoice'])===$position119,'B reversal preserves A entire financial projection');
essentialCheck(count($http)===$httpBefore119,'every declaration/retry/reversal check performs zero bank HTTP calls');
// An actual account-proven adverse observation remains a veto even after a recorded reversal.
$titles[$api16['number']]['state']='18';$percentageApi->query($a,7,$api16['origin'],hash('sha256','reconstructed119-reversal-adverse'));
essentialThrows(fn()=>$reverse119($a,7,$api16Payment,$proof119),'bb_api_reversal_adverse_history','later partial-state observation cannot be bypassed by replaying declared reversal');
essentialCheck($db->query("SELECT * FROM {$p}company_bank_receipt_reversals WHERE request_key=?",[$key119])->row_array()===$captured119&&$s->position($a,7,$api16['invoice'])===$position119,'adverse veto preserves the previously captured evidence and financial projection');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$api16OriginalNull,'reversal flow preserves legacy NULL invoice bytes');
echo 'BB119 reconstructed API16 declared reversal: '.$GLOBALS['essentialChecks'].' cumulative DB checks; independent synthetic declaration only, no bank refund execution or homologation.'.PHP_EOL;
