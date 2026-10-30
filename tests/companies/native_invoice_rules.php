<?php
require __DIR__.'/invoice_recurring_modes.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
require_once APPPATH.'services/NativeCompanyInvoiceRead.php';
$reader=new NativeCompanyInvoiceRead($db,$p);
$rule=static fn($id,$value,$op='equal')=>['match_type'=>'and','rules'=>[['id'=>$id,'type'=>NativeCompanyInvoiceRules::TYPES[$id],'operator'=>$op,'value'=>$value,'has_dynamic_value'=>false]]];
$all=$reader->page($a,7,['length'=>100]);
$paid=$reader->page($a,7,['length'=>100,'filters'=>$rule('status',['2'],'in')]);
foreach($paid['documents']as$document)essentialCheck($document['status']===2,'status IN rule executes in actual owned reader');
essentialCheck($paid['recordsFiltered']<(int)$all['recordsTotal'],'advanced filter changes canonical result count rather than only rendering UI');
$unpaid=$reader->page($a,7,['length'=>100,'filters'=>$rule('not_have_payment','1')]);
foreach($unpaid['documents']as$document){
 if(($document['lineage']??'')==='private_draft')essentialCheck($document['draft']['state']==='open'&&$document['draft']['invoice_id']===null&&!isset($document['position']),'private draft has no fabricated receipt/ledger position');
 else essentialCheck($document['position']['received']==='0.00'&&$document['position']['applied_credit']==='0.00'&&$document['status']!==5,'no-payment rule keeps native cancelled exclusion and canonical balance');
}
$manual=$reader->page($a,7,['length'=>100,'filters'=>$rule('made_payment_by',['company_manual'],'in')]);
essentialCheck(in_array($main,array_column($manual['documents'],'invoice_id'),true),'manual mode rule finds verified principal receipt using bound value');
$scheduled=$reader->page($a,7,['length'=>100,'filters'=>$rule('recurring','1')]);
essentialCheck(in_array($main,array_column($scheduled['documents'],'invoice_id'),true),'recurring filter finds actual schedule principal despite legacy recurring column zero');
essentialCheck(!in_array($child,array_column($scheduled['documents'],'invoice_id'),true),'issued recurring child is not mistaken for principal template');
$notScheduled=$reader->page($a,7,['length'=>100,'filters'=>$rule('recurring','0')]);
essentialCheck(!in_array($main,array_column($notScheduled['documents'],'invoice_id'),true),'inverse recurrence rule excludes actual principal rather than reading default legacy field');
$foreign=$reader->page($b,7,['length'=>100,'filters'=>$rule('made_payment_by',['company_manual'],'in')]);
essentialCheck(!in_array($main,array_column($foreign['documents'],'invoice_id'),true),'payment-mode subquery cannot widen main issuer predicate');
$none=$reader->page($a,7,['filters'=>$rule('total','999999.00','greater')]);essentialCheck($none['recordsFiltered']===0,'amount comparison is applied as bound SQL condition');
$or=['match_type'=>'or','rules'=>array_merge($rule('status',['2'],'in')['rules'],$rule('total','999999.00','greater')['rules'])];
essentialCheck($reader->page($a,7,['length'=>100,'filters'=>$or])['recordsFiltered']===$paid['recordsFiltered'],'OR grouping remains inside issuer scope');
essentialThrows(fn()=>$reader->page($a,7,['filters'=>$rule('total','0 OR 1=1')]),'native_company_invoice_filter_invalid','SQL text in numeric rule never reaches database');
essentialThrows(fn()=>$reader->page($a,7,['filters'=>$rule('sale_agent','999999')]),'native_company_invoice_filter_reference_denied','sale-agent rule cannot query foreign/inactive identities');
$nativeRow=$db->query('SELECT number,total,subtotal,date,duedate,sent,sale_agent FROM '.$p.'invoices WHERE id=?',[$main])->row_array();
foreach(['number'=>'number','total'=>'total','subtotal'=>'subtotal','date'=>'date','duedate'=>'duedate']as$field=>$column){
 $matched=$reader->page($a,7,['length'=>100,'filters'=>$rule($field,(string)$nativeRow[$column])]);
 essentialCheck(in_array($main,array_column($matched['documents'],'invoice_id'),true),'actual fixed column filter executes: '.$field);
}
$matched=$reader->page($a,7,['length'=>100,'filters'=>$rule('year',[substr($nativeRow['date'],0,4)],'in')]);
essentialCheck(in_array($main,array_column($matched['documents'],'invoice_id'),true),'year filter uses bound date ranges portable across DBs');
$dynamic=$rule('date',$nativeRow['date'],'dynamic');$dynamic['rules'][0]['has_dynamic_value']=true;
essentialCheck(in_array($main,array_column($reader->page($a,7,['length'=>100,'filters'=>$dynamic])['documents'],'invoice_id'),true),'dynamic date compiles to a bound resolved date, not SQL');
$db->query('UPDATE '.$p.'invoices SET sent=1,sale_agent=7 WHERE id=? AND issuer_id=?',[$main,$a]);
try{
 foreach(['sent'=>'1','sale_agent'=>'7']as$field=>$value)essentialCheck(in_array($main,array_column($reader->page($a,7,['length'=>100,'filters'=>$rule($field,$value)])['documents'],'invoice_id'),true),'actual authorized native field predicate: '.$field);
}finally{$db->query('UPDATE '.$p.'invoices SET sent=?,sale_agent=? WHERE id=? AND issuer_id=?',[$nativeRow['sent'],$nativeRow['sale_agent'],$main,$a]);}
$before=$db->query('SELECT * FROM '.$p.'company_invoice_schedule_modes WHERE issuer_id=? ORDER BY schedule_id',[$a])->result_array();
$db->query('UPDATE '.$p.'company_invoice_schedule_modes SET mode_hash=? WHERE issuer_id=? AND schedule_id=?',[str_repeat('0',64),$a,$sid]);
try{essentialThrows(fn()=>$reader->page($a,7,['filters'=>$rule('recurring','1')]),'issuer_invoice_recurring_modes_corrupt','tampered mode fails existing provenance reader rather than claiming recurring metadata');}
finally{foreach($before as$row)$db->query('UPDATE '.$p.'company_invoice_schedule_modes SET mode_hash=? WHERE issuer_id=? AND schedule_id=?',[$row['mode_hash'],$a,$row['schedule_id']]);}
echo 'Native invoice advanced rule execution/recurrence proof/issuer scope: '.$GLOBALS['essentialChecks']." cumulative checks PASS.\n";
