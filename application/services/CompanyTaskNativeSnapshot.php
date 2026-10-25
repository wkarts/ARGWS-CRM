<?php
declare(strict_types=1);
/** Additive core-474 columns must not rewrite the meaning/hash of pre-474 financial evidence. */
final class CompanyTaskNativeSnapshot
{
    public static function row(array$row):array
    {
        $hasJson=array_key_exists('native_fields_json',$row);$hasRevision=array_key_exists('native_fields_revision',$row);
        if(!$hasJson&&!$hasRevision)return$row;
        if(($row['native_fields_json']??null)===null&&($row['native_fields_revision']??null)===null){unset($row['native_fields_json'],$row['native_fields_revision']);return$row;}
        if(!$hasJson||!$hasRevision||!is_string($row['native_fields_json'])||!preg_match('/^[1-9][0-9]*$/D',(string)$row['native_fields_revision']))throw new RuntimeException('native_task_form_state_invalid');
        json_decode($row['native_fields_json'],true,32,JSON_THROW_ON_ERROR);return$row;
    }
}
