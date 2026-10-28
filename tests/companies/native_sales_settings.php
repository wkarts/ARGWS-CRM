<?php
// Real allocator, storage and sales writer; no Settings route is enabled here.
require __DIR__.'/sales.php';
require_once APPPATH.'services/NativeCompanySalesSettings.php';
$forms=new NativeCompanySalesSettings($db,$p);$start=$GLOBALS['essentialChecks'];
$estimateA=$forms->page($a,7,'estimates');$estimateB=$forms->page($b,7,'estimates');
$proposalA=$forms->page($a,7,'proposals');$proposalB=$forms->page($b,7,'proposals');
essentialCheck(count($estimateA['values'])===17&&count($proposalA['values'])===12,'all original setting fields have bounded owned values');
$estimate=$estimateA['values'];$estimate['estimate_prefix']='SYN-A-';$estimate['predefined_terms_estimate']='Termos A';$estimate['next_estimate_number']=(string)max(1000,((int)$estimate['next_estimate_number'])+1);
$saved=$forms->save($a,7,'estimates',$estimateA['revision'],$estimate);
essentialCheck($saved['values']===$estimate,'all estimate fields are confirmed in selected company');
essentialCheck($forms->page($b,7,'estimates')===$estimateB&&$forms->page($a,7,'proposals')===$proposalA,'saving estimate settings leaves other company and proposal group unchanged');
essentialThrows(fn()=>$forms->save($a,7,'estimates',$estimateA['revision'],$estimate),'native_sales_settings_stale','stale full form rejected');
$bad=$estimate;$bad['default_estimates_pipeline_sort']='unknown';essentialThrows(fn()=>$forms->save($a,7,'estimates',$saved['revision'],$bad),'native_sales_settings_invalid','invalid sort rejected before effects');
$bad=$estimate;$bad['unknown']='1';essentialThrows(fn()=>$forms->save($a,7,'estimates',$saved['revision'],$bad),'native_sales_settings_invalid','undeclared option rejected');
$bad=$estimate;unset($bad['show_project_on_estimate']);essentialThrows(fn()=>$forms->save($a,7,'estimates',$saved['revision'],$bad),'native_sales_settings_invalid','missing option rejected');
$bad=$estimate;$bad['estimate_prefix']="bad\nheader";essentialThrows(fn()=>$forms->save($a,7,'estimates',$saved['revision'],$bad),'native_sales_settings_invalid','control characters rejected');
essentialCheck($forms->page($a,7,'estimates')===$saved,'invalid requests leave both settings and sequence unchanged');
essentialThrows(fn()=>$forms->page($a,12,'estimates'),'native_sales_settings_denied','revoked actor cannot read settings');
essentialThrows(fn()=>$forms->page($principal,7,'estimates'),'native_sales_settings_denied','principal stays on its original settings path');
$newId=$sales->create($a,7,'estimates',array_replace($salesInput,['client_id'=>$ca]),hash('sha256','native-sales-settings-estimate'));
$newDoc=$sales->document($a,7,$newId);
essentialCheck(str_starts_with($newDoc['formatted_number'],'SYN-A-')&&$newDoc['terms']==='Termos A'&&(int)$newDoc['number']===(int)$estimate['next_estimate_number'],'actual estimate writer consumes saved prefix, terms and real allocator');
$proposal=$proposalA['values'];$proposal['proposal_number_prefix']='SYN-P-';$proposal['predefined_terms_proposal']='Proposta A';
$savedProposal=$forms->save($a,7,'proposals',$proposalA['revision'],$proposal);
essentialCheck($savedProposal['values']===$proposal&&$forms->page($b,7,'proposals')===$proposalB,'proposal settings remain isolated');
$newProposalId=$sales->create($a,7,'proposals',array_replace($salesInput,['client_id'=>$ca]),hash('sha256','native-sales-settings-proposal'));
$newProposal=$sales->document($a,7,$newProposalId);
essentialCheck(str_starts_with($newProposal['formatted_number'],'SYN-P-')&&$newProposal['terms']==='Proposta A','actual proposal writer consumes owned prefix and terms');
// A later storage failure must roll back the earlier series update too.
$current=$forms->page($a,7,'estimates');$atomic=$current['values'];$atomic['next_estimate_number']=(string)((int)$atomic['next_estimate_number']+3);$atomic['estimate_prefix']='MUST-ROLL-BACK-';
if($db->driver()==='mysql')$db->query("CREATE TRIGGER {$p}native_sales_settings_failure BEFORE INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_settings_batch_updated' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'; END IF; END");
else $db->query("CREATE TRIGGER {$p}native_sales_settings_failure BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='company_settings_batch_updated' BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$forms->save($a,7,'estimates',$current['revision'],$atomic),$db->driver()==='mysql'?'issuer_storage_failed':'company_settings_write_unconfirmed','failed late settings audit rolls back whole form');
$db->query('DROP TRIGGER '.$p.'native_sales_settings_failure');
essentialCheck($forms->page($a,7,'estimates')===$current,'both settings and allocator roll back on late failure');
// Real internal Settings form adapter shares the exact stored revision and real due-date consumer.
require_once APPPATH.'services/NativeCompanySettingsForms.php';require_once APPPATH.'services/NativeCompanySalesDates.php';
$nativeForms=new NativeCompanySettingsForms($db,$p);$nativePage=$nativeForms->page($a,7,'estimates');$nativeValues=$nativePage['values'];$nativeValues['estimate_due_after']='3';
$nativeSaved=$nativeForms->save($a,7,'estimates',$nativeValues,[],$nativePage['revision']);
essentialCheck($nativeSaved['values']['estimate_due_after']==='3'&&(new NativeCompanySalesDates($db,$p))->dueDate($a,7,'estimates','2026-01-31')==='2026-02-03','internal native form write reaches real company-specific date consumer');
echo 'Native sales settings storage: '.($GLOBALS['essentialChecks']-$start)." added checks. Other consumers and HTTP forms remain closed.\n";
