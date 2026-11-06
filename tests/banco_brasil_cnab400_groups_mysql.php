<?php
/** Actual module migration, invoice writer, grouping and immutable export on disposable MySQL. */
require __DIR__.'/banco_brasil_cnab400_preparation_mysql.php';
$startGroups=$GLOBALS['essentialChecks'];
$approvalGroup=['currency_reference'=>'Synthetic currency contract','interest_reference'=>'Synthetic exemption contract','homologation_reference'=>'Synthetic homologation declaration','currency_confirmed'=>'1','interest_confirmed'=>'1','homologation_confirmed'=>'1'];
$prepare400->declareApproval($a,7,$one['id'],$approvalGroup);
$generatedGroup=$prepare400->generate($a,7,$one['id'],'2026-10-19',$payer400);
essentialCheck($generatedGroup['export_group']['state']==='open'&&$generatedGroup['export_group']['title_count']===1,'first title automatically joins open export group');
$createGroupTitle=static function(string $key,bool $generate=true)use($s,$a,$input,$prepare400,$connection,$binding400,$approvalGroup,$payer400):array{
    $invoice=$s->create($a,7,$input,hash('sha256','group-invoice-'.$key));
    $prepared=$prepare400->prepare($a,7,$connection,$binding400,$invoice,hash('sha256','group-prepare-'.$key));
    $prepare400->reserve($a,7,$prepared['id'],'1');$prepare400->declareApproval($a,7,$prepared['id'],$approvalGroup);
    return $generate?$prepare400->generate($a,7,$prepared['id'],'2026-10-20',$payer400):$prepared;
};
$nextGroupTitle=$createGroupTitle('later');
essentialCheck($nextGroupTitle['export_group']['id']===$generatedGroup['export_group']['id']&&$nextGroupTitle['export_group']['title_count']===2,'different emission dates share compatible open group');
$originsBeforeGroup=$db->query('SELECT * FROM '.$p.'bb_origins ORDER BY id')->result_array();
$snapshotsBeforeGroup=$db->query('SELECT * FROM '.$p.'bb_origin_snapshots ORDER BY origin_id')->result_array();
$sourceBytesBeforeGroup=$db->query('SELECT id,content,content_hash FROM '.$p.'bb_batches ORDER BY id')->result_array();
$invoiceBeforeGroup=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$paymentBeforeGroup=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
$replayGroup=$prepare400->generate($a,7,$one['id'],'2026-10-19',$payer400);
essentialCheck($replayGroup['export_group']['title_count']===2,'generation retry never duplicates a group member');
essentialThrows(fn()=>$prepare400->reportTransmission($a,7,$one['id'],'Synthetic reference'),'bb_batch_unavailable','open group is not a downloaded or transmitted file');
$db->failOn='INSERT INTO '.$p.'bb_audit';
essentialThrows(fn()=>$prepare400->download($a,7,$one['id']),'issuer_storage_failed','failed download audit rolls back freeze and disclosure states');$db->failOn=null;
essentialCheck($db->query('SELECT state FROM '.$p.'bb_cnab_groups WHERE id=?',[$generatedGroup['export_group']['id']])->row()->state==='open','audit failure leaves group open without frozen bytes');
$fileGroup=$prepare400->download($a,7,$nextGroupTitle['id']);
essentialCheck($fileGroup['title_count']===2&&count(explode("\r\n",rtrim($fileGroup['content'],"\r\n")))===4,'first download produces one header, two details and one trailer');
$linesGroup=explode("\r\n",$fileGroup['content']);
essentialCheck(substr($linesGroup[0],94,6)==='201026'&&substr($linesGroup[1],394,6)==='000002'&&substr($linesGroup[2],394,6)==='000003'&&substr($linesGroup[3],394,6)==='000004','real codec selects compatible generation date and consecutive file record sequence');
essentialCheck($prepare400->download($a,7,$one['id'])===$fileGroup,'download through another member returns identical frozen bytes and filename');
essentialCheck($db->query('SELECT * FROM '.$p.'bb_origins ORDER BY id')->result_array()===$originsBeforeGroup&&$db->query('SELECT * FROM '.$p.'bb_origin_snapshots ORDER BY origin_id')->result_array()===$snapshotsBeforeGroup,'grouping/export never moves or rewrites immutable origins and snapshots');
essentialCheck($db->query('SELECT id,content,content_hash FROM '.$p.'bb_batches ORDER BY id')->result_array()===$sourceBytesBeforeGroup,'individual validated source bytes remain unchanged');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$invoiceBeforeGroup&&$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array()===$paymentBeforeGroup,'download creates neither invoices nor financial payments');
$db->query('UPDATE '.$p.'bb_cnab_groups SET content_hash=? WHERE id=?',[str_repeat('0',64),$fileGroup['group_id']]);
essentialThrows(fn()=>$prepare400->download($a,7,$one['id']),'bb400_group_corrupt','corrupted frozen envelope is rejected rather than regenerated');
$db->query('UPDATE '.$p.'bb_cnab_groups SET content_hash=? WHERE id=?',[$fileGroup['hash'],$fileGroup['group_id']]);
$afterFreezeGroup=$createGroupTitle('after-freeze');
essentialCheck($afterFreezeGroup['export_group']['id']!==$fileGroup['group_id']&&$afterFreezeGroup['export_group']['state']==='open','new emission after first download opens another group');
essentialCheck($prepare400->download($a,7,$one['id'])===$fileGroup,'later generation does not modify previous export');
essentialThrows(fn()=>$prepare400->download($b,7,$one['id']),'bb400_preparation_unavailable','known foreign preparation cannot expose grouped file');
essentialThrows(fn()=>$prepare400->download($a,999,$one['id']),'bb400_preparation_access_denied','unauthorized actor cannot download group');
$prepare400->reportTransmission($a,7,$one['id'],'Synthetic reference');
foreach([$one,$nextGroupTitle] as $member){
    $state=$db->query('SELECT b.delivery_state FROM '.$p.'bb_batches b JOIN '.$p.'bb_cnab_preparations p ON p.batch_id=b.id WHERE p.id=?',[$member['id']])->row()->delivery_state;
    essentialCheck($state==='reported_submitted','explicit group report marks every member as operator-reported only');
}
$prepare400->reportTransmission($a,7,$nextGroupTitle['id'],'Synthetic reference');
essentialThrows(fn()=>$prepare400->reportTransmission($a,7,$nextGroupTitle['id'],'Different reference'),'bb_submission_report_conflict','conflicting report cannot overwrite frozen group history');
essentialCheck($prepare400->download($a,7,$one['id'])===$fileGroup,'reported transmission does not change exported bytes');


// Another real account/contract gets a separate open export envelope.
[$separateConnection,$unused240]=bbSyntheticConnection($a,'7654321','001:00452-3:000000123875-X');
$separateStored=$store->connection($separateConnection,$a);$separateBinding=$store->registerLayout($separateStored,7,\BancoBrasil\LayoutCatalog::CNAB400);
$bank->declareContract($a,7,$separateConnection,$separateBinding,$contract,\BancoBrasil\LayoutCatalog::CNAB400);
$separateInvoice=$s->create($a,7,$input,hash('sha256','separate-group-invoice'));
$separatePrepared=$prepare400->prepare($a,7,$separateConnection,$separateBinding,$separateInvoice,hash('sha256','separate-group-preparation'));
$prepare400->reserve($a,7,$separatePrepared['id'],'1');$prepare400->declareApproval($a,7,$separatePrepared['id'],$approvalGroup);
$separateGenerated=$prepare400->generate($a,7,$separatePrepared['id'],'2026-10-20',$payer400);
essentialCheck($separateGenerated['export_group']['id']!==$afterFreezeGroup['export_group']['id'],'actual different account/agreement never joins otherwise open company group');
$separateFile=$prepare400->download($a,7,$separatePrepared['id']);
essentialCheck($separateFile['title_count']===1&&substr($separateFile['content'],129,7)==='7654321','separate export contains only its exact verified bank agreement');
$racePreparedGroup=$createGroupTitle('race',false);
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$groupWorkers=[];
try{
    foreach([
        ['action'=>'generate','preparation'=>$racePreparedGroup['id'],'date'=>'2026-10-20','payer'=>$payer400],
        ['action'=>'download','preparation'=>$afterFreezeGroup['id']],
    ] as $request){
        $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/support/BbCnab400PreparationWorker.php',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('group worker unavailable');
        fwrite($pipes[0],json_encode($request+['issuer'=>$a,'actor'=>7],JSON_THROW_ON_ERROR));fclose($pipes[0]);$groupWorkers[]=[$process,$pipes];
    }
    $deadline=microtime(true)+15;$waiters=0;
    do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
    essentialCheck($waiters>=2,'generation and download wait on the same real MySQL financial mutex');
    $db->trans_commit();$groupResults=[];
    foreach($groupWorkers as [$process,$pipes]){
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        essentialCheck(proc_close($process)===0&&$err==='','group race worker completes without error');$groupResults[]=$out;
    }
    $raceFile=$prepare400->download($a,7,$afterFreezeGroup['id']);
    essentialCheck($raceFile['hash']===$groupResults[1],'concurrent issuance never changes a file after first download');
    $raceSource=$prepare400->recover($a,7,$racePreparedGroup['id']);
    $joined=$raceSource['export_group']['id']===$raceFile['group_id'];
    essentialCheck($joined?$raceFile['title_count']===2:($raceFile['title_count']===1&&$raceSource['export_group']['state']==='open'),'racing title belongs either to frozen group before download or a new open group afterwards');
    essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'bb_cnab_group_members WHERE preparation_id=?',[$racePreparedGroup['id']])->row()->n===1,'race produces exactly one export membership');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();}

$overviewGroup=$prepare400->groups($a,7);
essentialCheck(count($overviewGroup)>=3&&!isset($overviewGroup[0]['content'],$overviewGroup[0]['snapshot_json']),'native group overview exposes only public operational metadata');
essentialCheck($prepare400->groups($b,7)===[],'other company sees no grouped export metadata');
$viewFile=APP_MODULES_PATH.'banco_brasil/views/index.php';
essentialCheck(str_contains(file_get_contents($viewFile),'Lotes CNAB400 da empresa'),'operational native view includes group table without replacing shell');
echo 'CNAB400 groups: '.($GLOBALS['essentialChecks']-$startGroups).' added real SQL checks; no bank calls or actual homologation.'.PHP_EOL;
