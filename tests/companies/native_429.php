<?php
/** NOOP429 preserves every current financial and portal publication row. */
require __DIR__.'/native_428.php';
$preserved429=[];$native429tables=['invoices'=>'id','invoicepaymentrecords'=>'id','company_bank_receipt_components'=>'payment_id','company_bank_receipt_reversals'=>'request_key','company_financial_events'=>'evidence_key'];
foreach(CompanyPortalOperationsSchema::definitions($p) as $table=>$definition)$native429tables[$table]=str_contains($table,'requests')?'request_key':(str_contains($table,'files')?'file_key':($table==='company_portal_ticket_replies'?'reply_id':'issuer_id'));
foreach($native429tables as $table=>$order)$preserved429[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.$order)->result_array();
require_once APPPATH.'migrations/429_version_429.php';$m429=new Migration_Version_429();$m429->db=$db;$m429->up();$m429->up();
foreach($preserved429 as $table=>$rows)essentialCheck($rows===$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.$native429tables[$table])->result_array(),'native429 NOOP/replay preserves '.$table.' originals and publication bytes');
CompanyPortalOperationsSchema::assertValid($db,$p);CompanyBankReversalSchema::assertValid($db,$p);
echo 'Native429 NOOP/replay original financial and private publication rows: '.$GLOBALS['essentialChecks'].' cumulative actual database checks. Native HTTP runner tracking is a separate proof.'.PHP_EOL;
