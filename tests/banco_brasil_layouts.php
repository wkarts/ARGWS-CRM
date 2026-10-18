<?php
declare(strict_types=1);
require __DIR__.'/banco_brasil_store.php';
require __DIR__.'/banco_brasil_cnab400.php';
$n=0;
$binding400=$store->registerLayout($c,9,\BancoBrasil\LayoutCatalog::CNAB400);
check($binding400!==$binding&&count($store->layoutBindings(1))===2,'two explicit immutable bindings');
check($store->registerLayout($c,9,\BancoBrasil\LayoutCatalog::CNAB400)===$binding400,'registration idempotent');
check($store->layoutBinding($binding400,$c)['verification_state']==='declared_unverified','declaration not bank homologation');
check($store->layoutBinding($binding400,$c)['date_window']==='2000-2099','explicit two-digit date policy');
rejects(fn()=>$store->importReturn($c,9,$wire400,0),'explicit selection required');
rejects(fn()=>$store->importReturn($c,9,$wire400,$binding),'no automatic fallback from 240 to400');
rejects(fn()=>$store->importReturn($c,9,$r2,$binding400),'no automatic fallback from400 to240');
$other=$c;$other['id']=2;rejects(fn()=>$store->layoutBinding($binding400,$other),'binding cannot cross connections');
$other=$c;$other['issuer_id']=2;rejects(fn()=>$store->layoutBinding($binding400,$other),'binding cannot cross issuers');
$other=$c;$other['environment']='production';rejects(fn()=>$store->layoutBinding($binding400,$other),'binding cannot reinterpret environment');
$before=$db->tables;$result=$store->importReturn($c,9,$wire400,$binding400);
check(!$result['duplicate']&&$result['financial_effect']==='not_applied','400 imported for review');
$file=end($db->tables['t_bb_return_files']);$event=end($db->tables['t_bb_return_events']);$link=end($db->tables['t_bb_return_file_events']);
check($file['layout_binding_id']===$binding400&&$event['layout_binding_id']===$binding400&&$link['layout_binding_id']===$binding400,'file event and provenance retain binding');
check($file['layout_id']===\BancoBrasil\LayoutCatalog::CNAB400,'file layout immutable');
$p=json_decode($file['provenance_json'],true);
check($p['declared_environment']==='sandbox'&&$p['environment_evidence']==='not_present_in_file'&&$p['authenticity']==='not_verified','provenance distinguished from selected environment');
check($p['trailer_raw_totals']['simple']['total_raw']==='00000000020000','ambiguous total remains raw durable');
check(count($db->tables['t_bb_return_events'])===2,'240 and400 observations do not collide');
check($store->importReturn($c,9,$wire400,$binding400)['duplicate'],'400 duplicate file');
$store->importReturn($c,9,bb400Mutate($wire400,0,101,'0000002'),$binding400);
check(count($db->tables['t_bb_return_events'])===2&&count($db->tables['t_bb_return_file_events'])===4,'400 repackaged event retains all provenance');
$before=$db->tables;
rejects(fn()=>$store->importReturn($c,9,bb400Mutate(bb400Mutate($wire400,0,101,'0000003'),1,189,'0000000000001'),$binding400),'same event conflicting extras rejected');
check($db->tables===$before,'400 conflict rolls back complete import');
// Simulate rows genuinely created by 101. Selecting a profile must never reclassify them.
unset($db->tables['t_bb_return_files'][0]['layout_binding_id'],$db->tables['t_bb_return_files'][0]['provenance_json']);
unset($db->tables['t_bb_return_events'][0]['layout_binding_id'],$db->tables['t_bb_return_file_events'][0]['layout_binding_id']);
$legacy=$db->tables;$store->importReturn($c,9,$r2,$binding);
check($db->tables===$legacy,'duplicate legacy file not attributed retroactively');
check(count($store->recentReturns(1))===4&&count($store->recentEvents(1))===2,'legacy and current histories readable');
$saved=$db->tables['t_bb_layout_bindings'][1];$db->tables['t_bb_layout_bindings'][1]['return_source_hash']=str_repeat('0',64);
rejects(fn()=>$store->importReturn($c,9,$wire400,$binding400),'tampered profile is not trusted');$db->tables['t_bb_layout_bindings'][1]=$saved;
$db->failTable='t_bb_audit';$prod=$data;$prod['environment']='production';rejects(fn()=>$store->createConnection(1,9,$issuer,$prod),'audit failure connection rollback');$db->failTable='';
check(!isset($db->tables['t_invoicepaymentrecords'])&&!isset($db->tables['t_invoices']),'neither return format mutates finance');
class BbReadFailureDb extends BbDb {
 public $protectedReads=0;
 function get($table){if($this->db_debug||$this->save_queries)throw new LogicException('diagnostics exposed');$this->protectedReads++;return false;}
}
$failureDb=new BbReadFailureDb();$failureStore=new \BancoBrasil\ModuleStore($failureDb,'t_');
foreach([fn()=>$failureStore->connections(1),fn()=>$failureStore->connection(1,1),fn()=>$failureStore->layoutBindings(1),
    fn()=>$failureStore->layoutBinding(1,$c),fn()=>$failureStore->recentReturns(1),fn()=>$failureStore->recentEvents(1)] as $read){
 try{$read();throw new LogicException('failure accepted');}catch(RuntimeException $e){check($e->getMessage()==='bb_storage_unavailable','fixed read storage failure');}
 check($failureDb->db_debug===true&&$failureDb->save_queries===true,'read restores caller diagnostics');
}
check($failureDb->protectedReads===6,'all reads protected before executing query');
echo "OK {$n} Banco do Brasil layout binding checks\n";
