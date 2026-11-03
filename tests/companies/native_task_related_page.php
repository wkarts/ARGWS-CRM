<?php
require __DIR__.'/native_task_ticket_form.php';require_once APPPATH.'services/NativeCompanyRelatedTaskPage.php';
$columns=[];for($n=0;$n<9;$n++)$columns[]=['data'=>(string)$n,'name'=>'','searchable'=>'true','orderable'=>$n?'true':'false','search'=>['value'=>'','regex'=>'false']];
$wire=['draw'=>'3','start'=>'0','length'=>'25','columns'=>$columns,'order'=>[['column'=>'5','dir'=>'asc']],'search'=>['value'=>'','regex'=>'false']];
$pages=new NativeCompanyRelatedTaskPage($db,$p);$page=$pages->read($a,7,'ticket',$ticketCanonical,$wire,'2026-10-25');
essentialCheck($page['draw']===3&&$page['recordsTotal']===1&&$page['recordsFiltered']===1&&$page['rows'][0]['id']===$new,'Original DataTable counts and canonical ID from real parent-owned rows');
essentialCheck($page['rows'][0]['facts']['billable']===($page['rows'][0]['fields']['billable']===1)&&$page['rows'][0]['facts']['billed']===false,'Declared billable and actual billing fence are distinct facts');
$filtered=$pages->read($a,7,'ticket',$ticketCanonical,array_replace($wire,['search'=>['value'=>'missing task','regex'=>'false']]),'2026-10-25');essentialCheck($filtered['recordsTotal']===1&&$filtered['recordsFiltered']===0&&$filtered['rows']===[],'Actual search changes filtered count without hiding total');
$offset=$pages->read($a,7,'ticket',$ticketCanonical,array_replace($wire,['start'=>'1']),'2026-10-25');essentialCheck($offset['rows']===[]&&$offset['recordsFiltered']===1,'Native pagination preserves filtered count');
essentialThrows(fn()=>$pages->read($a,7,'ticket',$ticketCanonical,array_replace($wire,['order'=>[['column'=>'99','dir'=>'asc']]]),'2026-10-25'),'native_task_table_request_invalid','No caller-selected SQL columns');
essentialThrows(fn()=>$pages->read($b,7,'ticket',$ticketCanonical,$wire,'2026-10-25'),'native_ticket_denied','Cross-company parent cannot count task rows');
echo 'Native related task page: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
