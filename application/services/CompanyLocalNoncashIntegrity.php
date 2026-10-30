<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Expected native images are captured before the operation; a later re-sealed journal cannot redefine them. */
final class CompanyLocalNoncashIntegrity
{
    public static function rows($db,string$sql,array$args=[]):array{return IssuerStorage::query($db,$sql,$args)->result_array();}
    public static function json(array$value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    public static function canonical($value)
    {
        if(is_array($value)){foreach($value as$key=>$child){if(is_scalar($child)&&in_array($key,['amount','nominal','discount','abatement','qty','rate','taxrate','subtotal','total_tax','total','discount_total','adjustment','discount_percent'],true)){try{$child=CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor((string)$child));}catch(Throwable$ignored){}}$value[$key]=self::canonical($child);}if(!array_is_list($value))ksort($value);return$value;}
        return $value===null?null:(string)$value;
    }
    public static function equal($actual,$expected):bool{return self::canonical($actual)===self::canonical($expected);}
    public static function content($db,string$p,int$issuer,int$id):string
    {
        $invoice=self::rows($db,'SELECT * FROM '.$p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;
        if(!$invoice)throw new DomainException('company_local_noncash_denied');
        foreach(['status','sent','datesend','last_overdue_reminder','last_due_reminder','last_recurring_date','cycles','last_recurring_date','date_sent']as$field)unset($invoice[$field]);
        $data=[$invoice,self::rows($db,'SELECT * FROM '.$p.'issuers_invoice_documents WHERE invoice_id=? AND issuer_id=? FOR UPDATE',[$id,$issuer]),self::rows($db,"SELECT * FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY id FOR UPDATE",[$id]),self::rows($db,"SELECT * FROM {$p}item_tax WHERE rel_id=? AND rel_type='invoice' ORDER BY id FOR UPDATE",[$id])];
        return hash('sha256',self::json(self::canonical($data)));
    }
    public static function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','issuers_client_links','clients','invoices','issuers_invoice_documents','issuers_sequences','itemable','item_tax','currencies','company_currency_units','countries','issuers_audit','company_financial_events','invoicepaymentrecords','company_payment_receipts','credits','company_local_noncash_declarations','company_local_noncash_restorations','company_local_noncash_settlements'];}
    public static function frame($db,string$p,int$issuer,int$actor,int$id):array
    {
        IssuerTransaction::assertOwned($db,$p,self::tables());$invoice=self::rows($db,'SELECT * FROM '.$p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;if(!$invoice)throw new DomainException('company_local_noncash_denied');
        $queries=[
            'invoice'=>['SELECT * FROM '.$p.'invoices WHERE id=? AND issuer_id=?',[$id,$issuer]],
            'document'=>['SELECT * FROM '.$p.'issuers_invoice_documents WHERE invoice_id=?',[$id]],
            'items'=>["SELECT * FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY id",[$id]],
            'taxes'=>["SELECT * FROM {$p}item_tax WHERE rel_id=? AND rel_type='invoice' ORDER BY id",[$id]],
            'registry'=>['SELECT * FROM '.$p.'issuers_registry WHERE id=?',[$issuer]],
            'actor'=>['SELECT * FROM '.$p.'staff WHERE staffid=?',[$actor]],
            'identity'=>['SELECT * FROM '.$p.'issuers_installation_identity ORDER BY id',[]],
            'membership'=>['SELECT * FROM '.$p.'issuers_staff WHERE issuer_id=? AND staff_id=?',[$issuer,$actor]],
            'grants'=>['SELECT * FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=? ORDER BY feature,capability',[$issuer,$actor]],
            'client'=>['SELECT * FROM '.$p.'clients WHERE userid=?',[$invoice['clientid']]],
            'owner'=>['SELECT * FROM '.$p.'issuers_clients WHERE client_id=?',[$invoice['clientid']]],
            'links'=>['SELECT * FROM '.$p.'issuers_client_links WHERE client_id=? ORDER BY issuer_id',[$invoice['clientid']]],
            'sequences'=>['SELECT * FROM '.$p.'issuers_sequences WHERE issuer_id=? ORDER BY series',[$issuer]],
            'currency'=>['SELECT * FROM '.$p.'currencies WHERE id=?',[$invoice['currency']]],
            'unit'=>['SELECT * FROM '.$p.'company_currency_units WHERE currency_id=?',[$invoice['currency']]],
            'countries'=>['SELECT * FROM '.$p.'countries ORDER BY country_id',[]],
            'payments'=>['SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$id]],
            'receipts'=>['SELECT * FROM '.$p.'company_payment_receipts WHERE invoice_id=? ORDER BY request_key',[$id]],
            'credits'=>['SELECT * FROM '.$p.'credits WHERE invoice_id=? ORDER BY id',[$id]],
            'local_declarations'=>['SELECT * FROM '.$p.'company_local_noncash_declarations WHERE invoice_id=? ORDER BY request_key',[$id]],
            'local_restorations'=>['SELECT * FROM '.$p.'company_local_noncash_restorations WHERE invoice_id=? ORDER BY request_key',[$id]],
            'local_settlements'=>['SELECT * FROM '.$p.'company_local_noncash_settlements WHERE invoice_id=? ORDER BY request_key',[$id]],
            'journal'=>['SELECT * FROM '.$p.'company_financial_events WHERE issuer_id=? ORDER BY issuer_sequence',[$issuer]],
            'audit'=>['SELECT * FROM '.$p.'issuers_audit WHERE issuer_id=? ORDER BY id',[$issuer]],
        ];
        if($db->table_exists($p.'company_credit_entries'))$queries['entries']=['SELECT * FROM '.$p.'company_credit_entries WHERE invoice_id=? ORDER BY request_key',[$id]];
        $frame=[];foreach($queries as$key=>[$sql,$args])$frame[$key]=self::rows($db,$sql.' FOR UPDATE',$args);return$frame;
    }
    public static function assertFrame($db,string$p,int$issuer,int$actor,int$id,array$expected):void
    {
        $actual=self::frame($db,$p,$issuer,$actor,$id);foreach($expected as$key=>$value)if(!self::equal($actual[$key]??null,$value))throw new RuntimeException('issuer_local_noncash_image_unconfirmed');
    }
    public static function assertRow($db,string$p,string$table,string$key,$value,array$expected):void
    {
        if(!preg_match('/^[a-z_]+$/D',$table)||!preg_match('/^[a-z_]+$/D',$key))throw new InvalidArgumentException('company_local_noncash_invalid');$rows=self::rows($db,'SELECT * FROM '.$p.$table.' WHERE '.$key.'=? FOR UPDATE',[$value]);if(count($rows)!==1||!self::equal($rows[0],$expected))throw new RuntimeException('issuer_local_noncash_image_unconfirmed');
    }
}
