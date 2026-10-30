<?php

declare(strict_types=1);

/** RS256 OIDC validation using the pinned JWT library; never treats a mutable email/UPN as authority. */
final class CompanyMicrosoftMailIdentity
{
    public static function guid(string$value):string{if(!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D',$value)||$value==='00000000-0000-0000-0000-000000000000')throw new DomainException('company_microsoft_mail_identity_invalid');return strtolower($value);}
    public static function tenant(string$value):string{$value=self::guid($value);if($value==='9188040d-6c67-4c5b-b112-36a304b66dad')throw new DomainException('company_microsoft_mail_tenant_invalid');return$value;}
    public static function issuer(string$tenant):string{return 'https://login.microsoftonline.com/'.self::tenant($tenant).'/v2.0';}
    public static function verify(array$jwks,string$token,string$tenant,string$client,string$nonceHash,bool$initial,string$sender,int$now):string
    {
        $tenant=self::tenant($tenant);$client=self::guid($client);$issuer=self::issuer($tenant);
        if(strlen($token)>20000||!is_array($jwks['keys']??null)||count($jwks['keys'])<1||count($jwks['keys'])>64||!preg_match('/^[a-f0-9]{64}$/D',$nonceHash))throw new DomainException('company_microsoft_mail_identity_invalid');$keys=[];
        foreach($jwks['keys']as$key){if(!is_array($key)||($key['kty']??'')!=='RSA'||($key['use']??'sig')!=='sig'||($key['alg']??'RS256')!=='RS256')continue;if(isset($key['issuer'])&&!in_array($key['issuer'],[$issuer,'https://login.microsoftonline.com/{tenantid}/v2.0'],true))continue;if(!is_string($key['kid']??null)||$key['kid']===''||strlen($key['kid'])>255||isset($keys[$key['kid']])||!is_string($key['n']??null)||!is_string($key['e']??null)||strlen($key['n'])>2048||strlen($key['e'])>16)throw new DomainException('company_microsoft_mail_identity_invalid');$key['alg']='RS256';$keys[$key['kid']]=$key;}
        if(!$keys)throw new DomainException('company_microsoft_mail_identity_invalid');$oldTime=\Firebase\JWT\JWT::$timestamp;$oldLeeway=\Firebase\JWT\JWT::$leeway;
        try{\Firebase\JWT\JWT::$timestamp=$now;\Firebase\JWT\JWT::$leeway=60;$header=new stdClass();$claims=(array)\Firebase\JWT\JWT::decode($token,\Firebase\JWT\JWK::parseKeySet(['keys'=>array_values($keys)],'RS256'),$header);
            if(($header->alg??'')!=='RS256'||($header->typ??'')!=='JWT'||isset($header->jku)||isset($header->jwk)||isset($header->x5u)||isset($header->crit)||($claims['ver']??'')!=='2.0'||($claims['aud']??null)!==$client||($claims['iss']??null)!==$issuer||!is_string($claims['tid']??null)||self::guid($claims['tid'])!==$tenant||!is_string($claims['oid']??null)||!is_string($claims['sub']??null)||$claims['sub']===''||strlen($claims['sub'])>255||preg_match('/[\x00-\x20\x7f]/',$claims['sub']))throw new DomainException('company_microsoft_mail_identity_invalid');
            foreach(['iat','nbf','exp']as$key)if(!is_int($claims[$key]??null))throw new DomainException('company_microsoft_mail_identity_invalid');if($claims['iat']>$now+60||$claims['nbf']>$now+60||$claims['exp']<=$now-60||$claims['exp']<=$claims['iat']||$claims['exp']>$now+86400)throw new DomainException('company_microsoft_mail_identity_invalid');
            if($initial||array_key_exists('nonce',$claims)){if(!is_string($claims['nonce']??null)||strlen($claims['nonce'])>255||!hash_equals($nonceHash,hash('sha256',$claims['nonce'])))throw new DomainException('company_microsoft_mail_identity_invalid');}
            // First profile: configured individual mailbox must match the signed username hint.
            // Authorization remains own ACL + immutable tenant/object; this is only a conservative SMTP target check.
            if(!is_string($claims['preferred_username']??null)||!filter_var($claims['preferred_username'],FILTER_VALIDATE_EMAIL)||!hash_equals(strtolower($sender),strtolower($claims['preferred_username'])))throw new DomainException('company_microsoft_mail_sender_mismatch');
            return hash('sha256',json_encode([$tenant,self::guid($claims['oid'])],JSON_THROW_ON_ERROR));
        }catch(Throwable$error){if($error instanceof DomainException&&str_starts_with($error->getMessage(),'company_microsoft_mail_'))throw$error;throw new DomainException('company_microsoft_mail_identity_invalid');}
        finally{\Firebase\JWT\JWT::$timestamp=$oldTime;\Firebase\JWT\JWT::$leeway=$oldLeeway;}
    }
}
