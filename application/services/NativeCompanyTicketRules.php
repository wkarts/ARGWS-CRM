<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceId.php';
/** Closed grammar of the ORIGINAL eight ticket rules; never accepts caller SQL. */
final class NativeCompanyTicketRules
{
    public const TYPES=['subject'=>'TextRule','department'=>'SelectRule','status'=>'MultiSelectRule','priority'=>'SelectRule','service'=>'SelectRule','merged'=>'BooleanRule','my_tickets'=>'BooleanRule','assigned'=>'SelectRule'];
    private static function fail():never{throw new DomainException('native_ticket_filter_invalid');}
    private static function id(string $kind,$value):string
    {
        if((!is_string($value)&&!is_int($value))||!preg_match('/^[1-9][0-9]*$/D',(string)$value))self::fail();
        if($kind==='assigned'){if(strlen((string)$value)>10||(int)$value>2147483647)self::fail();return(string)$value;}
        try{NativeCompanyResourceId::decode('ticket_'.$kind,$value);}catch(Throwable$e){self::fail();}return(string)$value;
    }
    public static function normalizeBuilder(array $builder):array
    {
        if(count($builder)!==2||array_diff(array_keys($builder),['match_type','rules'])||!in_array($builder['match_type']??null,['and','or'],true)||!is_array($builder['rules'])||!array_is_list($builder['rules'])||count($builder['rules'])>100)self::fail();$out=[];
        foreach($builder['rules']as$r){
            if(!is_array($r)||array_diff(array_keys($r),['id','type','operator','value','has_dynamic_value'])||!isset(self::TYPES[$r['id']??''])||($r['type']??null)!==self::TYPES[$r['id']]||!array_key_exists('value',$r))self::fail();
            $id=$r['id'];$type=$r['type'];$op=$r['operator']??'equal';if($op==='')$op='equal';$v=$r['value'];
            if(!in_array($r['has_dynamic_value']??false,[false,0,'0','false'],true))self::fail();
            $ops=match($type){'TextRule'=>['equal','not_equal','begins_with','not_begins_with','contains','not_contains','ends_with','not_ends_with'],'MultiSelectRule'=>['in','not_in'],'BooleanRule'=>['equal'],'SelectRule'=>$id==='assigned'?['equal','not_equal','is_empty','is_not_empty']:['equal','not_equal']};
            if(!in_array($op,$ops,true))self::fail();
            if($type==='TextRule'){if(!is_string($v)||mb_strlen($v)>500||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))self::fail();}
            elseif($type==='BooleanRule'){if(!in_array($v,[0,1,'0','1'],true))self::fail();$v=(string)$v;}
            elseif(in_array($op,['is_empty','is_not_empty'],true)){if(!in_array($v,[null,'',0,'0'],true))self::fail();$v='0';}
            elseif($type==='MultiSelectRule'){if(!is_array($v)||!array_is_list($v)||!$v||count($v)>500)self::fail();$v=array_values(array_unique(array_map(fn($x)=>self::id($id,$x),$v)));}
            else{$v=self::id($id,$v);}
            $out[]=['id'=>$id,'type'=>$type,'operator'=>$op,'value'=>$v,'has_dynamic_value'=>false];
        }return['match_type'=>$builder['match_type'],'rules'=>$out];
    }
    /** q is a trusted normalized ticket projection; catalog values are canonical transport IDs. */
    public static function compile(array $builder,int $actor):array
    {
        if($actor<1||$actor>2147483647)self::fail();$b=self::normalizeBuilder($builder);$parts=[];$args=[];
        foreach($b['rules']as$r){['id'=>$id,'operator'=>$op,'value'=>$v]=$r;
            if($id==='merged'){$parts[]='q.merged_ticket_id IS '.($v==='1'?'NOT ':'').'NULL';continue;}
            if($id==='my_tickets'){$parts[]='q.assigned'.($v==='1'?'=':'<>').'?';$args[]=$actor;continue;}
            if(in_array($op,['is_empty','is_not_empty'],true)){$parts[]='q.assigned'.($op==='is_empty'?'=':'<>').'?';$args[]=0;continue;}
            $column='q.'.$id;
            if(in_array($op,['in','not_in'],true)){$parts[]=$column.($op==='in'?' IN ':' NOT IN ').'('.implode(',',array_fill(0,count($v),'?')).')';$args=array_merge($args,$v);continue;}
            if(in_array($op,['equal','not_equal'],true)){$parts[]=$column.($op==='equal'?'=':'<>').'?';$args[]=$v;continue;}
            $value=str_replace(['!','%','_'],['!!','!%','!_'],$v);$base=str_starts_with($op,'not_')?substr($op,4):$op;$value=match($base){'begins_with'=>$value.'%','ends_with'=>'%'.$value,'contains'=>'%'.$value.'%',default=>throw new DomainException('native_ticket_filter_invalid')};$parts[]=$column.(str_starts_with($op,'not_')?' NOT LIKE ':' LIKE ')."? ESCAPE '!'";$args[]=$value;
        }
        return['sql'=>$parts?'('.implode(' '.strtoupper($b['match_type']).' ',array_map(fn($s)=>'('.$s.')',$parts)).')':'','params'=>$args,'builder'=>$b];
    }
}
