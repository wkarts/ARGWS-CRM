<?php
declare(strict_types=1);

require_once __DIR__.'/NativeCompanyInvoiceRules.php';
require_once __DIR__.'/NativeCompanyResourceId.php';

/** The original contracts filter vocabulary, compiled only against a bounded projection. */
final class NativeCompanyContractRules
{
    public const TYPES = [
        'subject'=>'TextRule', 'datestart'=>'DateRule', 'dateend'=>'DateRule',
        'contract_value'=>'NumberRule', 'trash'=>'BooleanRule', 'signed'=>'BooleanRule',
        'marked_as_signed'=>'BooleanRule', 'expired'=>'BooleanRule',
        'contract_type'=>'MultiSelectRule', 'year'=>'MultiSelectRule',
    ];

    private static function fail(): never { throw new DomainException('native_contract_filter_invalid'); }

    public static function normalizeBuilder(array $builder, string $format='Y-m-d|%Y-%m-%d'): array
    {
        if (array_diff(array_keys($builder), ['match_type','rules']) || count($builder)!==2
            || !in_array($builder['match_type']??null, ['and','or'], true)
            || !is_array($builder['rules']??null) || !array_is_list($builder['rules']) || count($builder['rules'])>100) self::fail();
        $rules=[];
        foreach ($builder['rules'] as $rule) {
            if (!is_array($rule) || array_diff(array_keys($rule), ['id','type','operator','value','has_dynamic_value'])
                || !isset(self::TYPES[$rule['id']??'']) || ($rule['type']??null)!==self::TYPES[$rule['id']]
                || !array_key_exists('value',$rule)) self::fail();
            $id=$rule['id']; $type=$rule['type']; $op=$rule['operator']??'equal'; if ($op==='') $op='equal';
            $ops=match($type) {
                'TextRule'=>['equal','not_equal','begins_with','not_begins_with','contains','not_contains','ends_with','not_ends_with'],
                'DateRule'=>array_merge(['equal','not_equal','between','not_between','less','less_or_equal','greater','greater_or_equal','dynamic'], $id==='dateend'?['is_empty','is_not_empty']:[]),
                'NumberRule'=>['equal','not_equal','between','not_between','less','less_or_equal','greater','greater_or_equal'],
                'BooleanRule'=>['equal'], 'MultiSelectRule'=>['in','not_in'],
            };
            if (!in_array($op,$ops,true)) self::fail();
            $dynamic=$rule['has_dynamic_value']??false;
            if (!in_array($dynamic,[true,false,0,1,'0','1','true','false'],true)) self::fail();
            $dynamic=in_array($dynamic,[true,1,'1','true'],true)||$op==='dynamic';
            if ($dynamic && ($type!=='DateRule'||!in_array($op,['dynamic','between','not_between'],true))) self::fail();
            $value=$rule['value'];
            if (in_array($op,['is_empty','is_not_empty'],true)) { $value=null; $dynamic=false; }
            elseif ($type==='TextRule') {
                if (!is_string($value)||mb_strlen($value)>500||preg_match('//u',$value)!==1||preg_match('/[\x00-\x1f\x7f]/',$value)) self::fail();
            } elseif ($type==='BooleanRule') {
                if (!in_array($value,[true,false,0,1,'0','1'],true)) self::fail();
                $value=in_array($value,[true,1,'1'],true)?'1':'0';
            } elseif ($type==='MultiSelectRule') {
                if (!is_array($value)||!array_is_list($value)||!$value||count($value)>500) self::fail();
                $value=array_values(array_unique(array_map(static function($v) use ($id) {
                    if (!is_string($v)&&!is_int($v)) self::fail();
                    if ($id==='year') { if (!preg_match('/^[1-9][0-9]{3}$/D',(string)$v)) self::fail(); }
                    else { NativeCompanyResourceId::decode('contract_type',$v); }
                    return (string)$v;
                },$value)));
            } else {
                $range=in_array($op,['between','not_between'],true);
                if ($range&&(!is_array($value)||!array_is_list($value)||count($value)!==2)) self::fail();
                $values=$range?$value:[$value];
                $values=array_map(static function($v) use ($type,$format,$dynamic) {
                    if ($type==='DateRule') {
                        if ($dynamic) { NativeCompanyInvoiceRules::dynamicDate($v,'2026-01-15'); return $v; }
                        return NativeCompanyInvoiceRules::dateValue($v,$format);
                    }
                    return NativeCompanyInvoiceRules::normalizeBuilder(['match_type'=>'and','rules'=>[['id'=>'total','type'=>'NumberRule','operator'=>'equal','value'=>$v]]])['rules'][0]['value'];
                },$values);
                $value=$range?$values:$values[0];
            }
            $rules[]=['id'=>$id,'type'=>$type,'operator'=>$op,'value'=>$value,'has_dynamic_value'=>$dynamic];
        }
        return ['match_type'=>$builder['match_type'],'rules'=>$rules];
    }

    public static function compile(array $builder, string $today, string $format='Y-m-d|%Y-%m-%d'): array
    {
        NativeCompanyInvoiceRules::dateValue($today,'Y-m-d|%Y-%m-%d');
        $builder=self::normalizeBuilder($builder,$format); $parts=[]; $params=[];
        foreach ($builder['rules'] as $rule) {
            ['id'=>$id,'operator'=>$op,'value'=>$value,'has_dynamic_value'=>$dynamic]=$rule;
            if ($dynamic) {
                $value=is_array($value)?array_map(static fn($v)=>NativeCompanyInvoiceRules::dynamicDate($v,$today),$value):NativeCompanyInvoiceRules::dynamicDate($value,$today);
                if ($op==='dynamic') $op='equal';
            }
            $column=$rule['type']==='BooleanRule'?'CAST(q.'.$id.' AS SIGNED)':'q.'.$id;
            if ($id==='expired') {
                // Same date predicates as the 4.0.2 rule: today is neither future nor expired.
                $parts[]='(q.dateend IS NOT NULL AND q.dateend'.($value==='1'?'<':'>').'? AND q.trash=0)'; $params[]=$today; continue;
            }
            if (in_array($op,['is_empty','is_not_empty'],true)) { $parts[]=$column.($op==='is_empty'?' IS NULL':' IS NOT NULL'); continue; }
            if (in_array($op,['in','not_in'],true)) { $parts[]=$column.($op==='in'?' IN ':' NOT IN ').'('.implode(',',array_fill(0,count($value),'?')).')'; $params=array_merge($params,$value); continue; }
            if (in_array($op,['between','not_between'],true)) { $parts[]=$column.($op==='between'?' BETWEEN ':' NOT BETWEEN ').'? AND ?'; $params=array_merge($params,$value); continue; }
            $operators=['equal'=>'=','not_equal'=>'<>','less'=>'<','less_or_equal'=>'<=','greater'=>'>','greater_or_equal'=>'>='];
            if (isset($operators[$op])) { $parts[]=$column.$operators[$op].'?'; $params[]=$value; continue; }
            $value=str_replace(['!','%','_'],['!!','!%','!_'],$value); $base=str_starts_with($op,'not_')?substr($op,4):$op;
            $value=match($base) { 'begins_with'=>$value.'%', 'ends_with'=>'%'.$value, 'contains'=>'%'.$value.'%', default=>throw new DomainException('native_contract_filter_invalid') };
            $parts[]=$column.(str_starts_with($op,'not_')?' NOT LIKE ':' LIKE ')."? ESCAPE '!'"; $params[]=$value;
        }
        return ['sql'=>$parts?'('.implode(' '.strtoupper($builder['match_type']).' ',array_map(static fn($s)=>'('.$s.')',$parts)).')':'','params'=>$params,'builder'=>$builder];
    }
}
