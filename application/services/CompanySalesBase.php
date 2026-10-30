<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';
require_once __DIR__.'/CompanyInvoiceLines.php';
require_once __DIR__.'/CompanySalesSchema.php';
require_once __DIR__.'/CompanyCurrencyUnits.php';
require_once __DIR__.'/CompanySalesPreferences.php';
require_once __DIR__.'/CompanyInvoiceNativeFields.php';
require_once __DIR__.'/CompanyInvoiceProjectInput.php';

/** Internal explicit-origin helpers. Ambient session and legacy document models are never read. */
abstract class CompanySalesBase
{
    public function __construct(protected $db,protected string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');}
    protected function rows(string $sql,array $params=[]):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    protected function auth(int $issuer,int $actor,string $feature,string $cap='view'):void{if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,$feature,$cap))throw new DomainException('company_sales_denied');}
    protected function participantTables():array{$tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_client_links','issuers_audit','issuers_sequences','staff','clients','currencies','countries','company_currency_units','options','company_sales_documents','company_recurring','company_recurring_runs','company_invoice_requests','invoices','issuers_invoice_documents'];if($this->db->table_exists($this->prefix.'company_projects'))$tables[]='company_projects';return$tables;}
    protected function tx(int $issuer,int $actor,callable $operation,array $extraTables=[]){CompanySalesSchema::assertValid($this->db,$this->prefix);return IssuerTransaction::run($this->db,$this->prefix,array_values(array_unique(array_merge($this->participantTables(),$extraTables))),function()use($issuer,$actor,$operation){(new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);return $operation();});}
    public function currencies($issuer,$actor,string $feature):array{$issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);if(!in_array($feature,['invoices','estimates','proposals'],true))throw new DomainException('company_sales_invalid');$this->auth($issuer,$actor,$feature);$rows=$this->rows('SELECT id,name FROM '.$this->prefix.'currencies ORDER BY id');$this->auth($issuer,$actor,$feature);return $rows;}
    protected function insert(string $table,array $data):void{IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.$table.' (`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));}
    protected function audit(int $issuer,int $actor,int $subject,string $event):void{$this->insert('issuers_audit',['issuer_id'=>$issuer,'actor_id'=>$actor,'subject_id'=>$subject,'event'=>$event,'created_at'=>date('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();if(!$id||!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=?',[$id,$issuer,$actor,$subject,$event]))throw new RuntimeException('issuer_sales_write_unconfirmed');}
    public static function key(string $key):string{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_sales_invalid');return $key;}
    public static function date($date):string{if(!is_string($date)||!($d=DateTimeImmutable::createFromFormat('!Y-m-d',$date))||$d->format('Y-m-d')!==$date)throw new DomainException('company_sales_invalid');return $date;}
    public static function input(array $input):array
    {
        if(array_diff(array_keys($input),['client_id','currency','date','due_date','items','note','discount_mode','discount_value','discount_type','adjustment','calculation_contract','native_fields','native_project','native_reference','native_tags','native_custom_fields'])||!is_array($input['items']??null))throw new DomainException('company_sales_invalid');
        $amounts=CompanyInvoiceLines::calculate($input['items'],array_intersect_key($input,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));
        $note=$input['note']??'';if(!is_string($note)||strlen($note)>20000||preg_match('//u',$note)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$note))throw new DomainException('company_sales_invalid');
        if(array_key_exists('native_reference',$input)&&(!is_string($input['native_reference'])||strlen($input['native_reference'])>100||preg_match('//u',$input['native_reference'])!==1||preg_match('/[\x00-\x1f\x7f]/',$input['native_reference'])))throw new DomainException('company_sales_invalid');
        if(array_key_exists('native_tags',$input)){
            if(!is_array($input['native_tags'])||!array_is_list($input['native_tags'])||count($input['native_tags'])>100)throw new DomainException('native_tags_invalid');
            $seen=[];foreach($input['native_tags'] as$label){if(!is_string($label)||trim($label)!==$label||$label===''||preg_match('//u',$label)!==1||preg_match('/[\x00-\x1f\x7f]/',$label)||mb_strlen($label)>100)throw new DomainException('native_tags_invalid');$key=mb_strtolower($label,'UTF-8');if(isset($seen[$key]))throw new DomainException('native_tags_invalid');$seen[$key]=true;}
        }
        if(array_key_exists('native_custom_fields',$input)){if(!is_array($input['native_custom_fields'])||count($input['native_custom_fields'])>256)throw new DomainException('native_custom_field_invalid');try{$json=json_encode($input['native_custom_fields'],JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('native_custom_field_invalid');}if(strlen($json)>65536)throw new DomainException('native_custom_field_invalid');}
        $date=self::date($input['date']??null);$due=($input['due_date']??null)===null&&array_key_exists('native_fields',$input)?null:self::date($input['due_date']??null);if($due!==null&&$due<$date)throw new DomainException('company_sales_invalid');
        return ['client_id'=>IssuerPolicy::id($input['client_id']??null),'currency'=>IssuerPolicy::id($input['currency']??null),'date'=>$date,'due_date'=>$due,'items'=>array_map(fn($r)=>['description'=>$r['description'],'qty'=>$r['qty'],'rate'=>$r['rate'],'taxes'=>$r['taxes']]+CompanyInvoiceLines::metadata($r),$amounts['items']),'note'=>trim($note),'discount_mode'=>$amounts['discount_mode'],'discount_value'=>$amounts['discount_value'],'discount_type'=>$amounts['discount_type'],'adjustment'=>$amounts['adjustment']]+(array_key_exists('calculation_contract',$input)?['calculation_contract'=>$input['calculation_contract']]:[])+(array_key_exists('native_fields',$input)?['native_fields'=>CompanyInvoiceNativeFields::input($input['native_fields'])]:[])+(array_key_exists('native_project',$input)?['native_project'=>CompanyInvoiceProjectInput::normalize($input['native_project'])]:[])+(array_key_exists('native_reference',$input)?['native_reference'=>$input['native_reference']]:[])+(array_key_exists('native_tags',$input)?['native_tags'=>$input['native_tags']]:[])+(array_key_exists('native_custom_fields',$input)?['native_custom_fields'=>$input['native_custom_fields']]:[]);
    }
    protected function customTablesForPayload(array $payload):array
    {
        if(!array_key_exists('native_custom_fields',$payload))return [];
        if(!$this->db->table_exists($this->prefix.'company_custom_fields')||!$this->db->table_exists($this->prefix.'company_custom_field_values'))throw new DomainException('native_estimate_form_fields_unavailable');
        require_once __DIR__.'/NativeCompanyCustomFields.php';return (new NativeCompanyCustomFields($this->db,$this->prefix))->tableNames();
    }
    protected function tagTablesForPayload(array $payload):array
    {
        if(!array_key_exists('native_tags',$payload)||$payload['native_tags']===[])return [];
        if(!$this->db->table_exists($this->prefix.'company_work_tags')||!$this->db->table_exists($this->prefix.'company_work_tag_links'))throw new DomainException('native_estimate_form_tags_unavailable');
        require_once __DIR__.'/NativeCompanyResourceTags.php';return NativeCompanyResourceTags::tableNames();
    }
    /** Live reference checks precede new/edited estimate metadata; historical documents retain their snapshot. */
    protected function assertNativeFields(int $issuer,int $actor,string $kind,array $payload):void
    {
        if(!array_key_exists('native_fields',$payload)&&!array_key_exists('native_project',$payload)&&!array_key_exists('native_reference',$payload)&&!array_key_exists('native_tags',$payload)&&!array_key_exists('native_custom_fields',$payload))return;
        if($kind!=='estimates')throw new DomainException('company_sales_invalid');
        $fields=CompanyInvoiceNativeFields::input($payload['native_fields']??[]);$acl=new IssuerResourceAccess($this->db,$this->prefix);
        if(isset($fields['sale_agent'])&&$fields['sale_agent']!==0&&(!$acl->member($issuer,$fields['sale_agent'])||!$acl->can($issuer,$fields['sale_agent'],'estimates','view')))throw new DomainException('native_estimate_agent_denied');
        foreach(['billing_country','shipping_country'] as$key)if(!empty($fields[$key])&&count($this->rows('SELECT country_id FROM '.$this->prefix.'countries WHERE country_id=?',[$fields[$key]]))!==1)throw new DomainException('native_estimate_country_denied');
        $project=$payload['native_project']??null;
        if($project!==null){
            if(!$this->db->table_exists($this->prefix.'company_projects'))throw new DomainException('native_estimate_project_unavailable');
            require_once __DIR__.'/CompanyProjects.php';$project=CompanyInvoiceProjectInput::normalize($project);
            $row=(new CompanyProjects($this->db,$this->prefix))->getInOwnedTransaction($issuer,$actor,$project['source_id']);
            if((int)$row['client_id']!==$payload['client_id'])throw new DomainException('native_estimate_project_denied');
            if((int)$row['revision']!==$project['source_revision'])throw new DomainException('native_estimate_project_stale');
        }
    }
    protected function context(int $issuer,int $actor,int $client,int $currency,string $kind='invoices',bool $legacyPreferences=false,bool $nativeInvoiceDefaults=false):array
    {
        $this->auth($issuer,$actor,'customers');
        $r=$this->rows('SELECT legal_name,tax_id,settings_json FROM '.$this->prefix.'issuers_registry WHERE id=? AND active=1 FOR UPDATE',[$issuer])[0]??null;if(!$r)throw new DomainException('company_sales_denied');
        $c=$this->rows('SELECT c.userid,c.company,c.vat,o.tax_id FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND (o.owner_issuer_id=? OR EXISTS(SELECT 1 FROM '.$this->prefix.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)) FOR UPDATE',[$client,$issuer,$issuer])[0]??null;
        if(!$c)throw new DomainException('company_sales_recipient_denied');$tax=IssuerPolicy::taxId((string)($c['vat']??''),false)??'';if($tax!==($c['tax_id']??''))throw new DomainException('company_sales_recipient_denied');
        $unit=(new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($currency);$cur=['id'=>$unit['currency_id'],'name'=>$unit['iso_code']];
        $store=new CompanySettingsStorage($this->db,$this->prefix);$g=fn($n)=>$store->get($issuer,$n);$settings=json_decode($r['settings_json']??'{}',true,32,JSON_THROW_ON_ERROR);$assets=$settings['company_brand_assets']??[];
        $profile=['address'=>CompanySettingsIdentity::documentAddress($g),'city'=>$g('invoice_company_city'),'state'=>$g('company_state'),'postcode'=>$g('invoice_company_postal_code'),'country_code'=>strtoupper($g('invoice_company_country_code')),'phone'=>$g('invoice_company_phonenumber'),'email'=>$g('smtp_email'),'prefix'=>$store->exists($issuer,'invoice_prefix')?$g('invoice_prefix'):'INV-','terms'=>$g('predefined_terms_invoice'),'padding'=>6,'logo_inline'=>$assets['logo']??'','signature_inline'=>$assets['signature']??''];
        $r['settings_json']=json_encode(['document_profile'=>$profile],JSON_THROW_ON_ERROR);$identity=InvoiceIssuerDocuments::identity($r);
        // Only a digest of transport/settings is persisted. Secrets never enter document DTOs.
        $config=[];foreach(['smtp_host','smtp_port','smtp_encryption','smtp_email','smtp_username','smtp_password','invoice_prefix','predefined_terms_invoice'] as $name)$config[$name]=$g($name);
        $context=['issuer_id'=>$issuer,'identity'=>$identity,'recipient'=>['client_id'=>$client,'name'=>(string)$c['company'],'tax_id'=>$tax],'currency'=>['id'=>(int)$cur['id'],'name'=>$cur['name']],'configuration_hash'=>hash('sha256',json_encode([$settings,$config],JSON_THROW_ON_ERROR)),'transport'=>['mode'=>'manual_notification','automatic_delivery'=>false]];
        if($kind!=='invoices')$context['sales_preferences']=CompanySalesPreferences::capture($store,$issuer,$kind,$legacyPreferences);
        if($nativeInvoiceDefaults){
            if($kind!=='estimates')throw new DomainException('company_sales_invalid');
            $clientNote=$g('predefined_clientnote_invoice');
            if(strlen($clientNote)>20000||preg_match('//u',$clientNote)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$clientNote))throw new DomainException('company_sales_invalid');
            $context['native_invoice_defaults']=['format'=>1,'clientnote'=>$clientNote];
        }
        return $context;
    }
    protected static function encode(array $data):string{return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    protected static function hash(array $data):string{return hash('sha256',self::encode($data));}
    protected function assertOrigin(int $issuer,int $actor,array $payload,string $originHash,array $context=[]):void
    {
        $oldFormat=$context['sales_preferences']['format']??null;$current=$this->context($issuer,$actor,(int)$payload['client_id'],(int)$payload['currency'],$context['sales_preferences']['kind']??'invoices',is_int($oldFormat)&&$oldFormat<=3,array_key_exists('native_invoice_defaults',$context));
        // Read and conversion of version 1 documents retain their exact original hash contract.
        if(($context['sales_preferences']['format']??null)===1){unset($current['sales_preferences']['pdf']);$current['sales_preferences']['format']=1;}
        if(!hash_equals($originHash,self::hash($current)))throw new DomainException('company_sales_origin_changed');
    }
    /** A committed canonical receipt can be finalized after configuration changes. Its immutable document snapshot must match the frozen origin. */
    protected function assertOriginOrReceipt(int $issuer,int $actor,array $payload,string $originHash,array $context,string $key,bool $allowUnrelatedSettings=false):void
    {
        $receipt=$this->rows('SELECT r.issuer_id,r.actor_id,r.invoice_id,d.legal_identity_json AS identity_json,d.recipient_identity_json AS recipient_json FROM '.$this->prefix.'company_invoice_requests r JOIN '.$this->prefix.'invoices i ON i.id=r.invoice_id AND i.issuer_id=r.issuer_id JOIN '.$this->prefix.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE r.request_key=? FOR UPDATE',[$key])[0]??null;
        if(!$receipt){
            if(!$allowUnrelatedSettings){$this->assertOrigin($issuer,$actor,$payload,$originHash,$context);return;}
            $format=$context['sales_preferences']['format']??null;$current=$this->context($issuer,$actor,(int)$payload['client_id'],(int)$payload['currency'],$context['sales_preferences']['kind']??'invoices',is_int($format)&&$format<=3,array_key_exists('native_invoice_defaults',$context));
            if($format===1){unset($current['sales_preferences']['pdf']);$current['sales_preferences']['format']=1;}
            // The automation issues a local invoice, not a message. Every fiscal,
            // recipient, currency and document preference remains exact; only the
            // digest of unrelated company options and transport may differ.
            unset($current['configuration_hash'],$context['configuration_hash']);if(!hash_equals(self::hash($context),self::hash($current)))throw new DomainException('company_sales_origin_changed');return;
        }
        if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||!hash_equals(self::hash(CompanyInvoicePresentation::fiscalIdentity($this->decode($receipt['identity_json']))),self::hash(CompanyInvoicePresentation::fiscalIdentity($context['identity'])))||!hash_equals(self::hash($this->decode($receipt['recipient_json'])),self::hash($context['recipient'])) )throw new DomainException('company_sales_origin_changed');
    }
    protected function decode(string $json):array{$value=json_decode($json,true,32,JSON_THROW_ON_ERROR);if(!is_array($value))throw new RuntimeException('issuer_sales_write_unconfirmed');return $value;}
}
