<?php
define("BASEPATH", __DIR__);
define("APPPATH", dirname(__DIR__)."/application/");
require_once __DIR__.'/../application/services/NativeCompanyInvoiceDataTable.php';
$checks=0;
function dtCheck($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);++$checks;}
function dtReject($input,$message){try{NativeCompanyInvoiceDataTable::request($input);throw new RuntimeException('accepted '.$message);}catch(DomainException $e){dtCheck(true,$message);}}
$columns=[];for($i=0;$i<10;++$i)$columns[]=['data'=>(string)$i,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];
$input=['draw'=>'17','start'=>'25','length'=>'25','search'=>['value'=>'50%_','regex'=>'false'],'columns'=>$columns,'order'=>[['column'=>'0','dir'=>'desc']],'filters'=>['match_type'=>'and','rules'=>[]],'last_order_identifier'=>'invoices'];
$parsed=NativeCompanyInvoiceDataTable::request($input);
dtCheck($parsed['query']['draw']===17&&$parsed['query']['search']==='50%_'&&$parsed['query']['start']===25,'actual DataTables wire envelope preserves values');
dtCheck($parsed['scope']===[],'unrelated list has no fabricated scope');
$scoped=NativeCompanyInvoiceDataTable::request($input,7);dtCheck($scoped['scope']===['customer_id'=>7],'route client is explicit scope candidate, not SQL');
foreach([0=>'number',1=>'total',2=>'total_tax',3=>'year',4=>'date',5=>'customer',6=>'project',7=>'tags',8=>'due_date',9=>'status']as$index=>$field){$case=$input;$case['order'][0]['column']=$index;dtCheck(NativeCompanyInvoiceDataTable::request($case)['query']['order']===$field,'fixed ordinal '.$index);}
$multi=$input;$multi['order'][]=['column'=>4,'dir'=>'asc'];
dtCheck(NativeCompanyInvoiceDataTable::request($multi)['query']['orders']===[['field'=>'number','direction'=>'desc'],['field'=>'date','direction'=>'asc']],'multi-column native order preserves every declared priority');
$custom=$input;$custom['columns'][]=['data'=>'10','name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];$custom['order']=[['column'=>10,'dir'=>'asc','type'=>'number']];
$definition=['id'=>123,'type'=>'number','show_on_table'=>1];
dtCheck(NativeCompanyInvoiceDataTable::request($custom,'',[$definition])['query']['orders']===[['field'=>'cf_123','direction'=>'asc']],'custom ordinal derives only from supplied authorized catalogue');
$all=$input;$all['length']='-1';dtCheck(NativeCompanyInvoiceDataTable::request($all)['query']['length']===-1,'native all choice remains explicit and is not truncated into one page');
$cases=[];
$case=$input;$case['issuer_id']=99;$cases[]=$case;
$case=$input;$case['search']['regex']='true';$cases[]=$case;
$case=$input;$case['columns'][0]['name']='i.issuer_id';$cases[]=$case;
$case=$input;$case['columns'][0]['search']['value']='secret';$cases[]=$case;
$case=$input;$case['columns'][0]['data']='total';$cases[]=$case;
$case=$input;$case['order'][0]['column']='0 OR 1=1';$cases[]=$case;
$case=$input;$case['order'][0]['dir']='desc;delete';$cases[]=$case;
$case=$input;$case['order'][]=['column'=>0,'dir'=>'asc'];$cases[]=$case;
$case=$input;$case['length']=-2;$cases[]=$case;
$case=$input;$case['last_order_identifier']='foreign';$cases[]=$case;
foreach($cases as$i=>$case)dtReject($case,'invalid envelope '.$i);
$output=NativeCompanyInvoiceDataTable::response(['draw'=>17,'recordsTotal'=>2,'recordsFiltered'=>1,'documents'=>[[]]],[['safe']]);
dtCheck($output===['draw'=>17,'iTotalRecords'=>2,'iTotalDisplayRecords'=>1,'aaData'=>[['safe']]],'native response names preserve DataTables protocol');
echo "Native invoice DataTables HTTP shape: {$checks} checks PASS.\n";
