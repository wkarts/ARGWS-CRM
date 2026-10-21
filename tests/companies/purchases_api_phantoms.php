<?php
declare(strict_types=1);
require __DIR__.'/purchases_fixture.php'; require_once APPPATH.'services/CompanyPurchasesApi.php';
foreach (CompanyPurchasesApi::SCOPES as $scope=>$caps) { if ((CompanyApiTokens::SCOPES[$scope]??null)!==$caps) { throw new RuntimeException('Finite Purchase API scope integration missing'); } }
CompanyApiSchema::ensure($db,$p); $db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'company_api','manage')",[$a]);
$tokens=new CompanyApiTokens($db,$p); $credential=$tokens->issue($a,12,'Synthetic final-read witness',['purchases:read','purchases:report'],1,$key('phantom-token')); $api=new CompanyPurchasesApi($db,$p);
$chain=purchaseOrder($a,12,'reader-witness'); $receipt=$svc->receive($a,12,$chain['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'3.000']]],$key('reader-witness-receipt')); $payable=purchaseDocument($a,12,$chain['order'],[$receipt],'reader-witness');
function purchasePhantomSnapshot(): array { global $db,$p; $out=purchaseSnapshot(); $out['api_tokens']=$db->query('SELECT * FROM '.$p.'company_api_tokens ORDER BY token_id')->result_array(); return $out; }
$row=['issuer_id'=>$a,'payable_id'=>$payable,'kind'=>'manual_external_settlement','amount_cents'=>100,'currency'=>'BRL','event_date'=>$today,'external_reference'=>'Late valid event','note'=>'Synthetic trigger','reverses_id'=>null,'file_id'=>null,'created_by'=>12,'created_at'=>gmdate('Y-m-d H:i:s')]; $row['record_hash']=CompanyPurchasesValues::hash($row);
$insert='INSERT INTO '.$p.'company_purchase_events (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_map(fn($value)=>$value===null?'NULL':$db->pdo->quote((string)$value),array_values($row))).')';
$reads=['get'=>fn()=>$api->read($credential['secret'],'payables',$payable),'page'=>fn()=>$api->read($credential['secret'],'payables'),'report'=>fn()=>$api->read($credential['secret'],'reports',null,['from'=>'2000-01-01','as_of'=>$today]),'export'=>fn()=>$api->read($credential['secret'],'export',null,['from'=>'2000-01-01','as_of'=>$today])];
foreach ($reads as $name=>$read) {
    $before=purchasePhantomSnapshot(); $db->query("CREATE TRIGGER {$p}purchase_ap_phantom AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_api_request_authorized' THEN ".$insert."; END IF; END");
    purchaseThrows($read,'issuer_purchase_write_unconfirmed','API '.$name.' cannot confirm a stale balance after a valid event is inserted by its final audit');
    purchaseCheck(purchasePhantomSnapshot()===$before,'API '.$name.' rolls back late event, token usage and audit without stale success'); $db->query('DROP TRIGGER '.$p.'purchase_ap_phantom');
}
$vendor=$svc->get($a,12,'supplier',$chain['supplier']); unset($vendor['id']); $vendor['name']='Late supplier row';
$insert='INSERT INTO '.$p.'company_purchase_suppliers (`'.implode('`,`',array_keys($vendor)).'`) VALUES('.implode(',',array_map(fn($value)=>$value===null?'NULL':$db->pdo->quote((string)$value),array_values($vendor))).')';
$before=purchasePhantomSnapshot(); $db->query("CREATE TRIGGER {$p}purchase_page_phantom AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_api_request_authorized' THEN ".$insert."; END IF; END");
purchaseThrows(fn()=>$api->read($credential['secret'],'suppliers'),'issuer_purchase_write_unconfirmed','paginated ID collection cannot omit an added matching row after API audit'); purchaseCheck(purchasePhantomSnapshot()===$before,'page collection phantom leaves zero delta'); $db->query('DROP TRIGGER '.$p.'purchase_page_phantom');
purchaseCheck($api->read($credential['secret'],'payables',$payable)['position']['outstanding_cents']===3303&&count($api->read($credential['secret'],'suppliers')['items'])===1,'unchanged API read and collection remain positively usable');
$position=$api->read($credential['secret'],'payables',$payable)['position'];
$eventId=$svc->settle($a,12,$payable,$position['revision_token'],['amount'=>'1.00','event_date'=>$today,'external_reference'=>'Initial owned declaration','acknowledged'=>1],$key('initial-event-reader'));
$eventInsert='INSERT INTO '.$p.'company_purchase_events (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_map(fn($value)=>$value===null?'NULL':$db->pdo->quote((string)$value),array_values($row))).')';
foreach (['event detail'=>fn()=>$api->read($credential['secret'],'events',$eventId),'event page'=>fn()=>$api->read($credential['secret'],'events'),'empty event page'=>fn()=>$api->read($credential['secret'],'events',null,['after'=>$eventId])] as $label=>$read) {
    $before=purchasePhantomSnapshot();
    $db->query("CREATE TRIGGER {$p}purchase_event_phantom AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_api_request_authorized' THEN ".$eventInsert."; END IF; END");
    purchaseThrows($read,'issuer_purchase_write_unconfirmed',$label.' cannot return success when its AP event collection changes after the final API audit');
    purchaseCheck(purchasePhantomSnapshot()===$before,$label.' final-collection denial rolls back event, audit and token usage');
    $db->query('DROP TRIGGER '.$p.'purchase_event_phantom');
}
echo 'Purchases final collection witnesses: '.$GLOBALS['essentialChecks'].' checks passed on '.$db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
