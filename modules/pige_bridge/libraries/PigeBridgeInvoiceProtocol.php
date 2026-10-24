<?php
declare(strict_types=1);
require_once __DIR__.'/PigeBridgeProtocol.php';
/** Pure versioned invoice contract. Local invoice != external charge or received payment. */
final class PigeBridgeInvoiceProtocol
{
    public const PATH='/pige_bridge/api/v2/invoices';
    public const STATUS_PATH='/pige_bridge/api/v2/invoices/status';
    private const FLAGS=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS|JSON_THROW_ON_ERROR;
    private static function exact(array $v,array $keys):void{$actual=array_keys($v);sort($actual);sort($keys);if($actual!==$keys)throw new InvalidArgumentException('invoice_fields_invalid');}
    private static function envelope(string $body,bool $lookup):array
    {
        if(strlen($body)>PigeBridgeProtocol::MAX_BODY_BYTES)throw new InvalidArgumentException('body_too_large');
        $event=json_decode($body,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($event)||($event['schema_version']??null)!==2||($event['event_type']??null)!==($lookup?'invoice.status.requested':'invoice.requested')||!is_array($event['data']??null)||json_encode($event,self::FLAGS)!==$body)throw new InvalidArgumentException('invoice_envelope_invalid');
        self::exact($event,['schema_version','event_id','event_type','scope','data']);
        self::exact($event['data'],$lookup?['charge_id']:['charge_id','client_id','amount_cents','currency','due_date','description']);
        $probe=$event;$probe['schema_version']=1;$probe['event_type']='charge.requested';
        if($lookup)$probe['data']=['charge_id'=>$event['data']['charge_id'],'amount_cents'=>1,'currency'=>'BRL','due_date'=>'2030-01-01','description'=>'Invoice status'];
        else{if(!is_int($event['data']['client_id'])||$event['data']['client_id']<1||$event['data']['client_id']>2147483647)throw new InvalidArgumentException('invoice_client_invalid');unset($probe['data']['client_id']);}
        PigeBridgeProtocol::decode(json_encode($probe,self::FLAGS));return $event;
    }
    public static function decode(string $body):array{return self::envelope($body,false);}
    public static function decodeStatus(string $body):array{return self::envelope($body,true);}
    public static function semanticHash(array $event):string
    {
        $event=self::decode(json_encode($event,self::FLAGS));$scope=[];$data=[];
        foreach(['school_id','issuer_id','account_id','environment'] as $key)$scope[$key]=$event['scope'][$key];
        foreach(['charge_id','client_id','amount_cents','currency','due_date','description'] as $key)$data[$key]=$event['data'][$key];
        return hash('sha256',json_encode(['schema_version'=>2,'event_id'=>$event['event_id'],'event_type'=>$event['event_type'],'scope'=>$scope,'data'=>$data],self::FLAGS));
    }
}
