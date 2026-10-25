<?php
declare(strict_types=1);
/** Explicit original task form fields. Missing historical attributes are not defaults or evidence. */
final class NativeCompanyTaskFormValues
{
    public const FIELDS=['start_date','billable','hourly_rate','is_public','visible_to_client','workflow_status','date_finished','relation_type','relation_source_kind','relation_source_id'];
    public static function normalize(array$input):array
    {
        if(array_diff(array_keys($input),self::FIELDS)||array_diff(self::FIELDS,array_keys($input)))self::fail();$out=[];
        $out['start_date']=self::date($input['start_date']);
        foreach(['billable','is_public','visible_to_client']as$field){if(!in_array($input[$field],[0,1,'0','1'],true))self::fail();$out[$field]=(int)$input[$field];}
        $rate=$input['hourly_rate'];if((!is_string($rate)&&!is_int($rate))||!preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D',(string)$rate,$m))self::fail();$out['hourly_rate']=$m[1].'.'.str_pad($m[2]??'',2,'0');
        if(!in_array($input['workflow_status'],[1,2,3,4,5,'1','2','3','4','5'],true))self::fail();$out['workflow_status']=(int)$input['workflow_status'];
        $finished=$input['date_finished'];if($finished!==null){if(!is_string($finished))self::fail();$date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$finished);if(!$date||$date->format('Y-m-d H:i:s')!==$finished)self::fail();}if(($out['workflow_status']===5)!==($finished!==null))self::fail();$out['date_finished']=$finished;
        $type=$input['relation_type'];$source=$input['relation_source_kind'];$id=$input['relation_source_id'];
        if($type===null){if($source!==null||$id!==null)self::fail();}
        else{if(!is_string($type)||!in_array($type,['project','invoice','customer','estimate','contract','ticket','expense','lead','proposal'],true)||!is_string($source)||!preg_match('/^[a-z_]{1,40}$/D',$source)||(!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$id)||(int)$id>4294967295)self::fail();$id=(int)$id;}
        $out['relation_type']=$type;$out['relation_source_kind']=$source;$out['relation_source_id']=$id;return$out;
    }
    public static function date($value):string{if(!is_string($value))self::fail();$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)self::fail();return$value;}
    public static function lifecycle(int$workflow):string{return match($workflow){1=>'todo',2=>'awaiting_feedback',3=>'testing',4=>'in_progress',5=>'done',default=>throw new DomainException('native_task_form_invalid')};}
    private static function fail():never{throw new DomainException('native_task_form_invalid');}
}
