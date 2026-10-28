<?php
/** Overview task counts use the same authorized reader as the project task table. */
require __DIR__.'/native_project_related_tasks.php';
require_once APPPATH.'services/NativeCompanyProjectDetail.php';
$detail=new NativeCompanyProjectDetail($db,$p);$metrics=$detail->taskMetrics($a,7,$projectCanonical);
$facts=(new NativeCompanyRelatedTaskRead($db,$p))->read($a,7,'project',$projectCanonical);
$open=count(array_filter($facts['items'],static fn($item)=>(int)$item['fields']['workflow_status']!==5));
essentialCheck($metrics['total']===count($facts['items'])&&$metrics['open']===$open&&$metrics['completed']===$metrics['total']-$open,'overview counts actual authorized task statuses');
essentialCheck($metrics['completed_percent']===($metrics['total']===0?0:round($metrics['completed']*100/$metrics['total'],2)),'overview task completion percentage has native precision');
essentialThrows(fn()=>$detail->taskMetrics($b,7,$projectCanonical),'native_resource_reference_denied','foreign issuer cannot read project task metrics');
echo "Project detail task metrics match real scoped related tasks; detail route remains closed.\n";
