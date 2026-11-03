<?php
require __DIR__.'/../application/services/NativeCompanyTimerPresentation.php';
$n=0;function check($ok,$why){global$n;if(!$ok)throw new RuntimeException($why);++$n;}
$timer=['start_time'=>strtotime('2026-10-25 15:01:00 UTC'),'time_basis'=>'runtime_datetime','storage_timezone'=>'America/Sao_Paulo'];
foreach(CompanySettingsFormats::dates()as$key=>$phpFormat)foreach(['12','24']as$hour){$actual=NativeCompanyTimerPresentation::startedLabel($timer,['dateformat'=>$key,'time_format'=>$hour],'America/Sao_Paulo');$before=date_default_timezone_get();date_default_timezone_set('America/Sao_Paulo');$expected=date($phpFormat.($hour==='24'?' H:i':' g:i A'),$timer['start_time']);date_default_timezone_set($before);check($actual===$expected,'native formatter output parity');}
foreach([array_replace($timer,['time_basis'=>'guessed']),array_replace($timer,['start_time'=>'123']),array_replace($timer,['time_basis'=>'utc_datetime'])]as$invalid){try{NativeCompanyTimerPresentation::startedLabel($invalid,['dateformat'=>'Y-m-d|%Y-%m-%d','time_format'=>'24'],'UTC');throw new RuntimeException('accepted');}catch(DomainException$e){check($e->getMessage()==='native_timer_presentation_invalid','invalid provenance rejected');}}
check(date_default_timezone_get()===$before,'formatting never changes runtime timezone');echo "Native timer formatting: $n checks passed.\n";
