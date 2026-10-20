<?php

declare(strict_types=1);

/** Setup-only primary Baileys proof. It is never used as permission to send a message. */
final class CompanyConnectPairingProof
{
    public const PROTOCOL='evolution-v2-baileys-qr-v1';
    public static function identity(array$response,string$name,string$state):array
    {
        $row=$response[0]??null;
        if(!array_is_list($response)||count($response)!==1||!is_array($row)||($row['name']??null)!==$name||($row['integration']??null)!=='WHATSAPP-BAILEYS'||($row['connectionStatus']??null)!==$state||!in_array($state,['open','connecting','close'],true)||!is_string($row['id']??null)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD',$row['id'])||!array_key_exists('ownerJid',$row))throw new DomainException('company_connect_pairing_identity_unconfirmed');
        $owner=$row['ownerJid'];
        if($owner!==null&&$owner!==''){
            if(!is_string($owner)||!preg_match('/^([1-9][0-9]{1,14})(?::[0-9]{1,5})?@s\.whatsapp\.net$/D',$owner,$number))throw new DomainException('company_connect_pairing_identity_unconfirmed');$owner=$number[1].'@s.whatsapp.net';
        }else{$owner='';if($state==='open')throw new DomainException('company_connect_pairing_identity_unconfirmed');}
        return ['provider_instance_id'=>strtolower($row['id']),'provider_owner_jid'=>$owner,'state'=>$state];
    }
    public static function state(array$response,string$name):string
    {
        if(array_keys($response)!==['instance']||!is_array($response['instance'])||($response['instance']['instanceName']??null)!==$name||!in_array($response['instance']['state']??null,['open','connecting','close'],true))throw new DomainException('company_connect_pairing_identity_unconfirmed');return $response['instance']['state'];
    }
    /** A PNG only. No remote URL, SVG/HTML, raw QR code or unverified pairing code is exposed. */
    public static function png(array$response):string
    {
        $keys=array_keys($response);sort($keys);
        if($keys!==['base64','code','count','pairingCode']||!is_int($response['count'])||$response['count']<1||$response['count']>100||!is_string($response['code'])||strlen($response['code'])<8||strlen($response['code'])>4096||preg_match('/[^\x20-\x7e]/',$response['code'])||($response['pairingCode']!==null&&(!is_string($response['pairingCode'])||strlen($response['pairingCode'])>128))||!is_string($response['base64'])||!str_starts_with($response['base64'],'data:image/png;base64,')||strlen($response['base64'])>90000)throw new DomainException('company_connect_pairing_offer_unconfirmed');
        $encoded=substr($response['base64'],22);$png=base64_decode($encoded,true);
        if(!is_string($png)||base64_encode($png)!==$encoded||!str_starts_with($png,"\x89PNG\r\n\x1a\n")||!function_exists('getimagesizefromstring'))throw new DomainException('company_connect_pairing_offer_unconfirmed');
        $size=@getimagesizefromstring($png);if(!$size||($size['mime']??null)!=='image/png'||$size[0]<16||$size[1]<16||$size[0]>2048||$size[1]>2048)throw new DomainException('company_connect_pairing_offer_unconfirmed');return $response['base64'];
    }
}
