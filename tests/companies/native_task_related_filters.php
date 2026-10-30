<?php
require __DIR__.'/native_task_related_table.php';
require_once APPPATH.'services/NativeCompanyFilters.php';
NativeCompanyFilterSchema::ensure($db,$p);
foreach(['filters'=>'id INTEGER PRIMARY KEY,staff_id INTEGER,name TEXT,identifier TEXT,is_shared INTEGER,builder TEXT','filter_defaults'=>'staff_id INTEGER,filter_id INTEGER,identifier TEXT,view TEXT']as$t=>$ddl)if(!$db->table_exists($p.$t))$db->query('CREATE TABLE '.$p.$t.' ('.$ddl.')');
unset($db->data_cache['table_names']);
$store=new NativeCompanyFilters($db,$p,$a,7,$table);
$builder=['match_type'=>'and','rules'=>[['id'=>'status','type'=>'MultiSelectRule','operator'=>'in','value'=>['1','4']]]];
$input=['name'=>'Task rules','is_shared'=>1,'builder'=>$builder];$saved=$store->save($input);
essentialCheck($saved['identifier']==='related_tasks'&&$store->listing()[0]['builder']===$table->normalizeBuilder($builder),'Actual related_tasks store persists normalized native rules');
$store->default($saved['id']);essentialCheck($store->listing()[0]['is_default']===true,'Actual task filter default persists');
$changed=$store->save(['name'=>'Revised']+$input,$saved['id'],$saved['revision']);essentialCheck($changed['revision']==='2','Task filter CAS advances');
essentialThrows(fn()=>$store->save($input,$saved['id'],$saved['revision']),'native_filter_stale','Task filter stale CAS denied');
essentialThrows(fn()=>new NativeCompanyFilters($db,$p,$b,7,$table),'native_filter_denied','Foreign issuer cannot reuse actual task table authority');
$rebuilt=$factory->make($a,7,'related_tasks',$store->listing());essentialCheck(str_contains((string)$rebuilt->filtersJs(),'Revised'),'Saved task rules rebuild native app-filters');
$store->delete($changed['id'],$changed['revision']);essentialCheck($store->listing()===[],'Task filter delete preserves actual default integrity');
echo 'Related tasks real filter persistence: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
