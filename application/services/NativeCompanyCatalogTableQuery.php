<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFields.php';

/** Fixed SQL fragments for the original item columns; callers retain their reader authority. */
final class NativeCompanyCatalogTableQuery
{
    public const FIELDS=['id','description','long_description','rate','taxrate','taxrate_2','unit','group_name'];

    public static function plan($db,string $p,int $issuer,int $actor,bool $legacy,int $start,int $length,string $query,array $orders=[],array $definitions=[]):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||$start<0||$length<1||$length>500
            ||preg_match('//u',$query)!==1||mb_strlen($query)>191||str_contains($query,"\0"))throw new DomainException('company_catalog_invalid');
        if($definitions&&(new NativeCompanyCustomFields($db,$p))->definitions($issuer,$actor,'items',['active'=>1])!==$definitions)throw new DomainException('company_catalog_columns_changed');
        $tables=$legacy?['items','taxes','items_groups']:['company_items','company_taxes','company_item_groups'];
        $owner=$legacy?'':' AND %s.issuer_id=i.issuer_id';
        $from=' FROM '.$p.$tables[0].' i LEFT JOIN '.$p.$tables[1].' t ON t.id=i.tax'.sprintf($owner,'t')
            .' LEFT JOIN '.$p.$tables[1].' t2 ON t2.id=i.tax2'.sprintf($owner,'t2')
            .' LEFT JOIN '.$p.$tables[2].' g ON g.id=i.group_id'.sprintf($owner,'g');
        $where=$legacy?'1=1':'i.issuer_id=?';$args=$legacy?[]:[$issuer];
        $expressions=[];
        foreach(['id'=>'i.id','description'=>'i.description','long_description'=>'i.long_description','rate'=>'CAST(i.rate AS DECIMAL(15,4))',
            'taxrate'=>'CAST(t.taxrate AS DECIMAL(15,4))','taxrate_2'=>'CAST(t2.taxrate AS DECIMAL(15,4))','unit'=>'i.unit','group_name'=>'g.name'] as $field=>$sql)$expressions[$field]=['sql'=>$sql,'params'=>[],'type'=>''];
        foreach($definitions as $definition){
            $params=[$issuer,$definition['field_source_kind'],$definition['source_field_id'],$legacy?'item':'company_item'];
            $sql='(SELECT cv.value FROM '.$p.'company_custom_field_values cv WHERE cv.issuer_id=? AND cv.field_source_kind=? AND cv.field_id=? AND cv.source_kind=? AND cv.source_id=i.id)';
            if($legacy&&$definition['field_source_kind']==='custom_field'){
                $sql='COALESCE('.$sql.',(SELECT lv.value FROM '.$p.'customfieldsvalues lv WHERE lv.fieldid=? AND lv.fieldto=? AND lv.relid=i.id))';
                $params[]=$definition['source_field_id'];$params[]='items_pr';
            }
            $expressions['custom_field_'.$definition['id']]=['sql'=>$sql,'params'=>$params,'type'=>$definition['type']];
        }
        $filtered=$where;$filteredArgs=$args;
        if($query!==''){
            $needle='%'.strtr($query,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$search=[];
            foreach($expressions as $field=>$expression){
                if($field==='id')continue;
                $search[]='CAST('.$expression['sql']." AS CHAR) LIKE ? ESCAPE '!'";
                $filteredArgs=array_merge($filteredArgs,$expression['params'],[$needle]);
            }
            $filtered.=' AND ('.implode(' OR ',$search).')';
        }
        if(!$orders)$orders=[['field'=>'description','direction'=>'asc']];
        if(!array_is_list($orders)||count($orders)>count($expressions))throw new DomainException('company_catalog_invalid');
        $sort=[];$sortArgs=[];$seen=[];
        foreach($orders as $order){
            $field=$order['field']??null;$direction=$order['direction']??null;
            if(!is_string($field)||!isset($expressions[$field])||isset($seen[$field])||!in_array($direction,['asc','desc'],true))throw new DomainException('company_catalog_invalid');
            $seen[$field]=true;$expression=$expressions[$field];$sql=$expression['sql'];
            if($expression['type']==='number')$sql='CAST('.$sql.' AS DECIMAL(65,10))';
            $sort[]=$sql.' '.strtoupper($direction);$sortArgs=array_merge($sortArgs,$expression['params']);
        }
        if(!isset($seen['id']))$sort[]='i.id ASC';
        return ['total'=>'SELECT COUNT(*) AS n'.$from.' WHERE '.$where,'total_args'=>$args,
            'filtered'=>'SELECT COUNT(*) AS n'.$from.' WHERE '.$filtered,'filtered_args'=>$filteredArgs,
            'ids'=>'SELECT i.id'.$from.' WHERE '.$filtered.' ORDER BY '.implode(',',$sort).' LIMIT '.$length.' OFFSET '.$start,
            'ids_args'=>array_merge($filteredArgs,$sortArgs)];
    }
}
