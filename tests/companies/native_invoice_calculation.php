<?php
require_once dirname(__DIR__,2).'/application/services/CompanyInvoiceAmounts.php';
// Execute the original native sales calculator as differential oracle; only its formatting dependencies are seams.
function get_decimal_places(){return 2;}function get_base_currency(){return 'BRL';}function app_format_money($n,$currency){return(string)$n;}
$source=file_get_contents(dirname(__DIR__,2).'/application/helpers/sales_helper.php');if(!preg_match('/^function calculate_sales_total\([^\n]+\).*?^\}/ms',$source,$match))throw new RuntimeException('native calculator source missing');eval($match[0]);
$n=0;function calculationCheck($ok,$label){global$n;if(!$ok)throw new RuntimeException($label);$n++;}
$item=fn($rate,$tax='10')=>['description'=>'Synthetic','qty'=>'1','rate'=>$rate,'taxes'=>[['name'=>'Tax','rate'=>$tax]]];
$cases=[[$item('100'),'percent','10','','0'],[$item('100'),'fixed','105','after_tax','0'],[$item('100'),'percent','10','after_tax','0'],[$item('100'),'percent','10','before_tax','0'],[$item('0.05'),'percent','50','before_tax','0'],[$item('10.01','7.25'),'fixed','0.01','before_tax','-0.01'],[$item('10.01','7.25'),'percent','12.25','after_tax','0.04']];
foreach($cases as[$line,$mode,$value,$type,$adjustment]){
 $options=['discount_mode'=>$mode,'discount_value'=>$value,'discount_type'=>$type,'adjustment'=>$adjustment,'calculation_contract'=>'native_sales_v1'];$actual=CompanyInvoiceAmounts::calculate([$line],$options);
 $native=$line;$native['taxes']=array_map(fn($t)=>['taxname'=>$t['name'].'|'.$t['rate'],'taxrate'=>$t['rate']],$line['taxes']);$oracle=calculate_sales_total([$native],['discount_total_type'=>$mode,'discount_percent'=>$mode==='percent'?$value:'0','discount_total'=>$mode==='fixed'?$value:'0','discount_type'=>$type,'adjustment'=>$adjustment]);
 foreach(['subtotal'=>'subtotal','tax'=>'total_tax','discount_total'=>'discount_calculated','total'=>'total']as$a=>$b)calculationCheck($actual[$a]===number_format($oracle[$b],2,'.',''),'original native differential '.$a.' '.json_encode($options));
}
$legacyOptions=['discount_mode'=>'percent','discount_value'=>'10','discount_type'=>'after_tax'];$legacy=CompanyInvoiceAmounts::calculate([$item('100')],$legacyOptions);$native=CompanyInvoiceAmounts::calculate([$item('100')],$legacyOptions+['calculation_contract'=>'native_sales_v1']);
calculationCheck($legacy['total']==='100.00'&&!isset($legacy['calculation_contract']),'omission retains exact historical algorithm and shape');calculationCheck($native['total']==='99.00'&&$native['discount_total']==='11.00','native after-tax percentage includes tax in its basis');
$oldHash=hash('sha256',json_encode($legacy,JSON_THROW_ON_ERROR));calculationCheck(hash('sha256',json_encode(CompanyInvoiceAmounts::calculate([$item('100')],$legacyOptions),JSON_THROW_ON_ERROR))===$oldHash,'new version does not mutate historical canonical bytes');
$large=CompanyInvoiceAmounts::calculate([$item('9000000000000.01','10')],$legacyOptions+['calculation_contract'=>'native_sales_v1']);calculationCheck($large['total']==='8910000000000.01','exact rational computation remains correct beyond safe float cents');
try{CompanyInvoiceAmounts::calculate([$item('100')],['calculation_contract'=>'future']);throw new RuntimeException('unknown contract accepted');}catch(DomainException$e){calculationCheck($e->getMessage()==='company_invoice_calculation_contract_invalid','unknown explicit version is denied');}
foreach([['discount_mode'=>'fixed','discount_value'=>'111','discount_type'=>'after_tax'],['discount_mode'=>'fixed','discount_value'=>'101','discount_type'=>'before_tax']]as$bad){try{CompanyInvoiceAmounts::calculate([$item('100')],$bad+['calculation_contract'=>'native_sales_v1']);throw new RuntimeException('negative economic amount accepted');}catch(DomainException$e){calculationCheck(true,'native negative economic amount is rejected');}}
echo "Native calculation differential: $n checks PASS; original PHP oracle, browser JS still pending.\n";
