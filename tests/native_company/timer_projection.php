<?php
declare(strict_types=1);
require __DIR__.'/presentation.php';
final class NativeCompanyTasksRead {
 static int $reads=0;static bool $change=false;static array $clocks=[];
 function __construct($db,$prefix,$timezone){check($prefix==='tbl'&&$timezone===date_default_timezone_get(),'explicit runtime clock basis');}
 function startedTimers($issuer,$actor,$now){check($issuer===2&&$actor===17,'timer provider exact scope');self::$clocks[]=$now;self::$reads++;return [['id'=>'47244640257','task_id'=>'8589934593','task_subject'=>'Scoped task','start_time'=>1760000000,'time_basis'=>'unix_epoch','total_seconds'=>self::$change&&self::$reads%2===0?10:9,'revision'=>1]];}
}
final class NativeCompanyTimerPolicy {
 static bool $change=false;static int $reads=0;
 function __construct($db,$prefix){}
 function read($issuer,$actor){check($issuer===2&&$actor===17,'timer policy exact scope');self::$reads++;return ['can_edit'=>true,'can_view_all'=>false,'auto_stop'=>true,'revision'=>self::$change&&self::$reads%2===0?2:1,'source'=>'owned_override'];}
}
final class NativeCompanyBlankTimers {static function policyHash($policy){return hash('sha256',json_encode($policy,JSON_THROW_ON_ERROR));}}
CompanyRequestScope::$native=true;CompanyRequestScope::$issuer=2;CompanyRequestScope::$token='2:projection';NativeCompanyAdminPresentation::prepare($ci);
$before=date_default_timezone_get();$projection=NativeCompanyAdminPresentation::timerProjection($ci);
check(count(array_unique(NativeCompanyTasksRead::$clocks))===1,'one clock across both reads');
check($projection['timer_policy']['can_start_blank']===false,'auto-stop with active timer prevents blank');
check($projection['started_timers'][0]['started_at_label']===(new DateTimeImmutable('@1760000000'))->setTimezone(new DateTimeZone($before))->format('d/m/Y H:i'),'actual shared formatter uses configured format and explicit runtime timezone');
check(date_default_timezone_get()===$before,'formatter does not mutate process timezone');
foreach(['task','policy'] as $change){
 NativeCompanyTasksRead::$reads=0;NativeCompanyTimerPolicy::$reads=0;NativeCompanyTasksRead::$change=$change==='task';NativeCompanyTimerPolicy::$change=$change==='policy';
 try{NativeCompanyAdminPresentation::timerProjection($ci);throw new RuntimeException('changed snapshot accepted');}catch(DomainException $e){check($e->getMessage()==='native_company_task_read_changed','changed snapshot rejected');}
}
echo "Native timer projection: actual formatter, explicit scope/timezone, one clock and two snapshot-revocation negatives passed; reader/DB covered separately.\n";
