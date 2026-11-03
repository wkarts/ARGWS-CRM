<?php
require __DIR__.'/invoices.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
require_once APPPATH.'services/NativeCompanyInvoiceRead.php';
require_once APPPATH.'services/NativeCompanyInvoiceIdentity.php';
require_once APPPATH.'services/NativeCompanyInvoiceTable.php';
if(!function_exists('_l')){function _l($key){return $key;}}
$reader = new NativeCompanyInvoiceRead($db, $p);
$beforeRead = $db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
foreach ([$a, $b] as $owner) {
    $read = $reader->page($owner, 7, ['draw'=>'17', 'length'=>'100']);
    $expected = (int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices WHERE issuer_id=?', [$owner])->row()->n;
    essentialCheck($read['draw'] === 17 && $read['recordsTotal'] === $expected && $read['recordsFiltered'] === $expected, 'native counts belong only to selected issuer');
    essentialCheck(count($read['documents']) === $expected, 'complete bounded synthetic table has no missing own invoices');
    foreach ($read['documents'] as $document) {
        essentialCheck($document['issuer_id'] === $owner && $document['invoice_id'] !== 42, 'every row is an authorized owned document, never native NULL');
    }
    $first = $reader->page($owner, 7, ['length'=>1, 'order'=>'date', 'direction'=>'asc']);
    $second = $reader->page($owner, 7, ['length'=>1, 'start'=>1, 'order'=>'date', 'direction'=>'asc']);
    if ($expected > 1) { essentialCheck($first['documents'][0]['invoice_id'] !== $second['documents'][0]['invoice_id'], 'equal dates have deterministic ID tie-break pagination'); }
}
$largePage=$reader->page($a,7,['length'=>500]);
essentialCheck(count($largePage['documents'])===$largePage['recordsFiltered'],'native configured page size above100 is not silently rejected or capped');
$all=$reader->page($a,7,['length'=>'-1','start'=>'999']);
essentialCheck(count($all['documents'])===$all['recordsFiltered'],'native ALL returns every authorized row and ignores offset like native helper, without silent cap');
$foreign = $reader->page($a, 7, ['client_id'=>$cb]);
essentialCheck($foreign['documents'] === [] && $foreign['recordsFiltered'] === 0, 'foreign client filter cannot bypass issuer predicate');
$wildcard = $reader->page($a, 7, ['search'=>'%']);
essentialCheck($wildcard['recordsFiltered'] === 0, 'literal percent is not a wildcard expanding the result');
$historical = $reader->page($b, 7, ['search'=>'Private A']);
essentialCheck(in_array($ib, array_column($historical['documents'], 'invoice_id'), true), 'historical B recipient remains searchable after A unlinks current directory access');
essentialThrows(fn()=>$reader->page($a, 999999), 'company_invoice_access_denied', 'read table requires live actor authorization');
foreach ([['length'=>-2], ['length'=>(string)PHP_INT_MAX.'0'], ['order'=>'i.id; DROP TABLE invoices'], ['direction'=>'desc, i.issuer_id'], ['issuer_id'=>$b], ['search'=>['x']], ['status'=>0], ['year'=>'2026 OR 1=1']] as $query) {
    essentialThrows(fn()=>$reader->page($a, 7, $query), 'native_company_invoice_query_invalid', 'invalid native query fails closed');
}
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array() === $beforeRead, 'table adapter never backfills formatted numbers or changes invoice bytes');
$summaryBefore = $reader->summary($a, 7, null, [2026]);
$newInput = ['client_id'=>$ca, 'currency'=>1, 'date'=>'2026-01-01', 'due_date'=>'2026-01-02',
    'items'=>[['description'=>'Native summary overdue fixture', 'qty'=>'1.00', 'rate'=>'20.00', 'taxes'=>[]]], 'note'=>'Synthetic'];
$overdueId = $s->create($a, 7, $newInput, hash('sha256', 'native-summary-overdue'));
$writer->recordCompanyManual($a, 7, $overdueId, ['amount'=>'5.00', 'date'=>'2026-01-01', 'reference'=>'native-summary-partial', 'note'=>'Synthetic'], hash('sha256', 'native-summary-partial'));
// A partial payment remains status3; forcing status4 would contradict the canonical balance.
$db->query('UPDATE '.$p.'invoices SET status=4 WHERE id=? AND issuer_id=?', [$overdueId, $a]);
essentialThrows(fn()=>$reader->summary($a,7,1,[2026]), 'company_invoice_balance_review', 'tampered partial payment status cannot be hidden by native summary');
$db->query('UPDATE '.$p.'invoices SET status=3 WHERE id=? AND issuer_id=?', [$overdueId, $a]);
$unpaidInput=$newInput; $unpaidInput['items'][0]['rate']='10.00';
$unpaidOverdue=$s->create($a,7,$unpaidInput,hash('sha256','native-summary-unpaid-overdue'));
// Stored native overdue classification is valid only while the full amount is outstanding.
$db->query('UPDATE '.$p.'invoices SET status=4 WHERE id=? AND issuer_id=?', [$unpaidOverdue,$a]);
$db->query("INSERT INTO {$p}currencies(id,symbol,name,decimal_separator,thousand_separator,placement,isdefault) VALUES(2,'USD','USD','.',',','before',0)");
$otherCurrency = $newInput; $otherCurrency['currency'] = 2;
$s->create($a, 7, $otherCurrency, hash('sha256', 'native-summary-usd'));
$otherYear = $newInput; $otherYear['date'] = '2025-01-01'; $otherYear['due_date'] = '2025-01-02';
$s->create($a, 7, $otherYear, hash('sha256', 'native-summary-other-year'));
$financialBefore = $db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
$after = $reader->summary($a, 7, 1, ['2026']);
foreach (['due'=>2500, 'paid'=>500, 'overdue'=>1000] as $field=>$delta) {
    essentialCheck(CompanyInvoiceAmounts::minor($after['total_result'][$field]) - CompanyInvoiceAmounts::minor($summaryBefore['total_result'][$field]) === $delta, 'summary delta is exact and excludes other currency/year: '.$field);
}
essentialCheck(is_object($after['total_result']['currency']) && $after['total_result']['currency']->name === 'BRL', 'native money formatter receives complete currency object');
essentialCheck(count($after['invoices_total_currencies']) === 2 && array_column($after['invoices_years'], 'year') === [2026,2025], 'selector metadata is based on owned documents');
essentialCheck($after['percent_by_status'][5]['total_by_status'] === 0 && $after['percent_by_status'][1]['total'] === array_sum($after['counts']) - $after['counts'][5], 'dashboard percentage preserves native cancelled denominator');
essentialCheck($reader->summary($a, 7, 2, [2026])['total_result']['due'] === '20.00', 'USD never mixed into BRL totals');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array() === $financialBefore, 'summary cannot mutate any receipt');
essentialThrows(fn()=>$reader->summary($a, 7, null, ['2026 OR 1=1']), 'native_company_invoice_query_invalid', 'summary year cannot inject SQL');
$primary = (new IssuerResourceAccess($db, $p))->primaryId();
$principalBefore = $reader->summary($primary, 7, 1, [2026]);
essentialCheck($principalBefore['lineages']['legacy']['included'] === true
    && $principalBefore['lineages']['legacy']['totals']['due'] === '7.00', 'proven primary includes exactly the recorded NULL lineage');
essentialCheck($after['lineages']['legacy']['included'] === false && $after['lineages']['legacy']['totals']['due'] === '0.00', 'secondary summary never inherits NULL lineage');
$settings->saveIdentity($primary, 7, ['legal_name'=>'Synthetic principal invoice', 'tax_id'=>'11144477735',
    'profile'=>['invoice_company_address'=>'Principal synthetic street','invoice_company_city'=>'Synthetic city','company_state'=>'BA','invoice_company_country_code'=>'BR','invoice_company_postal_code'=>'00000-000','invoice_company_phonenumber'=>'']]);
$primaryClient = $customers->create($primary, 7, ['company'=>'Private principal', 'vat'=>'52998224725']);
$primaryInput = $newInput; $primaryInput['client_id'] = $primaryClient;
$primaryNamed = $s->create($primary, 7, $primaryInput, hash('sha256', 'native-summary-principal-named'));
$principalAfter = $reader->summary($primary, 7, 1, [2026]);
essentialCheck($principalAfter['total_result']['due'] === '27.00'
    && $principalAfter['lineages']['named']['totals']['due'] === '20.00'
    && $principalAfter['lineages']['legacy']['totals']['due'] === '7.00', 'principal union preserves named plus NULL with explicit disjoint bases');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array() === $legacyBefore, 'union never adopts or rewrites legacy NULL invoice');
$unionPage=$reader->page($primary,7,['length'=>100,'order'=>'total','direction'=>'asc']);
essentialCheck($unionPage['recordsTotal']===2&&$unionPage['recordsFiltered']===2,'primary SQL union counts both disjoint persisted lineages');
essentialCheck(array_column($unionPage['documents'],'invoice_id')===[42,$primaryNamed],'primary sorts both lineages together before pagination');
essentialCheck($unionPage['documents'][0]['lineage']==='native_legacy_null'&&$unionPage['documents'][0]['issuer_id']===null
    &&$unionPage['documents'][1]['lineage']==='named_company','NULL record is never relabelled a company document or provider proof');
$unionSecond=$reader->page($primary,7,['length'=>1,'start'=>1,'order'=>'total','direction'=>'asc']);
essentialCheck(count($unionSecond['documents'])===1&&$unionSecond['documents'][0]['invoice_id']===$primaryNamed,'primary pagination happens after union, not once per source');
$unionRule=['match_type'=>'or','rules'=>[
    ['id'=>'total','type'=>'NumberRule','operator'=>'equal','value'=>'7','has_dynamic_value'=>false],
    ['id'=>'total','type'=>'NumberRule','operator'=>'equal','value'=>'20','has_dynamic_value'=>false]]];
essentialCheck($reader->page($primary,7,['filters'=>$unionRule])['recordsFiltered']===2,'same rules execute separately within each authorized lineage');
essentialCheck($reader->page($primary,7,['search'=>'Private principal'])['recordsFiltered']===1,'named principal searches immutable recipient snapshot');
essentialCheck($reader->page($a,7,['filters'=>$unionRule])['recordsFiltered']!==$unionPage['recordsTotal']||!in_array(42,array_column($reader->page($a,7,['filters'=>$unionRule])['documents'],'invoice_id'),true),'secondary OR never expands to NULL');
// Invalid historical relation must not reveal another issuer private client name.
$db->query('UPDATE '.$p.'invoices SET clientid=? WHERE id=42',[$cb]);
$protectedPage=$reader->page($primary,7,['length'=>100]);
$legacyProtected=array_values(array_filter($protectedPage['documents'],static fn($doc)=>$doc['invoice_id']===42))[0];
essentialCheck($legacyProtected['customer_label']==='','NULL invoice does not hydrate foreign private directory row');
$db->query('UPDATE '.$p.'invoices SET clientid=9999 WHERE id=42');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'union remains read-only without number repair or adoption');
$identity = new NativeCompanyInvoiceIdentity($db, $p);
essentialCheck($identity->resolve($primary, 7, 42)['lineage'] === 'native_legacy_null', 'principal resolves actual NULL lineage, not an owned-reader fallback');
essentialCheck($identity->resolve($primary, 7, $primaryNamed)['lineage'] === 'named_company', 'same principal URL selects owned writer for positive issuer row');
essentialCheck($identity->resolve($a, 7, $branded)['lineage'] === 'named_company', 'secondary resolves its actual named invoice');
foreach ([[$a,42], [$b,$branded], [$primary,$branded], [$primary,99999999]] as [$owner,$invoice]) {
    essentialThrows(fn()=>$identity->resolve($owner,7,$invoice), 'company_invoice_access_denied', 'missing or foreign record never falls through to legacy');
}
$options=['sale_agent'=>[], 'status'=>array_map(static fn($status)=>['value'=>(string)$status,'label'=>'Status '.$status],range(1,6)),
    'year'=>[['value'=>2026,'label'=>'2026']], 'made_payment_by'=>[['value'=>'company_manual','label'=>'Manual']]];
$filter=['id'=>1,'issuer_id'=>$a,'staff_id'=>7,'is_shared'=>0,'is_default'=>false,'name'=>'Own native filter','identifier'=>'invoices',
    'builder'=>['match_type'=>'and','rules'=>[['id'=>'total','type'=>'NumberRule','operator'=>'greater','value'=>'10.00','has_dynamic_value'=>false]]]];
$nativeTable=new NativeCompanyInvoiceTable($db,$p,$a,7,'invoices',$options,[$filter]);
essentialCheck($nativeTable->contextIdentity()===['issuer_id'=>$a,'actor_id'=>7],'filter catalog exposes authenticated issuer and actor binding');
essentialCheck($nativeTable->normalizeBuilder($filter['builder'])['rules'][0]['value']==='10','storage uses the same native table rule normalizer, not a duplicated grammar');
essentialCheck($nativeTable->id()==='invoices'&&$nativeTable->viewName()==='invoices'&&count($nativeTable->rules())===12,'same native invoice table ID/view and twelve native base rules');
essentialCheck(str_contains((string)$nativeTable->filtersJs(),'Own native filter'),'saved filter is actually preserved, not replaced with empty placeholder');
$nativeTable->findRule('status')->setValue(['2']);
essentialCheck($nativeTable->findRule('status')->value===null,'quick-stat rule mutations cannot contaminate another table or response');
$foreignFilter=$filter;$foreignFilter['issuer_id']=$b;
essentialThrows(fn()=>new NativeCompanyInvoiceTable($db,$p,$a,7,'invoices',$options,[$foreignFilter]),'native_company_invoice_table_invalid','foreign-company saved filter rejected before serialization');
$callbackOptions=$options;$callbackOptions['year']=static fn()=>[];
essentialThrows(fn()=>new NativeCompanyInvoiceTable($db,$p,$a,7,'invoices',$callbackOptions,[]),'native_company_invoice_table_invalid','rule options never execute callbacks or global model hydration');
echo 'Native invoice read A/B/NULL/filter/order/pagination/snapshot/ACL: '.$GLOBALS['essentialChecks']." cumulative checks.\n";
