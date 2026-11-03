<?php
declare(strict_types=1);
/** Versioned native sales calculation, exact rational minor units; never float or a client total. */
final class CompanyInvoiceNativeAmounts
{
 public const CONTRACT='native_sales_v1';
 private static function clean(string$n):string{return ltrim($n,'0')?:'0';}
 private static function cmp(string$a,string$b):int{return strlen($a)<=>strlen($b)?:strcmp($a,$b);}
 private static function add(string$a,string$b):string{$out='';$carry=0;for($i=strlen($a)-1,$j=strlen($b)-1;$i>=0||$j>=0||$carry;$i--,$j--){$v=($i>=0?(int)$a[$i]:0)+($j>=0?(int)$b[$j]:0)+$carry;$out=($v%10).$out;$carry=intdiv($v,10);}return self::clean($out);}
 private static function sub(string$a,string$b):string{if(self::cmp($a,$b)<0)throw new DomainException('company_invoice_invalid_input');$out='';$borrow=0;for($i=strlen($a)-1,$j=strlen($b)-1;$i>=0;$i--,$j--){$v=(int)$a[$i]-($j>=0?(int)$b[$j]:0)-$borrow;$borrow=$v<0?1:0;if($v<0)$v+=10;$out=$v.$out;}return self::clean($out);}
 private static function mul(string$a,string$b):string{$out='0';$zeros='';for($j=strlen($b)-1;$j>=0;$j--){$part='0';for($k=0;$k<(int)$b[$j];$k++)$part=self::add($part,$a);$out=self::add($out,self::clean($part.$zeros));$zeros.='0';}return$out;}
 private static function plus(array$a,array$b):array{return[self::add(self::mul($a[0],$b[1]),self::mul($b[0],$a[1])),self::mul($a[1],$b[1])];}
 private static function minus(array$a,array$b):array{return[self::sub(self::mul($a[0],$b[1]),self::mul($b[0],$a[1])),self::mul($a[1],$b[1])];}
 private static function times(array$a,string$n,string$d):array{return[self::mul($a[0],$n),self::mul($a[1],$d)];}
 private static function rounded(array$f):int
 {
  [$n,$d]=$f;if($d==='0'||strlen($n)>128||strlen($d)>128)throw new DomainException('company_invoice_invalid_input');$quotient='';$rem='0';
  foreach(str_split($n)as$digit){$rem=self::clean(($rem==='0'?'':$rem).$digit);$q=0;while(self::cmp($rem,$d)>=0){$rem=self::sub($rem,$d);$q++;}$quotient.=$q;}
  $quotient=self::clean($quotient);if(self::cmp(self::mul($rem,'2'),$d)>=0)$quotient=self::add($quotient,'1');if(self::cmp($quotient,(string)CompanyInvoiceAmounts::MAX)>0)throw new DomainException('company_invoice_invalid_input');return(int)$quotient;
 }
 /** Inputs are already normalized by the canonical invoice line validator. */
 public static function calculate(array$prepared,array$lines,int$subtotal,array$options):array
 {
  if(($options['calculation_contract']??null)!==self::CONTRACT)throw new DomainException('company_invoice_calculation_contract_invalid');
  $mode=$options['discount_mode']??'fixed';$type=$options['discount_type']??'after_tax';if(!in_array($mode,['fixed','percent'],true)||!in_array($type,['','before_tax','after_tax'],true))throw new DomainException('company_invoice_invalid_input');
  $value=CompanyInvoiceAmounts::minor($options['discount_value']??'0');if(($mode==='percent'&&$value>10000)||($mode==='fixed'&&$type==='before_tax'&&$value>$subtotal))throw new DomainException('company_invoice_discount_exceeds_subtotal');
  $taxNumerator='0';foreach($prepared as$k=>$item)foreach($item['taxes']as$tax)$taxNumerator=self::add($taxNumerator,self::mul((string)$lines[$k],(string)CompanyInvoiceAmounts::minor($tax['rate'])));
  $tax=[$taxNumerator,'10000'];$discount=$mode==='percent'?self::times([(string)$subtotal,'1'],(string)$value,'10000'):[(string)$value,'1'];
  if($type==='before_tax')$tax=$mode==='percent'?self::times($tax,(string)(10000-$value),'10000'):($subtotal===0?['0','1']:self::times($tax,(string)($subtotal-$value),(string)$subtotal));
  $gross=self::plus([(string)$subtotal,'1'],$tax);if($type==='after_tax'&&$mode==='percent')$discount=self::times($gross,(string)$value,'10000');
  if($type==='')$discount=['0','1'];
  $total=self::minus($gross,$discount);$adjustment=CompanyInvoiceAmounts::minor($options['adjustment']??'0',true);$total=$adjustment<0?self::minus($total,[(string)-$adjustment,'1']):self::plus($total,[(string)$adjustment,'1']);$rounded=self::rounded($total);
  return ['items'=>$prepared,'subtotal'=>CompanyInvoiceAmounts::decimal($subtotal),'tax'=>CompanyInvoiceAmounts::decimal(self::rounded($tax)),'discount_mode'=>$mode,'discount_value'=>CompanyInvoiceAmounts::decimal($value),'discount_type'=>$type,'discount_percent'=>$mode==='percent'?CompanyInvoiceAmounts::decimal($value):'0.00','discount_total'=>CompanyInvoiceAmounts::decimal(self::rounded($discount)),'adjustment'=>CompanyInvoiceAmounts::decimal($adjustment),'total'=>CompanyInvoiceAmounts::decimal($rounded),'calculation_contract'=>self::CONTRACT];
 }
}
