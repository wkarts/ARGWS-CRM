<?php
declare(strict_types=1);
require __DIR__.'/purchases_fixture.php'; require_once APPPATH.'services/CompanyPurchasesApi.php';
foreach (CompanyPurchasesApi::SCOPES as $scope=>$caps) { if ((CompanyApiTokens::SCOPES[$scope]??null)!==$caps) { throw new RuntimeException('Finite Purchase token scope integration missing: '.$scope); } }
CompanyApiSchema::ensure($db,$p); foreach ([$a,$b] as $owner) { $db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'company_api','manage')",[$owner]); }
$tokens=new CompanyApiTokens($db,$p); $api=new CompanyPurchasesApi($db,$p); $all=array_keys(CompanyPurchasesApi::SCOPES);
$issued=[]; foreach ([$a,$b,$primary] as $owner) { $issued[$owner]=$tokens->issue($owner,$owner===$primary?7:12,'Synthetic Purchase '.$owner,$all,1,$key('api-token-'.$owner)); }
$ta=$issued[$a]; $tb=$issued[$b]; $ro=$tokens->issue($a,12,'Purchase read',['purchases:read'],1,$key('api-read')); $write=$tokens->issue($a,12,'Purchase write',['purchases:write'],1,$key('api-write'));
function purchaseApiSnapshot(): array { global $db,$p; $out=purchaseSnapshot(); foreach (['company_api_tokens'=>'token_id','company_api_token_requests'=>'request_key'] as $table=>$sort) { $out[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.$sort)->result_array(); } return $out; }
$chains=[];
foreach ([$a,$b,$primary] as $owner) {
    $secret=$issued[$owner]['secret']; $tag='api-'.$owner;
    $supplier=$api->write($secret,'suppliers',null,['action'=>'create','input'=>['name'=>'Vendor '.$tag,'email'=>'supplier@example.invalid']],$key($tag.'-supplier'))['result'];
    $api->write($secret,'suppliers',$supplier,['action'=>'update','revision'=>1,'input'=>['name'=>'Vendor '.$tag.' updated','email'=>'supplier@example.invalid','active'=>1]],$key($tag.'-supplier-update'));
    $body=['action'=>'create','input'=>['title'=>'RFQ '.$tag,'needed_date'=>$today,'currency'=>'BRL','lines'=>[['description'=>'API item','unit'=>'UN','quantity'=>'2.000']]]];
    $response=$api->write($secret,'requests',null,$body,$key($tag.'-request')); $request=$response['result'];
    purchaseCheck($response['issuer_id']===$owner&&$api->write($secret,'requests',null,$body,$key($tag.'-request'))===$response,'API derives persisted company '.$owner.' and returns exact retry');
    $api->write($secret,'requests',$request,['action'=>'approve','revision'=>1,'input'=>[]],$key($tag.'-approve'));
    $quote=$api->write($secret,'quotes',null,['action'=>'create','input'=>['request_id'=>$request,'supplier_id'=>$supplier,'valid_until'=>$today,'unit_prices'=>['5.00'],'tax_rate'=>'0.00']],$key($tag.'-quote'))['result'];
    $order=$api->write($secret,'orders',null,['action'=>'create','input'=>['quote_id'=>$quote,'request_revision'=>2,'order_date'=>$today,'expected_date'=>$today]],$key($tag.'-order'))['result'];
    $receipt=$api->write($secret,'receipts',$order,['action'=>'create','revision'=>1,'input'=>['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'2.000']]]],$key($tag.'-receive'))['result'];
    $payable=$api->write($secret,'documents',null,['action'=>'post','input'=>['order_id'=>$order,'receipt_ids'=>[$receipt],'vendor_number'=>'INV-'.$tag,'document_date'=>$today,'due_date'=>$today,'category_id'=>$categories[$owner]]],$key($tag.'-post'))['result'];
    $position=$api->read($secret,'payables',$payable)['position']; purchaseCheck($position['outstanding_cents']===1000,'API company '.$owner.' receipt posts one canonical cost and AP');
    $event=$api->write($secret,'payables',$payable,['action'=>'settle','revision'=>$position['revision_token'],'input'=>['amount'=>'4.00','event_date'=>$today,'external_reference'=>'API external declaration','acknowledged'=>1]],$key($tag.'-settle'))['result'];
    $position=$api->read($secret,'payables',$payable)['position']; purchaseCheck($position['outstanding_cents']===600,'API company '.$owner.' settlement updates only AP balance');
    $api->write($secret,'events',$event,['action'=>'reverse','revision'=>$position['revision_token'],'input'=>['event_date'=>$today,'reason'=>'API correction','acknowledged'=>1]],$key($tag.'-reverse'));
    purchaseCheck($api->read($secret,'payables',$payable)['position']['outstanding_cents']===1000,'API company '.$owner.' append-only reversal restores balance');
    $chains[$owner]=compact('supplier','request','quote','order','receipt','payable','event');
}
$x=$chains[$a]; $requestBody=['action'=>'create','input'=>['title'=>'Scope test','needed_date'=>$today,'currency'=>'BRL','lines'=>[['description'=>'item','unit'=>'UN','quantity'=>'1.000']]]];
purchaseCheck(count($api->read($ro['secret'],'requests')['items'])===1&&count($api->read($tb['secret'],'requests')['items'])===1,'read token lists only its company positive data');
purchaseThrows(fn()=>$api->read($tb['secret'],'orders',$x['order']),'company_purchase_denied','foreign API token cannot read another company order');
purchaseThrows(fn()=>$api->write($ro['secret'],'requests',null,$requestBody,$key('readonly-create')),'company_api_scope_denied','read token cannot create');
purchaseThrows(fn()=>$api->write($write['secret'],'requests',$x['request'],['action'=>'approve','revision'=>3,'input'=>[]],$key('write-approve')),'company_api_scope_denied','generic write never implies approval');
purchaseThrows(fn()=>$api->write($write['secret'],'payables',$x['payable'],['action'=>'settle','revision'=>'not-used','input'=>[]],$key('write-pay')),'company_api_scope_denied','generic write never implies manual payment authority');
purchaseThrows(fn()=>$api->write($write['secret'],'invites',$x['request'],['action'=>'issue','input'=>['supplier_id'=>$x['supplier'],'days'=>1]],$key('write-portal')),'company_api_scope_denied','generic write never grants portal invitations');
[$direct]=CompanyPurchases::forApi($db,$p,$ta['secret'],'purchases:write'); purchaseThrows(fn()=>$direct->approveRequest($a,12,$x['request'],3,$key('direct-approve')),'company_api_scope_denied','service-level scope guard cannot be bypassed by a broader raw token');
purchaseThrows(fn()=>$api->read('invalid','requests'),'company_api_unauthenticated','malformed token is never identity');
purchaseThrows(fn()=>$api->write($ta['secret'],'requests',null,$requestBody+['issuer_id'=>$b],$key('issuer-override')),'company_purchase_invalid','body cannot replace persisted company');
purchaseThrows(fn()=>$api->write($ta['secret'],'requests',null,['action'=>'create','input'=>$requestBody['input']+['actor_id'=>7]],$key('actor-override')),'company_purchase_invalid','payload cannot impersonate installation master');
purchaseThrows(fn()=>$api->write($ta['secret'],'orders',$x['order'],['action'=>'send_bank_order','input'=>[]],$key('unsupported-bank')),'company_api_invalid_input','unsupported bank action is not reported as implemented');
$pdf="%PDF-1.4\n%%EOF\n"; $file=$api->write($ta['secret'],'files',$x['request'],['action'=>'upload','input'=>['filename'=>'api.pdf','content_base64'=>base64_encode($pdf),'supplier_id'=>$x['supplier'],'portal_visible'=>1]],$key('api-upload'))['result'];
purchaseCheck($api->file($ro['secret'],$file)['content']===$pdf&&count($api->read($ta['secret'],'files',$x['request']))===1,'API uploads and downloads owned exact attachment bytes');
purchaseThrows(fn()=>$api->file($tb['secret'],$file),'company_purchase_denied','foreign token cannot download a known attachment');
purchaseCheck(str_starts_with($api->read($ta['secret'],'order_pdf',$x['order']),'%PDF-'),'API renders actual owned purchase order PDF');
$invite=$api->write($ta['secret'],'invites',$x['request'],['action'=>'issue','input'=>['supplier_id'=>$x['supplier'],'days'=>1]],$key('api-invite'))['result'];
purchaseCheck(is_string($invite['secret'])&&count($api->read($ta['secret'],'invites',$x['request']))===1,'portal scope issues a bound supplier invitation');
$api->write($ta['secret'],'invites',$invite['id'],['action'=>'revoke','revision'=>1,'input'=>[]],$key('api-revoke-invite'));
[$portal]=CompanyPurchases::forPortal($db,$p,$invite['secret']); purchaseThrows(fn()=>$portal->portalView(),'company_purchase_portal_denied','API invitation revocation invalidates existing credentials');
$api->write($ta['secret'],'settings',null,['action'=>'save','revision'=>1,'input'=>['prefix'=>'API_A','base_currency'=>'BRL','rates'=>['BRL'=>'1.000000','USD'=>'5.250000']]],$key('api-settings'));
purchaseCheck($api->read($ro['secret'],'settings')['prefix']==='API_A','settings scope changes only own explicit numbering and FX configuration');
purchaseCheck($api->read($ta['secret'],'reports',null,['from'=>'2000-01-01','as_of'=>$today])['totals_for_this_page']['BRL']['outstanding_cents']===1000&&str_contains($api->read($ta['secret'],'export',null,['from'=>'2000-01-01','as_of'=>$today]),'INV-api-'.$a),'report scope derives owned AP and CSV from canonical sources');
purchaseThrows(fn()=>$api->read($ro['secret'],'reports',null,['from'=>'2000-01-01','as_of'=>$today]),'company_api_scope_denied','generic read does not imply sensitive AP reporting');
$before=purchaseApiSnapshot(); $db->query("CREATE TRIGGER {$p}purchase_api_late AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_api_request_authorized' THEN UPDATE {$p}company_purchase_requests SET title='Late corruption' WHERE issuer_id=NEW.issuer_id; END IF; END");
purchaseThrows(fn()=>$api->write($ta['secret'],'requests',null,$requestBody,$key('late-api')),'issuer_purchase_write_unconfirmed','last API usage audit cannot corrupt earlier business readback'); purchaseCheck(purchaseApiSnapshot()===$before,'late API corruption rolls back business receipt audit and token usage'); $db->query('DROP TRIGGER '.$p.'purchase_api_late');
$db->query("CREATE TRIGGER {$p}purchase_api_revoke AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_api_request_authorized' THEN UPDATE {$p}company_api_tokens SET active=0 WHERE token_id=JSON_UNQUOTE(JSON_EXTRACT(NEW.metadata_json,'$.token_id')); END IF; END");
purchaseThrows(fn()=>$api->write($ta['secret'],'requests',null,$requestBody,$key('late-api-revoke')),'company_api_unauthenticated','token is revalidated after final API audit'); purchaseCheck(purchaseApiSnapshot()===$before,'late credential revocation cannot commit a business effect'); $db->query('DROP TRIGGER '.$p.'purchase_api_revoke');
$tokens->revoke($a,12,$ta['token_id']); purchaseThrows(fn()=>$api->read($ta['secret'],'requests'),'company_api_unauthenticated','persisted API revocation immediately closes reads');
$db->query("UPDATE {$p}company_api_tokens SET expires_at='2000-01-01 00:00:00' WHERE token_id=?",[$ro['token_id']]); purchaseThrows(fn()=>$api->read($ro['secret'],'requests'),'company_api_unauthenticated','expired API credentials cannot reuse company authority');
purchaseCheck(count($db->query('SELECT * FROM '.$p.'company_financial_events')->result_array())===3,'three API company chains each produce one expense event and no settlement cash/AR event');
echo 'Purchases finite API: '.$GLOBALS['essentialChecks'].' checks passed on '.$db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
