<?php
declare(strict_types=1);
namespace Asaas;
/** Minimum DTO: never exports raw provider payloads, customer data or external references. */
final class PaymentListResult
{
    public static function read(array $response,int $offset):array
    {
        $rows=$response['data']??null;$more=$response['hasMore']??null;$total=$response['totalCount']??null;
        if(!is_array($rows)||!array_is_list($rows)||count($rows)>50||!is_bool($more)||(!is_int($total)&&!is_string($total))||!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',(string)$total))throw new \DomainException('asaas_monitor_response_invalid');
        $total=(int)$total;$n=count($rows);
        if(($n>0&&$total<$offset+$n)||($more&&($n===0||$total<=$offset+$n))||(!$more&&$total>$offset+$n))throw new \DomainException('asaas_monitor_response_invalid');
        $seen=[];$items=[];
        foreach($rows as $row){
            if(!is_array($row)||!is_string($row['id']??null)||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$row['id'])||isset($seen[$row['id']]))throw new \DomainException('asaas_monitor_response_invalid');
            $seen[$row['id']]=true;$value=$row['value']??null;
            if((!is_int($value)&&!is_string($value))||!preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D',(string)$value,$m))throw new \DomainException('asaas_monitor_response_invalid');
            $status=$row['status']??null;$billing=$row['billingType']??null;
            $items[]=['remote_id'=>$row['id'],'status'=>in_array($status,['PENDING','OVERDUE','CONFIRMED','RECEIVED','RECEIVED_IN_CASH','REFUNDED','REFUND_REQUESTED','REFUND_IN_PROGRESS','CHARGEBACK_REQUESTED','CHARGEBACK_DISPUTE','AWAITING_CHARGEBACK_REVERSAL','DUNNING_REQUESTED','DUNNING_RECEIVED','AWAITING_RISK_ANALYSIS','AUTHORIZED'],true)?$status:'UNKNOWN',
                'billing_type'=>in_array($billing,['BOLETO','PIX','CREDIT_CARD','UNDEFINED'],true)?$billing:'UNKNOWN','value'=>$m[1].'.'.str_pad($m[2]??'',2,'0'),'origin_id'=>null,'invoice_id'=>null];
        }
        return ['items'=>$items,'offset'=>$offset,'next_offset'=>$more?$offset+$n:null,'total'=>$total];
    }
}
