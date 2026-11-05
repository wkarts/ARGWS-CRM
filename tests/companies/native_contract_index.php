<?php
/** Production projection, filters and row template against an isolated real database. */
require __DIR__.'/contracts_operations.php';
require __DIR__.'/native_contract_index_schema_fixture.php';
require_once APPPATH.'services/NativeCompanyContractIndex.php';
require_once APPPATH.'services/NativeCompanyContractTableFactory.php';
require_once APPPATH.'services/NativeCompanyFilters.php';
function _l($key){return$key;}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function admin_url($path=''){return'https://fixture.invalid/admin/'.$path;}
function app_format_money($value,$currency){return$currency.' '.number_format((float)$value,2,'.',',');}
function _d($date){return(string)$date;}
$start=$GLOBALS['essentialChecks'];
$reader=new NativeCompanyContractIndex($db,$p);
$request=static function(int$columns=9):array{return['draw'=>'3','start'=>'0','length'=>'25','search'=>['value'=>'','regex'=>'false'],'order'=>[['column'=>'6','dir'=>'asc']],'columns'=>array_map(static fn($n)=>['data'=>(string)$n,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']],range(0,$columns-1))];};
$pageA=$reader->page($a,7,$request());$pageB=$reader->page($b,7,$request());
essentialCheck($pageA['recordsTotal']===count($pageA['rows'])&&$pageB['recordsTotal']===count($pageB['rows']),'Original DataTable totals use each complete owned dataset');
essentialCheck(!array_intersect(array_column($pageA['rows'],'id'),array_column($pageB['rows'],'id')),'A and B contract canonical identities are disjoint');
$revokedShared=array_values(array_filter($pageB['rows'],static fn($r)=>(int)$r['source_id']===$sharedContract))[0];
essentialCheck($revokedShared['company']==='Contract customer A'&&(int)$revokedShared['client_visible']===0,'Issued B contract retains its frozen customer name after sharing revocation without current A identity');
essentialCheck(!str_contains(json_encode($pageB),'New customer A'),'B index never hydrates the revoked shared customer current name');
$attack=$request();$attack['search']['value']="%' OR 1=1 --";
essentialCheck($reader->page($b,7,$attack)['recordsFiltered']===0,'Global search keeps literal metacharacters and issuer predicates');
$limited=$request();$limited['length']='1';$limited['start']='1';$limited['order']=[['column'=>'0','dir'=>'desc']];
$sorted=$pageB['rows'];usort($sorted,static fn($l,$r)=>(int)$r['id']<=>(int)$l['id']);
essentialCheck($reader->page($b,7,$limited)['rows'][0]['id']===$sorted[1]['id'],'Native ordering and pagination return the requested record');
$filter=$request();$filter['filters']=['match_type'=>'and','rules'=>[['id'=>'dateend','type'=>'DateRule','operator'=>'less','value'=>'2027-01-01']]];
essentialCheck($reader->page($b,7,$filter)['recordsFiltered']===$pageB['recordsTotal'],'Original date filter evaluates actual contract dates');
$clear=$request();$clear['filters']=['match_type'=>'and'];
essentialCheck($reader->page($b,7,$clear)['recordsFiltered']===$pageB['recordsTotal'],'Empty jQuery rules transport clears filters');
foreach([
 ['match_type'=>'and','rules'=>[['id'=>'subject);DROP TABLE x','type'=>'TextRule','value'=>'x']]],
 ['match_type'=>'or 1=1','rules'=>[]],
 ['match_type'=>'and','rules'=>[['id'=>'subject','type'=>'TextRule','operator'=>'contains','value'=>['x']]]],
]as$bad)essentialThrows(fn()=>NativeCompanyContractRules::normalizeBuilder($bad),'native_contract_filter_invalid','Closed contract rule grammar refuses injected or malformed structure');
$bad=$request();$bad['order']=[['column'=>'0 DESC; SELECT 1','dir'=>'asc']];
essentialThrows(fn()=>$reader->page($b,7,$bad),'native_company_table_request_invalid','Sort identifier cannot become SQL');
$types=new NativeCompanyContractTypes($db,$p);$type=$types->save($b,7,null,null,'Maintenance <script>',$key('index-type'));
$db->query('INSERT INTO '.$p.'currencies(id,name,symbol,decimal_separator,thousand_separator,placement,isdefault) VALUES(?,?,?,?,?,?,?)',[2,'USD','$','.',',','before',0]);
$known=$contracts->create($b,7,array_replace($contractInput,['client_id'=>$cb,'name'=>'Index known','contract_value'=>'123.45','currency'=>'BRL','contract_type'=>$type['id']]),$key('index-known'));
$foreign=$contracts->create($b,7,array_replace($contractInput,['client_id'=>$cb,'name'=>'Index foreign currency','contract_value'=>'999.50','currency'=>'USD','contract_type'=>$type['id']]),$key('index-foreign'));
$filter['filters']=['match_type'=>'and','rules'=>[['id'=>'contract_type','type'=>'MultiSelectRule','operator'=>'in','value'=>[$type['id']]],['id'=>'contract_value','type'=>'NumberRule','operator'=>'greater','value'=>123.44]]];
essentialCheck($reader->page($b,7,$filter)['recordsFiltered']===2,'Native type and decimal NumberRule accept the actual form payload');
$presentation=$reader->presentation($b,7,'2026-11-05');$chart=json_decode($presentation['chart_types_values'],true);
essentialCheck($chart['datasets'][0]['data']===[123.45],'Base-currency chart never adds the USD amount');
essentialCheck(str_contains($presentation['chart_types'],'\\u003Cscript\\u003E'),'Chart labels are safe in an inline script');
$contracts->transition($b,7,$known,1,'activate',$key('index-activate'));
$contracts->markSignature($b,7,$known,2,'mark_as_signed',$key('index-mark'));
$filter['filters']=['match_type'=>'and','rules'=>[['id'=>'marked_as_signed','type'=>'BooleanRule','value'=>'1']]];
$marked=$reader->page($b,7,$filter);
essentialCheck($marked['recordsFiltered']===1&&(int)$marked['rows'][0]['source_id']===$known&&(int)$marked['rows'][0]['signed']===0,'Staff declaration is displayed independently from a customer signature');
$fieldService=new NativeCompanyCustomFields($db,$p);
$field=$fieldService->saveDefinition($b,7,['fieldto'=>'contracts','name'=>'External reference','type'=>'input','show_on_table'=>1],null,null,str_repeat('e',32));
$typedKnown=NativeCompanyResourceId::encode('company_contract',$known);
IssuerTransaction::run($db,$p,array_merge($fieldService->tableNames(),['company_contracts']),fn()=>$fieldService->saveInOwnedTransaction($b,7,'contracts',$typedKnown,[$field['id']=>'<script>external-reference']));
$customRequest=$request(10);$customRequest['search']['value']='external-reference';$customRequest['order']=[['column'=>'9','dir'=>'asc','type'=>'input']];
$custom=$reader->page($b,7,$customRequest);
essentialCheck($custom['recordsFiltered']===1&&$custom['rows'][0]['custom_values'][$field['id']]==='<script>external-reference','Original custom-field column supports real scoped values, search and ordering');
$contract_row=$custom['rows'][0];$custom_fields=$custom['definitions'];$can_view_customers=true;$can_edit=true;$can_delete=true;
include APPPATH.'views/admin/contracts/native_table_row.php';
essentialCheck(count(array_filter(array_keys($row),'is_int'))===10&&str_contains($row[9],'&lt;script&gt;external-reference')&&!str_contains(json_encode($row),'<script>external-reference'),'Actual row template has original 9 columns plus escaped custom value');
essentialCheck(str_contains($row[8],'marked_as_signed')&&str_contains($row[1],'#contract-actions'),'Actual signature column and reviewed deletion action preserve their distinct behaviors');
$factory=new NativeCompanyContractTableFactory($db,$p);$table=$factory->make($b,7);$filters=new NativeCompanyFilters($db,$p,$b,7,$table);
$saved=$filters->save(['name'=>'Own signed contracts','is_shared'=>0,'builder'=>$filter['filters']]);
essentialCheck(count($filters->listing())===1&&$filters->listing()[0]['id']===$saved['id'],'Saved native contracts filter uses the company/actor namespace');
essentialCheck((new NativeCompanyFilters($db,$p,$a,7,$factory->make($a,7)))->listing()===[],'Saved contract filters cannot remain after a company switch');
essentialThrows(fn()=>$reader->page($b,12,$request()),'native_contract_denied','Revoked membership cannot read data or totals');
// The previous minimal historical fixture is replaced only in this disposable test database.
$db->query('DROP TABLE '.$p.'contracts');$ddl=$contractIndexPlan['contracts']['create'];
if($db->driver()==='sqlite')$ddl=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$ddl);
$db->query($ddl);if($db->driver()==='mysql')foreach($contractIndexPlan['contracts']['alters']as$ddl)$db->query($ddl);
$db->query('INSERT INTO '.$p.'clients(company,datecreated) VALUES(?,?)',['Historical principal customer','2026-11-05 00:00:00']);$legacyClient=(int)$db->insert_id();
$db->query('INSERT INTO '.$p.'contracts(id,subject,client,datestart,dateend,content,description,addedfrom,dateadded,contract_value,trash,not_visible_to_client,hash,marked_as_signed) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$contractIds[$primary],'Historical same raw ID',$legacyClient,'2026-01-01','2027-01-01','','',7,'2026-01-01',12,0,0,'synthetic',0]);
$principal=$reader->page($primary,7,$request());
$ids=array_column($principal['rows'],'id');
essentialCheck(in_array((string)$contractIds[$primary],$ids,true)&&in_array(NativeCompanyResourceId::encode('company_contract',$contractIds[$primary]),$ids,true),'Principal preserves historical and company-owned contracts even when raw IDs collide');
essentialCheck($principal['recordsTotal']===2,'Principal union contains only its historical and owned rows');
$dateRule=NativeCompanyContractRules::compile(['match_type'=>'and','rules'=>[['id'=>'dateend','type'=>'DateRule','operator'=>'dynamic','value'=>'+2 days']]],'2026-11-05');
essentialCheck($dateRule['params']===['2026-11-07'],'Relative date rule remains bounded data and resolves against the declared day');
// Exercise raw JSON projection semantics, not acceptance of this payload by the contract writer.
// MySQL JSON_UNQUOTE alone turns JSON null into the text "null", unlike SQLite.
$nullFixtureId=$contractIds[$a];$snapshot=$db->query('SELECT snapshot_json FROM '.$p.'company_contracts WHERE id=? AND issuer_id=?',[$nullFixtureId,$a])->row_array()['snapshot_json'];
try{
    $nullable=['client'=>['name'=>'null'],'contract_value'=>null,'currency'=>null,'contract_type'=>['id'=>null,'name'=>null]];
    $db->query('UPDATE '.$p.'company_contracts SET snapshot_json=? WHERE id=? AND issuer_id=?',[json_encode($nullable,JSON_THROW_ON_ERROR),$nullFixtureId,$a]);
    $nullablePage=$reader->page($a,7,$request());$nullableRow=array_values(array_filter($nullablePage['rows'],static fn($r)=>(int)$r['source_id']===$nullFixtureId))[0];
    essentialCheck($nullableRow['contract_value']===null&&$nullableRow['currency']===null&&$nullableRow['contract_type']===null&&$nullableRow['type_name']===null,'Real JSON null projection stays SQL NULL before monetary cast and type grouping');
    essentialCheck($nullableRow['company']==='null','The literal customer name null is not erased by JSON absence normalization');
}finally{$db->query('UPDATE '.$p.'company_contracts SET snapshot_json=? WHERE id=? AND issuer_id=?',[$snapshot,$nullFixtureId,$a]);}
echo 'Native contract index: '.($GLOBALS['essentialChecks']-$start).' added real database/projection/filter/row checks passed ('.$db->driver().'). No browser or production-isolation claim.'.PHP_EOL;
