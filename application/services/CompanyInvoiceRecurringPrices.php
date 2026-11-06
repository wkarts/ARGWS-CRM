<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceRepricingSchema.php';

/** Verify every revision; choose only the payload effective for the occurrence being read. */
final class CompanyInvoiceRecurringPrices
{
    public static function json(array $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    public static function hash(array $value):string{return hash('sha256',self::json($value));}
    public static function lineHash(array $line):string{unset($line['rate'],$line['qty']);return self::hash($line);}
    public static function auditMetadata(array $row):array
    {
        return ['proposal_key'=>$row['proposal_key'],'effective_date'=>$row['effective_date'],'before_hash'=>$row['before_hash'],'payload_hash'=>$row['payload_hash']];
    }
    public static function payload($db,string $p,array $schedule,string $date):array
    {
        $base=json_decode($schedule['payload_json'],true,32,JSON_THROW_ON_ERROR);$issuer=(int)$schedule['issuer_id'];$id=(int)$schedule['id'];
        $tables=CompanyInvoiceRepricingSchema::availableTables($db,$p);
        if(!$tables){
            if(IssuerStorage::query($db,'SELECT id FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? LIMIT 1',[$issuer,$id,'invoice_reprice_applied'])->row_array())throw new RuntimeException('invoice_reprice_history_missing');
            return$base;
        }
        IssuerTransaction::assertOwned($db,$p,array_merge($tables,['issuers_audit']));
        $rows=IssuerStorage::query($db,'SELECT * FROM '.$p.'company_invoice_schedule_prices WHERE issuer_id=? AND schedule_id=? ORDER BY id FOR UPDATE',[$issuer,$id])->result_array();
        $audits=IssuerStorage::query($db,'SELECT id FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=?',[$issuer,$id,'invoice_reprice_applied'])->result_array();
        if(count($audits)!==count($rows))throw new RuntimeException('invoice_reprice_history_missing');
        $current=$base;$selected=$base;$lastDate='0000-00-00';
        foreach($rows as$row){
            $next=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($next)||!hash_equals($row['before_hash'],self::hash($current))||!hash_equals($row['payload_hash'],self::hash($next))||$row['effective_date']<$lastDate)throw new RuntimeException('invoice_reprice_history_corrupt');
            $oldOther=$current;$newOther=$next;unset($oldOther['items'],$newOther['items']);
            if($oldOther!==$newOther||!is_array($next['items']??null)||count($next['items'])<count($current['items']))throw new RuntimeException('invoice_reprice_history_corrupt');
            foreach($current['items']as$index=>$line)if(self::lineHash($line)!==self::lineHash($next['items'][$index]))throw new RuntimeException('invoice_reprice_history_corrupt');
            $proof=IssuerStorage::query($db,'SELECT id FROM '.$p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$issuer,$row['actor_id'],$id,'invoice_reprice_applied',self::json(self::auditMetadata($row))])->result_array();
            $proposal=IssuerStorage::query($db,'SELECT state FROM '.$p.'company_invoice_reprice_proposals WHERE issuer_id=? AND request_key=?',[$issuer,$row['proposal_key']])->row_array();
            if(count($proof)!==1||($proposal['state']??'')!=='applied')throw new RuntimeException('invoice_reprice_history_corrupt');
            $current=$next;$lastDate=$row['effective_date'];if($row['effective_date']<=$date)$selected=$next;
        }
        return$selected;
    }
    public static function occurrencePayload($db,string $p,array $schedule,string $date):array
    {
        $payload=self::payload($db,$p,$schedule,$date);
        $days=(int)(new DateTimeImmutable($payload['date'],new DateTimeZone('UTC')))->diff(new DateTimeImmutable($payload['due_date'],new DateTimeZone('UTC')))->format('%a');
        $payload['date']=$date;$payload['due_date']=(new DateTimeImmutable($date,new DateTimeZone('UTC')))->modify('+'.$days.' days')->format('Y-m-d');
        return$payload;
    }
}
