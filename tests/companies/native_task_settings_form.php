<?php
require __DIR__.'/native_task_workflow_defaults.php';
require_once APPPATH.'services/NativeCompanySettingsForms.php';
$forms=new NativeCompanySettingsForms($db,$p);$old=$forms->page($a,7,'tasks');$other=$forms->page($b,7,'tasks');
$v=$old['values'];$v['default_task_status']='3';$v['default_task_priority']='4';$v['auto_stop_tasks_timers_on_new_timer']='1';$v['timer_started_change_status_in_progress']='1';$v['tasks_kanban_limit']='70';$v['task_modal_class']='modal-xl';
$saved=$forms->save($a,7,'tasks',array_reverse($v,true),[],$old['revision']);
essentialCheck($saved['values']===$v,'same original task controls persist through atomic policy and scoped option writers independent of POST ordering');
essentialCheck($forms->page($b,7,'tasks')===$other,'other company settings byte-preserved');
essentialCheck((new CompanyTaskPreferences($db,$p))->page($a,7)['default_task_status']==='testing','native workflow3 maps to canonical testing, not historical source id3');
essentialThrows(fn()=>$forms->save($a,7,'tasks',$v,[],$old['revision']),'native_settings_stale','stale complete form denied');
$bad=$v;$bad['default_task_status']='999';essentialThrows(fn()=>$forms->save($a,7,'tasks',$bad,[],$saved['revision']),'company_task_preferences_invalid','unknown workflow denied');
$bad=$v;$bad['task_modal_class']='modal-xl" onclick="bad';essentialThrows(fn()=>$forms->save($a,7,'tasks',$bad,[],$saved['revision']),'native_task_settings_invalid','modal class cannot inject attributes');
$bad=$v;$bad['smtp_password']='not allowed';essentialThrows(fn()=>$forms->save($a,7,'tasks',$bad,[],$saved['revision']),'native_task_settings_invalid','task tab cannot write unrelated secrets');
$db->query("CREATE TRIGGER reject_native_task_settings BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='company_settings_batch_updated' BEGIN SELECT RAISE(ABORT,'synthetic'); END");
$changed=$v;$changed['default_task_status']='5';$changed['tasks_kanban_limit']='71';
essentialThrows(fn()=>$forms->save($a,7,'tasks',$changed,[],$saved['revision']),'issuer_storage_failed','later options audit failure rolls back all earlier policy participants');
$db->query('DROP TRIGGER reject_native_task_settings');essentialCheck($forms->page($a,7,'tasks')===$saved,'whole settings group rollback confirmed');
essentialThrows(fn()=>$forms->page($a,52,'tasks'),'native_settings_denied','unrelated actor cannot view task settings');
echo "Native Settings/tasks real SQLite: original14 controls, four existing policy participants, A/B, workflow mapping, CAS and whole-form rollback passed.\n";
$native_settings_task_values=$saved['values'];$task_statuses=WorkStatusPresentation::tasks('_l');
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
ob_start();try{require APPPATH.'views/admin/settings/includes/tasks.php';$nativeHtml=ob_get_clean();}catch(Throwable$e){ob_end_clean();throw$e;}finally{restore_error_handler();}
$dom=new DOMDocument();$dom->loadHTML($nativeHtml,LIBXML_NOERROR|LIBXML_NOWARNING);$xpath=new DOMXPath($dom);
foreach(NativeCompanyTaskSettings::fields()as$name)essentialCheck($xpath->query('//*[@name="settings['.$name.']"]')->length>0,'original task setting field retained '.$name);
essentialCheck($xpath->query('//select[@name="settings[default_task_status]"]/option[@value="3" and @selected]')->length===1,'original status select renders real canonical testing mapped to workflow3');
essentialCheck(!str_contains($nativeHtml,'/company_context')&&!str_contains($nativeHtml,'/company_settings'),'native settings partial adds no parallel links');
echo "Actual original task settings partial and production fields helper rendered all controls with global get_option forbidden.\n";
