<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceAmounts.php';
require_once __DIR__.'/issuers/IssuerPolicy.php';
/** Native task_timer_round, then sec2qty(2dp), without binary floats or ambient hooks. */
final class CompanyTaskBillingAmounts
{
    public static function profile($rate,$mode,$minutes):array
    {
        if(!is_string($rate)||!is_string($mode)||!in_array($mode,['none','up','down','nearest'],true)||!(is_int($minutes)||is_string($minutes))||!preg_match('/^(0|5|10|15|20|25|30|35|40|45)$/D',(string)$minutes))throw new DomainException('company_task_billing_invalid');
        $n=(int)$minutes;if(($mode==='none')!==($n===0))throw new DomainException('company_task_billing_invalid');$minor=CompanyInvoiceAmounts::minor($rate);if($minor<1)throw new DomainException('company_task_billing_invalid');
        return ['basis'=>'task_hourly_native_2dp_v1','rate'=>CompanyInvoiceAmounts::decimal($minor),'round_mode'=>$mode,'round_minutes'=>$n];
    }
    private static function timestamp(string $v):bool{ $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v);return $d!==false&&$d->format('Y-m-d H:i:s')===$v;}
    public static function calculate(array $timers,array $profile,bool $allowZeroTotal=false):array
    {
        $profile=self::profile($profile['rate']??null,$profile['round_mode']??null,$profile['round_minutes']??null);if(!$timers||count($timers)>500)throw new DomainException('company_task_billing_timer');$seconds=0;
        foreach($timers as $t){if(!is_array($t)||!isset($t['stopped_at'])||!is_string($t['started_at']??null)||!is_string($t['stopped_at'])||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$t['started_at'])||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$t['stopped_at'])||!self::timestamp($t['started_at'])||!self::timestamp($t['stopped_at'])||$t['stopped_at']<$t['started_at']||!preg_match('/^(0|[1-9][0-9]{0,9})$/D',(string)($t['seconds']??''))||(int)$t['seconds']>4294967295)throw new DomainException('company_task_billing_timer');$seconds+=(int)$t['seconds'];}
        $rounded=$seconds;$interval=$profile['round_minutes']*60;if($interval){$units=intdiv($seconds,$interval);$remainder=$seconds%$interval;if($profile['round_mode']==='up'&&$remainder>0||$profile['round_mode']==='nearest'&&$remainder*2>=$interval)$units++;$rounded=$units*$interval;}
        // max500 uint32 timers safely fit signed64 multiplication by100.
        $qty=intdiv($rounded*100+1800,3600);if($qty<1&&$allowZeroTotal)throw new DomainException('company_task_billing_zero_quantity');if($qty<1||$qty>99999900)throw new DomainException('company_task_billing_quantity');
        $hours=CompanyInvoiceAmounts::decimal($qty);$total=CompanyInvoiceAmounts::calculate([['description'=>'Task','qty'=>$hours,'rate'=>$profile['rate']]]);if(!$allowZeroTotal&&CompanyInvoiceAmounts::minor($total['total'])<1)throw new DomainException('company_task_billing_quantity');
        return $profile+['seconds'=>$seconds,'rounded_seconds'=>$rounded,'hours'=>$hours,'subtotal'=>$total['subtotal'],'timer_timestamp_basis'=>'native_server_local_naive_duration_seconds_authoritative'];
    }
    /** Native timesheets_individualy: each persisted timer is rounded and priced independently. */
    public static function timesheets(array $timers,array $profile):array
    {
        if(!$timers||count($timers)>500)throw new DomainException('company_task_billing_timer');$seconds=0;$rounded=0;$qty=0;$lines=[];$excluded=[];$seen=[];
        foreach($timers as $t){$id=IssuerPolicy::id($t['id']??null);if(isset($seen[$id]))throw new DomainException('company_task_billing_timer');$seen[$id]=true;try{$math=self::calculate([$t],$profile,true);}catch(DomainException $e){if($e->getMessage()!=='company_task_billing_zero_quantity')throw $e;$excluded[]=$id;$seconds+=(int)$t['seconds'];continue;}
            $seconds+=$math['seconds'];$rounded+=$math['rounded_seconds'];$qty+=CompanyInvoiceAmounts::minor($math['hours']);$lines[]=['timer_id'=>$id,'seconds'=>$math['seconds'],'rounded_seconds'=>$math['rounded_seconds'],'hours'=>$math['hours'],'started_at'=>$t['started_at'],'stopped_at'=>$t['stopped_at']];}
        if(!$lines||count($lines)>100)throw new DomainException('company_task_billing_quantity');
        return array_replace(self::profile($profile['rate']??null,$profile['round_mode']??null,$profile['round_minutes']??null),['basis'=>'task_timesheets_native_2dp_v1'])+['layout'=>'timesheets_individually','seconds'=>$seconds,'rounded_seconds'=>$rounded,'hours'=>CompanyInvoiceAmounts::decimal($qty),'lines'=>$lines,'excluded_zero_quantity_timer_ids'=>$excluded,'quantity_basis'=>'sum_of_independently_rounded_timer_hours_2dp','timer_timestamp_basis'=>'native_server_local_naive_duration_seconds_authoritative'];
    }

}
