<?php
require __DIR__.'/native_task_related_filters.php';
$main=$factory->make($a,7,'tasks');
essentialCheck($main->id()==='tasks'&&$main->viewName()==='tasks','Main tasks retains original app-filters identifier and view');
$mainStore=new NativeCompanyFilters($db,$p,$a,7,$main);
$input=['name'=>'Main task filter','is_shared'=>1,'builder'=>$builder];$created=$mainStore->save($input);
essentialCheck($created['identifier']==='tasks'&&count($mainStore->listing())===1&&$store->listing()===[],'Main and related task saved filters stay distinct');
$mainStore->default($created['id']);essentialCheck($mainStore->listing()[0]['is_default']===true,'Main filter default is persisted under exact company and view');
$updated=$mainStore->save(['name'=>'Updated main task filter']+$input,$created['id'],$created['revision']);
essentialThrows(fn()=>$mainStore->save($input,$created['id'],$created['revision']),'native_filter_stale','Old revision cannot replace main task filter');
essentialThrows(fn()=>$store->delete($updated['id'],$updated['revision']),'native_filter_denied','Related task view cannot delete main task filter');
essentialThrows(fn()=>new NativeCompanyFilters($db,$p,$b,7,$main),'native_filter_denied','Other company cannot use main task filter authority');
$rebuilt=$factory->make($a,7,'tasks',$mainStore->listing());essentialCheck(str_contains((string)$rebuilt->filtersJs(),'Updated main task filter'),'Main saved filters rehydrate through original component');
$mainStore->delete($updated['id'],$updated['revision']);essentialCheck($mainStore->listing()===[],'Main filter deletion preserves scoped defaults');
echo 'Original main task filter storage: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
