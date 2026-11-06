<?php
declare(strict_types=1);
namespace Asaas;
/** Official GET /payments subset. Canonical encoding prevents arbitrary paths/filters. */
final class PaymentListQuery
{
    public static function path(array $filters):string
    {
        if(array_diff(array_keys($filters),['offset','status','date_field','date_from','date_to']))throw new \DomainException('asaas_monitor_filters_invalid');
        $offset=$filters['offset']??'0';$status=$filters['status']??'all';$field=$filters['date_field']??'payment';
        if((!is_int($offset)&&!is_string($offset))||!preg_match('/^(?:0|[1-9][0-9]{0,5})$/D',(string)$offset)||!is_string($status)||!in_array($status,['all','PENDING','OVERDUE','CONFIRMED','RECEIVED','RECEIVED_IN_CASH'],true)||!is_string($field)||!isset(['payment'=>1,'due'=>1,'created'=>1][$field]))throw new \DomainException('asaas_monitor_filters_invalid');
        $query=['limit'=>50,'offset'=>(int)$offset];if($status!=='all')$query['status']=$status;
        $name=['payment'=>'paymentDate','due'=>'dueDate','created'=>'dateCreated'][$field];$dates=[];
        foreach(['date_from'=>'ge','date_to'=>'le'] as $key=>$operator){
            $value=$filters[$key]??'';if($value==='')continue;
            if(!is_string($value)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$value))throw new \DomainException('asaas_monitor_filters_invalid');
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new \DomainException('asaas_monitor_filters_invalid');
            $dates[$key]=$value;$query[$name.'['.$operator.']']=$value;
        }
        if(isset($dates['date_from'],$dates['date_to'])&&$dates['date_from']>$dates['date_to'])throw new \DomainException('asaas_monitor_filters_invalid');
        return 'payments?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
    }
    public static function allowed(string $path):bool
    {
        if(!str_starts_with($path,'payments?'))return false;
        parse_str(substr($path,9),$q);
        $f=['offset'=>$q['offset']??null];if(($q['limit']??null)!=='50')return false;
        if(isset($q['status']))$f['status']=$q['status'];
        $keys=['limit','offset','status'];$found=false;
        foreach(['paymentDate'=>'payment','dueDate'=>'due','dateCreated'=>'created'] as $key=>$value){
            if(!isset($q[$key]))continue;if($found||!is_array($q[$key])||array_diff(array_keys($q[$key]),['ge','le']))return false;$found=true;$keys[]=$key;$f['date_field']=$value;
            foreach(['ge'=>'date_from','le'=>'date_to'] as $op=>$dest)if(isset($q[$key][$op]))$f[$dest]=$q[$key][$op];
        }
        if(array_diff(array_keys($q),$keys))return false;
        try{return hash_equals(self::path($f),$path);}catch(\Throwable $e){return false;}
    }
}
