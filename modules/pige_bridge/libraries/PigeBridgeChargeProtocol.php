<?php
declare(strict_types=1);
require_once __DIR__.'/PigeBridgeProtocol.php';
final class PigeBridgeChargeProtocol
{
    public const ROOT='/pige_bridge/api/v3/charges';
    public const ACTIONS=['issue','status','sync','instruments','reconcile'];
    public static function path(string $action):string{if(!in_array($action,self::ACTIONS,true))throw new InvalidArgumentException('charge_action_invalid');return self::ROOT.($action==='issue'?'':'/'.$action);}
    public static function decode(string $body,string $action):array
    {
        self::path($action);if(strlen($body)>PigeBridgeProtocol::MAX_BODY_BYTES)throw new InvalidArgumentException('body_too_large');
        $flags=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS|JSON_THROW_ON_ERROR;$event=json_decode($body,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($event)||($event['schema_version']??null)!==3||($event['event_type']??null)!=='charge.'.$action.'.requested'||!is_array($event['data']??null)||json_encode($event,$flags)!==$body)throw new InvalidArgumentException('charge_envelope_invalid');
        $keys=array_keys($event);sort($keys);$expected=['schema_version','event_id','event_type','scope','data'];sort($expected);if($keys!==$expected)throw new InvalidArgumentException('charge_fields_invalid');
        $keys=array_keys($event['data']);sort($keys);$expected=match($action){'issue'=>['charge_id','payer_id','billing_type'],'sync'=>['charge_id','recovery_id'],'reconcile'=>['charge_id','proof_id'],default=>['charge_id']};if($action==='issue'&&array_key_exists('bb_title',$event['data']))$expected[]='bb_title';sort($expected);if($keys!==$expected)throw new InvalidArgumentException('charge_fields_invalid');
        if($action==='issue'&&(!in_array($event['data']['billing_type'],['PIX','BOLETO'],true)||!is_int($event['data']['payer_id'])||$event['data']['payer_id']<1||$event['data']['payer_id']>2147483647))throw new InvalidArgumentException('charge_payer_invalid');
        if($action==='reconcile'&&(!is_int($event['data']['proof_id'])||$event['data']['proof_id']<1||$event['data']['proof_id']>2147483647))throw new InvalidArgumentException('charge_proof_invalid');
        if($action==='sync'&&(!is_string($event['data']['recovery_id'])||!preg_match('/^[A-Za-z0-9_-]{0,64}$/D',$event['data']['recovery_id'])))throw new InvalidArgumentException('charge_recovery_invalid');
        if(array_key_exists('bb_title',$event['data'])){if(!is_array($event['data']['bb_title']))throw new InvalidArgumentException('charge_bb_title_invalid');$keys=array_keys($event['data']['bb_title']);sort($keys);$expected=['payer_address','payer_district','payer_zip','payer_city','payer_state','species','acceptance'];sort($expected);if($keys!==$expected)throw new InvalidArgumentException('charge_bb_title_invalid');foreach($event['data']['bb_title'] as $value)if(!is_string($value)||strlen($value)>100||trim($value)===''||preg_match('/[\x00-\x1f\x7f]/',$value))throw new InvalidArgumentException('charge_bb_title_invalid');}
        $probe=$event;$probe['schema_version']=1;$probe['event_type']='charge.requested';$probe['data']=['charge_id'=>$event['data']['charge_id'],'amount_cents'=>1,'currency'=>'BRL','due_date'=>'2030-01-01','description'=>'External operation'];PigeBridgeProtocol::decode(json_encode($probe,$flags));return $event;
    }
    public static function hash(array $event):string
    {
        $scope=[];$data=[];foreach(['school_id','issuer_id','account_id','environment'] as $key)$scope[$key]=$event['scope'][$key];foreach(['charge_id','payer_id','billing_type'] as $key)$data[$key]=$event['data'][$key];
        if(array_key_exists('bb_title',$event['data'])){$title=[];foreach(['payer_address','payer_district','payer_zip','payer_city','payer_state','species','acceptance'] as $key)$title[$key]=$event['data']['bb_title'][$key];$data['bb_title']=$title;}
        return hash('sha256',json_encode(['schema_version'=>3,'event_id'=>$event['event_id'],'event_type'=>'charge.issue.requested','scope'=>$scope,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
}
