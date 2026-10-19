<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';
require_once __DIR__.'/CompanySalesSchema.php';
require_once __DIR__.'/CompanyCurrencyUnits.php';
require_once __DIR__.'/CompanySalesPreferences.php';

/** Internal explicit-origin helpers. Ambient session and legacy document models are never read. */
abstract class CompanySalesBase
{
    public function __construct(protected $db,protected string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');}
    protected function rows(string $sql,array $params=[]):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    protected function auth(int $issuer,int $actor,string $feature,string $cap='view'):void{if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,$feature,$cap))throw new DomainException('company_sales_denied');}
    protected function tx(int $issuer,int $actor,callable $operation){CompanySalesSchema::assertValid($this->db,$this->prefix);return IssuerTransaction::run($this->db,$this->prefix,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_client_links','issuers_audit','issuers_sequences','staff','clients','currencies','company_currency_units','options','company_sales_documents','company_recurring','company_recurring_runs','company_invoice_requests','invoices','issuers_invoice_documents'],function()use($issuer,$actor,$operation){(new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);return $operation();});}
    public function currencies($issuer,$actor,string $feature):array{$issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);if(!in_array($feature,['invoices','estimates','proposals'],true))throw new DomainException('company_sales_invalid');$this->auth($issuer,$actor,$feature);$rows=$this->rows('SELECT id,name FROM '.$this->prefix.'currencies ORDER BY id');$this->auth($issuer,$actor,$feature);return $rows;}
    protected function insert(string $table,array $data):void{IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.$table.' (`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));}
    protected function audit(int $issuer,int $actor,int $subject,string $event):void{$this->insert('issuers_audit',['issuer_id'=>$issuer,'actor_id'=>$actor,'subject_id'=>$subject,'event'=>$event,'created_at'=>date('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();if(!$id||!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=?',[$id,$issuer,$actor,$subject,$event]))throw new RuntimeException('issuer_sales_write_unconfirmed');}
    public static function key(string $key):string{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_sales_invalid');return $key;}
    public static function date($date):string{if(!is_string($date)||!($d=DateTimeImmutable::createFromFormat('!Y-m-d',$date))||$d->format('Y-m-d')!==$date)throw new DomainException('company_sales_invalid');return $date;}
    public static function input(array $input):array
    {
        if(array_diff(array_keys($input),['client_id','currency','date','due_date','items','note','discount_mode','discount_value','discount_type','adjustment'])||!is_array($input['items']??null))throw new DomainException('company_sales_invalid');
        $amounts=CompanyInvoiceAmounts::calculate($input['items'],array_intersect_key($input,array_flip(['discount_mode','discount_value','discount_type','adjustment'])));
        $note=$input['note']??'';if(!is_string($note)||strlen($note)>20000||preg_match('//u',$note)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$note))throw new DomainException('company_sales_invalid');
        $date=self::date($input['date']??null);$due=self::date($input['due_date']??null);if($due<$date)throw new DomainException('company_sales_invalid');
        return ['client_id'=>IssuerPolicy::id($input['client_id']??null),'currency'=>IssuerPolicy::id($input['currency']??null),'date'=>$date,'due_date'=>$due,'items'=>array_map(fn($r)=>['description'=>$r['description'],'qty'=>$r['qty'],'rate'=>$r['rate'],'taxes'=>$r['taxes']],$amounts['items']),'note'=>trim($note),'discount_mode'=>$amounts['discount_mode'],'discount_value'=>$amounts['discount_value'],'discount_type'=>$amounts['discount_type'],'adjustment'=>$amounts['adjustment']];
    }
    protected function context(int $issuer,int $actor,int $client,int $currency,string $kind='invoices'):array
    {
        $this->auth($issuer,$actor,'customers');
        $r=$this->rows('SELECT legal_name,tax_id,settings_json FROM '.$this->prefix.'issuers_registry WHERE id=? AND active=1 FOR UPDATE',[$issuer])[0]??null;if(!$r)throw new DomainException('company_sales_denied');
        $c=$this->rows('SELECT c.userid,c.company,c.vat,o.tax_id FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND (o.owner_issuer_id=? OR EXISTS(SELECT 1 FROM '.$this->prefix.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)) FOR UPDATE',[$client,$issuer,$issuer])[0]??null;
        if(!$c)throw new DomainException('company_sales_recipient_denied');$tax=IssuerPolicy::taxId((string)($c['vat']??''),false)??'';if($tax!==($c['tax_id']??''))throw new DomainException('company_sales_recipient_denied');
        $unit=(new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($currency);$cur=['id'=>$unit['currency_id'],'name'=>$unit['iso_code']];
        $store=new CompanySettingsStorage($this->db,$this->prefix);$g=fn($n)=>$store->get($issuer,$n);$settings=json_decode($r['settings_json']??'{}',true,32,JSON_THROW_ON_ERROR);$assets=$settings['company_brand_assets']??[];
        $profile=['address'=>$g('invoice_company_address'),'city'=>$g('invoice_company_city'),'state'=>$g('company_state'),'postcode'=>$g('invoice_company_postal_code'),'country_code'=>strtoupper($g('invoice_company_country_code')),'phone'=>$g('invoice_company_phonenumber'),'email'=>$g('smtp_email'),'prefix'=>$g('invoice_prefix'),'terms'=>$g('predefined_terms_invoice'),'padding'=>6,'logo_inline'=>$assets['logo']??'','signature_inline'=>$assets['signature']??''];
        $r['settings_json']=json_encode(['document_profile'=>$profile],JSON_THROW_ON_ERROR);$identity=InvoiceIssuerDocuments::identity($r);
        // Only a digest of transport/settings is persisted. Secrets never enter document DTOs.
        $config=[];foreach(['smtp_host','smtp_port','smtp_encryption','smtp_email','smtp_username','smtp_password','invoice_prefix','predefined_terms_invoice'] as $name)$config[$name]=$g($name);
        $context=['issuer_id'=>$issuer,'identity'=>$identity,'recipient'=>['client_id'=>$client,'name'=>(string)$c['company'],'tax_id'=>$tax],'currency'=>['id'=>(int)$cur['id'],'name'=>$cur['name']],'configuration_hash'=>hash('sha256',json_encode([$settings,$config],JSON_THROW_ON_ERROR)),'transport'=>['mode'=>'manual_notification','automatic_delivery'=>false]];
        if($kind!=='invoices')$context['sales_preferences']=CompanySalesPreferences::capture($store,$issuer,$kind);
        return $context;
    }
    protected static function encode(array $data):string{return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    protected static function hash(array $data):string{return hash('sha256',self::encode($data));}
    protected function assertOrigin(int $issuer,int $actor,array $payload,string $originHash,array $context=[]):void
    {
        $current=$this->context($issuer,$actor,(int)$payload['client_id'],(int)$payload['currency'],$context['sales_preferences']['kind']??'invoices');
        // Read and conversion of version 1 documents retain their exact original hash contract.
        if(($context['sales_preferences']['format']??null)===1){unset($current['sales_preferences']['pdf']);$current['sales_preferences']['format']=1;}
        if(!hash_equals($originHash,self::hash($current)))throw new DomainException('company_sales_origin_changed');
    }
    /** A committed canonical receipt can be finalized after configuration changes. Its immutable document snapshot must match the frozen origin. */
    protected function assertOriginOrReceipt(int $issuer,int $actor,array $payload,string $originHash,array $context,string $key):void
    {
        $receipt=$this->rows('SELECT r.issuer_id,r.actor_id,r.invoice_id,d.legal_identity_json AS identity_json,d.recipient_identity_json AS recipient_json FROM '.$this->prefix.'company_invoice_requests r JOIN '.$this->prefix.'invoices i ON i.id=r.invoice_id AND i.issuer_id=r.issuer_id JOIN '.$this->prefix.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE r.request_key=? FOR UPDATE',[$key])[0]??null;
        if(!$receipt){$this->assertOrigin($issuer,$actor,$payload,$originHash,$context);return;}
        if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||!hash_equals(self::hash(CompanyInvoicePresentation::fiscalIdentity($this->decode($receipt['identity_json']))),self::hash(CompanyInvoicePresentation::fiscalIdentity($context['identity'])))||!hash_equals(self::hash($this->decode($receipt['recipient_json'])),self::hash($context['recipient'])) )throw new DomainException('company_sales_origin_changed');
    }
    protected function decode(string $json):array{$value=json_decode($json,true,32,JSON_THROW_ON_ERROR);if(!is_array($value))throw new RuntimeException('issuer_sales_write_unconfirmed');return $value;}
}
