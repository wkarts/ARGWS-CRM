<?php
declare(strict_types=1);
namespace Asaas;
/** Documented, bounded, read-only authorization queries; no caller-selected URL. */
final class AutomaticPixQuery
{
    public static function path(array $filters):string
    {
        if(array_diff(array_keys($filters),['offset','status','id']))throw new \DomainException('asaas_pix_filters_invalid');
        if(isset($filters['id'])){
            if(count($filters)!==1||!is_string($filters['id'])||!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$filters['id']))throw new \DomainException('asaas_pix_filters_invalid');
            return 'pix/automatic/authorizations/'.$filters['id'];
        }
        $offset=$filters['offset']??0;$status=$filters['status']??'all';
        if((!is_int($offset)&&!is_string($offset))||!preg_match('/^(?:0|[1-9][0-9]{0,5})$/D',(string)$offset)||!is_string($status)||!in_array($status,['all','CREATED','ACTIVE','CANCELLED','REFUSED','EXPIRED'],true))throw new \DomainException('asaas_pix_filters_invalid');
        $query=['limit'=>50,'offset'=>(int)$offset];if($status!=='all')$query['status']=$status;
        return 'pix/automatic/authorizations?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
    }
    public static function allowed(string $path):bool
    {
        if(preg_match('~^pix/automatic/authorizations/[A-Za-z0-9_-]{1,120}$~D',$path))return true;
        $prefix='pix/automatic/authorizations?';if(!str_starts_with($path,$prefix))return false;
        parse_str(substr($path,strlen($prefix)),$query);
        if(($query['limit']??null)!=='50'||array_diff(array_keys($query),['limit','offset','status']))return false;
        unset($query['limit']);try{return hash_equals(self::path($query),$path);}catch(\Throwable $e){return false;}
    }
    public static function item(array $row):array
    {
        $id=$row['id']??null;$status=$row['status']??null;$frequency=$row['frequency']??null;
        if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$id)||!is_string($status)||!is_string($frequency))throw new \DomainException('asaas_pix_response_invalid');
        // No customer, QR, contract, payer, bank or unrecognized remote fields leave this boundary.
        return ['remote_id'=>$id,'status'=>in_array($status,['CREATED','ACTIVE','CANCELLED','REFUSED','EXPIRED'],true)?$status:'UNKNOWN','frequency'=>in_array($frequency,['WEEKLY','MONTHLY','QUARTERLY','SEMIANNUALLY','ANNUALLY'],true)?$frequency:'UNKNOWN'];
    }
    public static function read(array $response,array $filters):array
    {
        if(isset($filters['id'])){
            $item=self::item($response);if(!hash_equals($filters['id'],$item['remote_id']))throw new \DomainException('asaas_pix_response_invalid');
            return ['items'=>[$item],'offset'=>0,'next_offset'=>null,'total'=>1];
        }
        $rows=$response['data']??null;$more=$response['hasMore']??null;$total=$response['totalCount']??null;$offset=(int)($filters['offset']??0);
        if(!is_array($rows)||!array_is_list($rows)||count($rows)>50||!is_bool($more)||(!is_int($total)&&!is_string($total))||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',(string)$total))throw new \DomainException('asaas_pix_response_invalid');
        $total=(int)$total;$n=count($rows);
        if(($n>0&&$total<$offset+$n)||($more&&($n===0||$total<=$offset+$n))||(!$more&&$total>$offset+$n))throw new \DomainException('asaas_pix_response_invalid');
        $seen=[];$items=[];
        foreach($rows as $row){
            if(!is_array($row))throw new \DomainException('asaas_pix_response_invalid');$item=self::item($row);
            if(isset($seen[$item['remote_id']]))throw new \DomainException('asaas_pix_response_invalid');$seen[$item['remote_id']]=true;$items[]=$item;
        }
        return ['items'=>$items,'offset'=>$offset,'next_offset'=>$more?$offset+$n:null,'total'=>$total];
    }
}
