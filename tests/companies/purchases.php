<?php
declare(strict_types=1);
require __DIR__.'/purchases_fixture.php';
$initial=purchaseSnapshot(); $x=purchaseOrder($a,12,'A'); $y=purchaseOrder($b,12,'B'); $z=purchaseOrder($primary,7,'P');
purchaseCheck(count($svc->page($a,12,'order')['items'])===1&&count($svc->page($b,12,'order')['items'])===1&&count($svc->page($primary,7,'order')['items'])===1,'A/B/principal each have a functional isolated positive order');
purchaseCheck((int)$svc->get($a,12,'order',$x['order'])['number']===1&&(int)$svc->get($b,12,'order',$y['order'])['number']===1,'per-company monotonic numbering');
purchaseThrows(fn()=>$svc->get($b,12,'order',$x['order']),'company_purchase_denied','foreign direct order denied');
purchaseThrows(fn()=>$svc->page($a,13,'order'),'company_purchase_denied','legacy admin never gains secondary purchasing');
purchaseThrows(fn()=>$svc->receive($a,14,$x['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'1.000']]],$key('reader-receive')),'company_purchase_denied','reader cannot receive');
$r1=$svc->receive($a,12,$x['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'1.000']],'note'=>'First partial'],$key('A-receipt-1'));
$po=$svc->get($a,12,'order',$x['order']); purchaseCheck($po['state']==='part_received'&&$po['receiving']['quantities'][1]===1000,'first partial receipt advances exact quantity');
purchaseCheck($svc->receive($a,12,$x['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'1.000']],'note'=>'First partial'],$key('A-receipt-1'))===$r1,'receipt exact retry returns persisted ID');
purchaseThrows(fn()=>$svc->receive($a,12,$x['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'1.000']]],$key('stale-receipt')),'company_purchase_stale','stale revision cannot receive twice');
purchaseThrows(fn()=>$svc->receive($a,12,$x['order'],2,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'2.001']]],$key('over-receipt')),'company_purchase_receipt_exceeds_order','over receipt blocked');
$pay1=purchaseDocument($a,12,$x['order'],[$r1],'A1'); $ap=$svc->get($a,12,'payable',$pay1); $pos=$ap['position'];
purchaseCheck((int)$ap['amount_cents']===1101&&$pos['outstanding_cents']===1101,'partial receipt posted once to canonical expense and immutable AP');
$expense=$expenseService->get($a,12,(int)$ap['expense_id']); purchaseCheck((int)$expense['total_cents']===1101&&(int)$expense['revision']===1,'expense totals exactly match AP document');
purchaseCheck(purchaseDocument($a,12,$x['order'],[$r1],'A1')===$pay1,'document retry has one expense/AP');
purchaseThrows(fn()=>purchaseDocument($a,12,$x['order'],[$r1],'A1-DUP'),'company_purchase_receipt_already_billed','another key cannot double bill same receipt');
$r2=$svc->receive($a,12,$x['order'],2,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'2.000']]],$key('A-receipt-2'));
$pay2=purchaseDocument($a,12,$x['order'],[$r2],'A2'); $ap2=$svc->get($a,12,'payable',$pay2);
purchaseCheck((int)$ap2['amount_cents']===2202&&$svc->get($a,12,'order',$x['order'])['state']==='received','remaining receipt generates separate immutable document/AP and completes order');
purchaseCheck($svc->get($a,12,'payable',$pay1)['record_hash']===$ap['record_hash'],'later receipt never rewrites first payable');
$financialBefore=$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_sequence')->result_array();
$event=$svc->settle($a,12,$pay1,$pos['revision_token'],['amount'=>'5.00','event_date'=>$today,'external_reference'=>'External transfer declared by operator','note'=>'Manual evidence','acknowledged'=>1],$key('settle-A1'));
$after=$svc->get($a,12,'payable',$pay1)['position']; purchaseCheck($after['declared_paid_cents']===500&&$after['outstanding_cents']===601,'partial external settlement declaration reduces only own AP');
purchaseThrows(fn()=>$svc->settle($a,12,$pay1,$after['revision_token'],['amount'=>'6.02','event_date'=>$today,'external_reference'=>'too much','acknowledged'=>1],$key('over-pay')),'company_purchase_settlement_exceeds_balance','excess settlement denied');
purchaseThrows(fn()=>$svc->settle($b,12,$pay1,$after['revision_token'],['amount'=>'1.00','event_date'=>$today,'external_reference'=>'cross','acknowledged'=>1],$key('cross-pay')),'company_purchase_denied','foreign payable settlement denied');
$reverse=$svc->reverseSettlement($a,12,$event,$after['revision_token'],['event_date'=>$today,'reason'=>'Operator corrected declaration; no bank refund executed','acknowledged'=>1],$key('reverse-A1'));
$restored=$svc->get($a,12,'payable',$pay1)['position']; purchaseCheck($restored['outstanding_cents']===1101&&count($restored['events'])===2,'reversal appends evidence and restores AP');
purchaseThrows(fn()=>$svc->reverseSettlement($a,12,$event,$restored['revision_token'],['event_date'=>$today,'reason'=>'duplicate','acknowledged'=>1],$key('reverse-twice')),'company_purchase_reversal_conflict','double reversal denied');
purchaseCheck($db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_sequence')->result_array()===$financialBefore,'settlement/reversal never duplicates expense, cash or AR journal');
$final=purchaseSnapshot(); foreach (['invoices','expenses','pur_orders','pur_vendor'] as $table) { purchaseCheck($final[$table]===$initial[$table],'legacy '.$table.' unchanged byte-for-byte'); }
purchaseCheck(count($final['company_expenses'])===2&&count($final['company_purchase_payables'])===2&&count($final['company_financial_events'])===2,'exactly one canonical cost and AP per posted document');
foreach ([[$b,12,$y,'B'],[$primary,7,$z,'P']] as [$owner,$actor,$chain,$label]) {
    $receipt=$svc->receive($owner,$actor,$chain['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'3.000']]],$key($label.'-complete-receipt'));
    $payable=purchaseDocument($owner,$actor,$chain['order'],[$receipt],$label.'-complete'); $row=$svc->get($owner,$actor,'payable',$payable);
    purchaseCheck((int)$row['amount_cents']===3303&&(int)$expenseService->get($owner,$actor,(int)$row['expense_id'])['issuer_id']===$owner,$label.' receipt/document/AP has its own canonical expense');
    $journal=$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_id,issuer_sequence')->result_array();
    $event=$svc->settle($owner,$actor,$payable,$row['position']['revision_token'],['amount'=>'33.03','event_date'=>$today,'external_reference'=>'External '.$label,'acknowledged'=>1],$key($label.'-complete-settlement'));
    $position=$svc->get($owner,$actor,'payable',$payable)['position']; purchaseCheck($position['outstanding_cents']===0,$label.' full manual external settlement reaches zero AP');
    $svc->reverseSettlement($owner,$actor,$event,$position['revision_token'],['event_date'=>$today,'reason'=>'Correction '.$label,'acknowledged'=>1],$key($label.'-complete-reversal'));
    purchaseCheck($svc->get($owner,$actor,'payable',$payable)['position']['outstanding_cents']===3303,$label.' reversal restores only its original AP');
    purchaseCheck($journal===$db->query('SELECT * FROM '.$p.'company_financial_events ORDER BY issuer_id,issuer_sequence')->result_array(),$label.' settlement/reversal has no duplicate cost or cash movement');
}
$fraction=purchaseOrder($a,12,'fraction','1.000','0.01','0.00'); $fractionReceipts=[];
foreach (['0.333','0.333','0.334'] as $index=>$quantity) { $fractionReceipts[]=$svc->receive($a,12,$fraction['order'],$index+1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>$quantity]]],$key('fraction-'.$index)); }
purchaseCheck(array_map(fn($id)=>(int)$svc->get($a,12,'receipt',$id)['base_cents'],$fractionReceipts)===[0,1,0],'cumulative rounding allocates one cent across three fractional receipts exactly');
purchaseThrows(fn()=>purchaseDocument($a,12,$fraction['order'],[$fractionReceipts[0]],'fraction-zero'),'company_purchase_invalid','a zero-cost partial alone cannot create an empty financial obligation');
$fractionPayable=purchaseDocument($a,12,$fraction['order'],$fractionReceipts,'fraction-all'); purchaseCheck((int)$svc->get($a,12,'payable',$fractionPayable)['amount_cents']===1,'aggregating fractional receipts preserves the full one-cent cost');
foreach (['1e2','01.000','1.0001','-1.000',1.0] as $bad) { purchaseThrows(fn()=>$svc->createRequest($a,12,['title'=>'Invalid precision','needed_date'=>$today,'currency'=>'BRL','lines'=>[['description'=>'x','unit'=>'UN','quantity'=>$bad]]],$key('invalid-'.serialize($bad))),'company_purchase_invalid','quantity rejects noncanonical or lossy decimal input'); }
$afterChains=purchaseSnapshot(); foreach (['invoices','expenses','pur_orders','pur_vendor'] as $table) { purchaseCheck($afterChains[$table]===$initial[$table],'complete A/B/principal/fraction chain preserves legacy '.$table); }
echo 'Purchases core: '.$GLOBALS['essentialChecks'].' checks passed on real '.$f->db->driver().PHP_EOL;
