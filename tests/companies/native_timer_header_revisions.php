<?php
namespace {require __DIR__.'/native_task_timer_read.php';}
namespace NativeTimerHeaderTest {
    // Pure rendering boundary only; actual presentation attestation has its separate integration suite.
    final class NativeCompanyAdminPresentation {public static function current($ci){return true;}public static function label($key){return$key;}}
    final class CompanyRequestScope {public static function adminUrl($ci,$path){return'https://synthetic.invalid/admin/'.$path;}}
    function get_instance(){return new \stdClass;}
    function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
    function render_textarea($name){return '<textarea name="'.e($name).'"></textarea>';}
    \class_alias(\NativeCompanyResourceId::class,__NAMESPACE__.'\\NativeCompanyResourceId');
    function render(array$startedTimers):string{$nativeTimerPolicy=['policy_hash'=>str_repeat('a',64),'can_edit'=>true,'can_view_all'=>false,'can_start_blank'=>false,'auto_stop'=>false];$source=file_get_contents(APPPATH.'views/admin/tasks/started_timers.php');$source=str_replace("require_once APPPATH.'services/NativeCompanyAdminPresentation.php';",'', $source);ob_start();try{eval('namespace '.__NAMESPACE__.'; use \\LogicException; ?>'.$source);return ob_get_clean();}catch(\Throwable$e){ob_end_clean();throw$e;}}
}
namespace {
$dto=$legacy[0];$dto['started_at_label']='25/10/2026 12:00';$html=\NativeTimerHeaderTest\render([$dto]);essentialCheck(str_contains($html,'data-timer-revision="'.$dto['revision'].'"')&&str_contains($html,'data-note-revision="0"'),'Original partial renders real legacy revision and metadata CAS without global model');
$owned=$rows[0];$owned['started_at_label']='25/10/2026 12:00';essentialCheck(str_contains(\NativeTimerHeaderTest\render([$owned]),'data-note-revision="0"'),'Original owned partial preserves integer task revision and undeclared note CAS');
foreach([array_replace($dto,['revision'=>1]),array_replace($owned,['revision'=>str_repeat('a',64)]),array_replace($owned,['note_revision'=>null]),array_replace($dto,['revision'=>str_repeat('z',64)])]as$invalid){try{\NativeTimerHeaderTest\render([$invalid]);throw new \RuntimeException('accepted');}catch(\LogicException$e){essentialCheck($e->getMessage()==='native_timer_projection_incomplete','Revision type follows canonical source, never caller marker');}}
echo 'Native timer header revisions: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
}
