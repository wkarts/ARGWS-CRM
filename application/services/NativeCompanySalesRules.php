<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceRules.php';

/** Exact 4.0.2 sales filter grammar; SQL names are fixed server-side and values are bound. */
final class NativeCompanySalesRules
{
    public static function types(string $kind):array
    {
        $common=['total'=>'NumberRule','subtotal'=>'NumberRule','date'=>'DateRule','signed'=>'BooleanRule',
            'status'=>'MultiSelectRule','year'=>'MultiSelectRule'];
        return match($kind){
            'estimates'=>['number'=>'NumberRule','reference_no'=>'TextRule']+$common+['expirydate'=>'DateRule',
                'sent'=>'BooleanRule','invoiced'=>'BooleanRule','sale_agent'=>'SelectRule'],
            'proposals'=>['subject'=>'TextRule']+$common+['open_till'=>'DateRule','expired'=>'BooleanRule',
                'rel_type'=>'SelectRule','assigned'=>'SelectRule'],
            default=>throw new DomainException('company_sales_invalid'),
        };
    }
    public static function custom(string $kind,array $definitions):array
    {
        $fieldto=$kind==='estimates'?'estimate':'proposal';$catalogue=[];
        foreach($definitions as $definition){
            $id=$definition['slug']??null;
            if(($definition['fieldto']??null)!==$fieldto||!is_string($id)
                ||!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,190}$/D',$id)||isset(self::types($kind)[$id])||isset($catalogue[$id])) self::fail();
            if(isset(NativeCompanyInvoiceCustomRules::TYPES[$definition['type']??'']))$catalogue[$id]=$definition;
        }
        return $catalogue;
    }
    private static function fail():never { throw new DomainException('native_sales_filter_invalid'); }
    public static function normalizeBuilder(string $kind,array $builder,string $format='Y-m-d|%Y-%m-%d',array $definitions=[]):array
    {
        if(count($builder)!==2||array_diff(array_keys($builder),['match_type','rules'])
            ||!in_array($builder['match_type'],['and','or'],true)||!is_array($builder['rules'])
            ||!array_is_list($builder['rules'])||count($builder['rules'])>100)self::fail();
        $types=self::types($kind);$custom=self::custom($kind,$definitions);$rules=[];
        foreach($builder['rules'] as $rule){
            if(!is_array($rule)||!is_string($rule['id']??null)) self::fail();
            $id=$rule['id'];
            if(isset($custom[$id])){$rules[]=NativeCompanyInvoiceCustomRules::normalize($rule,$custom[$id],$format);continue;}
            if(!isset($types[$id])||($rule['type']??null)!==$types[$id])self::fail();
            if($types[$id]==='TextRule'||$id==='rel_type'){
                $rules[]=NativeCompanyInvoiceCustomRules::normalize($rule,['slug'=>$id,
                    'type'=>$id==='rel_type'?'select':'input','options'=>'lead,customer'],$format);continue;
            }
            $base=$rule;
            $base['id']=match($id){'expirydate','open_till'=>'duedate','assigned'=>'sale_agent',
                'signed','invoiced','expired'=>'sent',default=>$id};
            $normalized=NativeCompanyInvoiceRules::normalizeBuilder(['match_type'=>'and','rules'=>[$base]],$format)['rules'][0];
            $normalized['id']=$id;
            if($id==='status'&&$kind==='estimates'&&in_array('6',$normalized['value'],true)) self::fail();
            $rules[]=$normalized;
        }
        return ['match_type'=>$builder['match_type'],'rules'=>$rules];
    }
    /** Alias sales is the directory's issuer-filtered, source-aware projection. */
    public static function compile(string $kind,array $builder,string $today,string $format,array $definitions=[],?callable $customProjection=null):array
    {
        $builder=self::normalizeBuilder($kind,$builder,$format,$definitions);$custom=self::custom($kind,$definitions);
        $today=NativeCompanyInvoiceRules::dateValue($today,'Y-m-d|%Y-%m-%d');$parts=[];$params=[];
        foreach($builder['rules'] as $rule){
            ['id'=>$id,'value'=>$value,'operator'=>$operator]=$rule;
            if(isset($custom[$id])){
                if($customProjection===null)self::fail();
                $compiled=NativeCompanyInvoiceCustomRules::compile($rule,$customProjection($custom[$id]),$today);
            }elseif($id==='sent'){
                $compiled=['sql'=>$value==='1'?'sales.sent=1':'(sales.sent=0 AND sales.status NOT IN (2,3,4))','params'=>[]];
            }elseif($id==='expired'){
                $compiled=['sql'=>'(sales.due_date IS NOT NULL AND sales.due_date'.($value==='1'?'<':'>').'? AND sales.status NOT IN(2,3))','params'=>[$today]];
            }elseif($id==='year'){
                $ranges=[];$args=[];foreach($value as $year){$ranges[]='(sales.date>=? AND sales.date<=?)';array_push($args,$year.'-01-01',$year.'-12-31');}
                $compiled=['sql'=>($operator==='not_in'?'NOT ':'').'('.implode(' OR ',$ranges).')','params'=>$args];
            }elseif($id==='status'){
                $compiled=['sql'=>'sales.status '.($operator==='in'?'IN':'NOT IN').' ('.implode(',',array_fill(0,count($value),'?')).')','params'=>$value];
            }else{
                $column=['number'=>'number','reference_no'=>'reference_no','subject'=>'subject','total'=>'total','subtotal'=>'subtotal',
                    'date'=>'date','expirydate'=>'due_date','open_till'=>'due_date','signed'=>'is_signed','invoiced'=>'invoiced',
                    'sale_agent'=>'agent','assigned'=>'agent','rel_type'=>'rel_type'][$id]??null;
                if($column===null)self::fail();$column='sales.'.$column;
                if(in_array($operator,['is_empty','is_not_empty'],true)){
                    $empty=in_array($id,['assigned','sale_agent'],true)?'('.$column.'=0 OR '.$column.' IS NULL)':'('.$column."='' OR ".$column.' IS NULL)';
                    $compiled=['sql'=>($operator==='is_not_empty'?'NOT ':'').$empty,'params'=>[]];
                }else $compiled=NativeCompanyInvoiceCustomRules::compile($rule,['sql'=>$column,'params'=>[]],$today);
            }
            $parts[]='('.$compiled['sql'].')';$params=array_merge($params,$compiled['params']);
        }
        return ['sql'=>$parts?'('.implode(' '.strtoupper($builder['match_type']).' ',$parts).')':'','params'=>$params,'builder'=>$builder];
    }
}
