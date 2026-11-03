<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskTimerRounding.php';
require_once __DIR__.'/utilities/Date.php';
require_once __DIR__.'/utilities/Format.php';
/** Native timesheet chart wire from already-authorized rows, not a resource reader. */
final class NativeCompanyStaffTimeChart
{
    public static function build(array $rows,string $range,string $from,string $to,string $timezone,array $rounding,callable $translate):array
    {
        if(!in_array($range,['today','this_week','last_week','this_month','last_month','period'],true))throw new DomainException('native_staff_timesheet_filter_invalid');
        $zone=new DateTimeZone($timezone);$dates=[];foreach([$from,$to]as$value){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,$zone);if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('native_staff_timesheet_filter_invalid');$dates[]=$date;}
        if($from>$to)throw new DomainException('native_staff_timesheet_filter_invalid');
        $chartType=match($range){'today'=>'today','this_week','last_week'=>'week','this_month','last_month'=>'month',default=>'weeks_split'};
        $groups=[];$labels=[];
        if($chartType==='today'){$groups[]=[$from,$to];$labels[]=$translate('today');}
        elseif($chartType==='week'||$chartType==='month'){
            for($date=$dates[0];$date<=$dates[1];$date=$date->modify('+1 day')){$day=$date->format('Y-m-d');$groups[]=[$day,$day];$labels[]=$chartType==='month'?$day:$date->format('d').' - '.$translate($date->format('l'));}
        }else{
            // Same week boundaries as utilities Date, without allocating every day of a long period.
            for($date=$dates[0];$date<=$dates[1];$date=$end->modify('+1 day')){$end=$date->modify('sunday this week');if($end>$dates[1])$end=$dates[1];$start=$date->format('Y-m-d');$last=$end->format('Y-m-d');$groups[]=[$start,$last];$labels[]=self::weekLabel($date,$end,$translate);}
        }
        $seconds=array_fill(0,count($groups),0);$total=0;
        foreach($rows as$row){if(!is_array($row)||!is_int($row['start_time']??null)||!is_int($row['total']??null)||$row['total']<0)throw new DomainException('native_staff_timesheet_invalid');$day=(new DateTimeImmutable('@'.$row['start_time']))->setTimezone($zone)->format('Y-m-d');if($day<$from||$day>$to)throw new DomainException('native_staff_timesheet_filter_invalid');$amount=NativeCompanyTaskTimerRounding::roundSeconds($row['total'],$rounding);if($total>PHP_INT_MAX-$amount)throw new DomainException('native_staff_timesheet_invalid');$total+=$amount;
            // Ordered intervals are disjoint; binary search avoids a row×weeks scan.
            $low=0;$high=count($groups)-1;$found=false;while($low<=$high){$mid=intdiv($low+$high,2);if($day<$groups[$mid][0])$high=$mid-1;elseif($day>$groups[$mid][1])$low=$mid+1;else{if($seconds[$mid]>PHP_INT_MAX-$amount)throw new DomainException('native_staff_timesheet_invalid');$seconds[$mid]+=$amount;$found=true;break;}}if(!$found)throw new DomainException('native_staff_timesheet_filter_invalid');
        }
        return ['chart_type'=>$chartType,'chart'=>['labels'=>$labels,'data'=>array_map([\app\services\utilities\Format::class,'sec2qty'],$seconds)],'logged_time'=>['total_logged_time_h'=>\app\services\utilities\Format::secondsToTime($total),'total_logged_time_d'=>\app\services\utilities\Format::sec2qty($total)]];
    }
    private static function weekLabel(DateTimeImmutable $start,DateTimeImmutable $end,callable $translate):string
    {
        // Original Date::splitWeeksChartLabel, with explicit timezone and translation rather than ambient date().
        $label=$start->format('d');if($start->format('Y')!==$end->format('Y'))$label.=' '.$translate($start->format('F')).' '.$start->format('Y');elseif($start->format('m')!==$end->format('m'))$label.=' '.$translate($start->format('F'));
        return $label.' - '.$end->format('d').' '.$translate($end->format('F')).' '.$end->format('Y');
    }
}
