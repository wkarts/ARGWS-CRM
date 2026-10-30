<?php
require __DIR__.'/native_task_index_projection.php';
require_once APPPATH.'services/NativeCompanyRelatedTaskPage.php';
$columns=[];for($n=0;$n<9;$n++)$columns[]=['data'=>(string)$n,'name'=>'','searchable'=>'true','orderable'=>$n?'true':'false','search'=>['value'=>'','regex'=>'false']];
$input=['draw'=>'9','start'=>'0','length'=>'25','columns'=>$columns,'order'=>[['column'=>'5','dir'=>'asc']],'search'=>['value'=>'','regex'=>'false']];
$page=(new NativeCompanyRelatedTaskPage($db,$p))->readIndex($a,7,$input,'2026-10-25');
essentialCheck($page['draw']===9&&$page['recordsTotal']>=2&&count($page['rows'])===$page['recordsFiltered'],'Main task page returns authorized mixed-source count and original DataTables wire');
$kinds=array_unique(array_column($page['rows'],'source_kind'));essentialCheck(in_array('company_task',$kinds,true)&&in_array('company_standalone_task',$kinds,true),'Main page combines project and standalone sources');
$filter=$input;$filter['search']['value']='Updated ticket-linked task';$found=(new NativeCompanyRelatedTaskPage($db,$p))->readIndex($a,7,$filter,'2026-10-25');
essentialCheck($found['recordsTotal']===$page['recordsTotal']&&$found['recordsFiltered']===1&&$found['rows'][0]['id']===$new,'Search returns exact scoped task with independent total');
$offset=$input;$offset['start']='1';$offset['length']='1';$one=(new NativeCompanyRelatedTaskPage($db,$p))->readIndex($a,7,$offset,'2026-10-25');essentialCheck(count($one['rows'])===1&&$one['recordsTotal']===$page['recordsTotal'],'Pagination does not change total');
$bad=$input;$bad['order']=[['column'=>'99','dir'=>'asc']];essentialThrows(fn()=>(new NativeCompanyRelatedTaskPage($db,$p))->readIndex($a,7,$bad,'2026-10-25'),'native_task_table_request_invalid','Caller cannot inject sort column');
$db->query('INSERT INTO '.$p.'taskstimers(id,task_id,start_time,end_time,staff_id,hourly_rate,note) VALUES(701,?,1577880000,NULL,7,125.50,?)',[$legacyId,'Preserved timer']);
$primaryPage=(new NativeCompanyRelatedTaskPage($db,$p))->readIndex(1,7,$input,'2026-10-25');
essentialCheck($primaryPage['recordsTotal']===1&&$primaryPage['recordsFiltered']===1&&$primaryPage['rows'][0]['id']===(string)$legacyId&&$primaryPage['rows'][0]['source_kind']==='task','Principal page contains the actual historical task and excludes coincident secondary identifiers');
essentialCheck($primaryPage['rows'][0]['facts']['startdate']==='2020-01-01'&&(int)$primaryPage['rows'][0]['running_timer']['id']===701&&(int)$primaryPage['rows'][0]['running_timer']['started_at']===1577880000&&$primaryPage['rows'][0]['current_user_is_creator'],'Principal task retains actual dates, creator and running timer');
essentialCheck((new NativeCompanyRelatedTaskPage($db,$p))->readIndex($a,7,$input,'2026-10-25')===$page,'Historical timer cannot change the secondary table');
echo 'Main task page: '.$GLOBALS['essentialChecks']." cumulative checks passed; rendering and HTTP remain pending.\n";
// Child fixtures consume prior assertions; do not retain their diagnostic SQL trace in the bounded PHP worker.
$db->queries=[];
