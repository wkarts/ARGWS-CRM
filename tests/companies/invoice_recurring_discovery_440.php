<?php
/** Regression for the reproduced440 waiting-principal starvation; original mode packet preserved. */
declare(strict_types=1);
require __DIR__.'/invoice_recurring_modes.php';
require_once APPPATH.'services/CompanyInvoiceRecurringDiscovery.php';
foreach([$a,$b,$principalBilling]as$owner)foreach($ir->page($owner,7)as$row)if(in_array($row['state'],['active','processing','paused','paused_processing'],true))$ir->control($owner,7,$row['id'],$row['revision'],'cancel',$mk('fair-isolate-'.$owner.'-'.$row['id']));
$parent=$s->create($a,7,$baseInput,$mk('fair-unpaid'));
$waiting=$ir->create($a,7,$baseInput,array_replace($ispec,['cycles'=>0,'mode'=>'issue','principal_invoice_id'=>$parent]),$mk('fair-waiting'));
$runnable=$ir->create($b,7,array_replace($baseInput,['client_id'=>$cb]),array_replace($ispec,['cycles'=>1,'mode'=>'draft','principal_invoice_id'=>0]),$mk('fair-runnable'));
$waitBefore=$db->query('SELECT * FROM '.$p.'company_invoice_schedules WHERE id=?',[$waiting])->row_array();$nativeBefore=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;$draftBefore=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n;
$first=$ir->processPending('2026-10-19',1);
$duringCycle=$ir->create($a,7,$baseInput,array_replace($ispec,['cycles'=>0,'mode'=>'issue','principal_invoice_id'=>$parent]),$mk('fair-arrived-during-pass'));
$second=$ir->processPending('2026-10-19',1);
essentialCheck($first===['completed'=>0,'blocked'=>0]&&$second===['completed'=>1,'blocked'=>0],'limit1 traverses unpaid A then emits eligible B on next invocation');
essentialCheck($ir->document($b,7,$runnable)['completed_cycles']==1&&$ir->document($b,7,$runnable)['state']==='completed','eligible company does not remain starved behind unpaid principal');
essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_schedules WHERE id=?',[$waiting])->row_array()===$waitBefore,'discovery never changes waiting principal schedule, date, cycles, revision or state');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$nativeBefore&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n===$draftBefore+1,'fair traversal creates only the authorized private draft and no native financial row');
// More waiting rows than a batch, same clock/date, then an eligible other-company series.
essentialCheck((int)$db->query('SELECT high_water_id FROM '.$p.'company_invoice_schedule_discovery WHERE id=1')->row()->high_water_id===$runnable,'new arrival does not move current pass high water before older eligible B');
$many=[$duringCycle];for($n=0;$n<7;$n++)$many[]=$ir->create($a,7,$baseInput,array_replace($ispec,['cycles'=>0,'mode'=>'issue','principal_invoice_id'=>$parent]),$mk('fair-many-'.$n));
$afterMany=$ir->create($b,7,array_replace($baseInput,['client_id'=>$cb]),array_replace($ispec,['cycles'=>1,'mode'=>'draft','principal_invoice_id'=>0]),$mk('fair-after-many'));
$out=[];for($n=0;$n<12;$n++){$out[]=$ir->processPending('2026-10-19',1);if($ir->document($b,7,$afterMany)['state']==='completed')break;}
essentialCheck($ir->document($b,7,$afterMany)['state']==='completed'&&count($out)<=10,'same-second stable ID round robin crosses more waiting rows than worker batch');
foreach(array_merge([$waiting],$many)as$id)essentialCheck($ir->document($a,7,$id)['completed_cycles']==0&&$ir->document($a,7,$id)['state']==='active','unpaid series remains unmodified by fairness '.$id);
$discovery=new CompanyInvoiceRecurringDiscovery($db,$p);$cursor=$db->query('SELECT * FROM '.$p.'company_invoice_schedule_discovery')->row_array();$audits=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_invoice_schedule_discovered'")->row()->n;
CompanyInvoiceRecurringDiscoverySchema::ensure($db,$p);CompanyInvoiceRecurringDiscoverySchema::ensure($db,$p);
essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_schedule_discovery')->row_array()===$cursor,'native ensure/replay preserves active cursor and revision');
// Cursor and provenance must commit together; a failure never skips a batch or emits side effects.
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$ir->processPending('2026-10-19',1),'issuer_storage_failed','discovery audit failure propagates without claiming worker success');$db->failOn=null;
essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_schedule_discovery')->row_array()===$cursor&&(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_invoice_schedule_discovered'")->row()->n===$audits,'failed audit leaves cursor and discovery audit unchanged');
$db->failOn='UPDATE '.$p.'company_invoice_schedule_discovery';essentialThrows(fn()=>$discovery->reserve('2026-10-19',1),'issuer_storage_failed','cursor write failure rolls back preceding audit');$db->failOn=null;
essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_schedule_discovery')->row_array()===$cursor&&(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_invoice_schedule_discovered'")->row()->n===$audits,'failed cursor write leaves no orphan provenance');
$db->trans_begin();try{essentialThrows(fn()=>$discovery->reserve('2026-10-19',1),'issuer_outer_transaction_not_allowed','discovery never joins caller transaction');essentialCheck($db->pdo->inTransaction(),'discovery rejection preserves outer transaction');}finally{$db->trans_rollback();}
foreach([0,101]as$limit)essentialThrows(fn()=>$discovery->reserve('2026-10-19',$limit),'company_invoice_recurring_invalid','batch size is bounded at entry');
$selected=$discovery->reserve('2026-10-19',3);essentialCheck(count($selected)<=3&&count(array_unique(array_column($selected,'id')))===count($selected),'wraparound never returns more than batch size or same ID twice');
$cursor=$db->query('SELECT * FROM '.$p.'company_invoice_schedule_discovery')->row_array();$db->query('UPDATE '.$p.'company_invoice_schedule_discovery SET batch_hash=?',[str_repeat('0',64)]);essentialThrows(fn()=>$discovery->reserve('2026-10-19',1),'issuer_invoice_recurring_discovery_corrupt','cursor mutation without matching immutable audit is rejected');$db->query('UPDATE '.$p.'company_invoice_schedule_discovery SET batch_hash=?',[$cursor['batch_hash']]);
// Discovery advances before execution; simulating a crash after reserve still revisits on the next bounded pass.
foreach(array_merge([$waiting],$many)as$id){$r=$ir->document($a,7,$id);$ir->control($a,7,$id,$r['revision'],'cancel',$mk('fair-remove-wait-'.$id));}
$crash=$ir->create($b,7,array_replace($baseInput,['client_id'=>$cb]),array_replace($ispec,['cycles'=>1,'mode'=>'draft','principal_invoice_id'=>0]),$mk('fair-reserve-crash'));$lost=$discovery->reserve('2026-10-19',1);
essentialCheck(count($lost)===1&&(int)$lost[0]['id']===$crash,'synthetic crash reserves only real durable IDs');$beforeCrash=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n;
essentialCheck($ir->processPending('2026-10-19',1)===['completed'=>1,'blocked'=>0]&&$ir->document($b,7,$crash)['completed_cycles']==1,'new invocation revisits reserved-but-unexecuted schedule after wrap');
essentialCheck($ir->processPending('2026-10-19',1)===['completed'=>0,'blocked'=>0]&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_drafts')->row()->n===$beforeCrash+1,'canonical occurrence remains idempotent after discovery replay');
if($db->driver()==='mysql'){
 $db->query('ALTER TABLE '.$p.'company_invoice_schedule_discovery ALTER COLUMN cursor_id SET DEFAULT 0');essentialThrows(fn()=>CompanyInvoiceRecurringDiscoverySchema::ensure($db,$p),'issuer_invoice_recurring_discovery_schema_invalid','native discovery metadata rejects implicit cursor defaults');$db->query('ALTER TABLE '.$p.'company_invoice_schedule_discovery ALTER COLUMN cursor_id DROP DEFAULT');CompanyInvoiceRecurringDiscoverySchema::ensure($db,$p);
 $db->failOn='CREATE TABLE IF NOT EXISTS `'.$p.'company_invoice_schedule_discovery`';essentialThrows(fn()=>CompanyInvoiceRecurringDiscoverySchema::ensure($db,$p),'issuer_storage_failed','native discovery DDL failure cannot report applied schema');$db->failOn=null;CompanyInvoiceRecurringDiscoverySchema::ensure($db,$p);
}
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyInvoice,'durable worker traversal preserves NULL financial legacy');
echo 'Invoice recurring durable bounded discovery/fairness/replay/rollback: '.$GLOBALS['essentialChecks'].' cumulative ('.$db->driver().'). No external transport.'.PHP_EOL;
