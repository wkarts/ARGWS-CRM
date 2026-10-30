<?php
require __DIR__.'/native_task_ticket_form.php';require_once APPPATH.'services/NativeCompanyRelatedTaskTableFactory.php';
$factory=new NativeCompanyRelatedTaskTableFactory($db,$p);$table=$factory->make($a,7);essentialCheck($table->id()==='related_tasks'&&$table->viewName()==='tasks_relations'&&$table->contextIdentity()===['issuer_id'=>$a,'actor_id'=>7],'Exact original related_tasks filter identity is issuer and actor bound');
essentialCheck(count($table->rules())===15,'All original fifteen native task filter controls exist');
$normalized=$table->normalizeBuilder(['match_type'=>'and','rules'=>[['id'=>'startdate','type'=>'DateRule','operator'=>'equal','value'=>'25/10/2026']]]);essentialCheck($normalized['rules'][0]['value']==='2026-10-25','Original localized date control uses selected company format');
essentialThrows(fn()=>$table->normalizeBuilder(['match_type'=>'and','rules'=>[['id'=>'status','type'=>'MultiSelectRule','operator'=>'in','value'=>['99']]]]),'native_task_filter_invalid','Unknown task status cannot become a filter');
echo 'Original related task table contract: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
