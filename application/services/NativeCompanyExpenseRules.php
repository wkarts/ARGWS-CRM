<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceRules.php';
require_once __DIR__.'/NativeCompanyResourceId.php';

/** Original expense filters, compiled against the authorized directory projection only. */
final class NativeCompanyExpenseRules
{
    public const TYPES=['expense_name'=>'TextRule','date'=>'DateRule','year'=>'MultiSelectRule','amount'=>'NumberRule','category'=>'MultiSelectRule','billable'=>'BooleanRule','unbilled'=>'BooleanRule','recurring'=>'BooleanRule','paymentmode'=>'SelectRule'];
    private static function fail():never{throw new DomainException('native_expense_filter_invalid');}
    public static function normalizeBuilder(array $builder,string $format='Y-m-d|%Y-%m-%d'):array
    {
        if(array_diff(array_keys($builder),['match_type','rules'])||count($builder)!==2||!in_array($builder['match_type']??null,['and','or'],true)||!is_array($builder['rules']??null)||!array_is_list($builder['rules'])||count($builder['rules'])>100)self::fail();
        $out=[];
        foreach($builder['rules']as$rule){
            if(!is_array($rule)||array_diff(array_keys($rule),['id','type','operator','value','has_dynamic_value'])||!isset(self::TYPES[$rule['id']??''])||($rule['type']??null)!==self::TYPES[$rule['id']]||!array_key_exists('value',$rule))self::fail();
            $id=$rule['id'];$type=$rule['type'];$op=$rule['operator']??($type==='MultiSelectRule'?'in':'equal');if($op==='')$op=$type==='MultiSelectRule'?'in':'equal';
            $ops=match($type){'TextRule'=>['equal','not_equal','begins_with','not_begins_with','contains','not_contains','ends_with','not_ends_with'],'DateRule'=>['equal','not_equal','between','not_between','less','less_or_equal','greater','greater_or_equal','dynamic'],'NumberRule'=>['equal','not_equal','between','not_between','less','less_or_equal','greater','greater_or_equal'],'BooleanRule'=>['equal'],'MultiSelectRule'=>['in','not_in'],'SelectRule'=>['equal','not_equal','is_empty','is_not_empty']};
            if(!in_array($op,$ops,true))self::fail();$dynamic=$rule['has_dynamic_value']??false;if(!in_array($dynamic,[true,false,0,1,'0','1','true','false'],true))self::fail();$dynamic=in_array($dynamic,[true,1,'1','true'],true)||$op==='dynamic';if($dynamic&&($type!=='DateRule'||!in_array($op,['dynamic','between','not_between'],true)))self::fail();$value=$rule['value'];
            if(in_array($op,['is_empty','is_not_empty'],true))$value='';
            elseif($type==='TextRule'){if(!is_string($value)||mb_strlen($value)>500||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value))self::fail();}
            elseif($type==='BooleanRule'){if(!in_array($value,[true,false,0,1,'0','1'],true))self::fail();$value=in_array($value,[true,1,'1'],true)?'1':'0';}
            elseif($type==='MultiSelectRule'||$type==='SelectRule'){
                if($type==='MultiSelectRule'&&(!is_array($value)||!array_is_list($value)||!$value||count($value)>500))self::fail();$values=$type==='MultiSelectRule'?$value:[$value];
                $values=array_values(array_unique(array_map(static function($v)use($id){if(!is_string($v)&&!is_int($v))self::fail();if($id==='year'){if(!preg_match('/^[1-9][0-9]{3}$/D',(string)$v))self::fail();}else{NativeCompanyResourceId::decode($id==='category'?'expense_category':'payment_mode',$v);}return(string)$v;},$values)));$value=$type==='MultiSelectRule'?$values:$values[0];
            }else{
                $range=in_array($op,['between','not_between'],true);if($range&&(!is_array($value)||!array_is_list($value)||count($value)!==2))self::fail();$values=$range?$value:[$value];
                $values=array_map(static function($v)use($type,$format,$dynamic){if($type==='DateRule'){if($dynamic){NativeCompanyInvoiceRules::dynamicDate($v,'2026-01-15');return$v;}return NativeCompanyInvoiceRules::dateValue($v,$format);}if(!is_string($v)&&!is_int($v))self::fail();$v=(string)$v;if(!preg_match('/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/D',$v))self::fail();return$v;},$values);$value=$range?$values:$values[0];
            }
            $out[]=['id'=>$id,'type'=>$type,'operator'=>$op,'value'=>$value,'has_dynamic_value'=>$dynamic];
        }return['match_type'=>$builder['match_type'],'rules'=>$out];
    }
    private static function cents(string $value):string{[$whole,$fraction]=array_pad(explode('.',$value,2),2,'');return ltrim($whole.str_pad($fraction,2,'0'),'0')?:'0';}
    public static function compile(array $builder,string $today,string $format='Y-m-d|%Y-%m-%d'):array
    {
        NativeCompanyInvoiceRules::dateValue($today,'Y-m-d|%Y-%m-%d');$builder=self::normalizeBuilder($builder,$format);$parts=[];$params=[];
        $columns=['expense_name'=>'q.expense_name','date'=>'q.expense_date','year'=>'q.year','amount'=>'CAST(q.amount_cents AS DECIMAL(30,0))','category'=>'CAST(q.category AS CHAR)','billable'=>'CAST(q.billable AS SIGNED)','unbilled'=>'CAST(q.unbilled AS SIGNED)','recurring'=>'CAST(q.recurring AS SIGNED)','paymentmode'=>'q.payment_mode'];
        foreach($builder['rules']as$rule){['id'=>$id,'operator'=>$op,'value'=>$value,'has_dynamic_value'=>$dynamic]=$rule;$column=$columns[$id];
            if($dynamic){$value=is_array($value)?array_map(static fn($v)=>NativeCompanyInvoiceRules::dynamicDate($v,$today),$value):NativeCompanyInvoiceRules::dynamicDate($value,$today);if($op==='dynamic')$op='equal';}
            if($id==='amount')$value=is_array($value)?array_map(self::cents(...),$value):self::cents($value);
            if(in_array($op,['is_empty','is_not_empty'],true)){$parts[]=$column.($op==='is_empty'?"=''":"<>''");continue;}
            if(in_array($op,['in','not_in'],true)){$parts[]=$column.($op==='in'?' IN ':' NOT IN ').'('.implode(',',array_fill(0,count($value),'?')).')';array_push($params,...$value);continue;}
            if(in_array($op,['between','not_between'],true)){$parts[]=$column.($op==='between'?' BETWEEN ':' NOT BETWEEN ').'? AND ?';array_push($params,...$value);continue;}
            $operators=['equal'=>'=','not_equal'=>'<>','less'=>'<','less_or_equal'=>'<=','greater'=>'>','greater_or_equal'=>'>='];if(isset($operators[$op])){$parts[]=$column.$operators[$op].'?';$params[]=$value;continue;}
            $pattern=str_replace(['!','%','_'],['!!','!%','!_'],$value);$base=str_starts_with($op,'not_')?substr($op,4):$op;$pattern=match($base){'begins_with'=>$pattern.'%','ends_with'=>'%'.$pattern,'contains'=>'%'.$pattern.'%',default=>throw new DomainException('native_expense_filter_invalid')};$parts[]=$column.(str_starts_with($op,'not_')?' NOT LIKE ':' LIKE ')."? ESCAPE '!'";$params[]=$pattern;
        }return['sql'=>$parts?'('.implode(' '.strtoupper($builder['match_type']).' ',array_map(static fn($s)=>'('.$s.')',$parts)).')':'','params'=>$params,'builder'=>$builder];
    }
}
