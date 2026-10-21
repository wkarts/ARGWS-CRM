<?php

declare(strict_types=1);
require __DIR__.'/purchases_fixture.php';
require_once APPPATH.'services/CompanyPurchasesApi.php';

CompanyApiSchema::ensure($db,$p);
foreach ([$a,$b] as $issuer) {
    $db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'company_api','manage')",[$issuer]);
}
$tokens=new CompanyApiTokens($db,$p); $api=new CompanyPurchasesApi($db,$p); $secrets=[]; $chain=[];
foreach ([$a,$b] as $issuer) {
    $secrets[$issuer]=$tokens->issue($issuer,12,'Purchase boundary matrix',array_keys(CompanyPurchasesApi::SCOPES),1,$key('matrix-token-'.$issuer))['secret'];
}
$call=static fn(int $issuer,string $resource,$id,string $action,array $input,string $tag,$revision=null,bool $hasRevision=false)=>$api->write($secrets[$issuer],$resource,$id,['action'=>$action,'input'=>$input]+($hasRevision?['revision'=>$revision]:[]),$key('matrix-'.$tag))['result'];
$requestInput=static fn(string $title): array=>['title'=>$title,'needed_date'=>$today,'currency'=>'BRL','lines'=>[['description'=>'Boundary item','unit'=>'UN','quantity'=>'1.000']]];
$pdf="%PDF-1.4\n%%EOF\n";
foreach ([$a,$b] as $issuer) {
    $tag='owned-'.$issuer;
    $supplier=$call($issuer,'suppliers',null,'create',['name'=>'Unique supplier '.$tag],$tag.'-supplier');
    $call($issuer,'suppliers',$supplier,'update',['name'=>'Unique supplier '.$tag,'active'=>1],$tag.'-supplier-update',1,true);
    $request=$call($issuer,'requests',null,'create',$requestInput('Boundary request '.$tag),$tag.'-request');
    $call($issuer,'requests',$request,'approve',[],$tag.'-approve',1,true);
    $quote=$call($issuer,'quotes',null,'create',['request_id'=>$request,'supplier_id'=>$supplier,'valid_until'=>$today,'unit_prices'=>['5.00'],'tax_rate'=>'0.00'],$tag.'-quote');
    $order=$call($issuer,'orders',null,'create',['quote_id'=>$quote,'request_revision'=>2,'order_date'=>$today,'expected_date'=>$today],$tag.'-order');
    $receipt=$call($issuer,'receipts',$order,'create',['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'1.000']]],$tag.'-receipt',1,true);
    $file=$call($issuer,'files',$request,'upload',['filename'=>'matrix-'.$issuer.'.pdf','content_base64'=>base64_encode($pdf),'supplier_id'=>$supplier],$tag.'-file');
    $payable=$call($issuer,'documents',null,'post',['order_id'=>$order,'receipt_ids'=>[$receipt],'vendor_number'=>'MATRIX-'.$issuer,'document_date'=>$today,'due_date'=>$today,'category_id'=>$categories[$issuer],'file_id'=>$file],$tag.'-document');
    $ap=$api->read($secrets[$issuer],'payables',$payable); $document=(int)$ap['document_id'];
    $event=$call($issuer,'payables',$payable,'settle',['amount'=>'1.00','event_date'=>$today,'external_reference'=>'Declared outside this system','acknowledged'=>1,'file_id'=>$file],$tag.'-settle',$ap['position']['revision_token'],true);
    $invite=$call($issuer,'invites',$request,'issue',['supplier_id'=>$supplier,'days'=>1],$tag.'-invite');
    $open=$call($issuer,'requests',null,'create',$requestInput('Open boundary request '.$tag),$tag.'-open');
    $chain[$issuer]=compact('supplier','request','quote','order','receipt','document','payable','event','file','invite','open');
}
$x=$chain[$a]; $y=$chain[$b];

function purchaseMatrixSnapshot(): array
{
    global $db,$p;
    $out=purchaseSnapshot();
    foreach (['company_api_tokens'=>'token_id','company_api_token_requests'=>'request_key'] as $table=>$key) {
        $out[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.$key)->result_array();
    }
    return $out;
}

/** One scenario includes the exact error and zero delta, not two inflated counts. */
function purchaseMatrixDenied(callable $work,string $expected,string $label): void
{
    $before=purchaseMatrixSnapshot(); $caught=null;
    try { $work(); } catch (Throwable $e) { $caught=$e->getMessage(); }
    purchaseCheck($caught===$expected&&purchaseMatrixSnapshot()===$before,$label.'; expected '.$expected.', actual '.var_export($caught,true).'; all business/audit/receipt/token rows unchanged');
}

foreach (['suppliers'=>'supplier','requests'=>'request','quotes'=>'quote','orders'=>'order','receipts'=>'receipt','documents'=>'document','payables'=>'payable','events'=>'event'] as $resource=>$kind) {
    $own=$api->read($secrets[$a],$resource,$x[$kind]);
    if ((int)$own['issuer_id']!==$a) { throw new RuntimeException('Positive detail lacks own company: '.$resource); }
    purchaseMatrixDenied(fn()=>$api->read($secrets[$b],$resource,$x[$kind]),'company_purchase_denied','A positive '.$resource.' detail is denied under B');
    $pageA=$api->read($secrets[$a],$resource); $pageB=$api->read($secrets[$b],$resource,null,['after'=>0]);
    purchaseCheck($pageA['items']!==[]&&$pageB['items']!==[]&&array_reduce($pageA['items'],static fn($ok,$row)=>$ok&&(int)$row['issuer_id']===$a,true)&&array_reduce($pageB['items'],static fn($ok,$row)=>$ok&&(int)$row['issuer_id']===$b,true),'nonempty '.$resource.' lists derive only persisted A or B ownership');
    purchaseMatrixDenied(fn()=>$api->read($secrets[$b],$resource,null,['issuer_id'=>$a]),'company_purchase_invalid','list '.$resource.' cannot replace B with issuer filter A');
}

foreach (['files'=>$x['request'],'invites'=>$x['request'],'order_pdf'=>$x['order']] as $resource=>$id) {
    $own=$api->read($secrets[$a],$resource,$id);
    if ($own===[]||$own==='') { throw new RuntimeException('Positive scoped resource empty: '.$resource); }
    purchaseMatrixDenied(fn()=>$api->read($secrets[$b],$resource,$id),'company_purchase_denied','A '.$resource.' parent cannot be read with B token');
}
if ($api->file($secrets[$a],$x['file'])['content']!==$pdf) { throw new RuntimeException('Positive attachment bytes differ'); }
purchaseMatrixDenied(fn()=>$api->file($secrets[$b],$x['file']),'company_purchase_denied','A download bytes cannot be read with B token');
foreach (['reports','export'] as $resource) {
    $qa=['from'=>'2000-01-01','as_of'=>$today];
    $ra=$api->read($secrets[$a],$resource,null,$qa); $rb=$api->read($secrets[$b],$resource,null,$qa);
    $encodedA=is_string($ra)?$ra:json_encode($ra,JSON_THROW_ON_ERROR); $encodedB=is_string($rb)?$rb:json_encode($rb,JSON_THROW_ON_ERROR);
    purchaseCheck(str_contains($encodedA,'MATRIX-'.$a)&&!str_contains($encodedA,'MATRIX-'.$b)&&str_contains($encodedB,'MATRIX-'.$b)&&!str_contains($encodedB,'MATRIX-'.$a),$resource.' includes positive data and excludes the other company');
    purchaseMatrixDenied(fn()=>$api->read($secrets[$b],$resource,null,$qa+['issuer_id'=>$a]),'company_purchase_invalid',$resource.' cannot override persisted B with query A');
}
purchaseCheck($api->read($secrets[$a],'settings')['prefix']==='C'.$a&&$api->read($secrets[$b],'settings')['prefix']==='C'.$b,'settings read has positive distinct per-company configuration');
purchaseMatrixDenied(fn()=>$api->read($secrets[$b],'settings',null,['issuer_id'=>$a]),'company_purchase_invalid','settings query cannot switch owner');

$negativeWrites=[
    ['suppliers',$x['supplier'],'update',['name'=>'Cross-company overwrite'],2,true,'supplier update'],
    ['requests',$x['request'],'approve',[],3,true,'request approval'],
    ['requests',$x['open'],'cancel',[],1,true,'request cancellation'],
    ['quotes',null,'create',['request_id'=>$x['open'],'supplier_id'=>$y['supplier'],'valid_until'=>$today,'unit_prices'=>['1.00'],'tax_rate'=>'0.00'],null,false,'quote foreign request'],
    ['quotes',null,'create',['request_id'=>$y['open'],'supplier_id'=>$x['supplier'],'valid_until'=>$today,'unit_prices'=>['1.00'],'tax_rate'=>'0.00'],null,false,'quote foreign supplier'],
    ['orders',null,'create',['quote_id'=>$x['quote'],'request_revision'=>3,'order_date'=>$today,'expected_date'=>$today],null,false,'order from foreign quote'],
    ['orders',$x['order'],'cancel',[],2,true,'order cancellation'],
    ['receipts',$x['order'],'create',['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'1.000']]],2,true,'physical receipt'],
    ['documents',null,'post',['order_id'=>$x['order'],'receipt_ids'=>[$x['receipt']],'vendor_number'=>'FOREIGN-ORDER','document_date'=>$today,'due_date'=>$today,'category_id'=>$categories[$b]],null,false,'document foreign order'],
    ['documents',null,'post',['order_id'=>$y['order'],'receipt_ids'=>[$x['receipt']],'vendor_number'=>'FOREIGN-RECEIPT','document_date'=>$today,'due_date'=>$today,'category_id'=>$categories[$b]],null,false,'document foreign receipt'],
    ['payables',$x['payable'],'settle',['amount'=>'1.00','event_date'=>$today,'external_reference'=>'foreign','acknowledged'=>1],$api->read($secrets[$a],'payables',$x['payable'])['position']['revision_token'],true,'external settlement'],
    ['events',$x['event'],'reverse',['event_date'=>$today,'reason'=>'foreign','acknowledged'=>1],$api->read($secrets[$a],'payables',$x['payable'])['position']['revision_token'],true,'declaration reversal'],
    ['files',$x['request'],'upload',['filename'=>'foreign.pdf','content_base64'=>base64_encode($pdf)],null,false,'file foreign request'],
    ['files',$y['request'],'upload',['filename'=>'foreign.pdf','content_base64'=>base64_encode($pdf),'supplier_id'=>$x['supplier']],null,false,'file foreign supplier'],
    ['files',$x['file'],'hide',[],1,true,'file concealment'],
    ['invites',$x['request'],'issue',['supplier_id'=>$y['supplier'],'days'=>1],null,false,'invite foreign request'],
    ['invites',$y['request'],'issue',['supplier_id'=>$x['supplier'],'days'=>1],null,false,'invite foreign supplier'],
    ['invites',$x['invite']['id'],'revoke',[],1,true,'invite revocation'],
];
foreach ($negativeWrites as $n=>[$resource,$id,$action,$input,$revision,$hasRevision,$label]) {
    purchaseMatrixDenied(fn()=>$call($b,$resource,$id,$action,$input,'negative-'.$n,$revision,$hasRevision),'company_purchase_denied','B cannot mutate A: '.$label);
}

foreach ([['suppliers',['name'=>'No implicit owner']],['requests',$requestInput('No implicit owner')],['settings',['prefix'=>'CROSS','base_currency'=>'BRL','rates'=>['BRL'=>'1.000000']]]] as [$resource,$input]) {
    $body=['action'=>$resource==='settings'?'save':'create','input'=>$input,'issuer_id'=>$a];
    purchaseMatrixDenied(fn()=>$api->write($secrets[$b],$resource,null,$body,$key('matrix-forged-'.$resource)),'company_purchase_invalid',$resource.' creation/configuration cannot nominate foreign issuer');
}

$unbilled=purchaseOrder($b,12,'matrix-unbilled','1.000','2.00','0.00');
$unbilledReceipt=$call($b,'receipts',$unbilled['order'],'create',['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'1.000']]],'unbilled-receipt',1,true);
$doc=['order_id'=>$unbilled['order'],'receipt_ids'=>[$unbilledReceipt],'vendor_number'=>'MATRIX-UNBILLED','document_date'=>$today,'due_date'=>$today,'category_id'=>$categories[$b]];
purchaseMatrixDenied(fn()=>$call($b,'documents',null,'post',array_replace($doc,['category_id'=>$categories[$a]]),'foreign-category'),'company_expense_reference_denied','B document cannot charge category A');
purchaseMatrixDenied(fn()=>$call($b,'documents',null,'post',$doc+['file_id'=>$x['file']],'foreign-document-file'),'company_purchase_denied','B document cannot bind attachment A');
$positionB=$api->read($secrets[$b],'payables',$y['payable'])['position'];
purchaseMatrixDenied(fn()=>$call($b,'payables',$y['payable'],'settle',['amount'=>'1.00','event_date'=>$today,'external_reference'=>'B with A evidence','file_id'=>$x['file'],'acknowledged'=>1],'foreign-settlement-file',$positionB['revision_token'],true),'company_purchase_denied','B settlement cannot attach evidence A');
$ownDoc=$call($b,'documents',null,'post',$doc,'positive-unbilled-document');
purchaseCheck((int)$api->read($secrets[$b],'payables',$ownDoc)['amount_cents']===200,'same B document succeeds with its own receipt and category after crossed attempts');

// Positive counterparts for destructive-looking actions use distinct new data.
$call($a,'requests',$x['open'],'cancel',[],'positive-cancel-request',1,true);
$cancellable=purchaseOrder($a,12,'matrix-cancel-order');
$call($a,'orders',$cancellable['order'],'cancel',[],'positive-cancel-order',1,true);
$hideFile=$call($a,'files',$x['request'],'upload',['filename'=>'hideable.pdf','content_base64'=>base64_encode($pdf)],'positive-hide-upload');
$call($a,'files',$hideFile,'hide',[],'positive-hide',1,true);
$call($a,'invites',$x['invite']['id'],'revoke',[],'positive-revoke',1,true);
$positionA=$api->read($secrets[$a],'payables',$x['payable'])['position'];
$call($a,'events',$x['event'],'reverse',['event_date'=>$today,'reason'=>'Owned correction','acknowledged'=>1],'positive-reverse',$positionA['revision_token'],true);
$call($a,'settings',null,'save',['prefix'=>'MATRIX_A','base_currency'=>'BRL','rates'=>['BRL'=>'1.000000']],'positive-settings',1,true);
purchaseCheck($api->read($secrets[$a],'requests',$x['open'])['state']==='cancelled'&&$api->read($secrets[$a],'orders',$cancellable['order'])['state']==='cancelled'&&$api->read($secrets[$a],'settings')['prefix']==='MATRIX_A','owned cancellation and settings actions succeed after corresponding crossed denials');
purchaseCheck($api->read($secrets[$a],'payables',$x['payable'])['position']['outstanding_cents']===500&&$api->read($secrets[$a],'invites',$x['request'])[0]['revoked']==1&&count($api->read($secrets[$a],'files',$x['request']))===1,'owned reversal, invitation revocation and hiding succeed without changing preserved evidence');

echo 'Purchases API company-origin matrix: '.$GLOBALS['essentialChecks'].' checks passed on '.$db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
