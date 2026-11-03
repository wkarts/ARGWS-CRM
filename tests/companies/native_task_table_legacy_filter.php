<?php
/** First-party dashboard's legacy My Tasks selector, with native closed grammar. */
declare(strict_types=1);
if(!defined('BASEPATH'))define('BASEPATH',__DIR__);
if(!defined('APPPATH'))define('APPPATH',dirname(__DIR__,2).'/application/');
require_once __DIR__.'/../../application/services/NativeCompanyRelatedTaskPage.php';
require_once __DIR__.'/../../application/services/NativeCompanyRelatedTaskRules.php';
$columns=[];
for($i=0;$i<9;$i++)$columns[]=['data'=>(string)$i,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];
$input=['draw'=>'1','start'=>'0','length'=>'25','columns'=>$columns,'order'=>[['column'=>'5','dir'=>'asc']],'search'=>['value'=>'','regex'=>'false'],'last_order_identifier'=>'tasks'];
$check=static function(bool $condition,string $description):void{if(!$condition)throw new RuntimeException('Test failed: '.$description);};
$deny=static function(array $wire,string $description)use($check):void{
    try{NativeCompanyRelatedTaskPage::request($wire,[]);throw new RuntimeException('Rejected request was accepted: '.$description);}
    catch(DomainException $error){$check($error->getMessage()==='native_task_table_request_invalid',$description);}
};
$native=NativeCompanyRelatedTaskPage::request($input,[]);
$check($native['filters']===['match_type'=>'and','rules'=>[]],'ordinary native table remains unchanged');
$legacy=NativeCompanyRelatedTaskPage::request($input+['my_tasks'=>'true'],[]);
$rule=['id'=>'my_tasks','type'=>'BooleanRule','operator'=>'equal','value'=>'1','has_dynamic_value'=>false];
$check($legacy['filters']===['match_type'=>'and','rules'=>[$rule]],'exact original dashboard hidden flag maps to typed rule');
$check(NativeCompanyRelatedTaskRules::matches($legacy['filters'],['my_tasks'=>true],'2026-10-29'),'own assigned task matches');
$check(!NativeCompanyRelatedTaskRules::matches($legacy['filters'],['my_tasks'=>false],'2026-10-29'),'other staff tasks never match');
$combined=NativeCompanyRelatedTaskPage::request($input+['my_tasks'=>'1','filters'=>['match_type'=>'and','rules'=>[$rule]]],[]);
$check(count($combined['filters']['rules'])===2,'existing AND filters are preserved, no omission of My Tasks');
foreach([false,'false','0','anything',[],['id'=>3]] as $wrong)$deny($input+['my_tasks'=>$wrong],'unapproved legacy flag rejected');
$deny($input+['my_tasks'=>'true','another_filter'=>1],'unrecognized legacy filters remain rejected');
$deny($input+['my_tasks'=>'true','filters'=>['match_type'=>'or','rules'=>[]]],'OR cannot weaken My Tasks scope');
$deny($input+['my_tasks'=>'true','filters'=>['match_type'=>'and','rules'=>array_fill(0,100,$rule)]],'bounded rule count remains enforced');
echo "Native task legacy dashboard filter: strict POST grammar and actor-only typed predicate passed.\n";
