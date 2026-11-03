<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyCustomerAccess.php';

/** Existing customer columns, not global configuration. Called only inside the owner writer. */
final class CompanyClientNativeFields
{
    public const TEXT=['website'=>150,'default_language'=>40,'billing_street'=>200,'billing_city'=>100,'billing_state'=>100,'billing_zip'=>100,'shipping_street'=>200,'shipping_city'=>100,'shipping_state'=>100,'shipping_zip'=>100];
    public const IDS=['country','billing_country','shipping_country','default_currency','show_primary_contact'];
    public const READ_ONLY=['registration_confirmed','leadid'];
    public static function fields(): array { return array_merge(array_keys(self::TEXT),self::IDS); }
    public static function normalize(array $input): array
    {
        if(array_diff(array_keys($input),self::fields()))throw new DomainException('company_client_invalid_input');
        foreach($input as $key=>&$value){
            if(!is_string($value)||preg_match('//u',$value)!==1||preg_match(in_array($key,['billing_street','shipping_street'],true)?'/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/':'/[\x00-\x1f\x7f]/',$value))throw new DomainException('company_client_invalid_input');
            $value=trim($value);
            if(isset(self::TEXT[$key])){
                if(strlen($value)>self::TEXT[$key])throw new DomainException('company_client_invalid_input');
                if($key==='website'&&$value!==''&&!filter_var(preg_match('~^https?://~i',$value)?$value:'https://'.$value,FILTER_VALIDATE_URL))throw new DomainException('company_client_invalid_input');
                if($key==='website'&&preg_match('~^[a-z][a-z0-9+.-]*:~i',$value)&&!preg_match('~^https?://~i',$value))throw new DomainException('company_client_invalid_input');
                if($key==='default_language'&&$value!==''&&!preg_match('/^[a-z][a-z0-9_-]{0,39}$/D',$value))throw new DomainException('company_client_invalid_input');
            }else{
                if($value==='')$value='0';
                if(!preg_match('/^(0|[1-9][0-9]{0,8})$/D',$value)||($key==='show_primary_contact'&&!in_array($value,['0','1'],true)))throw new DomainException('company_client_invalid_input');
            }
        }unset($value);ksort($input);return $input;
    }
    public static function ready($db,string $p): bool
    {
        foreach(array_merge(self::fields(),self::READ_ONLY) as $field)if(!IssuerStorage::fieldExists($db,$field,$p.'clients'))return false;
        return true;
    }
    public static function read($db,string $p,int $issuer,int $client): array
    {
        if(!self::ready($db,$p))throw new DomainException('company_client_native_schema_required');
        return IssuerStorage::query($db,'SELECT '.implode(',',array_merge(self::fields(),self::READ_ONLY)).' FROM '.$p.'clients WHERE userid=? AND EXISTS (SELECT 1 FROM '.$p.'issuers_clients o WHERE o.client_id='.$p.'clients.userid AND o.owner_issuer_id=?)',[$client,$issuer])->row_array()?:[];
    }
    public static function write($db,string $p,int $issuer,int $actor,int $client,array $input,string $cap): void
    {
        IssuerTransaction::assertOwned($db,$p,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','clients']);
        $access=new IssuerResourceAccess($db,$p);
        if(!in_array($cap,['create','edit'],true)||!($access->can($issuer,$actor,'customers',$cap)||($cap==='edit'&&(new CompanyCustomerAccess($db,$p))->can($issuer,$actor,$client,'edit')))||!IssuerStorage::query($db,'SELECT client_id FROM '.$p.'issuers_clients WHERE client_id=? AND owner_issuer_id=?',[$client,$issuer])->row())throw new DomainException('company_client_access_denied');
        if(!$input)return;
        if(!self::ready($db,$p))throw new DomainException('company_client_native_schema_required');
        $input=self::normalize($input);
        foreach(['country','billing_country','shipping_country'] as $key)if(!empty($input[$key])&&!IssuerStorage::query($db,'SELECT country_id FROM '.$p.'countries WHERE country_id=?',[$input[$key]])->row())throw new DomainException('company_client_invalid_input');
        if(!empty($input['default_language'])&&(!defined('APPPATH')||!is_dir(APPPATH.'language/'.$input['default_language'])))throw new DomainException('company_client_invalid_input');
        if(!empty($input['default_currency'])&&!IssuerStorage::query($db,'SELECT id FROM '.$p.'currencies WHERE id=?',[$input['default_currency']])->row())throw new DomainException('company_client_invalid_input');
        if(!empty($input['show_primary_contact'])&&!IssuerStorage::query($db,'SELECT id FROM '.$p.'contacts WHERE userid=? AND is_primary=1 AND active=1',[$client])->row())throw new DomainException('company_client_invalid_input');
        // A customer's currency is immutable once native financial documents exist.
        // Ownership was checked under the issuer lock above; this check only constrains this owner row.
        $before=self::read($db,$p,$issuer,$client);
        if($cap==='edit'&&isset($input['default_currency'])&&(int)$input['default_currency']!==(int)$before['default_currency']){
            foreach(['invoices'=>'clientid=?','creditnotes'=>'clientid=?','estimates'=>'clientid=?','expenses'=>'clientid=? AND billable=1','proposals'=>"rel_id=? AND rel_type='customer'"] as $table=>$predicate){
                if(!$db->table_exists($p.$table))throw new DomainException('company_client_native_schema_required');
                if(IssuerStorage::query($db,'SELECT 1 FROM '.$p.$table.' WHERE '.$predicate.' LIMIT 1',[$client])->row())throw new DomainException('company_client_currency_locked');
            }
        }
        $set=[];$args=[];foreach($input as $key=>$value){$set[]=$key.'=?';$args[]=$value;}$args[]=$client;$args[]=$issuer;
        IssuerStorage::query($db,'UPDATE '.$p.'clients SET '.implode(',',$set).' WHERE userid=? AND EXISTS (SELECT 1 FROM '.$p.'issuers_clients o WHERE o.client_id='.$p.'clients.userid AND o.owner_issuer_id=?)',$args);
        $row=self::read($db,$p,$issuer,$client);foreach($input as $key=>$value)if((string)($row[$key]??'')!==$value)throw new RuntimeException('issuer_client_write_unconfirmed');
        if(!($access->can($issuer,$actor,'customers',$cap)||($cap==='edit'&&(new CompanyCustomerAccess($db,$p))->can($issuer,$actor,$client,'edit'))))throw new DomainException('company_client_access_denied');
    }
}
