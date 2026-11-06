<?php
declare(strict_types=1);

require_once __DIR__.'/NativeCompanyInvoiceRules.php';
require_once __DIR__.'/CompanyLeadMetadata.php';

/** Only fields backed by CompanyLeads; legacy status/source identifiers are never adopted. */
final class NativeCompanyLeadRules
{
    public const TYPES=['name'=>'TextRule','phonenumber'=>'TextRule','country'=>'SelectRule','city'=>'TextRule','state'=>'TextRule','zip'=>'TextRule','lastcontact'=>'DateRule','dateadded'=>'DateRule','lead_value'=>'NumberRule','status'=>'MultiSelectRule','source'=>'MultiSelectRule','assigned'=>'SelectRule'];
    private static function fail():never{throw new DomainException('native_lead_filter_invalid');}
    public static function normalizeBuilder(array $builder,string $format='Y-m-d|%Y-%m-%d'):array
    {
        if(array_diff(array_keys($builder),['match_type','rules'])||count($builder)!==2||!in_array($builder['match_type']??null,['and','or'],true)||!is_array($builder['rules']??null)||!array_is_list($builder['rules'])||count($builder['rules'])>100)self::fail();
        $out=[];
        foreach($builder['rules']as$rule){
            if(!is_array($rule)||array_diff(array_keys($rule),['id','type','operator','value','has_dynamic_value'])||!isset(self::TYPES[$rule['id']??''])||($rule['type']??null)!==self::TYPES[$rule['id']]||!array_key_exists('value',$rule))self::fail();
            $id=$rule['id'];$type=$rule['type'];$op=$rule['operator']??($type==='MultiSelectRule'?'in':'equal');if($op==='')$op=$type==='MultiSelectRule'?'in':'equal';
            $ops=match($type){'TextRule'=>['equal','not_equal','begins_with','not_begins_with','contains','not_contains','ends_with','not_ends_with'],'NumberRule'=>['equal','not_equal','between','not_between','less','less_or_equal','greater','greater_or_equal'],'DateRule'=>['equal','not_equal','between','not_between','less','less_or_equal','greater','greater_or_equal','dynamic'],'MultiSelectRule'=>['in','not_in'],'SelectRule'=>$id==='assigned'?['equal','not_equal','is_empty','is_not_empty']:['equal','not_equal']};
            if(!in_array($op,$ops,true))self::fail();$dynamic=$rule['has_dynamic_value']??false;if(!in_array($dynamic,[true,false,0,1,'0','1','true','false'],true))self::fail();$dynamic=in_array($dynamic,[true,1,'1','true'],true)||$op==='dynamic';
            if($dynamic&&($type!=='DateRule'||!in_array($op,['dynamic','between','not_between'],true)))self::fail();$value=$rule['value'];
            if(in_array($op,['is_empty','is_not_empty'],true))$value=0;
            elseif($type==='TextRule'){if(!is_string($value)||mb_strlen($value)>500||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))self::fail();}
            elseif($type==='MultiSelectRule'||$type==='SelectRule'){
                if($type==='MultiSelectRule'&&(!is_array($value)||!array_is_list($value)||!$value||count($value)>500))self::fail();$values=$type==='MultiSelectRule'?$value:[$value];
                $values=array_values(array_unique(array_map(static function($v){if((!is_int($v)&&!is_string($v))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$v)||(float)$v>2147483647)self::fail();return(string)$v;},$values)));$value=$type==='MultiSelectRule'?$values:$values[0];
            }else{
                $range=in_array($op,['between','not_between'],true);if($range&&(!is_array($value)||!array_is_list($value)||count($value)!==2))self::fail();$values=$range?$value:[$value];
                $values=array_map(static function($v)use($type,$format,$dynamic){if($type==='NumberRule'){$value=CompanyLeadMetadata::decimal($v);if($value===null)self::fail();return$value;}if($dynamic){NativeCompanyInvoiceRules::dynamicDate($v,'2026-01-15');return$v;}return NativeCompanyInvoiceRules::dateValue($v,$format);},$values);$value=$range?$values:$values[0];
            }
            $out[]=['id'=>$id,'type'=>$type,'operator'=>$op,'value'=>$value,'has_dynamic_value'=>$dynamic];
        }
        return['match_type'=>$builder['match_type'],'rules'=>$out];
    }
    public static function compile(array $builder,string $today,string $format='Y-m-d|%Y-%m-%d'):array
    {
        NativeCompanyInvoiceRules::dateValue($today,'Y-m-d|%Y-%m-%d');$builder=self::normalizeBuilder($builder,$format);$parts=[];$params=[];
        $columns=['name'=>'q.name','phonenumber'=>'q.phone','country'=>'q.country','city'=>'q.city','state'=>'q.state','zip'=>'q.zip','lastcontact'=>'SUBSTR(q.lastcontact,1,10)','dateadded'=>'SUBSTR(q.created_at,1,10)','lead_value'=>'q.lead_value','status'=>'q.stage_id','source'=>'q.source_id','assigned'=>'q.assignee_id'];
        foreach($builder['rules']as$rule){['id'=>$id,'operator'=>$op,'value'=>$value,'has_dynamic_value'=>$dynamic]=$rule;$column=$columns[$id];
            if($dynamic){$value=is_array($value)?array_map(static fn($v)=>NativeCompanyInvoiceRules::dynamicDate($v,$today),$value):NativeCompanyInvoiceRules::dynamicDate($value,$today);if($op==='dynamic')$op='equal';}
            if(in_array($op,['is_empty','is_not_empty'],true)){$parts[]=$column.($op==='is_empty'?'=0':'<>0');continue;}
            if(in_array($op,['in','not_in'],true)){$parts[]=$column.($op==='in'?' IN ':' NOT IN ').'('.implode(',',array_fill(0,count($value),'?')).')';array_push($params,...$value);continue;}
            if(in_array($op,['between','not_between'],true)){$parts[]=$column.($op==='between'?' BETWEEN ':' NOT BETWEEN ').'? AND ?';array_push($params,...$value);continue;}
            $operators=['equal'=>'=','not_equal'=>'<>','less'=>'<','less_or_equal'=>'<=','greater'=>'>','greater_or_equal'=>'>='];if(isset($operators[$op])){$parts[]=$column.$operators[$op].'?';$params[]=$value;continue;}
            $pattern=str_replace(['!','%','_'],['!!','!%','!_'],$value);$base=str_starts_with($op,'not_')?substr($op,4):$op;$pattern=match($base){'begins_with'=>$pattern.'%','ends_with'=>'%'.$pattern,'contains'=>'%'.$pattern.'%',default=>throw new DomainException('native_lead_filter_invalid')};$parts[]=$column.(str_starts_with($op,'not_')?' NOT LIKE ':' LIKE ')."? ESCAPE '!'";$params[]=$pattern;
        }
        return['sql'=>$parts?'('.implode(' '.strtoupper($builder['match_type']).' ',array_map(static fn($v)=>'('.$v.')',$parts)).')':'','params'=>$params,'builder'=>$builder];
    }
}
