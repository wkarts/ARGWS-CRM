<?php
require __DIR__.'/native_invoice_read.php';
require_once APPPATH.'services/NativeCompanyFilters.php';
NativeCompanyFilterSchema::ensure($db,$p);
unset($db->data_cache['table_names']);
unset($db->data_cache['table_names']);
essentialCheck($db->table_exists($p.'filters')&&$db->table_exists($p.'filter_defaults'),'actual native filter tables supplied by invoice fixture');
$start=$GLOBALS['essentialChecks'];$tableA=new NativeCompanyInvoiceTable($db,$p,$a,7,'invoices',$options,[]);$tableB=new NativeCompanyInvoiceTable($db,$p,$b,7,'invoices',$options,[]);
$storeA=new NativeCompanyFilters($db,$p,$a,7,$tableA);$storeB=new NativeCompanyFilters($db,$p,$b,7,$tableB);
essentialThrows(fn()=>new NativeCompanyFilters($db,$p,$b,7,$tableA),'native_filter_denied','foreign options catalog cannot be paired to another issuer');
$builder=['match_type'=>'and','rules'=>[['id'=>'total','type'=>'NumberRule','operator'=>'greater','value'=>'10.00','has_dynamic_value'=>false]]];
$input=['name'=>'Owned synthetic filter','is_shared'=>1,'builder'=>$builder];$created=$storeA->save($input);
if($db->driver()==='sqlite'){
$db->query("CREATE TRIGGER {$p}ignore_filter_source BEFORE INSERT ON {$p}company_native_filters BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$storeA->save($input),'issuer_storage_failed','ignored identical source cannot reuse old filter identity');
essentialCheck(count($storeA->listing())===1,'ignored insert creates neither second filter nor false identity');
$db->query('DROP TRIGGER '.$p.'ignore_filter_source');}

essentialCheck(count($storeA->listing())===1&&$storeB->listing()===[],'shared filter means own company, never all companies');
essentialThrows(fn()=>$storeB->default($created['id']),'native_filter_denied','B cannot select A default');
$storeA->default($created['id']);essentialCheck($storeA->listing()[0]['is_default']===true,'native default persisted for actor');
$storeA->default(null);essentialCheck($storeA->listing()[0]['is_default']===false,'explicit unmark does not resurrect a legacy default');
$updated=$storeA->save(['name'=>'Changed']+$input,$created['id'],$created['revision']);essentialCheck($updated['revision']==='2','owned filter CAS revision advances');
essentialThrows(fn()=>$storeA->save($input,$created['id'],$created['revision']),'native_filter_stale','stale filter cannot overwrite');
$before=$storeA->listing();if($db->driver()==='sqlite'){
$db->query("CREATE TRIGGER {$p}ignore_filter_audit BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='native_filter_saved' BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$storeA->save(['name'=>'Rollback']+$input,$created['id'],'2'),'issuer_storage_failed','missing audit aborts whole mutation');
essentialCheck($storeA->listing()===$before,'failed audit rolls back filter');$db->query('DROP TRIGGER '.$p.'ignore_filter_audit');}
$storeA->delete($created['id'],'2');essentialCheck($storeA->listing()===[],'native deletion preserves no dangling visible filter');
$legacyId=22;$db->query('INSERT INTO '.$p.'filters(id,staff_id,name,identifier,is_shared,builder) VALUES(?,?,?,?,?,?)',[$legacyId,7,'Principal historical filter','invoices',0,json_encode($builder)]);$db->query('INSERT INTO '.$p.'filter_defaults(staff_id,filter_id,identifier,view) VALUES(?,?,?,?)',[7,$legacyId,'invoices','invoices']);
$legacy=new NativeCompanyFilters($db,$p,$primary,7,new NativeCompanyInvoiceTable($db,$p,$primary,7,'invoices',$options,[]));
essentialCheck($legacy->listing()[0]['id']==='22'&&$legacy->listing()[0]['is_default']===true,'principal preserves literal native identity and historical default');
$legacy->default(null);essentialCheck($legacy->listing()[0]['is_default']===false,'owned tombstone overrides but does not delete historical defaults');
essentialCheck($storeB->listing()===[],'legacy union is never available to secondary company');
echo 'Native filters actual PDO: '.($GLOBALS['essentialChecks']-$start).' checks passed; SQL rule execution remains the invoice reader contract.'.PHP_EOL;
