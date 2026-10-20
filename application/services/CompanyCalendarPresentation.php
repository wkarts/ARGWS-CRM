<?php

declare(strict_types=1);
/** All sources expose dates, not time-of-day; agenda deliberately labels them all-day. */
final class CompanyCalendarPresentation
{
    public static function views():array{return ['dayGridMonth'=>'Mês','dayGridWeek'=>'Semana','dayGridDay'=>'Dia','timeGridWeek'=>'Agenda da semana','timeGridDay'=>'Agenda do dia'];}
    public static function range(string $anchor,string $view,int $firstDay):array
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$anchor);if(!$date||$date->format('Y-m-d')!==$anchor||!isset(self::views()[$view])||$firstDay<0||$firstDay>6)throw new DomainException('company_calendar_invalid');$start=$date;$end=$date;
        if($view==='dayGridMonth'){$start=$date->modify('first day of this month');$end=$date->modify('last day of this month');$start=$start->modify('-'.(((int)$start->format('w')-$firstDay+7)%7).' days');$end=$end->modify('+'.(($firstDay+6-(int)$end->format('w')+7)%7).' days');}
        elseif(in_array($view,['dayGridWeek','timeGridWeek'],true)){$start=$date->modify('-'.(((int)$date->format('w')-$firstDay+7)%7).' days');$end=$start->modify('+6 days');}
        $step=match($view){'dayGridMonth'=>'month','dayGridWeek','timeGridWeek'=>'week',default=>'day'};
        $navigation=$view==='dayGridMonth'?$date->modify('first day of this month'):$date;return['start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'previous'=>$navigation->modify('-1 '.$step)->format('Y-m-d'),'next'=>$navigation->modify('+1 '.$step)->format('Y-m-d')];
    }
}
