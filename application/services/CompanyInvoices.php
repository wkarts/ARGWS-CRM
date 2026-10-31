<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/InvoiceIssuerDocuments.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/InvoicePaymentWriter.php';
require_once __DIR__.'/CompanyInvoiceAmounts.php';
require_once __DIR__.'/CompanyInvoiceLines.php';
require_once __DIR__.'/CompanyInvoiceNativeFields.php';
require_once __DIR__.'/CompanyInvoiceNativePreferences.php';
require_once __DIR__.'/CompanyInvoiceProjectInput.php';
require_once __DIR__.'/CompanyInvoiceProjectSchema.php';
require_once __DIR__.'/CompanyProjects.php';
require_once __DIR__.'/CompanyAssets.php';
require_once __DIR__.'/CompanyCurrencyUnits.php';
require_once __DIR__.'/CompanyFinancialJournal.php';
require_once __DIR__.'/CompanyInvoiceProviderTables.php';
require_once __DIR__.'/CompanyInvoiceBalance.php';

/** Native named-company invoices; legacy models, providers and hooks are never invoked by this adapter. */
final class CompanyInvoices
{
    private const MAX=999999999999999;
    private ?array $nativeCreatedLines = null;
    public function __construct(private $db,private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
    }
    private function rows(string $sql,array $params=[]):array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    private function authorize(int $issuer,int $actor,string $cap):void
    {
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'invoices',$cap))throw new DomainException('company_invoice_access_denied');
    }
    private function documents(int $issuer,int $actor):InvoiceIssuerDocuments
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);
        return new InvoiceIssuerDocuments($this->db,$this->prefix,$actor,static fn($id,$staff,$cap)=>$id===$issuer&&$staff===$actor&&$acl->can($issuer,$actor,'invoices',$cap==='create_invoices'?'create':'view'));
    }
    private function profile(int $issuer,int $actor,string $invoiceDate):array
    {
        $store=new CompanySettingsStorage($this->db,$this->prefix);$get=fn($name)=>$store->get($issuer,$name);
        $padding=$store->exists($issuer,'number_padding_prefixes')?$get('number_padding_prefixes'):'6';
        $format=$store->exists($issuer,'invoice_number_format')?$get('invoice_number_format'):'1';
        if(!preg_match('/^(?:0|[1-9][0-9]?)$/D',$padding)||!in_array($format,['1','2','3','4'],true))throw new DomainException('company_invoice_number_preferences_invalid');
        return ['address'=>CompanySettingsIdentity::documentAddress($get),'city'=>$get('invoice_company_city'),'state'=>$get('company_state'),
            'postcode'=>$get('invoice_company_postal_code'),'country_code'=>strtoupper($get('invoice_company_country_code')),
            'phone'=>$get('invoice_company_phonenumber'),'email'=>$get('smtp_email'),'prefix'=>$store->exists($issuer,'invoice_prefix')?$get('invoice_prefix'):'INV-',
            'terms'=>$get('predefined_terms_invoice'),'padding'=>(int)$padding,'number_format'=>(int)$format,'number_date'=>$invoiceDate,'pdf_preferences'=>CompanyInvoicePresentation::capture($store,$issuer,'invoices')]+(new CompanyAssets($this->db,$this->prefix))->invoiceProfileInOwnedTransaction($issuer,$actor);
    }
    private static function text($value,int $max,bool $required=false):string
    {
        if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)||($required&&trim($value)===''))throw new DomainException('company_invoice_invalid_input');
        return trim($value);
    }
    private static function date($value):string
    {
        $value=self::text($value,10,true);$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('company_invoice_invalid_input');return $value;
    }
    private static function cents($value):int
    {
        try{return \app\services\InvoicePaymentWriter::minorUnits($value);}catch(Throwable $e){throw new DomainException('company_invoice_invalid_input');}
    }
    public static function decimal(int $value):string{return CompanyInvoiceAmounts::decimal($value);}
    private static function input(array $input):array
    {
        $optionNames=['discount_mode','discount_value','discount_type','adjustment','calculation_contract'];
        if(array_diff(array_keys($input),array_merge(['client_id','date','due_date','currency','items','note','native_fields','native_project','native_preferences'],$optionNames))
            ||!isset($input['items'])||!is_array($input['items']))throw new DomainException('company_invoice_invalid_input');
        $amounts=CompanyInvoiceLines::calculate($input['items'],array_intersect_key($input,array_flip($optionNames)));
        $date=self::date($input['date']??null);$due=($input['due_date']??null)===null||($input['due_date']??null)===''?null:self::date($input['due_date']);
        if($due!==null&&$due<$date)throw new DomainException('company_invoice_invalid_input');
        return array_merge($amounts,['client_id'=>IssuerPolicy::id($input['client_id']??null),'date'=>$date,'due_date'=>$due,
            'currency'=>IssuerPolicy::id($input['currency']??null),'note'=>self::text($input['note']??'',20000)],
            array_key_exists('native_fields',$input)?['native_fields'=>CompanyInvoiceNativeFields::input($input['native_fields'])]:[],
            array_key_exists('native_project',$input)?['native_project'=>CompanyInvoiceProjectInput::normalize($input['native_project'])]:[],array_key_exists('native_preferences',$input)?['native_preferences'=>CompanyInvoiceNativePreferences::input($input['native_preferences'])]:[]);
    }
    public function transactionParticipants(array $extraTables=[]):array
    {
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_client_links','issuers_audit','staff','clients','invoices','issuers_invoice_documents','issuers_sequences','itemable','item_tax','currencies','countries','options','company_invoice_requests','invoicepaymentrecords','credits'];
        $tables=array_merge($tables,CompanyInvoiceProviderTables::current($this->db,$this->prefix));
        if($this->db->table_exists($this->prefix.CompanyInvoiceProjectSchema::TABLE)){CompanyInvoiceProjectSchema::assertValid($this->db,$this->prefix);$tables=array_merge($tables,[CompanyInvoiceProjectSchema::TABLE],CompanyProjects::ownedReferenceTables());}
        $tables=array_values(array_unique(array_merge($tables,$extraTables,(new CompanyCurrencyUnits($this->db,$this->prefix))->tables(),(new CompanyFinancialJournal($this->db,$this->prefix))->tables('invoice'),(new CompanyInvoiceBalance($this->db,$this->prefix))->tables())));
        return $tables;
    }
    private function transaction(callable $operation,array $extraTables=[])
    {return IssuerTransaction::run($this->db,$this->prefix,$this->transactionParticipants($extraTables),$operation);}
    public function currencies($issuer,$actor):array
    {
        $this->authorize(IssuerPolicy::id($issuer),IssuerPolicy::id($actor),'view');
        return $this->rows('SELECT id,name FROM '.$this->prefix.'currencies ORDER BY id');
    }
    /** Optional origin guard only vetoes an issue; it never grants authority. Runs under the canonical writer lock. */
    public function create($issuer,$actor,array $input,string $requestKey,?callable $originGuard=null,array $guardTables=[]):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_invoice_invalid_input');
        $nativeModes=$data['native_preferences']['allowed_payment_modes']??null;if($nativeModes!==null){require_once __DIR__.'/NativeCompanyInvoicePaymentChoices.php';$guardTables=array_values(array_unique(array_merge($guardTables,NativeCompanyInvoicePaymentChoices::validationTables($nativeModes))));}
        $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));$legacyHash=$this->legacyRequestHash($data,$input);
        return $this->transaction(function()use($issuer,$actor,$data,$requestKey,$hash,$legacyHash,$originGuard,$nativeModes):int{
            $this->authorize($issuer,$actor,'create');$this->authorize($issuer,$actor,'view');
            if($originGuard!==null)$originGuard();
            $receipt=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_requests WHERE request_key=? FOR UPDATE',[$requestKey])[0]??null;
            if($receipt){
                if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||(!hash_equals($receipt['input_hash'],$hash)&&($legacyHash===null||!hash_equals($receipt['input_hash'],$legacyHash))))throw new DomainException('company_invoice_request_conflict');
                $storedInvoice=$this->rows('SELECT id,currency FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$receipt['invoice_id'],$issuer])[0]??null;if(!$storedInvoice)throw new RuntimeException('issuer_invoice_write_unconfirmed');(new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($storedInvoice['currency']);
                if($originGuard!==null)$originGuard();
                return (int)$receipt['invoice_id'];
            }
            $client=$data['client_id'];
            if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'customers','view'))throw new DomainException('company_invoice_recipient_denied');
            if(!$this->rows('SELECT c.userid FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND (o.owner_issuer_id=? OR EXISTS(SELECT 1 FROM '.$this->prefix.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)) FOR UPDATE',[$client,$issuer,$issuer]))throw new DomainException('company_invoice_recipient_denied');
            $unit=(new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($data['currency']);
            if($nativeModes!==null)(new NativeCompanyInvoicePaymentChoices($this->db,$this->prefix))->validateInOwned($issuer,$actor,$nativeModes);
            $issuedStatus=isset($data['calculation_contract'])&&self::cents($data['total'])===0?2:1;
            $fields=['issuer_id'=>$issuer,'clientid'=>$client,'number'=>0,'prefix'=>'','number_format'=>1,'datecreated'=>date('Y-m-d H:i:s'),'date'=>$data['date'],'duedate'=>$data['due_date'],'currency'=>$data['currency'],'subtotal'=>$data['subtotal'],'total_tax'=>$data['tax'],'total'=>$data['total'],'adjustment'=>$data['adjustment'],'addedfrom'=>$actor,'hash'=>bin2hex(random_bytes(16)),'status'=>$issuedStatus,'clientnote'=>$data['note'],'discount_type'=>$data['discount_type'],'discount_percent'=>$data['discount_percent'],'discount_total'=>$data['discount_total'],'include_shipping'=>0,'show_shipping_on_invoice'=>0,'cancel_overdue_reminders'=>1,'allowed_payment_modes'=>serialize($nativeModes??[]),'billing_street'=>'','billing_city'=>'','billing_state'=>'','billing_zip'=>'','billing_country'=>0,'shipping_street'=>'','shipping_city'=>'','shipping_state'=>'','shipping_zip'=>'','shipping_country'=>0];
            $this->insert('invoices',$fields);$id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $doc=$this->documents($issuer,$actor)->allocateInOwnedTransaction($id,$issuer,'INV',$this->profile($issuer,$actor,$data['date']));
            if(strlen($doc['number'])>10||(int)$doc['number']>2147483647)throw new DomainException('company_invoice_sequence_exhausted');
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'invoices SET number=?,prefix=?,formatted_number=?,number_format=?,terms=? WHERE id=? AND issuer_id=?',[$doc['number'],$doc['prefix'],$doc['formatted_number'],$doc['identity']['number_format']??1,$doc['identity']['terms'],$id,$issuer]);
            if(isset($data['native_fields']))$this->writeNativeFields($issuer,$actor,$id,$data['native_fields']);
            if(array_key_exists('native_project',$data))$this->writeNativeProject($issuer,$actor,$id,$client,$data['native_project']);
            $createdLineIds=$this->items($id,$data['items']);$this->confirmLines($id,$data['items'],$data['calculation_contract']??null);
            $this->insert('company_invoice_requests',['request_key'=>$requestKey,'issuer_id'=>$issuer,'actor_id'=>$actor,'input_hash'=>$hash,'invoice_id'=>$id,'created_at'=>date('Y-m-d H:i:s')]);
            $receipt=$this->rows('SELECT invoice_id,input_hash FROM '.$this->prefix.'company_invoice_requests WHERE request_key=?',[$requestKey])[0]??[];
            if((int)($receipt['invoice_id']??0)!==$id||($receipt['input_hash']??null)!==$hash)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $count=$this->rows("SELECT COUNT(*) n FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice'",[$id])[0]['n']??0;
            if((int)$count!==count($data['items']))throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $this->audit($issuer,$actor,$id,'company_invoice_created');$this->authorize($issuer,$actor,'create');
            $stored=$this->rows('SELECT issuer_id,total,formatted_number FROM '.$this->prefix.'invoices WHERE id=?',[$id])[0]??[];
            if((int)($stored['issuer_id']??0)!==$issuer||self::cents($stored['total']??null)!==self::cents($data['total'])||($stored['formatted_number']??null)!==$doc['formatted_number'])throw new RuntimeException('issuer_invoice_write_unconfirmed');
            if(isset($data['native_preferences'])){
                require_once __DIR__.'/NativeCompanyInvoiceCommunicationState.php';
                $this->nativeCreatedLines=['issuer'=>$issuer,'actor'=>$actor,'invoice'=>$id,'request_key'=>$requestKey,'items'=>$data['items'],'ids'=>$createdLineIds,'calculation_contract'=>$data['calculation_contract']??null];
                try{(new NativeCompanyInvoiceCommunicationState($this->db,$this->prefix))->setCreatedPreferenceInOwned($issuer,$actor,$id,$data['native_preferences']['cancel_overdue_reminders'],$this);}finally{$this->nativeCreatedLines=null;}
            }
            $this->journal($issuer,$actor,$id,$data['currency'],'invoice_issued',[],['date'=>$data['date'],'total'=>$data['total'],'status'=>$issuedStatus,'revision'=>$this->fingerprint($issuer,$id)]+(isset($data['calculation_contract'])?['calculation_contract'=>$data['calculation_contract']]:[]),$data['date'],hash('sha256','company-journal:invoice-issued:'.$requestKey));
            if($originGuard!==null){
                $this->nativeCreatedLines=['issuer'=>$issuer,'actor'=>$actor,'invoice'=>$id,'request_key'=>$requestKey,'items'=>$data['items'],'ids'=>$createdLineIds,'calculation_contract'=>$data['calculation_contract']??null];
                try{$originGuard();}finally{$this->nativeCreatedLines=null;}
            }
            if(isset($data['native_preferences'])){
                $preference=$this->rows('SELECT cancel_overdue_reminders FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$id,$issuer])[0]??null;
                if(!$preference||(int)$preference['cancel_overdue_reminders']!==$data['native_preferences']['cancel_overdue_reminders'])throw new RuntimeException('issuer_native_invoice_communication_unconfirmed');
            }
            if($nativeModes!==null){(new NativeCompanyInvoicePaymentChoices($this->db,$this->prefix))->validateInOwned($issuer,$actor,$nativeModes);$storedModes=$this->rows('SELECT allowed_payment_modes FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$id,$issuer])[0]??[];if(($storedModes['allowed_payment_modes']??null)!==serialize($nativeModes))throw new RuntimeException('issuer_native_invoice_payment_modes_unconfirmed');}
            if(isset($data['native_fields']))$this->confirmNativeFields($issuer,$actor,$id,$data['native_fields']);
            if(array_key_exists('native_project',$data))$this->confirmNativeProject($issuer,$actor,$id,$client,$data['native_project']);
            $this->confirmLines($id,$data['items'],$data['calculation_contract']??null);if($this->documents($issuer,$actor)->get($id,$issuer)!==$doc)throw new RuntimeException('issuer_invoice_write_unconfirmed');return $id;
        },$guardTables);
    }
    private function nativeProjectRow(int $issuer,int $invoice):?array
    {
        $rows=$this->rows('SELECT issuer_id,invoice_id,client_id,source_kind,source_id,source_revision FROM '.$this->prefix.CompanyInvoiceProjectSchema::TABLE.' WHERE invoice_id=?',[$invoice]);
        if(!$rows)return null;
        if(count($rows)!==1||(int)$rows[0]['issuer_id']!==$issuer)throw new DomainException('company_invoice_project_review');
        $row=$rows[0];
        foreach(['issuer_id','invoice_id','client_id','source_id','source_revision']as$key)$row[$key]=IssuerPolicy::id($row[$key]);
        CompanyInvoiceProjectInput::normalize(array_intersect_key($row,array_flip(['source_kind','source_id','source_revision'])));
        return $row;
    }
    /** Veto-only reference validation; never writes, grants authority or opens a transaction. */
    public function validateProjectInOwnedTransaction($issuer,$actor,$client,$input):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);
        $this->authorize($issuer,$actor,'view');
        $this->projectReference($issuer,$actor,$client,CompanyInvoiceProjectInput::normalize($input));
        $this->authorize($issuer,$actor,'view');
    }
    private function projectReference(int $issuer,int $actor,int $client,?array $input):void
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,array_merge([CompanyInvoiceProjectSchema::TABLE,'invoices'],CompanyProjects::ownedReferenceTables()));
        if($input===null)return;
        $project=(new CompanyProjects($this->db,$this->prefix))->getInOwnedTransaction($issuer,$actor,$input['source_id']);
        if((int)$project['client_id']!==$client||(int)$project['revision']!==$input['source_revision']||$project['status']!=='active')throw new DomainException('company_invoice_project_stale');
        // A revoked directory link may preserve historical documents but cannot authorize a new association.
        $recipient=(new CompanyClients($this->db,$this->prefix))->get($issuer,$actor,$client);
        if(!$recipient||(int)$recipient['active']!==1)throw new DomainException('company_invoice_recipient_denied');
    }
    private function writeNativeProject(int $issuer,int $actor,int $invoice,int $client,?array $input):void
    {
        $this->projectReference($issuer,$actor,$client,$input);
        $this->nativeProjectRow($issuer,$invoice); // A forged cross-issuer row is not overwritten.
        IssuerStorage::query($this->db,'DELETE FROM '.$this->prefix.CompanyInvoiceProjectSchema::TABLE.' WHERE issuer_id=? AND invoice_id=?',[$issuer,$invoice]);
        if($input!==null)$this->insert(CompanyInvoiceProjectSchema::TABLE,['issuer_id'=>$issuer,'invoice_id'=>$invoice,'client_id'=>$client]+$input);
        $this->confirmNativeProject($issuer,$actor,$invoice,$client,$input);
    }
    private function confirmNativeProject(int $issuer,int $actor,int $invoice,int $client,?array $input):void
    {
        $this->projectReference($issuer,$actor,$client,$input);
        $expected=$input===null?null:['issuer_id'=>$issuer,'invoice_id'=>$invoice,'client_id'=>$client]+$input;
        if($this->nativeProjectRow($issuer,$invoice)!==$expected)throw new RuntimeException('issuer_invoice_write_unconfirmed');
    }
    private function writeNativeFields(int $issuer, int $actor, int $invoice, array $input): void
    {
        $db=$this->db; $prefix=$this->prefix;
        IssuerTransaction::assertOwned($db, $prefix, ['issuers_lock','invoices','countries']);
        $input = CompanyInvoiceNativeFields::input($input);
        if (!$input) { return; }
        if(array_key_exists('sale_agent',$input)){require_once __DIR__.'/NativeCompanyInvoiceAgents.php';(new NativeCompanyInvoiceAgents($db,$prefix))->validate($issuer,$actor,$input['sale_agent']);}
        foreach (['billing_country','shipping_country'] as $field) {
            if (!empty($input[$field]) && !IssuerStorage::query($db, 'SELECT country_id FROM '.$prefix.'countries WHERE country_id=?', [$input[$field]])->result_array()) {
                throw new DomainException('company_invoice_native_fields_invalid');
            }
        }
        $columns = array_keys($input);
        IssuerStorage::query($db, 'UPDATE '.$prefix.'invoices SET '.implode(',', array_map(static fn($field)=>$field.'=?', $columns))
            .' WHERE id=? AND issuer_id=?', array_merge(array_values($input), [$invoice,$issuer]));
        $this->confirmNativeFields($issuer,$actor,$invoice,$input);
    }
    private function confirmNativeFields(int $issuer,int $actor,int $invoice,array $input):void
    {
        if(array_key_exists('sale_agent',$input)){require_once __DIR__.'/NativeCompanyInvoiceAgents.php';(new NativeCompanyInvoiceAgents($this->db,$this->prefix))->validate($issuer,$actor,$input['sale_agent']);}
        $input=CompanyInvoiceNativeFields::input($input);if(!$input)return;
        $read=$this->rows('SELECT '.implode(',',array_keys($input)).' FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer]);
        if(count($read)!==1||CompanyInvoiceNativeFields::input($read[0])!==$input)throw new RuntimeException('issuer_invoice_write_unconfirmed');
    }
    /** Pre-417 receipts remain retryable; no monetary option is silently discarded. */
    private function legacyRequestHash(array $data,array $input):?string
    {
        if(array_key_exists('calculation_contract',$data)||array_key_exists('native_fields',$data)||array_key_exists('native_project',$data)||array_key_exists('native_preferences',$data))return null;
        if($data['discount_total']!=='0.00'||$data['adjustment']!=='0.00'||$data['discount_percent']!=='0.00'||$data['discount_type']!=='after_tax')return null;
        $items=[];
        foreach($data['items'] as $i=>$item){
            if(CompanyInvoiceLines::metadata($item)!==[])return null;
            if(count($item['taxes'])>1)return null;
            $tax=$item['taxes'][0]??['name'=>trim((string)($input['items'][$i]['taxname']??'')),'rate'=>'0.00'];
            $items[]=['description'=>$item['description'],'qty'=>$item['qty'],'rate'=>$item['rate'],'taxrate'=>$tax['rate'],'taxname'=>$tax['name']];
        }
        return hash('sha256',json_encode(['client_id'=>$data['client_id'],'date'=>$data['date'],'due_date'=>$data['due_date'],'currency'=>$data['currency'],
            'items'=>$items,'note'=>$data['note'],'subtotal'=>$data['subtotal'],'tax'=>$data['tax'],'total'=>$data['total']],JSON_THROW_ON_ERROR));
    }
    private function insert(string $table,array $fields):void
    {
        IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.$table.' (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
    }
    /** Parent is checked by the caller before any body; exact canonical rows are bounded and normalized. */
    private function storedLines(int $invoice,?string $contract=null):array
    {
        $bounds=$this->rows("SELECT (SELECT COUNT(*) FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice') items,(SELECT COUNT(*) FROM ".$this->prefix."item_tax WHERE rel_id=? AND rel_type='invoice') taxes",[$invoice,$invoice])[0];if((int)$bounds['items']<1||(int)$bounds['items']>100||(int)$bounds['taxes']>1000)throw new DomainException('company_invoice_invalid_input');$rows=$this->rows("SELECT id,description,qty,rate,long_description,unit,is_optional,is_selected,item_order FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id LIMIT 101 FOR UPDATE",[$invoice]);$taxes=$this->rows("SELECT t.itemid,t.taxname,t.taxrate FROM ".$this->prefix."item_tax t JOIN ".$this->prefix."itemable i ON i.id=t.itemid AND i.rel_id=t.rel_id AND i.rel_type=t.rel_type WHERE t.rel_id=? AND t.rel_type='invoice' ORDER BY t.id LIMIT 1001 FOR UPDATE",[$invoice]);if(count($rows)!==(int)$bounds['items']||count($taxes)!==(int)$bounds['taxes'])throw new RuntimeException('issuer_invoice_write_unconfirmed');$by=[];foreach($taxes as $tax)$by[(int)$tax['itemid']][]=['name'=>$tax['taxname'],'rate'=>(string)$tax['taxrate']];$input=[];foreach($rows as $row)$input[]=['description'=>$row['description'],'qty'=>(string)$row['qty'],'rate'=>(string)$row['rate'],'taxes'=>$by[(int)$row['id']]??[]]+CompanyInvoiceLines::metadata($row,true);return CompanyInvoiceLines::normalize($input,$contract);
    }
    private function confirmLines(int $invoice,array $expected,?string $contract=null):void{if($this->storedLines($invoice,$contract)!==$expected)throw new RuntimeException('issuer_invoice_write_unconfirmed');}
    /** Complete staff editor, including unselected optional rows which the PDF intentionally excludes. */
    public function editorItems($issuer,$actor,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);return $this->transaction(function()use($issuer,$actor,$invoice):array{$this->authorize($issuer,$actor,'view');$this->authorize($issuer,$actor,'edit');if(!$this->rows('SELECT i.id FROM '.$this->prefix.'invoices i JOIN '.$this->prefix.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer]))throw new DomainException('company_invoice_access_denied');$lines=$this->storedLines($invoice,$this->calculationOptions($issuer,$actor,$invoice)['calculation_contract']??null);$this->authorize($issuer,$actor,'view');$this->authorize($issuer,$actor,'edit');if($lines!==$this->storedLines($invoice,$this->calculationOptions($issuer,$actor,$invoice)['calculation_contract']??null))throw new RuntimeException('issuer_invoice_write_unconfirmed');return $lines;});
    }
    /** Same persisted rows as the editor, including unselected optional lines, with view-only authority. */
    public function previewItems(int $issuer,int $actor,int $invoice):array
    {
        $this->authorize($issuer,$actor,'view');
        return $this->transaction(function()use($issuer,$actor,$invoice){
            $this->authorize($issuer,$actor,'view');if(!$this->rows('SELECT id FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer]))throw new DomainException('company_invoice_access_denied');
            $lines=$this->storedLines($invoice,$this->calculationOptions($issuer,$actor,$invoice)['calculation_contract']??null);$ids=$this->rows("SELECT id FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$invoice]);
            if(count($ids)!==count($lines))throw new RuntimeException('issuer_invoice_write_unconfirmed');$out=[];
            foreach($lines as$index=>$line)$out[]=['id'=>(int)$ids[$index]['id']]+$line;
            $this->authorize($issuer,$actor,'view');if($this->storedLines($invoice,$this->calculationOptions($issuer,$actor,$invoice)['calculation_contract']??null)!==$lines)throw new RuntimeException('issuer_invoice_write_unconfirmed');return$out;
        });
    }
    private function items(int $invoice,array $items):array
    {
        $created=[];
        foreach($items as $index=>$item){
            $this->insert('itemable',['rel_id'=>$invoice,'rel_type'=>'invoice','description'=>$item['description'],'long_description'=>$item['long_description']??'','qty'=>$item['qty'],'rate'=>$item['rate'],'unit'=>$item['unit']??'','is_optional'=>$item['is_optional']??0,'is_selected'=>$item['is_selected']??1,'item_order'=>$index+1]);
            $id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $created[]=$id;
            foreach($item['taxes'] as $tax)$this->insert('item_tax',['itemid'=>$id,'rel_id'=>$invoice,'rel_type'=>'invoice','taxrate'=>$tax['rate'],'taxname'=>$tax['name']]);
        }
        return $created;
    }
    /** Only available while this exact writer invokes its post-create compositor, never on replay. */
    public function createdLineIdsInOwned(int $issuer,int $actor,int $invoice,string $requestKey):array
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,['invoices','itemable','item_tax','company_invoice_requests']);
        $frame=$this->nativeCreatedLines;
        if(!$frame||$frame['issuer']!==$issuer||$frame['actor']!==$actor||$frame['invoice']!==$invoice||!hash_equals($frame['request_key'],$requestKey))throw new DomainException('company_invoice_creation_proof_required');
        $this->authorize($issuer,$actor,'view');$this->authorize($issuer,$actor,'create');
        $this->confirmLines($invoice,$frame['items'],$frame['calculation_contract']??null);
        $actual=$this->rows("SELECT id FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$invoice]);
        if(array_map('intval',array_column($actual,'id'))!==$frame['ids'])throw new RuntimeException('issuer_invoice_write_unconfirmed');
        return array_map(static fn($id,$item)=>['itemable_id'=>$id,'line'=>$item,'line_hash'=>hash('sha256',json_encode($item,JSON_THROW_ON_ERROR))],$frame['ids'],$frame['items']);
    }
    /** Exact instance/connection/transaction proof for native metadata on the newly inserted document only. */
    public function assertCreatedInOwned($db,string $prefix,int $issuer,int $actor,string $kind,int $id):void
    {
        if($db!==$this->db||$prefix!==$this->prefix||!in_array($kind,['invoice','invoice_item'],true)||$id<1)throw new DomainException('company_invoice_creation_proof_required');
        $frame=$this->nativeCreatedLines;
        if(!$frame)throw new DomainException('company_invoice_creation_proof_required');
        $lines=$this->createdLineIdsInOwned($issuer,$actor,$frame['invoice'],$frame['request_key']);
        if(($kind==='invoice'&&$id!==$frame['invoice'])||($kind==='invoice_item'&&!in_array($id,array_column($lines,'itemable_id'),true)))throw new DomainException('company_invoice_creation_proof_required');
    }
    public function document($issuer,$actor,$invoice):InvoiceIssuerDocumentData
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');
        return $this->documents($issuer,$actor)->forPdf($invoice,$issuer);
    }
    public function documentInOwned($issuer,$actor,$invoice):InvoiceIssuerDocumentData
    {
        require_once __DIR__.'/issuers/InvoiceIssuerDocumentData.php';$issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$this->authorize($issuer,$actor,'view');return InvoiceIssuerDocumentData::fromInvoiceInOwned($this->documents($issuer,$actor),$invoice,$issuer);
    }
    public function page($issuer,$actor,$after=0):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);if($after!==0)$after=IssuerPolicy::id($after);$this->authorize($issuer,$actor,'view');
        $rows=$this->rows('SELECT i.id,i.date,i.duedate,i.total,i.status,i.currency currency_id,d.formatted_number FROM '.$this->prefix.'invoices i JOIN '.$this->prefix.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.issuer_id=? AND i.id>? ORDER BY i.id LIMIT 51',[$issuer,$after]);
        foreach($rows as &$row){$unit=(new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($row['currency_id']);$row['currency']=$unit['iso_code'];$row['currency_basis']=$unit['basis'];unset($row['currency_id']);}unset($row);
        $this->authorize($issuer,$actor,'view');$next=count($rows)>50?(int)$rows[49]['id']:null;return ['items'=>array_slice($rows,0,50),'next'=>$next];
    }
    public function adjustments($issuer,$actor,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$this->authorize($issuer,$actor,'view');
        $row=$this->rows('SELECT currency,discount_type,discount_percent,discount_total,adjustment FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]??null;
        if(!$row)throw new DomainException('company_invoice_access_denied');
        (new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($row['currency']);
        $this->authorize($issuer,$actor,'view');
        $percent=CompanyInvoiceAmounts::minor((string)($row['discount_percent']??'0'));
        return ['discount_type'=>$row['discount_type'],'discount_mode'=>$percent>0?'percent':'fixed',
            'discount_value'=>$percent>0?CompanyInvoiceAmounts::decimal($percent):(string)$row['discount_total'],'adjustment'=>(string)$row['adjustment']]+$this->calculationOptions($issuer,$actor,$invoice);
    }
    private function calculationOptions(int $i,int $a,int $invoice):array{require_once __DIR__.'/CompanyInvoiceCalculationContract.php';return CompanyInvoiceCalculationContract::options($this->db,$this->prefix,$i,$a,$invoice);}
    /** Current position keeps provider reductions apart from cash and the issued invoice total. */
    public function position($issuer,$actor,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);return $this->transaction(function()use($issuer,$actor,$invoice):array{$this->authorize($issuer,$actor,'view');return (new CompanyInvoiceBalance($this->db,$this->prefix))->inOwned($issuer,$actor,$invoice);});
    }
    public function payments($issuer,$actor,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$this->authorize($issuer,$actor,'view');
        return $this->transaction(fn()=>$this->paymentsInOwned($issuer,$actor,$invoice));
    }
    public function paymentsInOwned(int $issuer,int $actor,int $invoice):array
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,$this->transactionParticipants());$this->authorize($issuer,$actor,'view');
        $position=(new CompanyInvoiceBalance($this->db,$this->prefix))->inOwned($issuer,$actor,$invoice);
        $resource=$this->rows('SELECT currency FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]??null;if(!$resource)throw new DomainException('company_invoice_access_denied');(new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($resource['currency']);
        $rows=$this->rows('SELECT p.id,p.amount,p.date,p.paymentmode,p.paymentmethod,p.transactionid,p.note FROM '.$this->prefix.'invoicepaymentrecords p JOIN '.$this->prefix.'invoices i ON i.id=p.invoiceid WHERE p.invoiceid=? AND i.issuer_id=? ORDER BY p.id',[$invoice,$issuer]);
        foreach($rows as &$row){$row['bank_components']=$position['components'][(int)$row['id']]??null;$reversed=0;$row['reversals']=[];
            if($this->db->table_exists($this->prefix.'company_credit_entries'))$row['reversals']=$this->rows("SELECT amount,book_date,reference,reason FROM {$this->prefix}company_credit_entries WHERE issuer_id=? AND invoice_id=? AND kind='manual_reverse' AND native_id=? ORDER BY created_at,request_key",[$issuer,$invoice,$row['id']]);
            foreach($row['reversals'] as $entry){$n=CompanyInvoiceAmounts::minor((string)$entry['amount']);if($n>999999999999999-$reversed)throw new DomainException('company_credit_balance_review');$reversed+=$n;}
            $row['bank_reversal']=$position['reversals'][(int)$row['id']]??null;$externalReversed=$row['bank_reversal']===null?0:CompanyInvoiceAmounts::minor($row['bank_reversal']['cash_principal_reversed']);$remaining=CompanyInvoiceAmounts::minor((string)$row['amount']);if($remaining>999999999999999-$reversed)throw new DomainException('company_credit_balance_review');$row['original_amount']=CompanyInvoiceAmounts::decimal($remaining+$reversed);if($externalReversed>$remaining)throw new DomainException('company_invoice_balance_review');$row['native_amount']=$row['amount'];$row['effective_amount']=CompanyInvoiceAmounts::decimal($remaining-$externalReversed);$row['manual_reversed_amount']=CompanyInvoiceAmounts::decimal($reversed);$row['external_reversed_amount']=CompanyInvoiceAmounts::decimal($externalReversed);$row['reversed_amount']=CompanyInvoiceAmounts::decimal($reversed+$externalReversed);
            $row['can_reverse']=$row['paymentmode']!==CompanyLocalNoncashSettlements::MODE&&CompanyInvoiceBalance::manualMode($this->db,$this->prefix,$issuer,$invoice,(int)$row['id'],$row['paymentmode'])&&CompanyInvoiceAmounts::minor((string)$row['amount'])>0&&(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'payments','delete');
        }unset($row);
        $this->authorize($issuer,$actor,'view');return $rows;
    }
    public function confirmPdfDownload($issuer,$actor,$invoice):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);
        $this->transaction(function()use($issuer,$actor,$invoice):void{
            $this->authorize($issuer,$actor,'view');
            $resource=$this->rows('SELECT currency FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;if(!$resource)throw new DomainException('company_invoice_access_denied');(new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($resource['currency']);
            $this->audit($issuer,$actor,$invoice,'company_invoice_pdf_downloaded');$id=(int)$this->db->insert_id();
            if($id<1||count($this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=?',[$id,$issuer,$actor,$invoice]))!==1)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $this->authorize($issuer,$actor,'view');
        });
    }
    public function revision($issuer,$actor,$invoice):string
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$this->authorize($issuer,$actor,'view');
        return $this->fingerprint($issuer,$invoice);
    }
    /** Financial command revision also changes between two partial receipts/reversals. */
    public function paymentRevision($issuer,$actor,$invoice):string
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);
        $revision=$this->revision($issuer,$actor,$invoice);
        $payments=$this->rows('SELECT id,amount,date,paymentmode,paymentmethod,transactionid,note FROM '.$this->prefix.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$invoice]);
        $credits=$this->rows('SELECT id,credit_id,amount FROM '.$this->prefix.'credits WHERE invoice_id=? ORDER BY id',[$invoice]);
        $this->authorize($issuer,$actor,'view');
        return hash('sha256',json_encode([$revision,$payments,$credits],JSON_THROW_ON_ERROR));
    }
    private function fingerprint(int $issuer,int $invoice):string
    {
        $row=$this->rows('SELECT id,clientid,currency,date,duedate,subtotal,total_tax,discount_type,discount_percent,discount_total,adjustment,total,status,clientnote,adminnote,terms,show_quantity_as,billing_street,billing_city,billing_state,billing_zip,billing_country,shipping_street,shipping_city,shipping_state,shipping_zip,shipping_country,include_shipping,show_shipping_on_invoice,allowed_payment_modes,cancel_overdue_reminders FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]??null;
        if(!$row)throw new DomainException('company_invoice_access_denied');
        (new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($row['currency']);
        $items=$this->rows("SELECT id,description,qty,rate,long_description,unit,is_optional,is_selected FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$invoice]);
        foreach($items as &$line){$extra=CompanyInvoiceLines::metadata($line,true);$line=array_intersect_key($line,array_flip(['id','description','qty','rate']))+$extra;}unset($line);
        $taxes=$this->rows("SELECT itemid,taxname,taxrate FROM ".$this->prefix."item_tax WHERE rel_id=? AND rel_type='invoice' ORDER BY id",[$invoice]);
         $frame=[$row,$items,$taxes];
        if($this->db->table_exists($this->prefix.CompanyInvoiceProjectSchema::TABLE)){$relation=$this->nativeProjectRow($issuer,$invoice);if($relation!==null)$frame[]=$relation;}
        $lifecycle=$this->rows("SELECT id FROM ".$this->prefix."issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_invoice_reopened' ORDER BY id DESC LIMIT 1",[$issuer,$invoice]);
        if($lifecycle)$frame[]=['reopen_audit_id'=>(int)$lifecycle[0]['id']];
        return hash('sha256',json_encode($frame,JSON_THROW_ON_ERROR));
    }
    public function change($issuer,$actor,$invoice,string $revision,?array $input=null,?callable $mutationGuard=null,array $guardTables=[],?array $lineMapping=null):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$data=$input===null?null:self::input($input);
        $preferences=$data['native_preferences']??null;
        if($preferences!==null&&$mutationGuard===null)throw new DomainException('company_invoice_native_preferences_creation_only');
        if($preferences!==null){require_once __DIR__.'/NativeCompanyInvoicePaymentChoices.php';$guardTables=array_values(array_unique(array_merge($guardTables,NativeCompanyInvoicePaymentChoices::validationTables($preferences['allowed_payment_modes']??[]))));}
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_invoice_stale');
        $this->transaction(function()use($issuer,$actor,$invoice,$revision,$data,$preferences,$mutationGuard,$lineMapping):void{
            $this->authorize($issuer,$actor,'edit');$this->authorize($issuer,$actor,'view');
            $row=$this->rows('SELECT id,clientid,currency,status,date,total FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
            if(!$row)throw new DomainException('company_invoice_access_denied');
            if($mutationGuard!==null&&$mutationGuard('before')===false)return;
            if($data!==null&&array_intersect_key($data,['calculation_contract'=>true])!==$this->calculationOptions($issuer,$actor,$invoice))throw new DomainException('company_invoice_calculation_contract_immutable');
            if(!$this->rows('SELECT invoice_id FROM '.$this->prefix.'company_invoice_requests WHERE invoice_id=? AND issuer_id=?',[$invoice,$issuer]))throw new DomainException('company_invoice_immutable');
            if($data===null&&(int)$row['status']===5)return; // Repeated cancellation does not erase history.
            if(!hash_equals($revision,$this->fingerprint($issuer,$invoice)))throw new DomainException('company_invoice_stale');
            if(!in_array((int)$row['status'],[1,4],true))throw new DomainException('company_invoice_immutable');
            (new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($row['currency']);
            $documentBefore=$this->documents($issuer,$actor)->get($invoice,$issuer);
            $before=['date'=>$row['date'],'total'=>self::decimal(self::cents($row['total'])),'status'=>(int)$row['status'],'revision'=>$revision];
            $providerTables=CompanyInvoiceProviderTables::current($this->db,$this->prefix);IssuerTransaction::assertOwned($this->db,$this->prefix,$providerTables);
            foreach($providerTables as $table)if($this->rows('SELECT id FROM '.$this->prefix.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new DomainException('company_invoice_provider_review_required');
            if((new CompanyBankReceiptComponents($this->db,$this->prefix))->forInvoiceInOwned($issuer,$actor,$invoice))throw new DomainException('company_invoice_immutable');
            foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column])if($this->rows('SELECT amount FROM '.$this->prefix.$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice]))throw new DomainException('company_invoice_immutable');
            if($data===null){IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'invoices SET status=5 WHERE id=? AND issuer_id=?',[$invoice,$issuer]);}
            else{
                if((int)$row['clientid']!==$data['client_id']||(int)$row['currency']!==$data['currency'])throw new DomainException('company_invoice_immutable');
                IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'invoices SET date=?,duedate=?,subtotal=?,total_tax=?,discount_type=?,discount_percent=?,discount_total=?,adjustment=?,total=?,clientnote=? WHERE id=? AND issuer_id=?',[$data['date'],$data['due_date'],$data['subtotal'],$data['tax'],$data['discount_type'],$data['discount_percent'],$data['discount_total'],$data['adjustment'],$data['total'],$data['note'],$invoice,$issuer]);
                if(isset($data['native_fields']))$this->writeNativeFields($issuer,$actor,$invoice,$data['native_fields']);
                if(array_key_exists('native_project',$data))$this->writeNativeProject($issuer,$actor,$invoice,(int)$row['clientid'],$data['native_project']);
                if($preferences!==null){
                    $modes=(new NativeCompanyInvoicePaymentChoices($this->db,$this->prefix))->validateInOwned($issuer,$actor,$preferences['allowed_payment_modes']??[]);
                    $serialized=serialize($preferences['allowed_payment_modes']??[]);
                    IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'invoices SET allowed_payment_modes=?,cancel_overdue_reminders=? WHERE id=? AND issuer_id=?',[$serialized,$preferences['cancel_overdue_reminders'],$invoice,$issuer]);
                    $saved=$this->rows('SELECT allowed_payment_modes,cancel_overdue_reminders FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]??null;
                    if(!$saved||$saved['allowed_payment_modes']!==$serialized||(int)$saved['cancel_overdue_reminders']!==$preferences['cancel_overdue_reminders'])throw new RuntimeException('issuer_invoice_write_unconfirmed');
                }
                $this->replaceItems($invoice,$data['items'],$lineMapping);
            }
            $this->audit($issuer,$actor,$invoice,$data===null?'company_invoice_cancelled':'company_invoice_updated');$this->authorize($issuer,$actor,'edit');
            $stored=$this->rows('SELECT total,status FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]??[];
            if(!$stored||($data===null?(int)$stored['status']!==5:self::cents($stored['total'])!==self::cents($data['total'])))throw new RuntimeException('issuer_invoice_write_unconfirmed');
            if($data!==null)$this->confirmLines($invoice,$data['items'],$data['calculation_contract']??null);
            $after=['date'=>$data===null?$row['date']:$data['date'],'total'=>self::decimal(self::cents($stored['total'])),'status'=>(int)$stored['status'],'revision'=>$this->fingerprint($issuer,$invoice)];
            $this->journal($issuer,$actor,$invoice,(int)$row['currency'],$data===null?'invoice_cancelled':'invoice_updated',$before,$after,$data===null?gmdate('Y-m-d'):$data['date'],hash('sha256','company-journal:invoice-'.($data===null?'cancelled':'updated').':'.$issuer.':'.$invoice.':'.$revision));
            if($data!==null&&isset($data['native_fields']))$this->confirmNativeFields($issuer,$actor,$invoice,$data['native_fields']);
            if($data!==null&&array_key_exists('native_project',$data))$this->confirmNativeProject($issuer,$actor,$invoice,(int)$row['clientid'],$data['native_project']);
            if($mutationGuard!==null)$mutationGuard('after');
            if($data!==null)$this->confirmLines($invoice,$data['items'],$data['calculation_contract']??null);if($this->documents($issuer,$actor)->get($invoice,$issuer)!==$documentBefore)throw new RuntimeException('issuer_invoice_write_unconfirmed');
        },$guardTables);
    }
    /** Original editor identities survive updates, including their inactive custom field history. */
    private function replaceItems(int $invoice,array $items,?array $mapping):void
    {
        if($mapping===null){
            IssuerStorage::query($this->db,"DELETE FROM ".$this->prefix."item_tax WHERE rel_id=? AND rel_type='invoice'",[$invoice]);
            IssuerStorage::query($this->db,"DELETE FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice'",[$invoice]);$this->items($invoice,$items);return;
        }
        if(array_keys($mapping)!==['sources','removed']||!is_array($mapping['sources'])||!array_is_list($mapping['sources'])||!is_array($mapping['removed'])||!array_is_list($mapping['removed'])||count($mapping['sources'])!==count($items))throw new DomainException('native_invoice_line_mapping_invalid');
        $actual=array_map('intval',array_column($this->rows("SELECT id FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY id FOR UPDATE",[$invoice]),'id'));
        $submitted=array_merge(array_values(array_filter($mapping['sources'],static fn($id)=>$id!==null)),$mapping['removed']);
        foreach($submitted as$id)if(!is_int($id)||$id<1)throw new DomainException('native_invoice_line_mapping_invalid');
        sort($submitted);if($submitted!==$actual||count($submitted)!==count(array_unique($submitted)))throw new DomainException('native_invoice_line_mapping_invalid');
        IssuerStorage::query($this->db,"DELETE FROM ".$this->prefix."item_tax WHERE rel_id=? AND rel_type='invoice'",[$invoice]);
        foreach($mapping['removed']as$id)IssuerStorage::query($this->db,"DELETE FROM ".$this->prefix."itemable WHERE id=? AND rel_id=? AND rel_type='invoice'",[$id,$invoice]);
        foreach($items as$position=>$item){
            $id=$mapping['sources'][$position];
            if($id===null){$id=$this->items($invoice,[$item])[0];IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'itemable SET item_order=? WHERE id=?',[$position+1,$id]);continue;}
            IssuerStorage::query($this->db,"UPDATE ".$this->prefix."itemable SET description=?,long_description=?,qty=?,rate=?,unit=?,is_optional=?,is_selected=?,item_order=? WHERE id=? AND rel_id=? AND rel_type='invoice'",[$item['description'],$item['long_description']??'',$item['qty'],$item['rate'],$item['unit']??'',$item['is_optional']??0,$item['is_selected']??1,$position+1,$id,$invoice]);
            foreach($item['taxes']as$tax)$this->insert('item_tax',['itemid'=>$id,'rel_id'=>$invoice,'rel_type'=>'invoice','taxrate'=>$tax['rate'],'taxname'=>$tax['name']]);
        }
    }
    /** Presentation order only. Exact owned item set, current content CAS, no monetary/journal rewrite. */
    public function reorderItems($issuer,$actor,$invoice,string $revision,array $orderedItemIds):string
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!array_is_list($orderedItemIds)||!$orderedItemIds)throw new DomainException('company_invoice_invalid_input');
        $ids=array_map(static fn($id)=>IssuerPolicy::id($id),$orderedItemIds);
        if(count($ids)!==count(array_unique($ids)))throw new DomainException('company_invoice_invalid_input');
        return $this->transaction(function()use($issuer,$actor,$invoice,$revision,$ids):string{
            $this->authorize($issuer,$actor,'view');$this->authorize($issuer,$actor,'edit');
            $record=$this->rows('SELECT * FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
            if(!$record)throw new DomainException('company_invoice_access_denied');
            if(!hash_equals($revision,$this->fingerprint($issuer,$invoice)))throw new DomainException('company_invoice_stale');
            if(!in_array((int)$record['status'],[1,4],true)||!$this->rows('SELECT invoice_id FROM '.$this->prefix.'company_invoice_requests WHERE invoice_id=? AND issuer_id=?',[$invoice,$issuer]))throw new DomainException('company_invoice_immutable');
            $providers=CompanyInvoiceProviderTables::current($this->db,$this->prefix);IssuerTransaction::assertOwned($this->db,$this->prefix,$providers);
            foreach($providers as$table)if($this->rows('SELECT id FROM '.$this->prefix.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new DomainException('company_invoice_provider_review_required');
            if((new CompanyBankReceiptComponents($this->db,$this->prefix))->forInvoiceInOwned($issuer,$actor,$invoice))throw new DomainException('company_invoice_immutable');
            foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']]as[$table,$column])if($this->rows('SELECT amount FROM '.$this->prefix.$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice]))throw new DomainException('company_invoice_immutable');
            (new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($record['currency']);
            $before=$this->rows("SELECT * FROM ".$this->prefix."itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id FOR UPDATE",[$invoice]);
            $current=array_map('intval',array_column($before,'id'));$expected=$current;$provided=$ids;sort($expected);sort($provided);
            if($expected!==$provided)throw new DomainException('company_invoice_item_scope_denied');
            if($current===$ids)return$revision;
            $document=$this->documents($issuer,$actor)->get($invoice,$issuer);
            $taxes=$this->rows("SELECT * FROM ".$this->prefix."item_tax WHERE rel_type='invoice' AND rel_id=? ORDER BY id",[$invoice]);
            foreach($ids as$position=>$id)IssuerStorage::query($this->db,"UPDATE ".$this->prefix."itemable SET item_order=? WHERE id=? AND rel_id=? AND rel_type='invoice'",[$position+1,$id,$invoice]);
            $after=$this->rows("SELECT * FROM ".$this->prefix."itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id FOR UPDATE",[$invoice]);
            if(array_map('intval',array_column($after,'id'))!==$ids)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $original=[];foreach($before as$row){unset($row['item_order']);$original[(int)$row['id']]=$row;}
            foreach($after as$position=>$row){if((int)$row['item_order']!==$position+1)throw new RuntimeException('issuer_invoice_write_unconfirmed');unset($row['item_order']);if($original[(int)$row['id']]!==$row)throw new RuntimeException('issuer_invoice_write_unconfirmed');}
            $next=$this->fingerprint($issuer,$invoice);$metadata=json_encode(['invoice_id'=>$invoice,'before_revision'=>$revision,'after_revision'=>$next,'ordered_item_ids'=>$ids],JSON_THROW_ON_ERROR);
            $this->insert('issuers_audit',['actor_id'=>$actor,'event'=>'company_invoice_items_reordered','issuer_id'=>$issuer,'subject_id'=>$invoice,'created_at'=>date('Y-m-d H:i:s'),'metadata_json'=>$metadata]);$audit=(int)$this->db->insert_id();
            if($audit<1||count($this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$actor,$invoice,'company_invoice_items_reordered',$metadata]))!==1)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            if($this->rows('SELECT * FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]!==$record||$this->documents($issuer,$actor)->get($invoice,$issuer)!==$document)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            if($this->rows("SELECT * FROM ".$this->prefix."item_tax WHERE rel_type='invoice' AND rel_id=? ORDER BY id",[$invoice])!==$taxes)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $this->authorize($issuer,$actor,'edit');$this->authorize($issuer,$actor,'view');return$next;
        });
    }

    /** Undo only a proven canonical local cancellation; all provider/receipt history stays fenced. */
    public function reopen($issuer,$actor,$invoice,string $revision):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_invoice_stale');
        $this->transaction(function()use($issuer,$actor,$invoice,$revision):void{
            $this->authorize($issuer,$actor,'edit');$this->authorize($issuer,$actor,'view');
            $row=$this->rows('SELECT id,currency,status,date,total FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
            if(!$row)throw new DomainException('company_invoice_access_denied');
            $providerTables=CompanyInvoiceProviderTables::current($this->db,$this->prefix);IssuerTransaction::assertOwned($this->db,$this->prefix,$providerTables);
            foreach($providerTables as $table)if($this->rows('SELECT id FROM '.$this->prefix.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new DomainException('company_invoice_provider_review_required');
            if((new CompanyBankReceiptComponents($this->db,$this->prefix))->forInvoiceInOwned($issuer,$actor,$invoice))throw new DomainException('company_invoice_immutable');
            foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column])if($this->rows('SELECT amount FROM '.$this->prefix.$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice]))throw new DomainException('company_invoice_immutable');
            (new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($row['currency']);
            $journal=new CompanyFinancialJournal($this->db,$this->prefix);$key=hash('sha256','company-journal:invoice-reopened:'.$issuer.':'.$invoice.':'.$revision);
            if($this->rows('SELECT evidence_key FROM '.$this->prefix.'company_financial_events WHERE evidence_key=?',[$key])){
                $done=$journal->capturedInvoiceEventInOwned($issuer,$key);
                if($done['kind']!=='invoice_reopened'||(int)$done['actor_id']!==$actor||$done['source_id']!==(string)$invoice||($done['before']['revision']??null)!==$revision||($done['after']['revision']??null)!==$this->fingerprint($issuer,$invoice))throw new DomainException('company_invoice_stale');
                return;
            }
            if((int)$row['status']!==5||!hash_equals($revision,$this->fingerprint($issuer,$invoice)))throw new DomainException('company_invoice_stale');
            $requests=$this->rows('SELECT * FROM '.$this->prefix.'company_invoice_requests WHERE invoice_id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer]);
            if(count($requests)!==1)throw new DomainException('company_invoice_reopen_review_required');
            $issued=$journal->capturedInvoiceEventInOwned($issuer,hash('sha256','company-journal:invoice-issued:'.$requests[0]['request_key']));
            if($issued['kind']!=='invoice_issued'||$issued['source_id']!==(string)$invoice||(int)$issued['actor_id']!==(int)$requests[0]['actor_id'])throw new DomainException('company_invoice_reopen_review_required');
            $latest=$this->rows("SELECT evidence_key FROM ".$this->prefix."company_financial_events WHERE issuer_id=? AND source_type='invoice' AND source_id=? ORDER BY issuer_sequence DESC LIMIT 1 FOR UPDATE",[$issuer,(string)$invoice]);
            $cancel=$journal->capturedInvoiceEventInOwned($issuer,$latest[0]['evidence_key']??'');
            $snapshot=['date'=>$row['date'],'total'=>self::decimal(self::cents($row['total'])),'status'=>5,'revision'=>$revision];
            if($cancel['kind']!=='invoice_cancelled'||$cancel['after']!==$snapshot||!in_array($cancel['before']['status']??null,[1,4],true))throw new DomainException('company_invoice_reopen_review_required');
            (new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($row['currency']);$document=$this->documents($issuer,$actor)->get($invoice,$issuer);
            $status=$cancel['before']['status'];
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'invoices SET status=? WHERE id=? AND issuer_id=? AND status=5',[$status,$invoice,$issuer]);
            $this->audit($issuer,$actor,$invoice,'company_invoice_reopened');
            $auditId=(int)$this->db->insert_id();
            if($auditId<1||count($this->rows("SELECT id FROM ".$this->prefix."issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event='company_invoice_reopened'",[$auditId,$issuer,$actor,$invoice]))!==1)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $stored=$this->rows('SELECT status,total,date FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??[];
            if((int)($stored['status']??0)!==$status||self::cents($stored['total']??'')!==self::cents($row['total'])||($stored['date']??null)!==$row['date'])throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $after=['date'=>$row['date'],'total'=>$snapshot['total'],'status'=>$status,'revision'=>$this->fingerprint($issuer,$invoice)];
            $this->journal($issuer,$actor,$invoice,(int)$row['currency'],'invoice_reopened',$snapshot+['cancellation_key'=>$cancel['evidence_key']],$after,gmdate('Y-m-d'),$key);
            if($this->documents($issuer,$actor)->get($invoice,$issuer)!==$document)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $this->authorize($issuer,$actor,'edit');$this->authorize($issuer,$actor,'view');
        });
    }
    /** Explicit local-only cancellation; ordinary provider/history guards remain unchanged. */
    public function cancelAfterAsaasFullRefund($issuer,$actor,$invoice,string $revision,CompanyPostRefundAsaasSource $source):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_invoice_stale');$source->assertStorage($this->db,$this->prefix);
        $this->transaction(function()use($issuer,$actor,$invoice,$revision,$source):void{
            $this->authorize($issuer,$actor,'edit');$this->authorize($issuer,$actor,'view');$row=$this->rows('SELECT id,currency,status,date,total FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;if(!$row)throw new DomainException('company_invoice_access_denied');if(!$this->rows('SELECT invoice_id FROM '.$this->prefix.'company_invoice_requests WHERE issuer_id=? AND invoice_id=?',[$issuer,$invoice]))throw new DomainException('company_invoice_immutable');
            if($source->assertCurrent($this->db,$this->prefix,$issuer,$actor,$invoice,$revision))return;
            (new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($row['currency']);$before=['date'=>$row['date'],'total'=>self::decimal(self::cents($row['total'])),'status'=>(int)$row['status'],'revision'=>$revision];IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'invoices SET status=5 WHERE issuer_id=? AND id=?',[$issuer,$invoice]);$this->audit($issuer,$actor,$invoice,'company_invoice_cancelled');$this->authorize($issuer,$actor,'edit');$stored=$this->rows('SELECT status,total FROM '.$this->prefix.'invoices WHERE issuer_id=? AND id=?',[$issuer,$invoice])[0]??null;if(!$stored||(int)$stored['status']!==5||self::cents($stored['total'])!==self::cents($row['total']))throw new RuntimeException('issuer_invoice_write_unconfirmed');$after=['date'=>$row['date'],'total'=>$before['total'],'status'=>5,'revision'=>$this->fingerprint($issuer,$invoice)];$this->journal($issuer,$actor,$invoice,(int)$row['currency'],'invoice_cancelled',$before,$after,gmdate('Y-m-d'),hash('sha256','company-journal:invoice-cancelled:'.$issuer.':'.$invoice.':'.$revision));$source->confirmed($this->db,$this->prefix,$issuer,$actor,$invoice,$revision);$this->authorize($issuer,$actor,'view');
        },$source->tables());
    }
    private function journal(int $issuer,int $actor,int $invoice,int $currency,string $kind,array $before,array $after,string $bookDate,string $key):void
    {
        $unit=(new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($currency);(new CompanyFinancialJournal($this->db,$this->prefix))->recordInOwned($issuer,$actor,['kind'=>$kind,'source_type'=>'invoice','source_id'=>$invoice,'book_date'=>$bookDate,'currency'=>$unit['iso_code'],'currency_basis'=>$unit['basis'],'amount'=>$after['total'],'before'=>$before,'after'=>$after],$key);
    }
    private function audit(int $issuer,int $actor,int $invoice,string $event):void
    {
        $this->insert('issuers_audit',['actor_id'=>$actor,'event'=>$event,'issuer_id'=>$issuer,'subject_id'=>$invoice,'created_at'=>date('Y-m-d H:i:s')]);
    }
}
