<?php
/** Real 400 origin/generation/print on disposable SQL. No bank calls or transmission. */
require __DIR__.'/banco_brasil_cnab400_preparation_mysql.php';
essentialThrows(fn()=>$prepare400->boleto($a,7,$one['id']),'bb_boleto_unavailable','reserved-only 400 preparation has no complete printable payer record');
$approvalPrint400=['currency_reference'=>'Synthetic currency contract','interest_reference'=>'Synthetic exemption contract','homologation_reference'=>'Synthetic homologation declaration','currency_confirmed'=>'1','interest_confirmed'=>'1','homologation_confirmed'=>'1'];
$prepare400->declareApproval($a,7,$one['id'],$approvalPrint400);
$prepare400->generate($a,7,$one['id'],'2026-10-19',$payer400);
$printSnapshot400=static function()use($db,$p):array{$out=[];foreach(['bb_cnab_preparations','bb_batches','bb_origins','bb_number_reservations','bb_batch_actions','bb_audit','invoicepaymentrecords','invoices'] as $table)$out[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return $out;};
$beforePrint400=$printSnapshot400();$boleto=$prepare400->boleto($a,7,$one['id']);
essentialCheck($printSnapshot400()===$beforePrint400,'400 print leaves origins, batches, counters, audit, invoices and payments unchanged');
essentialCheck($boleto['bank_state']==='generated_registration_not_confirmed'&&$boleto['environment']==='sandbox','400 print does not assert bank registration');
essentialCheck($boleto['our_number']===$db->query('SELECT remote_id FROM '.$p.'bb_origins WHERE id=?',[$reserved400['origin_id']])->row()->remote_id,'400 boleto uses the allocated immutable Nosso Numero');
essentialCheck($boleto['snapshot']['title']['payer_address']===$payer400['payer_address']&&$boleto['snapshot']['title']['payer_tax_id']===$boleto['document']['recipient']['tax_id'],'400 print uses validated record and immutable payer identity');
essentialCheck($prepare400->boleto($a,7,$one['id'])===$boleto,'400 reprint is identical before any download');
essentialThrows(fn()=>$prepare400->boleto($b,7,$one['id']),'bb400_preparation_unavailable','known foreign preparation cannot disclose boleto');
essentialThrows(fn()=>$prepare400->boleto($a,999,$one['id']),'bb400_preparation_access_denied','unprivileged actor cannot print');
$db->query('UPDATE '.$p.'invoices SET total=total+1 WHERE id=?',[$invoice400]);
essentialThrows(fn()=>$prepare400->boleto($a,7,$one['id']),'bb400_preparation_stale','changed invoice does not print historical amount');
$db->query('UPDATE '.$p.'invoices SET total=? WHERE id=?',[$beforeInvoice['total'],$invoice400]);
$db->query('UPDATE '.$p.'invoices SET status=5 WHERE id=?',[$invoice400]);
essentialThrows(fn()=>$prepare400->boleto($a,7,$one['id']),'bb400_preparation_invoice_unavailable','cancelled invoice cannot print');
$db->query('UPDATE '.$p.'invoices SET status=? WHERE id=?',[$beforeInvoice['status'],$invoice400]);
$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');
essentialThrows(fn()=>$prepare400->boleto($a,7,$one['id']),'bb400_preparation_access_denied','revoked staff cannot reprint');
$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
$racePrint400=static function(array $actions,?callable $change=null)use($db,$p,$observer,$blocker,$a,$one):array{
    $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[];
    try{
        foreach($actions as $action){
            $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/support/BbCnab400PreparationWorker.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process))throw new RuntimeException('worker unavailable');
            fwrite($pipes[0],json_encode(['action'=>$action,'issuer'=>$a,'actor'=>7,'preparation'=>$one['id']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];
        }
        $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
        essentialCheck($waiters>=2,'both400 workers wait on the actual financial mutex');
        if($change)$change();$db->trans_commit();$outputs=[];
        foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err==='','400 print/download worker completes without leakage');$outputs[]=$out;}
        return $outputs;
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
};
$raceResult400=$racePrint400(['print','download']);
$filePrint400=$prepare400->download($a,7,$one['id']);
essentialCheck($raceResult400===[$boleto['barcode'],$filePrint400['hash']],'400 print and first download serialize without changed instrument or remittance');
essentialCheck($racePrint400(['print','print'],fn()=>$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7'))===['denied','denied'],'revocation while400 printers wait prevents both disclosures');
$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
essentialCheck($prepare400->boleto($a,7,$one['id'])===$boleto,'first download does not change the printable instrument');
essentialCheck($prepare400->download($a,7,$one['id'])===$filePrint400,'printing does not alter already frozen400 bytes');
$boleto['barcode_svg']=\BancoBrasil\CnabBoleto::svg($boleto['barcode']);
ob_start();require APP_MODULES_PATH.'banco_brasil/views/cnab_boleto.php';$html=ob_get_clean();
essentialCheck(str_contains($html,'HOMOLOGAÇÃO — NÃO PAGAR')&&str_contains($html,$boleto['formatted_line']),'real shared print view renders400 data with explicit test status');
if(getenv('BB_BOLETO_HTML'))file_put_contents(getenv('BB_BOLETO_HTML'),$html);
echo 'CNAB400 printable instrument: '.$GLOBALS['essentialChecks'].' cumulative SQL checks; no bank validation.'.PHP_EOL;
