<?php
declare(strict_types=1);
/** Native custom-field grammar, driven only by authorized persisted definitions. */
final class NativeCompanyInvoiceCustomRules
{
    public const TYPES=['input'=>'TextRule','number'=>'NumberRule','select'=>'SelectRule','multiselect'=>'MultiSelectRule','checkbox'=>'CheckboxRule','date_picker'=>'DateRule','date_picker_time'=>'DateRule'];
    public static function catalogue(array $definitions):array
    {
        $map=[];foreach($definitions as$d){
            if(!is_array($d)||($d['fieldto']??null)!=='invoice'||!is_string($d['slug']??null)||!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,190}$/D',$d['slug'])||isset(NativeCompanyInvoiceRules::TYPES[$d['slug']])||isset($map[$d['slug']]))throw new DomainException('native_company_invoice_filter_invalid');
            if(!isset(self::TYPES[$d['type']??'']))continue;
            $map[$d['slug']]=$d;
        }return$map;
    }
    private static function fail():never{throw new DomainException('native_company_invoice_filter_invalid');}
    public static function normalize(array $rule,array $definition,string $format):array
    {
        if(array_diff(array_keys($rule),['id','value','has_dynamic_value','operator','type'])||!array_key_exists('value',$rule)||($rule['id']??null)!==$definition['slug']||($rule['type']??null)!==self::TYPES[$definition['type']])self::fail();
        $type=$rule['type'];$operator=$rule['operator']??'equal';if($operator==='')$operator='equal';
        $dynamic=$rule['has_dynamic_value']??false;
        if(in_array($type,['NumberRule','DateRule'],true)){
            $base=$rule;$base['id']=$type==='NumberRule'?'total':'date';
            $normalized=NativeCompanyInvoiceRules::normalizeBuilder(['match_type'=>'and','rules'=>[$base]],$format)['rules'][0];$normalized['id']=$rule['id'];return$normalized;
        }
        if(!in_array($dynamic,[false,0,'0','false'],true))self::fail();
        $value=$rule['value'];
        if($type==='TextRule'){
            if(!in_array($operator,['equal','not_equal','begins_with','not_begins_with','contains','not_contains','ends_with','not_ends_with'],true)||!is_string($value)||strlen($value)>65535||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))self::fail();
        }else{
            $choices=array_values(array_filter(array_map('trim',explode(',',$definition['options']??'')),static fn($v)=>$v!==''));
            if($type==='SelectRule'){
                if(!in_array($operator,['equal','not_equal'],true)||!is_string($value)||!in_array($value,$choices,true))self::fail();
            }else{
                if(!in_array($operator,['in','not_in'],true)||!is_array($value)||!array_is_list($value)||!$value||count($value)>100)self::fail();
                foreach($value as$v)if(!is_string($v)||!in_array($v,$choices,true))self::fail();$value=array_values(array_unique($value));
            }
        }
        return ['id'=>$rule['id'],'value'=>$value,'has_dynamic_value'=>false,'operator'=>$operator,'type'=>$type];
    }
    /** Build a value predicate; expression keys and all values are bound independently. */
    public static function compile(array $rule,array $projection,string $today):array
    {
        $column=$projection['sql'];$bindings=$projection['params'];$type=$rule['type'];$op=$rule['operator'];$value=$rule['value'];
        if($rule['has_dynamic_value']){$value=is_array($value)?array_map(static fn($v)=>NativeCompanyInvoiceRules::dynamicDate($v,$today),$value):NativeCompanyInvoiceRules::dynamicDate($value,$today);if($op==='dynamic')$op='equal';}
        if($type==='NumberRule')$column='CAST('.$column.' AS DECIMAL(65,30))';
        if($type==='DateRule')$column='SUBSTR('.$column.',1,10)';
        if(in_array($type,['MultiSelectRule','CheckboxRule'],true)){
            $parts=[];$params=[];
            foreach($value as$v){$parts[]=$column." LIKE ? ESCAPE '!'";$params=array_merge($params,$bindings,['%'.strtr($v,['!'=>'!!','%'=>'!%','_'=>'!_']).'%']);}
            $sql='('.implode(' OR ',$parts).')';
            // Native option not_in means absence of a matching value, including no stored value.
            if($op==='not_in'){$sql='('.$column.' IS NULL OR NOT '.$sql.')';$params=array_merge($bindings,$params);}
            return ['sql'=>$sql,'params'=>$params];
        }
        if($type==='SelectRule'&&$op==='not_equal')return ['sql'=>'('.$column.' IS NULL OR '.$column.'<>?)','params'=>array_merge($bindings,$bindings,[$value])];
        if(in_array($op,['between','not_between'],true)){
            if($value[0]===$value[1])return ['sql'=>$column.'=?','params'=>array_merge($bindings,[$value[0]])];
            return ['sql'=>$column.($op==='between'?' BETWEEN':' NOT BETWEEN').' ? AND ?','params'=>array_merge($bindings,$value)];
        }
        if(in_array($op,['begins_with','not_begins_with','contains','not_contains','ends_with','not_ends_with'],true)){
            $needle=strtr($value,['!'=>'!!','%'=>'!%','_'=>'!_']);$positive=str_replace('not_','',$op);
            if($positive!=='begins_with')$needle='%'.$needle;if($positive!=='ends_with')$needle.='%';
            return ['sql'=>$column.(str_starts_with($op,'not_')?' NOT LIKE':' LIKE')." ? ESCAPE '!'",'params'=>array_merge($bindings,[$needle])];
        }
        $operator=['equal'=>'=','not_equal'=>'<>','less'=>'<','less_or_equal'=>'<=','greater'=>'>','greater_or_equal'=>'>='][$op]??null;if($operator===null)self::fail();
        return ['sql'=>$column.$operator.'?','params'=>array_merge($bindings,[$value])];
    }
}
