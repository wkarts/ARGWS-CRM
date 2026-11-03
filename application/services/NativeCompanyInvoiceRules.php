<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsFormats.php';
require_once __DIR__.'/NativeCompanyInvoiceCustomRules.php';
/** Closed native invoice rule DTO grammar. It never runs callbacks or interpolates client SQL. */
final class NativeCompanyInvoiceRules
{
    public const TYPES=['number'=>'NumberRule','total'=>'NumberRule','subtotal'=>'NumberRule','date'=>'DateRule','duedate'=>'DateRule',
        'sent'=>'BooleanRule','sale_agent'=>'SelectRule','status'=>'MultiSelectRule','year'=>'MultiSelectRule','recurring'=>'BooleanRule',
        'not_have_payment'=>'BooleanRule','made_payment_by'=>'MultiSelectRule'];
    private static function fail():never{throw new DomainException('native_company_invoice_filter_invalid');}
    public static function operators(string $id):array
    {
        return match(self::TYPES[$id]??null){
            'NumberRule'=>['equal','not_equal','between','not_between','less','less_or_equal','greater','greater_or_equal'],
            'DateRule'=>array_merge(['equal','not_equal','between','not_between','less','less_or_equal','greater','greater_or_equal','dynamic'],$id==='duedate'?['is_empty','is_not_empty']:[]),
            'BooleanRule'=>['equal'], 'SelectRule'=>['equal','not_equal','is_empty','is_not_empty'],
            'MultiSelectRule'=>['in','not_in'],default=>throw new DomainException('native_company_invoice_filter_invalid'),
        };
    }
    private static function flag($value):bool
    {
        if(!in_array($value,[true,false,1,0,'1','0','true','false'],true))self::fail();
        return in_array($value,[true,1,'1','true'],true);
    }
    private static function number($value):string
    {
        if(is_float($value)){
            if(!is_finite($value))self::fail();
            $value=json_encode($value,JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
            if(stripos($value,'e')!==false){
                if(!preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?e([+-]?[0-9]+)$/iD',$value,$m)||abs((int)$m[4])>6)self::fail();
                $digits=$m[2].($m[3]??'');$point=strlen($m[2])+(int)$m[4];
                $value=$m[1].($point<=0?'0.'.str_repeat('0',-$point).$digits:($point>=strlen($digits)?$digits.str_repeat('0',$point-strlen($digits)):substr($digits,0,$point).'.'.substr($digits,$point)));
            }
        }
        if(!is_int($value)&&!is_string($value))self::fail();$value=(string)$value;
        if(strlen($value)>40)self::fail();
        if(preg_match('/^-?[0-9]+\.[0-9]+$/D',$value))$value=rtrim(rtrim($value,'0'),'.');
        if(!preg_match('/^-?(?:0|[1-9][0-9]{0,14})(?:\.[0-9]{1,6})?$/D',$value))self::fail();
        if(str_contains($value,'.'))$value=rtrim(rtrim($value,'0'),'.');
        return $value==='-0'?'0':$value;
    }
    public static function dateValue($value,string $format):string
    {
        if(!is_string($value)||strlen($value)>10||!isset(CompanySettingsFormats::dates()[$format]))self::fail();
        $style=preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D',$value)?'Y-m-d':CompanySettingsFormats::dates()[$format];
        $date=DateTimeImmutable::createFromFormat('!'.$style,$value,new DateTimeZone('UTC'));
        if(!$date||$date->format($style)!==$value||(int)$date->format('Y')<1000)self::fail();
        return $date->format('Y-m-d');
    }
    public static function dynamicDate($value,string $today):string
    {
        if(!is_string($value)||trim($value)===''||strlen($value)>100||preg_match('/[\x00-\x1f\x7f]/',$value))self::fail();
        $today=self::dateValue($today,'Y-m-d|%Y-%m-%d');
        // DateTime relative-date parsing is data interpretation only, never PHP evaluation or SQL.
        try{$date=@(new DateTimeImmutable($today.' 00:00:00',new DateTimeZone('UTC')))->modify(trim($value));}
        catch(Throwable $invalid){self::fail();}
        if(!$date||(int)$date->format('Y')<1000||(int)$date->format('Y')>9999)self::fail();
        return $date->format('Y-m-d');
    }
    public static function normalizeBuilder(array $builder,string $format='Y-m-d|%Y-%m-%d',array $customDefinitions=[]):array
    {
        if(count($builder)!==2||array_diff(array_keys($builder),['match_type','rules'])||!in_array($builder['match_type'],['and','or'],true)
            ||!is_array($builder['rules'])||!array_is_list($builder['rules'])||count($builder['rules'])>100)self::fail();
        $custom=NativeCompanyInvoiceCustomRules::catalogue($customDefinitions);
        $rules=[];
        foreach($builder['rules']as$rule){
            if(is_array($rule)&&is_string($rule['id']??null)&&isset($custom[$rule['id']])){$rules[]=NativeCompanyInvoiceCustomRules::normalize($rule,$custom[$rule['id']],$format);continue;}
            if(!is_array($rule)||array_diff(array_keys($rule),['id','value','has_dynamic_value','operator','type'])
                ||!is_string($rule['id']??null)||!isset(self::TYPES[$rule['id']])||($rule['type']??null)!==self::TYPES[$rule['id']]||!array_key_exists('value',$rule))self::fail();
            $id=$rule['id'];$type=self::TYPES[$id];$operator=$rule['operator']??'equal';
            if($operator==='')$operator='equal';
            if(!is_string($operator)||!in_array($operator,self::operators($id),true))self::fail();
            $dynamic=self::flag($rule['has_dynamic_value']??false);
            if($operator==='dynamic')$dynamic=true;
            if($dynamic&&($type!=='DateRule'||!in_array($operator,['dynamic','between','not_between'],true)))self::fail();
            $value=$rule['value'];
            if(in_array($operator,['is_empty','is_not_empty'],true)){$value=null;$dynamic=false;}
            elseif($type==='NumberRule'||$type==='DateRule'){
                $range=in_array($operator,['between','not_between'],true);
                if($range&&(!is_array($value)||!array_is_list($value)||count($value)!==2))self::fail();
                $values=$range?$value:[$value];$values=array_map(static function($v)use($type,$dynamic,$format){
                    if($type==='NumberRule')return self::number($v);
                    if($dynamic){self::dynamicDate($v,'2026-01-15');return trim($v);}
                    return self::dateValue($v,$format);
                },$values);$value=$range?$values:$values[0];
            }elseif($type==='BooleanRule'){
                if(!in_array($value,[0,1,'0','1',true,false],true))self::fail();$value=in_array($value,[1,'1',true],true)?'1':'0';
            }elseif($type==='SelectRule'){
                if((!is_int($value)&&!is_string($value))||!preg_match('/^(?:0|[1-9][0-9]{0,8})$/D',(string)$value))self::fail();$value=(string)$value;
            }else{
                if(!is_array($value)||!array_is_list($value)||!$value||count($value)>100)self::fail();
                $value=array_values(array_unique(array_map(static function($v)use($id){
                    if(!is_int($v)&&!is_string($v))self::fail();$v=(string)$v;
                    if($id==='status'&&!preg_match('/^[1-6]$/D',$v))self::fail();
                    if($id==='year'&&!preg_match('/^[1-9][0-9]{3}$/D',$v))self::fail();
                    if($id==='made_payment_by'&&!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$v))self::fail();return $v;
                },$value)));
            }
            $rules[]=['id'=>$id,'value'=>$value,'has_dynamic_value'=>$dynamic,'operator'=>$operator,'type'=>$type];
        }
        return ['match_type'=>$builder['match_type'],'rules'=>$rules];
    }
    /** Fixed SQL fragments for alias i. Prefix/lineage are server-owned, all rule values are bound. */
    public static function compile(array $builder,string $prefix,string $lineage,string $today,string $format='Y-m-d|%Y-%m-%d',?NativeCompanyInvoiceCustomColumns $customColumns=null):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||!in_array($lineage,['named','legacy','draft'],true))self::fail();
        $definitions=$customColumns?$customColumns->definitions():[];$custom=NativeCompanyInvoiceCustomRules::catalogue($definitions);
        $builder=self::normalizeBuilder($builder,$format,$definitions);$parts=[];$params=[];$dependencies=[];
        foreach($builder['rules']as$rule){
            ['id'=>$id,'value'=>$value,'operator'=>$operator,'has_dynamic_value'=>$dynamic]=$rule;
            if(isset($custom[$id])){$compiled=NativeCompanyInvoiceCustomRules::compile($rule,$customColumns->expression($custom[$id]['id'],$lineage==='draft'?'company_invoice_draft':'invoice'),$today);$parts[]=$compiled['sql'];$params=array_merge($params,$compiled['params']);$dependencies[]='custom_fields';continue;}
            if($dynamic){$value=is_array($value)?array_map(static fn($v)=>self::dynamicDate($v,$today),$value):self::dynamicDate($value,$today);if($operator==='dynamic')$operator='equal';}
            if(in_array($id,['sent','recurring','not_have_payment'],true)){
                if($id==='sent')$parts[]=$value==='1'?'(i.sent=1)':'(i.sent=0 AND i.status NOT IN (2,5))';
                elseif($id==='not_have_payment'){
                    $exists='(EXISTS (SELECT 1 FROM '.$prefix.'invoicepaymentrecords fp WHERE fp.invoiceid=i.id) OR EXISTS (SELECT 1 FROM '.$prefix.'credits fc WHERE fc.invoice_id=i.id))';
                    $parts[]='(i.status<>5 AND '.($value==='1'?'NOT ':'').$exists.')';
                }elseif($lineage==='draft')$parts[]=$value==='1'?'(1=0)':'(1=1)';
                elseif($lineage==='legacy')$parts[]=$value==='1'?'(i.recurring>0)':'(i.recurring=0)';
                else{
                    $dependencies[]='recurrence';
                    $exists='EXISTS (SELECT 1 FROM '.$prefix.'company_invoice_schedule_modes fm JOIN '.$prefix.'company_invoice_schedules fs ON fs.id=fm.schedule_id AND fs.issuer_id=fm.issuer_id WHERE fm.issuer_id=i.issuer_id AND fm.principal_invoice_id=i.id)';
                    $parts[]='('.($value==='1'?'':'NOT ').$exists.')';
                }
                continue;
            }
            if($id==='made_payment_by'){
                $parts[]='EXISTS (SELECT 1 FROM '.$prefix.'invoicepaymentrecords fp WHERE fp.invoiceid=i.id AND fp.paymentmode '.($operator==='in'?'IN':'NOT IN').' ('.implode(',',array_fill(0,count($value),'?')).'))';
                $params=array_merge($params,$value);continue;
            }
            if($id==='year'){
                $ranges=[];foreach($value as$year){$ranges[]='(i.date>=? AND i.date<=?)';$params[]=$year.'-01-01';$params[]=$year.'-12-31';}
                $parts[]='('.($operator==='not_in'?'NOT ':'').'('.implode(' OR ',$ranges).'))';continue;
            }
            $column=['number'=>'i.number','total'=>'i.total','subtotal'=>'i.subtotal','date'=>'i.date','duedate'=>'i.duedate','sale_agent'=>'i.sale_agent','status'=>'i.status'][$id]??null;
            if($column===null)self::fail();
            if($id==='sale_agent')$dependencies[]='sale_agents';
            if(in_array($operator,['is_empty','is_not_empty'],true)){
                $empty=$id==='sale_agent'?'('.$column.'=0 OR '.$column.' IS NULL)':'('.$column."='' OR ".$column.' IS NULL)';
                $parts[]=($operator==='is_not_empty'?'NOT ':'').$empty;continue;
            }
            if(in_array($operator,['in','not_in'],true)){
                $parts[]=$column.' '.($operator==='in'?'IN':'NOT IN').' ('.implode(',',array_fill(0,count($value),'?')).')';$params=array_merge($params,$value);continue;
            }
            if(in_array($operator,['between','not_between'],true)){
                // Preserve native App_table's same-endpoint range behavior, including its existing not_between quirk.
                if($value[0]===$value[1]){$parts[]=$column.'=?';$params[]=$value[0];}
                else{$parts[]=$column.' '.($operator==='between'?'BETWEEN':'NOT BETWEEN').' ? AND ?';$params=array_merge($params,$value);}continue;
            }
            $sqlOperator=['equal'=>'=','not_equal'=>'<>','less'=>'<','less_or_equal'=>'<=','greater'=>'>','greater_or_equal'=>'>='][$operator]??null;
            if($sqlOperator===null)self::fail();$parts[]=$column.$sqlOperator.'?';$params[]=$value;
        }
        return ['sql'=>$parts?'('.implode(' '.strtoupper($builder['match_type']).' ',array_map(static fn($part)=>'('.$part.')',$parts)).')':'',
            'params'=>$params,'dependencies'=>array_values(array_unique($dependencies)),'builder'=>$builder];
    }

}
