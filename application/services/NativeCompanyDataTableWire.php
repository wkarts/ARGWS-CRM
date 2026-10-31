<?php
declare(strict_types=1);
/** Native DataTables transport only. Resource filters, row authorization and SQL stay with each provider. */
final class NativeCompanyDataTableWire
{
    private static function fail():never{throw new DomainException('native_company_table_request_invalid');}
    private static function integer($v,bool $all=false):int
    {
        if($all&&($v===-1||$v==='-1'))return -1;
        if((!is_string($v)&&!is_int($v))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$v)||strlen((string)$v)>strlen((string)PHP_INT_MAX)||(strlen((string)$v)===strlen((string)PHP_INT_MAX)&&strcmp((string)$v,(string)PHP_INT_MAX)>0))self::fail();return(int)$v;
    }
    private static function flag($v):bool{if(!in_array($v,[true,false,1,0,'1','0','true','false'],true))self::fail();return in_array($v,[true,1,'1','true'],true);}
    public static function request(array $input,array $fields,array $types=[]):array
    {
        if(!$fields||!array_is_list($fields)||count(array_unique($fields))!==count($fields))self::fail();foreach($fields as$field)if(!is_string($field)||!preg_match('/^[a-z][a-z0-9_]*$/D',$field))self::fail();
        if(!$types)$types=array_fill(0,count($fields),'');if(!array_is_list($types)||count($types)!==count($fields))self::fail();
        if(array_diff(array_keys($input),['draw','start','length','search','columns','order']))self::fail();
        $search=$input['search']??['value'=>'','regex'=>false];if(!is_array($search)||array_diff(array_keys($search),['value','regex'])||!is_string($search['value']??null)||preg_match('//u',$search['value'])!==1||mb_strlen($search['value'])>191||str_contains($search['value'],"\0")||self::flag($search['regex']??false))self::fail();
        $columns=$input['columns']??null;if(!is_array($columns)||!array_is_list($columns)||count($columns)!==count($fields))self::fail();
        foreach($columns as$i=>$column){
            if(!is_array($column)||array_diff(array_keys($column),['data','name','searchable','orderable','search'])||(!is_int($column['data']??null)&&!is_string($column['data']??null))||(string)$column['data']!==(string)$i||($column['name']??'')!=='')self::fail();
            self::flag($column['searchable']??false);self::flag($column['orderable']??false);$specific=$column['search']??['value'=>'','regex'=>false];if(!is_array($specific)||array_diff(array_keys($specific),['value','regex'])||($specific['value']??null)!==''||self::flag($specific['regex']??false))self::fail();
        }
        $orders=$input['order']??[['column'=>0,'dir'=>'asc']];if(!is_array($orders)||!array_is_list($orders)||!$orders||count($orders)>count($fields))self::fail();$out=[];$seen=[];
        foreach($orders as$order){if(!is_array($order)||array_diff(array_keys($order),['column','dir','type']))self::fail();$i=self::integer($order['column']??null);if(!isset($fields[$i])||isset($seen[$i])||!in_array($order['dir']??null,['asc','desc'],true)||(isset($order['type'])&&$order['type']!==''&&$order['type']!==$types[$i]))self::fail();$seen[$i]=true;$out[]=['field'=>$fields[$i],'direction'=>$order['dir']];}
        $length=self::integer($input['length']??25,true);if($length===0)self::fail();$start=self::integer($input['start']??0);
        return ['draw'=>self::integer($input['draw']??0),'start'=>$length===-1?0:$start,'length'=>$length,'search'=>$search['value'],'orders'=>$out];
    }
}
