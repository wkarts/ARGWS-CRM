<?php
/** Golden monetary examples and invariants; no provider, database or generated expectations. */
require_once dirname(__DIR__,2).'/application/services/CompanyInvoiceAmounts.php';
$checks=0;
function amountCheck(bool $ok,string $reason):void{global $checks;if(!$ok)throw new RuntimeException($reason);++$checks;}
function amountDenied(callable $call):void{try{$call();}catch(DomainException $e){amountCheck(true,'rejected');return;}throw new RuntimeException('invalid amount accepted');}
function line(string $rate,array $taxes=[]):array{return ['description'=>'Synthetic item','qty'=>'1.00','rate'=>$rate,'taxes'=>$taxes];}
$items=[line('100.00',[['name'=>'ISS','rate'=>'5.00'],['name'=>'Synthetic levy','rate'=>'2.50']])];
$before=CompanyInvoiceAmounts::calculate($items,['discount_mode'=>'percent','discount_value'=>'10','discount_type'=>'before_tax','adjustment'=>'-0.01']);
amountCheck($before['subtotal']==='100.00'&&$before['tax']==='6.75'&&$before['discount_total']==='10.00'&&$before['total']==='96.74','before-tax percent reduces each tax base exactly');
$after=CompanyInvoiceAmounts::calculate($items,['discount_mode'=>'fixed','discount_value'=>'10','adjustment'=>'0.01']);
amountCheck($after['tax']==='7.50'&&$after['total']==='97.51','after-tax fixed discount preserves original tax bases');
$oneCent=CompanyInvoiceAmounts::calculate([line('0.01',[['name'=>'Tax A','rate'=>'50']]),line('0.01',[['name'=>'Tax B','rate'=>'100']])],['discount_value'=>'0.01','discount_type'=>'before_tax']);
amountCheck($oneCent['tax']==='0.01'&&$oneCent['total']==='0.02','largest-remainder tie awards cent to first line, never duplicates discount');
$max=CompanyInvoiceAmounts::calculate([line('9999999999999.99')],['discount_mode'=>'percent','discount_value'=>'99.99']);
amountCheck($max['discount_total']==='9998999999999.99'&&$max['total']==='1000000000.00','large percentage product cannot overflow or lose cent precision');
$prorata=CompanyInvoiceAmounts::calculate([line('9999999999999.97',[['name'=>'Tax','rate'=>'100']]),line('0.02')],['discount_value'=>'9999999999999.98','discount_type'=>'before_tax']);
amountCheck($prorata['total']==='0.02'&&$prorata['tax']==='0.01','large fixed-discount apportionment remains exact');
$full=CompanyInvoiceAmounts::calculate($items,['discount_mode'=>'percent','discount_value'=>'100','discount_type'=>'before_tax','adjustment'=>'1']);
amountCheck($full['total']==='1.00'&&$full['tax']==='0.00','100 percent before-tax discount removes taxable base');
foreach(['-1','1.234','1e2','NaN','+1',' 1','01','99999999999999',1.25,[],null] as $bad)amountDenied(fn()=>CompanyInvoiceAmounts::calculate($items,['discount_value'=>$bad]));
amountDenied(fn()=>CompanyInvoiceAmounts::calculate($items,['discount_value'=>'100.01']));
amountDenied(fn()=>CompanyInvoiceAmounts::calculate($items,['discount_mode'=>'percent','discount_value'=>'100.01']));
amountDenied(fn()=>CompanyInvoiceAmounts::calculate($items,['adjustment'=>'-107.50']));
amountDenied(fn()=>CompanyInvoiceAmounts::calculate($items,['discount_type'=>'unknown']));
amountDenied(fn()=>CompanyInvoiceAmounts::calculate([line('9999999999999.99',[['name'=>'Tax','rate'=>'100']])]));
amountDenied(fn()=>CompanyInvoiceAmounts::calculate([line('1',[['name'=>'ISS','rate'=>'1'],['name'=>' iss ','rate'=>'2']])]));
amountDenied(fn()=>CompanyInvoiceAmounts::calculate([line('1',[['name'=>'','rate'=>'1']])]));
amountDenied(fn()=>CompanyInvoiceAmounts::calculate([array_merge(line('1'),['taxname'=>'X','taxrate'=>'1'])]));
amountDenied(fn()=>CompanyInvoiceAmounts::calculate([line('1',array_fill(0,11,['name'=>'Tax','rate'=>'1']))]));
$legacy=CompanyInvoiceAmounts::calculate([['description'=>'Legacy','qty'=>'2.00','rate'=>'50.25','taxname'=>'Tax','taxrate'=>'5.00']]);
amountCheck($legacy['total']==='105.53','old single-tax payload keeps its exact original amount');
echo "Invoice monetary rules: {$checks} checks passed.\n";
