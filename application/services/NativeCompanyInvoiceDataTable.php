<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceRead.php';
/** The native DataTables wire shape; client column names never become SQL identifiers. */
final class NativeCompanyInvoiceDataTable
{
    private const COLUMNS=['number','total','total_tax','year','date','customer','project','tags','due_date','status'];
    private static function fail():never { throw new DomainException('native_company_invoice_table_request_invalid'); }
    private static function flag($value):bool
    {
        if(!in_array($value,[true,false,'true','false',1,0,'1','0'],true))self::fail();
        return in_array($value,[true,'true',1,'1'],true);
    }
    /** Returns query plus authorized-relation candidates; caller must resolve scope before reading. */
    public static function request(array $input,$clientId='',array $customDefinitions=[]):array
    {
        if(array_diff(array_keys($input),['draw','start','length','search','columns','order','filters','last_order_identifier','project_id']))self::fail();
        $search=$input['search']??['value'=>'','regex'=>false];
        if(!is_array($search)||array_diff(array_keys($search),['value','regex'])||!is_string($search['value']??null)||self::flag($search['regex']??false))self::fail();
        if(isset($input['last_order_identifier'])&&!in_array($input['last_order_identifier'],['invoices','project_invoices'],true))self::fail();
        $columnMap=self::COLUMNS;$columnTypes=array_fill(0,count($columnMap),'');
        foreach($customDefinitions as$definition){
            if(!is_array($definition)||!isset($definition['id'],$definition['type'])||!preg_match('/^[1-9][0-9]{0,15}$/D',(string)$definition['id'])||(int)($definition['show_on_table']??0)!==1)self::fail();
            $columnMap[]='cf_'.$definition['id'];$columnTypes[]=$definition['type'];
        }
        $columns=$input['columns']??null;
        if(!is_array($columns)||!array_is_list($columns)||count($columns)!==count($columnMap))self::fail();
        foreach($columns as$i=>$column){
            if(!is_array($column)||array_diff(array_keys($column),['data','name','searchable','orderable','search']))self::fail();
            // DataTables uses ordinal cell data; names are metadata only and cannot select arbitrary fields.
            if((!is_int($column['data']??null)&&!is_string($column['data']??null))||(string)($column['data']??'')!==(string)$i||($column['name']??'')!=='')self::fail();
            foreach(['searchable','orderable']as$flag)self::flag($column[$flag]??false);
            $specific=$column['search']??['value'=>'','regex'=>false];
            if(!is_array($specific)||array_diff(array_keys($specific),['value','regex'])||($specific['value']??null)!==''||self::flag($specific['regex']??false))self::fail();
        }
        $orders=$input['order']??[['column'=>0,'dir'=>'desc']];
        if(!is_array($orders)||!array_is_list($orders)||!$orders||count($orders)>count($columnMap))self::fail();
        $normalizedOrders=[];
        foreach($orders as$order){
            if(!is_array($order)||array_diff(array_keys($order),['column','dir','type'])||(!is_int($order['column']??null)&&!is_string($order['column']??null))||!preg_match('/^(?:0|[1-9][0-9]{0,3})$/D',(string)($order['column']??''))||!isset($columnMap[(int)($order['column']??-1)])||!in_array($order['dir']??null,['asc','desc'],true))self::fail();
            if(isset($order['type'])&&$order['type']!==''&&$order['type']!==$columnTypes[(int)$order['column']])self::fail();
            $field=$columnMap[(int)$order['column']];
            $normalizedOrders[]=['field'=>$field,'direction'=>$order['dir']];
        }
        $query=NativeCompanyInvoiceRead::query([
            'draw'=>$input['draw']??0,'start'=>$input['start']??0,'length'=>$input['length']??25,
            'search'=>$search['value'],'order'=>$normalizedOrders[0]['field'],'direction'=>$normalizedOrders[0]['direction'],'orders'=>$normalizedOrders,
            'filters'=>$input['filters']??['match_type'=>'and','rules'=>[]],
        ]);
        $scope=[];
        foreach(['customer_id'=>$clientId,'project_id'=>$input['project_id']??'']as$key=>$value){
            if($value===''||$value===null)continue;
            if((!is_int($value)&&!is_string($value))||!preg_match('/^[1-9][0-9]{0,15}$/D',(string)$value)||strlen((string)$value)>strlen((string)PHP_INT_MAX)||(int)$value<=0)self::fail();
            NativeCompanyResourceId::decode($key==='customer_id'?'client':'project',$value);$scope[$key]=(int)$value;
        }
        if(count($scope)>1)self::fail();
        return ['query'=>$query,'scope'=>$scope];
    }
    public static function response(array $page,array $rows):array
    {
        foreach(['draw','recordsTotal','recordsFiltered']as$key)if(!is_int($page[$key]??null)||$page[$key]<0)self::fail();
        if(!array_is_list($rows)||count($rows)!==count($page['documents']??[]))self::fail();
        return ['draw'=>$page['draw'],'iTotalRecords'=>$page['recordsTotal'],'iTotalDisplayRecords'=>$page['recordsFiltered'],'aaData'=>$rows];
    }
}
