<?php
// Cleanup our dependent tables before the prerequisite's schedule shutdown handler.
register_shutdown_function(static function():void{if(!isset($GLOBALS['db'],$GLOBALS['p']))return;foreach(['company_invoice_schedule_item_links','company_invoice_schedule_prices','company_invoice_reprice_proposals','company_items','company_taxes','company_item_groups']as$t)$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].$t);unset($GLOBALS['db']->data_cache['table_names']);});
require __DIR__.'/invoice_recurring_modes.php';
require_once APPPATH.'services/CompanyInvoiceRepricing.php';
$start=$GLOBALS['essentialChecks'];
foreach(['company_item_groups','company_taxes','company_items']as$table){
 if($db->driver()==='mysql')$db->query('CREATE TABLE '.$p.$table.'('.implode(',',NativeCompanyAuxiliarySchema::definitions($p)[$table]).') ENGINE=InnoDB');
 else{$parts=[];foreach(NativeCompanyAuxiliarySchema::fields()[$table]as$name=>[$type,$nullable]){$parts[]='`'.$name.'` '.(str_contains($type,'AUTO_INCREMENT')?'INTEGER PRIMARY KEY AUTOINCREMENT':$type).($nullable?' NULL':' NOT NULL');}$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).')');}
}
unset($db->data_cache['table_names']);CompanyInvoiceRepricingSchema::ensure($db,$p);CompanyInvoiceRepricingSchema::ensure($db,$p);
foreach([[101,$a,'X'],[102,$a,'Y'],[103,$b,'X']]as[$id,$owner,$desc])$db->query('INSERT INTO '.$p.'company_items(id,issuer_id,description,long_description,rate,currency_rates_json,unit,tax,tax2,group_id,revision) VALUES(?,?,?,?,?,?,?,NULL,NULL,NULL,1)',[$id,$owner,$desc,'Reviewed catalogue line','10.1000','{}','unit']);
$catalogBefore=$db->query('SELECT * FROM '.$p.'company_items ORDER BY id')->result_array();
$x=NativeCompanyResourceId::encode('company_item',101);$y=NativeCompanyResourceId::encode('company_item',102);$foreign=NativeCompanyResourceId::encode('company_item',103);
$base=$monthInput;$base['items']=[['description'=>'Legacy X description differs','qty'=>'2.00','rate'=>'10.10']];$spec=$ispec;$spec['cycles']=0;
$ids=[];for($n=0;$n<3;$n++)$ids[]=$ir->create($a,7,$base,$spec,hash('sha256','repricing-'.$n));
$invoice=$ir->run($a,7,$ids[0],'2026-01-01');$oldInvoice=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$invoice])->result_array();
$prices=new CompanyInvoiceRepricing($db,$p);
$directory=fn()=>array_column($prices->directory($a,7),null,'id');$rows=$directory();
$bad=['rules'=>[['item_id'=>$x,'rate'=>'20.00']],'targets'=>[['schedule_id'=>$ids[0],'source_hash'=>$rows[$ids[0]]['source_hash'],'lines'=>[['index'=>0,'item_id'=>$x,'qty'=>'']],'additions'=>[]]],'timezone'=>'America/Sao_Paulo','execute_local'=>'','filters'=>[]];
essentialThrows(fn()=>$prices->preview($a,7,$bad,hash('sha256','unreviewed')),'invoice_reprice_review_required','same description is never an item binding');
essentialThrows(fn()=>$prices->reviewLink($a,7,$ids[0],0,$foreign,$rows[$ids[0]]['payload_hash'],CompanyInvoiceRepricing::ZERO),'company_catalog_denied','foreign catalogue item cannot bind to this issuer');
foreach([$ids[0],$ids[1]]as$id)$prices->reviewLink($a,7,$id,0,$x,$rows[$id]['payload_hash'],CompanyInvoiceRepricing::ZERO);
$rows=$directory();$input=['rules'=>[['item_id'=>$x,'rate'=>'20.00'],['item_id'=>$y,'rate'=>'7.50']],'targets'=>[
 ['schedule_id'=>$ids[0],'source_hash'=>$rows[$ids[0]]['source_hash'],'lines'=>[['index'=>0,'item_id'=>$x,'qty'=>'3.00']],'additions'=>[['item_id'=>$y,'qty'=>'1.00']]],
 ['schedule_id'=>$ids[1],'source_hash'=>$rows[$ids[1]]['source_hash'],'lines'=>[['index'=>0,'item_id'=>$x,'qty'=>'']],'additions'=>[]]],'timezone'=>'America/Sao_Paulo','execute_local'=>'','filters'=>['item'=>$x,'payment_mode'=>'']];
$schedulesBefore=$db->query('SELECT * FROM '.$p.'company_invoice_schedules ORDER BY id')->result_array();$key=hash('sha256','reviewed-campaign');$proposal=$prices->preview($a,7,$input,$key);
essentialCheck($proposal['preview']['targets'][0]['after_amounts']['total']==='67.50'&&$proposal['preview']['targets'][1]['after_amounts']['total']==='40.00','native simulation applies rule price, local quantity and one local addition');
essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_schedules ORDER BY id')->result_array()===$schedulesBefore,'simulation changes no schedule or calendar');
essentialCheck($prices->preview($a,7,$input,$key)['revision']===$proposal['revision'],'preview retry returns exact same revision');
essentialThrows(fn()=>$prices->confirm($b,7,$key,$proposal['revision']),'invoice_reprice_denied','cross-company proposal inaccessible');
essentialThrows(fn()=>$prices->confirm($a,7,$key,str_repeat('f',64)),'invoice_reprice_stale','unreviewed revision cannot apply');
$applied=$prices->confirm($a,7,$key,$proposal['revision']);essentialCheck($applied['state']==='applied','exact reviewed proposal applied');
$after=$directory();essentialCheck($after[$ids[0]]['payload']['items'][0]['rate']==='20.00'&&$after[$ids[0]]['payload']['items'][0]['qty']==='3.00'&&count($after[$ids[0]]['payload']['items'])===2,'selected occurrence gets price, local quantity and local plus');
essentialCheck($after[$ids[1]]['payload']['items'][0]['qty']==='2.00'&&count($after[$ids[1]]['payload']['items'])===1&&$after[$ids[2]]['payload']===$rows[$ids[2]]['payload'],'same client other recurrence keeps quantity; unselected recurrence unchanged');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$invoice])->result_array()===$oldInvoice&&$db->query('SELECT * FROM '.$p.'company_items ORDER BY id')->result_array()===$catalogBefore,'emitted invoice and catalogue are byte-preserved');
$journals=$db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices ORDER BY id')->result_array();$prices->confirm($a,7,$key,$proposal['revision']);essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices ORDER BY id')->result_array()===$journals,'repeated confirmation creates no duplicate revisions');
foreach($ids as$id){$old=array_values(array_filter($schedulesBefore,fn($r)=>(int)$r['id']===$id))[0];$current=$db->query('SELECT * FROM '.$p.'company_invoice_schedules WHERE id=?',[$id])->row_array();foreach(['payload_json','payload_hash','input_hash','context_hash','config_json','config_hash','transport_hash','next_date','completed_cycles']as$field)essentialCheck($old[$field]===$current[$field],'repricing preserves historical '.$field);}
$future=$ir->run($a,7,$ids[0],'2026-02-01');essentialCheck(CompanyInvoiceAmounts::minor((string)$db->query('SELECT total FROM '.$p.'invoices WHERE id=?',[$future])->row()->total)===6750,'next canonical emission consumes effective snapshot');
essentialCheck($ir->document($a,7,$ids[0])['runs'][0]['canonical_invoice_id']===$invoice,'old occurrence remains independently verifiable after new emission');
$rows=$directory();$stale=$input;$stale['targets']=[['schedule_id'=>$ids[1],'source_hash'=>$rows[$ids[1]]['source_hash'],'lines'=>[['index'=>0,'item_id'=>$x,'qty'=>'']],'additions'=>[]]];$stale['rules']=[['item_id'=>$x,'rate'=>'30.00']];$p1=$prices->preview($a,7,$stale,hash('sha256','stale-proposal'));$p2=$prices->preview($a,7,$stale,hash('sha256','winning-proposal'));$prices->confirm($a,7,$p2['request_key'],$p2['revision']);
essentialThrows(fn()=>$prices->confirm($a,7,$p1['request_key'],$p1['revision']),'invoice_reprice_stale','concurrent repricing invalidates prior simulation');
essentialCheck(CompanyInvoiceRepricing::timing('America/Sao_Paulo','2027-01-01T10:00')==='2027-01-01 13:00:00','explicit business timezone converts to UTC');
essentialThrows(fn()=>CompanyInvoiceRepricing::timing('America/New_York','2027-03-14T02:30'),'invoice_reprice_time_invalid','nonexistent local time rejected');
essentialThrows(fn()=>CompanyInvoiceRepricing::timing('America/New_York','2027-11-07T01:30'),'invoice_reprice_time_ambiguous','ambiguous local time rejected rather than guessing offset');
// Clock-controlled scheduling still executes the real storage/ACL/financial transaction.
$now='2027-01-01 12:00:00';$scheduled=new CompanyInvoiceRepricing($db,$p,static function()use(&$now){return $now;});
$rows=$directory();$timed=$stale;$timed['targets'][0]['source_hash']=$rows[$ids[1]]['source_hash'];$timed['rules'][0]['rate']='35.00';$timed['execute_local']='2027-01-01T10:00';
$pending=$scheduled->preview($a,7,$timed,hash('sha256','scheduled-proposal'));
$beforeTimed=$db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices ORDER BY id')->result_array();
$scheduled->confirm($a,7,$pending['request_key'],$pending['revision']);
essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices ORDER BY id')->result_array()===$beforeTimed,'scheduled confirmation writes no price before due time');
essentialThrows(fn()=>$scheduled->execute($a,7,$pending['request_key']),'invoice_reprice_not_due','schedule cannot execute before explicit business time');
$now='2027-01-01 13:00:00';$scheduled->execute($a,7,$pending['request_key']);
essentialCheck($directory()[$ids[1]]['payload']['items'][0]['rate']==='35.00','due proposal applies through same verified financial transaction');
$journalCount=count($db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices')->result_array());$scheduled->execute($a,7,$pending['request_key']);
essentialCheck(count($db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices')->result_array())===$journalCount,'scheduled retry is idempotent');
$rows=$directory();$all=$input;$all['rules']=[['item_id'=>$x,'rate'=>'40.00']];$all['targets']=[];
foreach([$ids[0],$ids[1]]as$id)$all['targets'][]=['schedule_id'=>$id,'source_hash'=>$rows[$id]['source_hash'],'lines'=>[['index'=>0,'item_id'=>$x,'qty'=>'']],'additions'=>[]];
$both=$prices->preview($a,7,$all,hash('sha256','all-or-nothing'));
$one=$all;$one['targets']=[$all['targets'][1]];$one['rules'][0]['rate']='36.00';$other=$prices->preview($a,7,$one,hash('sha256','second-target-drift'));$prices->confirm($a,7,$other['request_key'],$other['revision']);
$firstBefore=$directory()[$ids[0]]['payload'];
essentialThrows(fn()=>$prices->confirm($a,7,$both['request_key'],$both['revision']),'invoice_reprice_stale','second target drift blocks whole batch');
essentialCheck($directory()[$ids[0]]['payload']===$firstBefore,'first target not partially changed when later target stale');
// Due stale proposals leave the automatic queue with an immutable, non-sensitive reason.
$now='2027-01-02 12:00:00';$rows=$directory();$blockedInput=$timed;$blockedInput['execute_local']='2027-01-02T10:00';$blockedInput['targets'][0]['source_hash']=$rows[$ids[1]]['source_hash'];
$blockProposal=$scheduled->preview($a,7,$blockedInput,hash('sha256','scheduled-stale-worker'));
$scheduled->confirm($a,7,$blockProposal['request_key'],$blockProposal['revision']);
$intervening=$blockedInput;$intervening['execute_local']='';$intervening['rules'][0]['rate']='37.00';
$interveningProposal=$prices->preview($a,7,$intervening,hash('sha256','scheduled-stale-intervening'));$prices->confirm($a,7,$interveningProposal['request_key'],$interveningProposal['revision']);
$beforeBlocked=$db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices ORDER BY id')->result_array();$now='2027-01-02 13:00:00';
$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$scheduled->processDue(),'issuer_storage_failed','failed blocked audit cannot claim a durable rejection');$db->failOn=null;
essentialCheck($scheduled->proposal($a,7,$blockProposal['request_key'])['state']==='scheduled','audit rollback leaves proposal available for safe retry');
essentialCheck($scheduled->processDue()===['applied'=>0,'blocked'=>1],'worker records stale rejection without application');
$blocked=$scheduled->proposal($a,7,$blockProposal['request_key']);
essentialCheck($blocked['state']==='blocked'&&$blocked['block_reason']==='source_changed'&&$blocked['preview_hash']===$blockProposal['preview_hash'],'blocked reason retains reviewed simulation');
essentialCheck($scheduled->processDue()===['applied'=>0,'blocked'=>0],'blocked proposal no longer monopolizes due queue');
essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices ORDER BY id')->result_array()===$beforeBlocked,'blocked worker does not alter any price journal');
$scheduled->cancel($a,7,$blocked['request_key'],$blocked['revision']);essentialCheck($scheduled->proposal($a,7,$blocked['request_key'])['state']==='cancelled','reviewed blocked proposal can be cancelled');
$now='2027-01-03 12:00:00';$rows=$directory();$revokedInput=$blockedInput;$revokedInput['execute_local']='2027-01-03T10:00';$revokedInput['targets'][0]['source_hash']=$rows[$ids[1]]['source_hash'];
$revoked=$scheduled->preview($a,7,$revokedInput,hash('sha256','scheduled-revoked-worker'));$scheduled->confirm($a,7,$revoked['request_key'],$revoked['revision']);
$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');$now='2027-01-03 13:00:00';
essentialCheck($scheduled->processDue()===['applied'=>0,'blocked'=>1],'revoked actor cannot apply scheduled prices');
$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
$revokedState=$scheduled->proposal($a,7,$revoked['request_key']);essentialCheck($revokedState['state']==='blocked'&&$revokedState['block_reason']==='access_changed','technical blocked observation survives authorization revocation');
essentialCheck($scheduled->processDue()===['applied'=>0,'blocked'=>0],'restored permission does not silently reauthorize blocked proposal');
$record=$db->query('SELECT * FROM '.$p.'company_invoice_schedule_prices ORDER BY id LIMIT 1')->row_array();
$db->query('UPDATE '.$p.'company_invoice_schedule_prices SET payload_hash=? WHERE id=?',[str_repeat('f',64),$record['id']]);
essentialThrows(fn()=>$directory(),'issuer_storage_failed','corrupt historical snapshot blocks reading/emission');
$db->query('UPDATE '.$p.'company_invoice_schedule_prices SET payload_hash=? WHERE id=?',[$record['payload_hash'],$record['id']]);
echo 'Invoice repricing: '.($GLOBALS['essentialChecks']-$start).' added checks ('.$db->driver().'); no provider calls, no calendar/catalogue/issued-invoice writes.'.PHP_EOL;
