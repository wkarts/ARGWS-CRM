<?php
/** Keep the original eight-column fixture unchanged for its downstream controller tests. */
require __DIR__.'/native_project_page.php';
$custom=new NativeCompanyCustomFields($db,$p);
$field=$custom->saveDefinition($a,7,['fieldto'=>'projects','name'=>'Original native project field','type'=>'input','show_on_table'=>1],null,null,str_repeat('7',32));
$canonicalProject=NativeCompanyResourceId::encode('company_project',$new);
IssuerTransaction::run($db,$p,array_merge($custom->tableNames(),['company_projects']),fn()=>$custom->saveInOwnedTransaction($a,7,'projects',$canonicalProject,[$field['id']=>'Scoped project value']));
$customRequest=$request;$customRequest['columns'][]=['data'=>'8','name'=>'','searchable'=>true,'orderable'=>true,'search'=>['value'=>'','regex'=>false]];$customRequest['search']['value']='Scoped project value';$customRequest['order']=[['column'=>8,'dir'=>'asc']];
$customPage=$page->read($a,7,$customRequest);
essentialCheck(count($customPage['rows'])===1&&$customPage['rows'][0]['id']===$canonicalProject&&$customPage['rows'][0]['custom_values'][$field['id']]==='Scoped project value','Typed custom-field namespace remains intact in project sorting, search and visible value');
echo 'Native projects actual custom-field column: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
