<?php
require __DIR__.'/../application/services/NativeCompanyTaskFormValues.php';
$n=0;$check=static function($ok,$message)use(&$n){$n++;if(!$ok)throw new RuntimeException($message);};$deny=static function($call)use($check){try{$call();throw new LogicException('invalid accepted');}catch(DomainException$e){$check($e->getMessage()==='native_task_form_invalid','expected native validation');}};
$input=['start_date'=>'2026-10-25','billable'=>1,'hourly_rate'=>'10.5','is_public'=>0,'visible_to_client'=>0,'workflow_status'=>1,'date_finished'=>null,'relation_type'=>null,'relation_source_kind'=>null,'relation_source_id'=>null];
$normal=NativeCompanyTaskFormValues::normalize($input);$check($normal['hourly_rate']==='10.50'&&$normal['billable']===1,'explicit monetary intent normalized exactly');
foreach([1=>'todo',2=>'awaiting_feedback',3=>'testing',4=>'in_progress',5=>'done']as$id=>$state)$check(NativeCompanyTaskFormValues::lifecycle($id)===$state,'native states are real distinct states');
foreach(['billable','hourly_rate','start_date']as$key){$bad=$input;unset($bad[$key]);$deny(fn()=>NativeCompanyTaskFormValues::normalize($bad));}
foreach(['2026-02-30','2026-10-25x']as$bad)$deny(fn()=>NativeCompanyTaskFormValues::normalize(array_replace($input,['start_date'=>$bad])));
foreach(['-1','1e2','1.234','9999999999999']as$bad)$deny(fn()=>NativeCompanyTaskFormValues::normalize(array_replace($input,['hourly_rate'=>$bad])));
$deny(fn()=>NativeCompanyTaskFormValues::normalize(array_replace($input,['billable'=>null])));
$deny(fn()=>NativeCompanyTaskFormValues::normalize(array_replace($input,['workflow_status'=>5])));
$done=NativeCompanyTaskFormValues::normalize(array_replace($input,['workflow_status'=>5,'date_finished'=>'2026-10-25 12:30:00']));$check($done['date_finished']==='2026-10-25 12:30:00','completion preserves actual supplied native timestamp');
$deny(fn()=>NativeCompanyTaskFormValues::normalize(array_replace($input,['relation_source_kind'=>'company_project','relation_source_id'=>1])));
echo "Native task explicit form values: $n checks passed. Pure validation only.\n";
