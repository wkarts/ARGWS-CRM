<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/application/services/NativeCompanyInvoiceRules.php';
$checks=0;
function ruleCheck(bool $ok):void{++$GLOBALS['checks'];if(!$ok)throw new RuntimeException('invoice rule normalization mismatch');}
function normalizeRule($id,$value,$operator='equal',$dynamic=false,$type=null){return NativeCompanyInvoiceRules::normalizeBuilder(['match_type'=>'and','rules'=>[['id'=>$id,'value'=>$value,'has_dynamic_value'=>$dynamic,'operator'=>$operator,'type'=>$type??NativeCompanyInvoiceRules::TYPES[$id]]]],'d/m/Y|%d/%m/%Y')['rules'][0];}
foreach(['number','total','subtotal']as$id){ruleCheck(normalizeRule($id,'10.500')['value']==='10.5');ruleCheck(normalizeRule($id,0.000001)['value']==='0.000001');ruleCheck(normalizeRule($id,['1.00',20],'between')['value']===['1','20']);}
foreach(['date','duedate']as$id){ruleCheck(normalizeRule($id,'28/02/2024')['value']==='2024-02-28');ruleCheck(normalizeRule($id,'today','dynamic')['has_dynamic_value']===true);ruleCheck(normalizeRule($id,['first day of this month','last day of this month'],'between',true)['value']===['first day of this month','last day of this month']);}
foreach(['sent','recurring','not_have_payment']as$id)ruleCheck(normalizeRule($id,true)['value']==='1');
ruleCheck(normalizeRule('sale_agent',7)['value']==='7');ruleCheck(normalizeRule('sale_agent','', 'is_empty')['value']===null);
ruleCheck(normalizeRule('status',[1,'2',1],'in')['value']===['1','2']);ruleCheck(normalizeRule('year',['2026'],'not_in')['value']===['2026']);ruleCheck(normalizeRule('made_payment_by',['company_manual',1],'in')['value']===['company_manual','1']);
ruleCheck(NativeCompanyInvoiceRules::dynamicDate('+2 days','2024-02-28')==='2024-03-01');ruleCheck(NativeCompanyInvoiceRules::dynamicDate('last day of this month','2024-02-15')==='2024-02-29');
foreach([['total','1 OR 1=1'],['date','30/02/2024'],['date','not a date','dynamic'],['total','1','dynamic',true],['sent','0','not_equal'],['status',['7'],'in'],['made_payment_by',["x') OR 1=1"],'in'],['sale_agent','01'],['total',[1],'between'],['total',INF],['total','1','equal',false,'DateRule']]as$args){try{normalizeRule(...$args);throw new RuntimeException('unsafe rule accepted');}catch(DomainException$expected){++$checks;}}
foreach([['match_type'=>'OR 1=1','rules'=>[]],['match_type'=>'and','rules'=>[['id'=>[],'value'=>1,'type'=>'NumberRule']]],['match_type'=>'and','rules'=>[],'sql'=>'SELECT']]as$builder){try{NativeCompanyInvoiceRules::normalizeBuilder($builder);throw new RuntimeException('unsafe builder accepted');}catch(DomainException$expected){++$checks;}}
$builder=['rules'=>[normalizeRule('date','today','dynamic')],'match_type'=>'or'];$normal=NativeCompanyInvoiceRules::normalizeBuilder($builder);ruleCheck(NativeCompanyInvoiceRules::normalizeBuilder($normal)===$normal);
$compiled=NativeCompanyInvoiceRules::compile(['match_type'=>'or','rules'=>[normalizeRule('total','12.50','greater'),normalizeRule('year',['2026'],'in')]],'synthetic_','named','2026-10-25');
ruleCheck(!str_contains($compiled['sql'],'12.5')&&!str_contains($compiled['sql'],'2026')&&$compiled['params']===['12.5','2026-01-01','2026-12-31']);
$equalRange=NativeCompanyInvoiceRules::compile(['match_type'=>'and','rules'=>[normalizeRule('total',['1','1'],'not_between')]],'synthetic_','named','2026-10-25');
ruleCheck($equalRange['sql']==='((i.total=?))'&&$equalRange['params']===['1']);
$mode=NativeCompanyInvoiceRules::compile(['match_type'=>'and','rules'=>[normalizeRule('made_payment_by',['company_manual'],'not_in')]],'synthetic_','named','2026-10-25');
ruleCheck(str_contains($mode['sql'],'EXISTS')&&str_contains($mode['sql'],'paymentmode NOT IN (?)')&&$mode['params']===['company_manual']);
echo "Native invoice twelve-rule grammar/dates/types/operators/injection: {$checks} checks PASS.\n";
