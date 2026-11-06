<?php
/** Real canonical invoice + CNAB transaction, including recurring occurrence writer. No provider transport. */
require __DIR__.'/banco_brasil_cnab400_preparation_mysql.php';
require_once APPPATH.'services/NativeCompanyInvoicePaymentChoices.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/InvoiceIssuance.php';
if(!$db->table_exists($p.'payment_modes')){preg_match('/CREATE TABLE `tblpayment_modes` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$m);$sql=str_replace('`tblpayment_modes`','`'.$p.'payment_modes`',$m[0]);$sql=str_replace('`id` int NOT NULL,','`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,',$sql);$db->query($sql);}
NativeCompanyPaymentModesSchema::ensure($db,$p);
$channelsAuto=new BancoBrasil\ChannelPreferences($db,$p);$channelsAuto->setEnabled($a,7,'cnab',true,$channelsAuto->page($a,7)['revision']);
$startIssue=$GLOBALS['essentialChecks'];
$instruction240=['contract_id'=>$decl,'timezone'=>'America/Sao_Paulo','payer'=>$payer400];
$autoNow=new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo'));$auto240=$input;$auto240['date']=$autoNow->modify('-1 day')->format('Y-m-d');$auto240['due_date']=$autoNow->modify('+30 days')->format('Y-m-d');$auto240['native_preferences']=['cancel_overdue_reminders'=>1,'allowed_payment_modes'=>['banco_brasil_cnab'],'bank_issuance'=>$instruction240];
$autoKey=hash('sha256','atomic-cnab240-invoice');
$invoiceAuto=$s->create($a,7,$auto240,$autoKey);
$originAuto=$db->query('SELECT * FROM '.$p.'bb_origins WHERE invoice_id=?',[$invoiceAuto])->row_array();
essentialCheck($originAuto['channel']==='cnab240'&&(int)$originAuto['issuer_id']===$a,'canonical creation automatically creates one owned CNAB240 title');
$batchAuto=(int)$db->query('SELECT batch_id FROM '.$p.'bb_batch_origins WHERE origin_id=?',[$originAuto['id']])->row()->batch_id;
essentialCheck($bank->boleto($a,7,$batchAuto,$invoiceAuto)['bank_state']==='generated_registration_not_confirmed','new canonical invoice has printable boleto before download/transmission');
essentialCheck($s->create($a,7,$auto240,$autoKey)===$invoiceAuto&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'bb_origins WHERE invoice_id=?',[$invoiceAuto])->row()->n===1,'canonical retry confirms ledger and never duplicates title');
$snapshotAtomic=static function()use($db,$p):array{
    $out=[];foreach(['invoices','itemable','item_tax','issuers_sequences','company_invoice_requests','bb_number_reservations','bb_origins','bb_origin_snapshots','bb_batches','bb_batch_requests','bb_invoice_issuances','issuers_audit','bb_audit'] as $table)$out[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return $out;
};
$beforeAtomic=$snapshotAtomic();$db->failOn='INSERT INTO '.$p.'bb_invoice_issuances';
essentialThrows(fn()=>$s->create($a,7,$auto240,hash('sha256','atomic-fail-ledger')),'issuer_storage_failed','bank linkage failure cannot report successful canonical issue');$db->failOn=null;
essentialCheck($snapshotAtomic()===$beforeAtomic,'bank failure rolls back invoice, lines, sequence, number, origin, batch and audit together');
$badBank=$auto240;$badBank['native_preferences']['bank_issuance']['contract_id']=2147483647;
essentialThrows(fn()=>$s->create($a,7,$badBank,hash('sha256','atomic-foreign-contract')),'bb_invoice_issuance_contract_denied','unavailable bank contract cannot be inferred or substituted');
essentialCheck($snapshotAtomic()===$beforeAtomic,'rejected bank contract leaves no invoice or reservation');
$oldVersion=$db->query("SELECT installed_version FROM {$p}modules WHERE module_name='banco_brasil'")->row()->installed_version;
$db->query("UPDATE {$p}modules SET installed_version='1.4.1' WHERE module_name='banco_brasil'");
essentialThrows(fn()=>$s->create($a,7,$auto240,hash('sha256','atomic-old-module')),'bb_invoice_issuance_denied','unmigrated bank runtime cannot issue invoice with automatic instruction');
$db->query("UPDATE {$p}modules SET installed_version=? WHERE module_name='banco_brasil'",[$oldVersion]);
$approvalAuto=['currency_reference'=>'Synthetic currency contract','interest_reference'=>'Synthetic exemption contract','homologation_reference'=>'Synthetic homologation declaration','currency_confirmed'=>'1','interest_confirmed'=>'1','homologation_confirmed'=>'1'];
$auto400=$auto240;$auto400['native_preferences']['bank_issuance']=['contract_id'=>$contract400,'timezone'=>'America/Sao_Paulo','payer'=>$payer400,'approval400'=>$approvalAuto];
$invoiceAuto400=$s->create($a,7,$auto400,hash('sha256','atomic-cnab400-invoice'));
$preparationAuto400=$db->query('SELECT id FROM '.$p.'bb_cnab_preparations WHERE invoice_id=?',[$invoiceAuto400])->row()->id;
$stateAuto400=$prepare400->recover($a,7,$preparationAuto400);
essentialCheck($stateAuto400['remittance_generated']&&$stateAuto400['export_group']['state']==='open','canonical CNAB400 issuance includes approval, reserved title and open export group atomically');
essentialCheck($prepare400->boleto($a,7,$preparationAuto400)['bank_state']==='generated_registration_not_confirmed','automatically issued CNAB400 has printable instrument without transmission');
$withoutApproval=$auto400;unset($withoutApproval['native_preferences']['bank_issuance']['approval400']);$beforeAtomic=$snapshotAtomic();
essentialThrows(fn()=>$s->create($a,7,$withoutApproval,hash('sha256','atomic-no-400-approval')),'bb400_homologation_required','400 approval is never inferred from gateway selection');
essentialCheck($snapshotAtomic()===$beforeAtomic,'missing explicit 400 declaration rolls back the whole issue');
require_once APPPATH.'services/CompanyInvoiceRecurring.php';
CompanyInvoiceRecurringSchema::ensure($db,$p);$recurringAuto=new CompanyInvoiceRecurring($db,$p);
$specAuto=['unit'=>'day','interval'=>1,'custom'=>true,'cycles'=>2,'send'=>false,'contact_id'=>0,'base_url'=>''];
$seriesAuto=$recurringAuto->create($a,7,$auto240,$specAuto,hash('sha256','automatic-bank-series'));
$runAutoOne=$recurringAuto->run($a,7,$seriesAuto,$autoNow->format('Y-m-d'));
$runAutoTwo=$recurringAuto->run($a,7,$seriesAuto,$autoNow->format('Y-m-d'));
essentialCheck($runAutoOne!==$runAutoTwo&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'bb_invoice_issuances WHERE invoice_id IN (?,?)',[$runAutoOne,$runAutoTwo])->row()->n===2,'two recurring occurrences use the same canonical atomic bank issuance');
essentialCheck($recurringAuto->run($a,7,$seriesAuto,$autoNow->format('Y-m-d'))===null,'completed recurring series cannot create another bank title');
$series400=$recurringAuto->create($a,7,$auto400,$specAuto,hash('sha256','automatic-bank400-series'));
$series400one=$recurringAuto->run($a,7,$series400,$autoNow->format('Y-m-d'));$series400two=$recurringAuto->run($a,7,$series400,$autoNow->format('Y-m-d'));
$groups400=$db->query('SELECT m.group_id FROM '.$p.'bb_cnab_group_members m JOIN '.$p.'bb_cnab_preparations p ON p.id=m.preparation_id WHERE p.invoice_id IN (?,?)',[$series400one,$series400two])->result_array();
essentialCheck(count($groups400)===2&&$groups400[0]['group_id']===$groups400[1]['group_id'],'CNAB400 recurring occurrences automatically share one compatible open group');
$beforeAtomic=$snapshotAtomic();
foreach(['timezone'=>'invalid/zone','payer'=>array_replace($payer400,['payer_zip'=>'123'])] as $field=>$value){$invalid=$auto240;$invalid['native_preferences']['bank_issuance'][$field]=$value;essentialThrows(fn()=>$s->create($a,7,$invalid,hash('sha256','invalid-'.$field)),'company_invoice_bank_instruction_invalid','invalid explicit bank '.$field.' rejected');}
$invalid=$auto240;$invalid['native_preferences']['allowed_payment_modes'][]='asaas';essentialThrows(fn()=>$s->create($a,7,$invalid,hash('sha256','conflicting-bank')),'company_invoice_bank_instruction_invalid','simultaneous online provider cannot silently create another obligation');
$channelsAuto->setEnabled($a,7,'cnab',false,$channelsAuto->page($a,7)['revision']);
essentialThrows(fn()=>$s->create($a,7,$auto240,hash('sha256','disabled-bank')),'native_invoice_payment_mode_denied','disabled CNAB is revalidated by the actual canonical writer');
$channelsAuto->setEnabled($a,7,'cnab',true,$channelsAuto->page($a,7)['revision']);
$bankChoices=(new BancoBrasil\InvoiceIssuance($db,$p))->choices($a,7);
essentialCheck(in_array($decl,array_map('intval',array_column($bankChoices,'id')),true)&&in_array($contract400,array_map('intval',array_column($bankChoices,'id')),true),'owned form offers both declared layouts without selecting one');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12',[$a]);
foreach([['customers','view'],['banco_brasil','view'],['banco_brasil','generate']] as [$feature,$cap])$db->query('INSERT IGNORE INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$a,$feature,$cap]);
$restrictedChoices=(new BancoBrasil\InvoiceIssuance($db,$p))->choices($a,12);
essentialCheck(count($restrictedChoices)>0&&!in_array(BancoBrasil\LayoutCatalog::CNAB400,array_column($restrictedChoices,'layout_id'),true),'operator without bank management sees240 but cannot confirm400 declarations');
$restrictedInvoice=$s->create($a,12,$auto240,hash('sha256','restricted-bank-create'));
essentialCheck((int)$db->query('SELECT actor_id FROM '.$p.'bb_invoice_issuances WHERE invoice_id=?',[$restrictedInvoice])->row()->actor_id===12,'restricted authorized operator creates only owned240 obligation');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature='banco_brasil' AND capability='generate'",[$a]);
$beforeRevoked=$snapshotAtomic();essentialThrows(fn()=>$s->create($a,12,$auto240,hash('sha256','revoked-bank-create')),'native_invoice_payment_mode_denied','bank permission revocation is checked inside canonical issue');essentialCheck($snapshotAtomic()===$beforeRevoked,'revoked bank authority leaves invoice and title state unchanged');
$wireBank=['enabled'=>'1']+$instruction240;
essentialCheck(CompanyInvoiceBankInstruction::form($wireBank)===$instruction240&&CompanyInvoiceBankInstruction::form(array_diff_key($wireBank,['enabled'=>true]))===null,'unchecked native control never grants generation; checked control preserves explicit instruction');
$native_bank_contracts=[['id'=>$decl,'layout_id'=>'CNAB240','account_id'=>'<script>unsafe</script>','agreement_number'=>'1234567','environment'=>'sandbox']];
ob_start();require APP_MODULES_PATH.'banco_brasil/views/invoice_issuance_fields.php';$renderedBank=ob_get_clean();
essentialCheck(str_contains($renderedBank,'name="bank_issuance[enabled]"')&&str_contains($renderedBank,'&lt;script&gt;unsafe&lt;/script&gt;')&&!str_contains($renderedBank,' checked')&&!str_contains($renderedBank,'<script>'),'actual native partial escapes configuration and never preselects consent');
// Two actual canonical writers retry the same bank instruction while blocked on the financial mutex.
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$atomicWorkers=[];
try{
    for($n=0;$n<2;$n++){$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/support/BbInvoiceIssuanceWorker.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode(['issuer'=>$a,'actor'=>7,'input'=>$auto400,'key'=>hash('sha256','concurrent-canonical-bank')],JSON_THROW_ON_ERROR));fclose($pipes[0]);$atomicWorkers[]=[$process,$pipes];}
    $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
    essentialCheck($waiters>=2,'both canonical CNAB400 writers wait on the same real financial mutex');$db->trans_commit();$ids=[];
    foreach($atomicWorkers as[$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err==='','atomic canonical writer succeeds without error');$ids[]=$out;}
    essentialCheck(ctype_digit($ids[0])&&$ids[0]===$ids[1]&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'bb_invoice_issuances WHERE invoice_id=?',[$ids[0]])->row()->n===1,'concurrent retries produce exactly one invoice, bank origin and issuance ledger');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();}

echo 'Automatic invoice CNAB issuance: '.($GLOBALS['essentialChecks']-$startIssue).' added MySQL checks; no network or bank transmission.'.PHP_EOL;
