<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskSettings.php';
/** The existing native rounding contract; no source access or billing authority. */
final class NativeCompanyTaskTimerRounding
{
 public static function roundSeconds(int$seconds,array$options):int
 {
  NativeCompanyTaskSettings::validateExtras($options);if($seconds<0||array_keys($options)!==['round_off_task_timer_option','round_off_task_timer_time'])throw new DomainException('native_billable_task_timer_invalid');$step=(int)$options['round_off_task_timer_time']*60;$q=intdiv($seconds,$step);$r=$seconds%$step;$mode=$options['round_off_task_timer_option'];if($mode==='0')return$seconds;if(($mode==='1'&&$r>0)||($mode==='3'&&$r*2>=$step))$q++;if($q>intdiv(PHP_INT_MAX,$step))throw new DomainException('native_billable_task_timer_invalid');return$q*$step;
 }
}
