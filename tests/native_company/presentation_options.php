<?php
declare(strict_types=1);
class CompanySettingsRuntime{
 static $primary=false;static function isPrimary(){return self::$primary;}static $issuer=2;static $actor=17;static array $values=['dateformat'=>'d/m/Y|%d/%m/%Y','time_format'=>'24','desktop_notifications'=>'1','auto_dismiss_desktop_notifications_after'=>'10','tables_pagination_limit'=>'37','task_modal_class'=>'modal-lg'];static array $reads=[];
 static function active(){return true;}static function issuerId(){return self::$issuer;}static function staffId(){return self::$actor;}static function exists($name){self::$reads[]=$name;return array_key_exists($name,self::$values);}static function get($name){return self::$values[$name];}
}
require_once __DIR__.'/../../application/services/NativeCompanyPresentationOptions.php';
function optionCheck($ok,$name){if(!$ok)throw new RuntimeException($name);}
$r=NativeCompanyPresentationOptions::capture(2,17);optionCheck($r['values']===CompanySettingsRuntime::$values,'exact persisted values');optionCheck(count(CompanySettingsRuntime::$reads)===6,'six public options only');
foreach([[3,17],[2,18]] as [$issuer,$actor]){try{NativeCompanyPresentationOptions::capture($issuer,$actor);throw new LogicException('foreign scope accepted');}catch(DomainException $e){optionCheck($e->getMessage()==='native_company_options_context_mismatch','scope mismatch');}}
foreach(['dateformat'=>'javascript:bad','time_format'=>'99','desktop_notifications'=>'yes','auto_dismiss_desktop_notifications_after'=>'99999','task_modal_class'=>'modal-invalid'] as $key=>$bad){$old=CompanySettingsRuntime::$values[$key];CompanySettingsRuntime::$values[$key]=$bad;try{NativeCompanyPresentationOptions::capture(2,17);throw new LogicException('invalid value accepted');}catch(DomainException $e){optionCheck($e->getMessage()==='native_company_options_invalid','invalid setting rejected');}CompanySettingsRuntime::$values[$key]=$old;}
CompanySettingsRuntime::$values=[];$r=NativeCompanyPresentationOptions::capture(2,17);foreach($r['values'] as $key=>$value)optionCheck($value===null&&$r['present'][$key]===false,'missing not fabricated');
echo "Native presentation options: allowlist, persisted values, absent provenance, A/B/actor and invalid values passed\n";

optionCheck(NativeCompanyPresentationOptions::pagination($r)==='25','native public default25');
foreach(['0','-1','01','1.5',(string)PHP_INT_MAX.'0'] as $bad){try{NativeCompanyPresentationOptions::pagination(['values'=>['tables_pagination_limit'=>$bad]]);throw new LogicException('invalid page limit accepted');}catch(DomainException $e){}}
optionCheck(NativeCompanyPresentationOptions::pagination(['values'=>['tables_pagination_limit'=>(string)PHP_INT_MAX]])===(string)PHP_INT_MAX,'native supported integer upper bound');
