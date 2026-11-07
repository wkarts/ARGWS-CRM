<?php
require __DIR__.'/banco_brasil_origination_mysql.php';
// Emissions on different dates and nonconsecutive invoices share an eligible open batch.
$groupOne=$s->create($a,7,$input,hash('sha256','open-one'));
$laterInput=$input;$laterInput['date']='2026-10-21';
$groupTwo=$s->create($a,7,$laterInput,hash('sha256','open-two'));
$group=$bank->generate($a,7,$connection,$binding,'2026-10-20',[array_replace($title,['invoice_id'=>$groupOne])],hash('sha256','group-one'));
$groupAgain=$bank->generate($a,7,$connection,$binding,'2026-10-21',[array_replace($title,['invoice_id'=>$groupTwo])],hash('sha256','group-two'));
essentialCheck($groupAgain===$group,'different emission dates share compatible open batch');
essentialCheck($db->query("SELECT content FROM {$p}bb_batches WHERE id=?",[$group])->row()->content==='','open batch has no falsely frozen bytes');
$generationDates=$db->query('SELECT snapshot_json FROM '.$p.'bb_origin_snapshots s JOIN '.$p.'bb_batch_origins m ON m.origin_id=s.origin_id WHERE m.batch_id=? ORDER BY s.origin_id',[$group])->result_array();essentialCheck(array_map(static fn($row)=>json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR)['generation_date'],$generationDates)===['2026-10-20','2026-10-21'],'each generation retains its own date without rewriting old origin');
$bank->finish($a,7,$group);
essentialCheck($db->query("SELECT generation_state FROM {$p}bb_batches WHERE id=?",[$group])->row()->generation_state==='open','legacy resume action does not close open group');
$groupFile=$bank->download($a,7,$group);
essentialCheck(count(explode("\r\n",rtrim($groupFile['content'],"\r\n")))===8,'first download freezes both titles');
essentialCheck($bank->generate($a,7,$connection,$binding,'2026-10-21',[array_replace($title,['invoice_id'=>$groupTwo])],hash('sha256','group-two'))===$group,'second command retries after freeze without another number');
essentialThrows(fn()=>$bank->generate($a,7,$connection,$binding,'2026-10-22',[array_replace($title,['invoice_id'=>$groupTwo])],hash('sha256','group-two')),'bb_batch_request_conflict','command payload cannot change after freeze');
essentialCheck($bank->download($a,7,$group)===$groupFile,'redownload is byte-identical after command retry');
// Published frozen files remain downloadable without new command rows (no migration backfill).
$db->query("DELETE FROM {$p}bb_batch_requests WHERE batch_id=?",[$group]);
essentialCheck($bank->download($a,7,$group)===$groupFile,'legacy frozen file without command ledger remains byte-identical');
essentialThrows(fn()=>$bank->download($b,7,$group),'bb_batch_unavailable','grouped file cannot cross company boundary');
echo 'Open CNAB240 batches: '.$GLOBALS['essentialChecks'].' MySQL checks passed.'.PHP_EOL;
