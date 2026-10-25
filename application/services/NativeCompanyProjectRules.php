<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyWorkStatusCatalog.php';
require_once __DIR__.'/CompanySettingsFormats.php';
/** Closed grammar for the unchanged native projects filter controls. Values never become SQL identifiers. */
final class NativeCompanyProjectRules
{
    public const TYPES=['name'=>'TextRule','start_date'=>'DateRule','deadline'=>'DateRule','billing_type'=>'SelectRule','status'=>'MultiSelectRule','members'=>'MultiSelectRule'];
    private static function fail():never{throw new DomainException('native_project_filter_invalid');}
    public static function normalizeBuilder(array$b,string$format='Y-m-d|%Y-%m-%d'):array
    {
        if(count($b)!==2||array_diff(array_keys($b),['match_type','rules'])||!in_array($b['match_type']??null,['and','or'],true)||!is_array($b['rules'])||!array_is_list($b['rules'])||count($b['rules'])>100)self::fail();
        $out=[];
        foreach($b['rules']as$r){
            if(!is_array($r)||array_diff(array_keys($r),['id','type','operator','value','has_dynamic_value'])||!isset(self::TYPES[$r['id']??''])||($r['type']??null)!==self::TYPES[$r['id']]||!array_key_exists('value',$r))self::fail();
            $id=$r['id'];$type=$r['type'];$op=$r['operator']??'equal';if($op==='')$op='equal';
            $ops=match($type){'TextRule'=>['equal','not_equal','begins_with','not_begins_with','contains','not_contains','ends_with','not_ends_with'],'DateRule'=>['equal','not_equal','between','not_between','less','less_or_equal','greater','greater_or_equal','dynamic'],'SelectRule'=>['equal','not_equal'],'MultiSelectRule'=>['in','not_in']};
            if(!in_array($op,$ops,true))self::fail();$dynamic=$r['has_dynamic_value']??false;
            if(!in_array($dynamic,[true,false,0,1,'0','1','true','false'],true))self::fail();$dynamic=in_array($dynamic,[true,1,'1','true'],true)||$op==='dynamic';
            if($dynamic&&($type!=='DateRule'||!in_array($op,['dynamic','between','not_between'],true)))self::fail();$value=$r['value'];
            if($type==='TextRule'){if(!is_string($value)||mb_strlen($value)>500||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))self::fail();}
            elseif($type==='SelectRule'){if(!in_array($value,[1,2,3,'1','2','3'],true))self::fail();$value=(string)$value;}
            elseif($type==='MultiSelectRule'){
                if(!is_array($value)||!array_is_list($value)||!$value||count($value)>500)self::fail();
                $value=array_values(array_unique(array_map(static function($v)use($id){if((!is_int($v)&&!is_string($v))||!preg_match('/^[1-9][0-9]{0,15}$/D',(string)$v))self::fail();if($id==='status')NativeCompanyWorkStatusCatalog::predicate('project',$v,[1,2,3,4,5]);return(string)$v;},$value)));
            }else{
                $range=in_array($op,['between','not_between'],true);if($range&&(!is_array($value)||!array_is_list($value)||count($value)!==2))self::fail();
                $values=$range?$value:[$value];$values=array_map(static function($v)use($dynamic,$format){return self::date($v,$format,$dynamic);},$values);$value=$range?$values:$values[0];
            }
            $out[]=['id'=>$id,'type'=>$type,'operator'=>$op,'value'=>$value,'has_dynamic_value'=>$dynamic];
        }return['match_type'=>$b['match_type'],'rules'=>$out];
    }
    private static function date($v,string$format,bool$dynamic=false):string
    {
        if(!is_string($v)||strlen($v)>100||trim($v)===''||preg_match('/[\x00-\x1f\x7f]/',$v))self::fail();
        if($dynamic){try{$d=@(new DateTimeImmutable('2026-01-15',new DateTimeZone('UTC')))->modify($v);}catch(Throwable$e){self::fail();}if(!$d)self::fail();return trim($v);}
        if(!isset(CompanySettingsFormats::dates()[$format]))self::fail();$style=preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D',$v)?'Y-m-d':CompanySettingsFormats::dates()[$format];$d=DateTimeImmutable::createFromFormat('!'.$style,$v,new DateTimeZone('UTC'));if(!$d||$d->format($style)!==$v||(int)$d->format('Y')<1000)self::fail();return$d->format('Y-m-d');
    }
    /** Compile against a normalized lineage-aware project row query: alias q and canonical transport IDs. */
    public static function compile(array$b,string$p,string$today,string$format='Y-m-d|%Y-%m-%d',?array$visibleMembers=null):array
    {
        $memberFence='';if($visibleMembers!==null){foreach($visibleMembers as$m)if(!is_int($m)||$m<1)self::fail();$memberFence=' AND pm.staff_id IN ('.($visibleMembers?implode(',',$visibleMembers):'0').')';}
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))self::fail();self::date($today,'Y-m-d|%Y-%m-%d');$b=self::normalizeBuilder($b,$format);$parts=[];$args=[];
        foreach($b['rules']as$r){['id'=>$id,'operator'=>$op,'value'=>$v,'has_dynamic_value'=>$dynamic]=$r;
            if($dynamic){$resolve=static function($s)use($today){try{$d=@(new DateTimeImmutable($today,new DateTimeZone('UTC')))->modify($s);}catch(Throwable$e){self::fail();}if(!$d||(int)$d->format('Y')<1000||(int)$d->format('Y')>9999)self::fail();return$d->format('Y-m-d');};$v=is_array($v)?array_map($resolve,$v):$resolve($v);if($op==='dynamic')$op='equal';}
            if($id==='members'){
                $place=implode(',',array_fill(0,count($v),'?'));
                // Native NOT IN means a project with at least one member outside the selected set, not NOT EXISTS membership.
                $sqlop=$op==='in'?'IN':'NOT IN';
                $parts[]="((q.source_kind='company_project' AND EXISTS(SELECT 1 FROM {$p}company_project_members pm WHERE pm.issuer_id=q.issuer_id AND pm.project_id=q.source_id AND pm.active=1$memberFence AND pm.staff_id $sqlop ($place))) OR (q.source_kind='project' AND EXISTS(SELECT 1 FROM {$p}project_members pm WHERE pm.project_id=q.source_id AND pm.staff_id $sqlop ($place))))";$args=array_merge($args,$v,$v);continue;
            }
            $column='q.'.$id;
            if(in_array($op,['in','not_in'],true)){$parts[]=$column.($op==='in'?' IN ':' NOT IN ').'('.implode(',',array_fill(0,count($v),'?')).')';$args=array_merge($args,$v);continue;}
            if(in_array($op,['between','not_between'],true)){if($v[0]===$v[1]){$parts[]=$column.'=?';$args[]=$v[0];}else{$parts[]=$column.($op==='between'?' BETWEEN ':' NOT BETWEEN ').'? AND ?';$args=array_merge($args,$v);}continue;}
            if(in_array($op,['equal','not_equal','less','less_or_equal','greater','greater_or_equal'],true)){$parts[]=$column.['equal'=>'=','not_equal'=>'<>','less'=>'<','less_or_equal'=>'<=','greater'=>'>','greater_or_equal'=>'>='][$op].'?';$args[]=$v;continue;}
            $value=str_replace(['!','%','_'],['!!','!%','!_'],$v);$base=str_starts_with($op,'not_')?substr($op,4):$op;$value=match($base){'begins_with'=>$value.'%','ends_with'=>'%'.$value,'contains'=>'%'.$value.'%',default=>throw new DomainException('native_project_filter_invalid')};$parts[]=$column.(str_starts_with($op,'not_')?' NOT LIKE ':' LIKE ')."? ESCAPE '!'";$args[]=$value;
        }
        return['sql'=>$parts?'('.implode(' '.strtoupper($b['match_type']).' ',array_map(static fn($s)=>'('.$s.')',$parts)).')':'','params'=>$args,'builder'=>$b];
    }
}
