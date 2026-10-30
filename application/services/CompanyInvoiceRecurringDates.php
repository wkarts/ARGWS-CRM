<?php

declare(strict_types=1);

/** Anchored calendar arithmetic shared by creator, validator and bounded worker. */
final class CompanyInvoiceRecurringDates
{
    public static function date($value):string
    {
        if(!is_string($value)||!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D',$value)||!($d=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC')))||$d->format('Y-m-d')!==$value)throw new DomainException('company_invoice_recurring_invalid');return$value;
    }
    public static function configuration(array $data):array
    {
        if(array_diff(array_keys($data),['unit','interval','custom','cycles','send','contact_id','base_url']))throw new DomainException('company_invoice_recurring_invalid');
        $int=static function($v,int$min,int$max):int{if(!(is_int($v)||is_string($v)&&preg_match('/^(0|[1-9][0-9]*)$/D',$v))||(int)$v<$min||(int)$v>$max)throw new DomainException('company_invoice_recurring_invalid');return(int)$v;};
        $flag=static function($v):bool{if(!in_array($v,[true,false,0,1,'0','1'],true))throw new DomainException('company_invoice_recurring_invalid');return in_array($v,[true,1,'1'],true);};
        $unit=$data['unit']??'';$interval=$int($data['interval']??null,1,1000);$custom=$flag($data['custom']??false);$cycles=$int($data['cycles']??0,0,10000);$send=$flag($data['send']??false);$contact=$int($data['contact_id']??0,0,2147483647);$base=$data['base_url']??'';
        if(!in_array($unit,['day','week','month','year'],true)||!$custom&&($unit!=='month'||$interval>12)||!is_string($base)||strlen($base)>2000||!$send&&($contact!==0||$base!==''))throw new DomainException('company_invoice_recurring_invalid');
        if($send){$url=parse_url($base);if($contact<1||!$url||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||!isset($url['host'])||(($url['scheme']??'')!=='https'&&!(($url['scheme']??'')==='http'&&in_array($url['host'],['127.0.0.1','localhost','[::1]'],true))))throw new DomainException('company_invoice_recurring_invalid');}
        return['unit'=>$unit,'interval'=>$interval,'custom'=>$custom,'cycles'=>$cycles,'send'=>$send,'contact_id'=>$contact,'base_url'=>$base];
    }
    /** Step0 is the explicit first planned invoice. Unlimited never means an unbounded invocation. */
    public static function occurrence(string $anchor,array$config,int$step):?string
    {
        self::date($anchor);if($step<0||$step>4000000)throw new DomainException('company_invoice_recurring_invalid');$n=$config['interval']*$step;$d=new DateTimeImmutable($anchor,new DateTimeZone('UTC'));$unit=$config['unit'];if($unit==='year'&&(int)$d->format('Y')+$n>9999||$unit==='month'&&((int)$d->format('Y')*12+(int)$d->format('n')-1+$n)>9999*12+11)return null;
        if(in_array($unit,['day','week'],true))$next=$d->modify('+'.$n.' '.$unit);elseif($unit==='month')$next=$d->modify('first day of this month')->modify('+'.$n.' months');elseif($unit==='year')$next=new DateTimeImmutable(((int)$d->format('Y')+$n).'-'.$d->format('m').'-01',new DateTimeZone('UTC'));else throw new DomainException('company_invoice_recurring_invalid');
        if((int)$next->format('Y')>9999)return null;$value=$next->format('Y-m-d');if(in_array($unit,['month','year'],true))$value=$next->format('Y-m-').str_pad((string)min((int)$d->format('d'),(int)$next->format('t')),2,'0',STR_PAD_LEFT);return self::date($value);
    }
}
