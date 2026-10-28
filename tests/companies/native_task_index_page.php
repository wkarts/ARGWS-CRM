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
essentialThrows(fn()=>(new NativeCompanyRelatedTaskPage($db,$p))->readIndex(1,7,$input,'2026-10-25'),'native_task_main_legacy_projection_required','Secondary-only projection cannot omit historical principal data');
echo 'Main task page: '.$GLOBALS['essentialChecks']." cumulative checks passed; rendering and HTTP remain pending.\n";
