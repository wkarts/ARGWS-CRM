<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyProjectRules.php';
/** Closed grammar matching the original related-tasks controls. No user SQL or field names. */
final class NativeCompanyRelatedTaskRules
{
    public const TYPES=['name'=>'TextRule','startdate'=>'DateRule','duedate'=>'DateRule','status'=>'MultiSelectRule','priority'=>'MultiSelectRule','todays_tasks'=>'BooleanRule','duedate_passed'=>'BooleanRule','not_assigned'=>'BooleanRule','my_tasks'=>'BooleanRule','my_following_tasks'=>'BooleanRule','upcoming_tasks'=>'BooleanRule','recurring'=>'BooleanRule','billable'=>'BooleanRule','billed'=>'BooleanRule','assigned'=>'MultiSelectRule'];
    public static function normalizeBuilder(array$b,string$format='Y-m-d|%Y-%m-%d'):array
    {
        if(array_diff(array_keys($b),['match_type','rules'])||!in_array($b['match_type']??null,['and','or'],true)||!is_array($b['rules']??null)||!array_is_list($b['rules'])||count($b['rules'])>100)throw new DomainException('native_task_filter_invalid');$out=[];
        foreach($b['rules']as$r){
            if(!is_array($r)||array_diff(array_keys($r),['id','type','operator','value','has_dynamic_value'])||!isset(self::TYPES[$r['id']??''])||($r['type']??null)!==self::TYPES[$r['id']]||!array_key_exists('value',$r))throw new DomainException('native_task_filter_invalid');
            $id=$r['id'];$type=$r['type'];
            if(in_array($type,['TextRule','DateRule'],true)){
                if($id==='duedate'&&in_array($r['operator']??null,['is_empty','is_not_empty'],true)){$r=['id'=>$id,'type'=>$type,'operator'=>$r['operator'],'value'=>null,'has_dynamic_value'=>false];}
                else{$r['id']=match($id){'name'=>'name','startdate'=>'start_date','duedate'=>'deadline'};$r=NativeCompanyProjectRules::normalizeBuilder(['match_type'=>'and','rules'=>[$r]],$format)['rules'][0];$r['id']=$id;}
            }else{
                if(!in_array($r['has_dynamic_value']??false,[false,0,'0','false'],true))throw new DomainException('native_task_filter_invalid');$op=$r['operator']??($type==='BooleanRule'?'equal':'in');
                if($type==='BooleanRule'){if(!in_array($op,['','equal','not_equal'],true)||!in_array($r['value'],[true,false,0,1,'0','1'],true))throw new DomainException('native_task_filter_invalid');$value=in_array($r['value'],[true,1,'1'],true)?'1':'0';$op=$op===''?'equal':$op;}
                else{if(!in_array($op,['in','not_in'],true)||!is_array($r['value'])||!array_is_list($r['value'])||!$r['value']||count($r['value'])>500)throw new DomainException('native_task_filter_invalid');$value=[];foreach($r['value']as$v){if((!is_string($v)&&!is_int($v))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$v)||($id==='status'&&!in_array((string)$v,['1','2','3','4','5'],true))||($id==='priority'&&!in_array((string)$v,['1','2','3','4'],true)))throw new DomainException('native_task_filter_invalid');$value[]=(string)$v;}$value=array_values(array_unique($value));}
                $r=['id'=>$id,'type'=>$type,'operator'=>$op,'value'=>$value,'has_dynamic_value'=>false];
            }$out[]=$r;
        }return['match_type'=>$b['match_type'],'rules'=>$out];
    }
    public static function matches(array$b,array$facts,string$today):bool
    {
        $b=self::normalizeBuilder($b);$results=[];
        foreach($b['rules']as$r){$id=$r['id'];if(!array_key_exists($id,$facts))throw new DomainException('native_task_filter_fact_required');$actual=$facts[$id];$value=$r['value'];$op=$r['operator'];
            if($r['type']==='TextRule'){$actual=mb_strtolower((string)$actual);$value=mb_strtolower($value);$yes=match($op){'equal','not_equal'=>$actual===$value,'begins_with','not_begins_with'=>str_starts_with($actual,$value),'contains','not_contains'=>str_contains($actual,$value),'ends_with','not_ends_with'=>str_ends_with($actual,$value)};$yes=str_starts_with($op,'not_')?!$yes:$yes;}
            elseif($r['type']==='MultiSelectRule'){$values=is_array($actual)?array_map('strval',$actual):[(string)$actual];$yes=(bool)array_intersect($values,$value);if($op==='not_in')$yes=!$yes;}
            elseif($r['type']==='BooleanRule'&&in_array($id,['todays_tasks','duedate_passed','upcoming_tasks'],true)){
                if(!isset($facts['status'])||!array_key_exists('startdate',$facts)||!array_key_exists('duedate',$facts))throw new DomainException('native_task_filter_fact_required');
                $positive=($value==='1')!==($op==='not_equal');
                $date=$id==='duedate_passed'?$facts['duedate']:$facts['startdate'];
                $matches=$date!==null&&match($id){'todays_tasks'=>$date===$today,'duedate_passed'=>$date<$today,'upcoming_tasks'=>$date>$today};
                // Match SQL NULL semantics and keep completed tasks out of these native filters.
                $yes=$facts['status']!==5&&$date!==null&&($positive?$matches:!$matches);
            }
            elseif($r['type']==='BooleanRule'){if(!is_bool($actual)&&$actual!==null)throw new DomainException('native_task_filter_fact_required');$yes=$actual!==null&&$actual===($value==='1');if($op==='not_equal')$yes=$actual!==null&&!$yes;}
            else{
                if(in_array($op,['is_empty','is_not_empty'],true))$yes=$op==='is_empty'?$actual===null:$actual!==null;
                elseif($actual===null)$yes=false;
                else{$convert=static function($v)use($r,$today){return$r['has_dynamic_value']?(new DateTimeImmutable($today,new DateTimeZone('UTC')))->modify($v)->format('Y-m-d'):$v;};$v=is_array($value)?array_map($convert,$value):$convert($value);$yes=match($op){'equal','dynamic'=>$actual===$v,'not_equal'=>$actual!==$v,'less'=>$actual<$v,'less_or_equal'=>$actual<=$v,'greater'=>$actual>$v,'greater_or_equal'=>$actual>=$v,'between'=>$actual>=$v[0]&&$actual<=$v[1],'not_between'=>$actual<$v[0]||$actual>$v[1]};}
            }$results[]=$yes;
        }return!$results||($b['match_type']==='and'?!in_array(false,$results,true):in_array(true,$results,true));
    }
}
