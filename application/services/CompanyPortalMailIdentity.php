<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Exact native contact/password/owner recovery identity; sharing is never a mail origin. */
final class CompanyPortalMailIdentity
{
    public static function contact($db,string$p,int$issuer,?string$email=null,?int$id=null):?array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$origin='CASE WHEN o.client_id IS NULL THEN i.primary_issuer_id ELSE o.owner_issuer_id END';
        $rows=IssuerStorage::query($db,'SELECT p.id,p.userid,p.email,p.firstname,p.lastname,p.password,c.company,r.id issuer_id,r.legal_name FROM '.$p.'contacts p JOIN '.$p.'clients c ON c.userid=p.userid LEFT JOIN '.$p.'issuers_clients o ON o.client_id=c.userid LEFT JOIN '.$p.'issuers_installation_identity i ON i.id=1 JOIN '.$p.'issuers_registry r ON r.id=('.$origin.') WHERE r.id=? AND r.active=1 AND p.active=1 AND c.active=1'.($email!==null?' AND LOWER(p.email)=?':' AND p.id=?').' ORDER BY p.id LIMIT 2 FOR UPDATE',[$issuer,$email??$id])->result_array();return count($rows)===1?$rows[0]:null;
    }
    public static function stamp(array$row,string$secret):string{if(strlen($secret)<32)throw new RuntimeException('company_portal_configuration_required');return hash_hmac('sha256',json_encode([(int)$row['id'],(int)$row['userid'],(int)$row['issuer_id'],$row['email'],$row['password']],JSON_THROW_ON_ERROR),$secret);}
    public static function recipient(array$row):string{return hash('sha256',json_encode([(int)$row['id'],(int)$row['userid'],$row['email'],$row['firstname'],$row['lastname']],JSON_THROW_ON_ERROR));}
    public static function payload(object$mail):string{$values=[];foreach(['engine','email','cc','bcc','message','alt_message','headers','attachments','issuer_id']as$key)$values[$key]=(string)($mail->$key??'');return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));}
    public static function confirmed(array$row,array$expected):void{foreach($expected as$key=>$value)if(!array_key_exists($key,$row)||(string)$row[$key]!== (string)$value)throw new RuntimeException('issuer_portal_mail_write_unconfirmed');}
}
