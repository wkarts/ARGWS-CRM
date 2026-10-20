<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyLocalNoncashSchema.php';require_once __DIR__.'/CompanyLocalNoncashIntegrity.php';require_once __DIR__.'/CompanyInvoiceAmounts.php';require_once __DIR__.'/CompanyCurrencyUnits.php';

/** A LOCAL declaration is a reduction of debt; it never masquerades as a native payment or credit. */
final class CompanyLocalNoncash
{
    public const BASIS='local_full_noncash_declaration_v3';
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('company_local_noncash_invalid');}
    public static function json(array$data):string{return CompanyLocalNoncashIntegrity::json($data);}
    public static function today():string{return(new DateTimeImmutable('now',new DateTimeZone('America/Bahia')))->format('Y-m-d');}
    public static function text($value,int$limit):string{if(!is_string($value)||strlen($value)>$limit||trim($value)===''||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('company_local_noncash_invalid');return trim($value);}
    public static function date($value):string{$d=is_string($value)?DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;if(!$d||$d->format('Y-m-d')!==$value||$value>self::today())throw new DomainException('company_local_noncash_invalid');return$value;}
    public static function key($value):string{if(!is_string($value)||!preg_match('/^[a-f0-9]{64}$/D',$value))throw new DomainException('company_local_noncash_invalid');return$value;}
    public static function amount($value):string{if(!is_string($value)&&!is_int($value))throw new DomainException('company_local_noncash_invalid');$n=CompanyInvoiceAmounts::minor((string)$value);if($n<0||$n>CompanyInvoiceAmounts::MAX)throw new DomainException('company_local_noncash_invalid');return CompanyInvoiceAmounts::decimal($n);}
    public static function input(array$input):array
    {
        if(array_diff(array_keys($input),['local_declaration_confirmed','component_policy_confirmed','discount','abatement','book_date','reference','reason'])||($input['local_declaration_confirmed']??null)!=='1'||($input['component_policy_confirmed']??null)!=='1')throw new DomainException('company_local_noncash_confirmation_required');
        return['local_declaration_confirmed'=>'1','component_policy_confirmed'=>'1','discount'=>self::amount($input['discount']??null),'abatement'=>self::amount($input['abatement']??null),'book_date'=>self::date($input['book_date']??null),'reference'=>self::text($input['reference']??null,191),'reason'=>self::text($input['reason']??null,2000)];
    }
    public static function restoreInput(array$input):array
    {
        if(array_diff(array_keys($input),['local_restoration_confirmed','book_date','reference','reason'])||($input['local_restoration_confirmed']??null)!=='1')throw new DomainException('company_local_noncash_confirmation_required');
        return['local_restoration_confirmed'=>'1','book_date'=>self::date($input['book_date']??null),'reference'=>self::text($input['reference']??null,191),'reason'=>self::text($input['reason']??null,2000)];
    }
    public static function declarationKey(int$i,int$id,string$origin):string{return hash('sha256',self::json([self::BASIS,$i,$id,self::key($origin)]));}
    public static function restorationKey(string$key):string{return hash('sha256','local-noncash-restored-v3:'.self::key($key));}
    public static function recordHash(array$row):string{unset($row['record_hash']);return hash('sha256',self::json(CompanyLocalNoncashIntegrity::canonical($row)));}
    public function tables():array{return array_merge(CompanyLocalNoncashSchema::tables(),['invoices','issuers_invoice_documents','itemable','item_tax','company_currency_units','currencies','issuers_audit']);}
    private function rows(string$sql,array$args=[]):array{return CompanyLocalNoncashIntegrity::rows($this->db,$sql,$args);}
    private function decode(array$row,int$i,int$id):array
    {
        if((int)$row['issuer_id']!==$i||(int)$row['invoice_id']!==$id||!hash_equals(self::key($row['record_hash']),self::recordHash($row))||!hash_equals(self::key($row['payload_hash']),hash('sha256',$row['payload_json'])))throw new DomainException('company_local_noncash_review');
        $data=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($data)||self::json($data)!==$row['payload_json']||($data['basis']??null)!==self::BASIS||($data['issuer_id']??null)!==$i||($data['invoice_id']??null)!==$id||($data['actor_id']??null)!==(int)$row['created_by']||($data['book_date']??null)!==$row['book_date'])throw new DomainException('company_local_noncash_review');$auditEvent=array_key_exists('nominal',$row)?'company_local_noncash_declared':'company_local_noncash_restored';$audit=$this->rows('SELECT actor_id,created_at FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? AND metadata_json=? FOR UPDATE',[$i,$id,$auditEvent,self::json(['request_key'=>$row['request_key'],'payload_hash'=>$row['payload_hash']])]);if(count($audit)!==1||(int)$audit[0]['actor_id']!==(int)$row['created_by']||$audit[0]['created_at']!==$row['created_at'])throw new DomainException('company_local_noncash_review');return$data;
    }
    /** Historical reader needs native/core evidence, never current banking credentials or a fabricated staff identity. */
    public function positionInOwned(int$i,int$id):?array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());CompanyLocalNoncashSchema::ensure($this->db,$this->p,false);$decls=$this->rows('SELECT * FROM '.$this->p.'company_local_noncash_declarations WHERE invoice_id=? FOR UPDATE',[$id]);$restores=$this->rows('SELECT * FROM '.$this->p.'company_local_noncash_restorations WHERE invoice_id=? FOR UPDATE',[$id]);
        if(!$decls){if($restores||$this->rows('SELECT request_key FROM '.$this->p.'company_local_noncash_settlements WHERE invoice_id=? FOR UPDATE',[$id]))throw new DomainException('company_local_noncash_review');return null;}
        if(count($decls)!==1||count($restores)>1)throw new DomainException('company_local_noncash_review');$row=$decls[0];$data=$this->decode($row,$i,$id);
        foreach(['origin_id','operation_id']as$field)if(($data[$field]??null)!==(int)$row[$field])throw new DomainException('company_local_noncash_review');foreach(['nominal','discount','abatement']as$field)if(($data[$field]??null)!==self::amount((string)$row[$field]))throw new DomainException('company_local_noncash_review');
        if($row['request_key']!==self::declarationKey($i,$id,$data['origin_hash'])||($data['input']??null)!==self::input($data['input']??[]))throw new DomainException('company_local_noncash_review');
        if($data['discount']!==$data['input']['discount']||$data['abatement']!==$data['input']['abatement']||$data['book_date']!==$data['input']['book_date']||CompanyInvoiceAmounts::minor($data['discount'])+CompanyInvoiceAmounts::minor($data['abatement'])!==CompanyInvoiceAmounts::minor($data['nominal'])||!hash_equals($data['content_hash'],CompanyLocalNoncashIntegrity::content($this->db,$this->p,$i,$id)))throw new DomainException('company_local_noncash_review');
        $parent=$this->rows('SELECT total,currency,status,date,clientid FROM '.$this->p.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$i])[0]??null;if(!$parent||self::amount((string)$parent['total'])!==$data['nominal']||(int)$parent['clientid']!==$data['client_id']||$parent['date']!==$data['invoice_date'])throw new DomainException('company_local_noncash_review');$unit=(new CompanyCurrencyUnits($this->db,$this->p))->resolveRead($parent['currency']);if($unit['iso_code']!==$data['currency']||$unit['basis']!==$data['currency_basis'])throw new DomainException('company_local_noncash_review');
        $restoration=null;if($restores){$rr=$restores[0];$restoration=$this->decode($rr,$i,$id);if($rr['request_key']!==self::restorationKey($row['request_key'])||$rr['declaration_key']!==$row['request_key']||($restoration['declaration_key']??null)!==$row['request_key']||($restoration['input']??null)!==self::restoreInput($restoration['input']??[])||$rr['book_date']<$row['book_date'])throw new DomainException('company_local_noncash_review');}
        if($restoration===null?(int)$parent['status']!==2:!in_array((int)$parent['status'],[1,2,3,4],true))throw new DomainException('company_local_noncash_review');
        return['key'=>$row['request_key'],'data'=>$data,'restored'=>$restoration!==null,'restoration'=>$restoration,'discount'=>$restoration?'0.00':$data['discount'],'abatement'=>$restoration?'0.00':$data['abatement']];
    }
    public function assertNoHistoryInOwned(int$i,int$id):void{if($this->positionInOwned($i,$id)!==null)throw new DomainException('company_local_noncash_history_requires_review');}
}
