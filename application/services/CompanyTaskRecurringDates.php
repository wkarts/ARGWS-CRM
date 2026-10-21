<?php
declare(strict_types=1);

/** Local calendar dates; no server timezone or seconds-based month arithmetic. */
final class CompanyTaskRecurringDates
{
    private static function number($value,int $min,int $max):int
    {
        if(!(is_int($value)||is_string($value))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$value)||(float)$value<$min||(float)$value>$max)throw new DomainException('company_task_recurring_invalid');
        return (int)$value;
    }
    public static function date($value):string
    {
        if(!is_string($value)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$value)||(int)substr($value,0,4)<2000||!checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4)))throw new DomainException('company_task_recurring_invalid');
        return $value;
    }
    public static function configuration(array $input):array
    {
        $fields=['unit','interval','first_date','timezone','cycles','due_days','require_previous_done'];
        if(count($input)!==count($fields)||array_diff(array_keys($input),$fields)||!in_array($input['unit']??null,['day','week','month'],true)||!is_string($input['timezone']??null)||!in_array($input['timezone'],DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true))throw new DomainException('company_task_recurring_invalid');
        $c=['unit'=>$input['unit'],'interval'=>self::number($input['interval'],1,120),'first_date'=>self::date($input['first_date']),'timezone'=>$input['timezone'],'cycles'=>self::number($input['cycles'],1,120),'due_days'=>self::number($input['due_days'],0,3650),'require_previous_done'=>self::number($input['require_previous_done'],0,1)];
        self::due($c,self::occurrence($c,$c['cycles']-1));
        return $c;
    }
    public static function occurrence(array $c,int $cycle):string
    {
        if($cycle<0||$cycle>119)throw new DomainException('company_task_recurring_invalid');
        // Civil Gregorian arithmetic is independent of DST and historically skipped local days.
        $base=new DateTimeImmutable($c['first_date'].' 12:00:00',new DateTimeZone('UTC'));$n=$cycle*$c['interval'];
        if($c['unit']==='month'){
            $month=((int)$base->format('Y'))*12+(int)$base->format('n')-1+$n;$year=intdiv($month,12);$m=$month%12+1;
            if($year>9999)throw new DomainException('company_task_recurring_invalid');
            $first=$base->setDate($year,$m,1);$day=min((int)$base->format('j'),(int)$first->format('t'));$date=$first->setDate($year,$m,$day)->format('Y-m-d');
        }else $date=$base->modify('+'.($n*($c['unit']==='week'?7:1)).' days')->format('Y-m-d');
        return self::date($date);
    }
    public static function due(array $c,string $date):string
    {
        return self::date((new DateTimeImmutable($date.' 12:00:00',new DateTimeZone('UTC')))->modify('+'.$c['due_days'].' days')->format('Y-m-d'));
    }
    public static function now(?string $utc=null):string
    {
        $utc=$utc??gmdate('Y-m-d H:i:s');$d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$utc,new DateTimeZone('UTC'));
        if(!$d||$d->format('Y-m-d H:i:s')!==$utc)throw new DomainException('company_task_recurring_invalid');return $utc;
    }
    public static function localDate(array $c,string $utc):string
    {
        return (new DateTimeImmutable(self::now($utc),new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($c['timezone']))->format('Y-m-d');
    }
}
