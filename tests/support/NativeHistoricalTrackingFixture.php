<?php
declare(strict_types=1);
/** Synthetic historical boundary for the disposable native436-derived fixture only. */
final class NativeHistoricalTrackingFixture
{
    public static function prepare($db,string $prefix,int $previous,callable $check):void
    {
        if(PHP_SAPI!=='cli'||!preg_match('/^essential_[a-f0-9]{10}_$/D',$prefix)||!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')||$previous<439||$previous>444)throw new LogicException('historical_tracking_fixture_invalid');
        $check($db->table_exists($prefix.'migrations'),'historical fixture inherits the native tracking table');
        $rows=$db->query('SELECT * FROM `'.$prefix.'migrations`')->result_array();
        $check(count($rows)===1&&array_keys($rows[0])===['version']&&(int)$rows[0]['version']===447,'synthetic historical preparation requires exactly the inherited447 marker');
        $check($db->query('UPDATE `'.$prefix.'migrations` SET version=? WHERE version=447',[$previous])!==false,'synthetic historical starting marker written before capture');
        $rows=$db->query('SELECT * FROM `'.$prefix.'migrations`')->result_array();
        $check(count($rows)===1&&array_keys($rows[0])===['version']&&(int)$rows[0]['version']===$previous,'synthetic starting marker confirmed by persisted readback');
    }
}
