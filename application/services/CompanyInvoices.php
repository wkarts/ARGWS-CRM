<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/InvoiceIssuerDocuments.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/InvoicePaymentWriter.php';
require_once __DIR__.'/CompanyInvoiceAmounts.php';
require_once __DIR__.'/CompanyAssets.php';
require_once __DIR__.'/CompanyCurrencyUnits.php';
require_once __DIR__.'/CompanyFinancialJournal.php';
require_once __DIR__.'/CompanyInvoiceBalance.php';

/** Native named-company invoices; legacy models, providers and hooks are never invoked by this adapter. */
final class CompanyInvoices
{
    private const MAX=999999999999999;
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
    private function profile(int $issuer,int $actor):array
    {
        $store=new CompanySettingsStorage($this->db,$this->prefix);$get=fn($name)=>$store->get($issuer,$name);
        return ['address'=>$get('invoice_company_address'),'city'=>$get('invoice_company_city'),'state'=>$get('company_state'),
            'postcode'=>$get('invoice_company_postal_code'),'country_code'=>strtoupper($get('invoice_company_country_code')),
            'phone'=>$get('invoice_company_phonenumber'),'email'=>$get('smtp_email'),'prefix'=>$get('invoice_prefix'),
            'terms'=>$get('predefined_terms_invoice'),'padding'=>6,'pdf_preferences'=>CompanyInvoicePresentation::capture($store,$issuer,'invoices')]+(new CompanyAssets($this->db,$this->prefix))->invoiceProfileInOwnedTransaction($issuer,$actor);
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
        $optionNames=['discount_mode','discount_value','discount_type','adjustment'];
        if(array_diff(array_keys($input),array_merge(['client_id','date','due_date','currency','items','note'],$optionNames))
            ||!isset($input['items'])||!is_array($input['items']))throw new DomainException('company_invoice_invalid_input');
        $amounts=CompanyInvoiceAmounts::calculate($input['items'],array_intersect_key($input,array_flip($optionNames)));
        $date=self::date($input['date']??null);$due=self::date($input['due_date']??null);
        if($due<$date)throw new DomainException('company_invoice_invalid_input');
        return array_merge($amounts,['client_id'=>IssuerPolicy::id($input['client_id']??null),'date'=>$date,'due_date'=>$due,
            'currency'=>IssuerPolicy::id($input['currency']??null),'note'=>self::text($input['note']??'',20000)]);
    }
    private function transaction(callable $operation,array $extraTables=[])
    {
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_client_links','issuers_audit','staff','clients','invoices','issuers_invoice_documents','issuers_sequences','itemable','item_tax','currencies','options','company_invoice_requests','invoicepaymentrecords','credits'];
        foreach(['bb_origins','asaas_payment_origins'] as $table)if($this->db->table_exists($this->prefix.$table))$tables[]=$table;
        $tables=array_values(array_unique(array_merge($tables,$extraTables,(new CompanyCurrencyUnits($this->db,$this->prefix))->tables(),(new CompanyFinancialJournal($this->db,$this->prefix))->tables('invoice'),(new CompanyInvoiceBalance($this->db,$this->prefix))->tables())));
        return IssuerTransaction::run($this->db,$this->prefix,$tables,$operation);
    }
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
        $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));$legacyHash=$this->legacyRequestHash($data,$input);
        return $this->transaction(function()use($issuer,$actor,$data,$requestKey,$hash,$legacyHash,$originGuard):int{
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
            $fields=['issuer_id'=>$issuer,'clientid'=>$client,'number'=>0,'prefix'=>'','number_format'=>1,'datecreated'=>date('Y-m-d H:i:s'),'date'=>$data['date'],'duedate'=>$data['due_date'],'currency'=>$data['currency'],'subtotal'=>$data['subtotal'],'total_tax'=>$data['tax'],'total'=>$data['total'],'adjustment'=>$data['adjustment'],'addedfrom'=>$actor,'hash'=>bin2hex(random_bytes(16)),'status'=>1,'clientnote'=>$data['note'],'discount_type'=>$data['discount_type'],'discount_percent'=>$data['discount_percent'],'discount_total'=>$data['discount_total'],'include_shipping'=>0,'show_shipping_on_invoice'=>0,'cancel_overdue_reminders'=>1,'allowed_payment_modes'=>serialize([]),'billing_street'=>'','billing_city'=>'','billing_state'=>'','billing_zip'=>'','billing_country'=>0,'shipping_street'=>'','shipping_city'=>'','shipping_state'=>'','shipping_zip'=>'','shipping_country'=>0];
            $this->insert('invoices',$fields);$id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $doc=$this->documents($issuer,$actor)->allocateInOwnedTransaction($id,$issuer,'INV',$this->profile($issuer,$actor));
            if(strlen($doc['number'])>10||(int)$doc['number']>2147483647)throw new DomainException('company_invoice_sequence_exhausted');
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'invoices SET number=?,prefix=?,formatted_number=?,terms=? WHERE id=? AND issuer_id=?',[$doc['number'],$doc['prefix'],$doc['formatted_number'],$doc['identity']['terms'],$id,$issuer]);
            $this->items($id,$data['items']);
            $this->insert('company_invoice_requests',['request_key'=>$requestKey,'issuer_id'=>$issuer,'actor_id'=>$actor,'input_hash'=>$hash,'invoice_id'=>$id,'created_at'=>date('Y-m-d H:i:s')]);
            $receipt=$this->rows('SELECT invoice_id,input_hash FROM '.$this->prefix.'company_invoice_requests WHERE request_key=?',[$requestKey])[0]??[];
            if((int)($receipt['invoice_id']??0)!==$id||($receipt['input_hash']??null)!==$hash)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $count=$this->rows("SELECT COUNT(*) n FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice'",[$id])[0]['n']??0;
            if((int)$count!==count($data['items']))throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $this->audit($issuer,$actor,$id,'company_invoice_created');$this->authorize($issuer,$actor,'create');
            $stored=$this->rows('SELECT issuer_id,total,formatted_number FROM '.$this->prefix.'invoices WHERE id=?',[$id])[0]??[];
            if((int)($stored['issuer_id']??0)!==$issuer||self::cents($stored['total']??null)!==self::cents($data['total'])||($stored['formatted_number']??null)!==$doc['formatted_number'])throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $this->journal($issuer,$actor,$id,$data['currency'],'invoice_issued',[],['date'=>$data['date'],'total'=>$data['total'],'status'=>1,'revision'=>$this->fingerprint($issuer,$id)],$data['date'],hash('sha256','company-journal:invoice-issued:'.$requestKey));
            if($originGuard!==null)$originGuard();
            return $id;
        },$guardTables);
    }
    /** Pre-417 receipts remain retryable; no monetary option is silently discarded. */
    private function legacyRequestHash(array $data,array $input):?string
    {
        if($data['discount_total']!=='0.00'||$data['adjustment']!=='0.00'||$data['discount_percent']!=='0.00'||$data['discount_type']!=='after_tax')return null;
        $items=[];
        foreach($data['items'] as $i=>$item){
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
    private function items(int $invoice,array $items):void
    {
        foreach($items as $index=>$item){
            $this->insert('itemable',['rel_id'=>$invoice,'rel_type'=>'invoice','description'=>$item['description'],'long_description'=>'','qty'=>$item['qty'],'rate'=>$item['rate'],'unit'=>'','is_optional'=>0,'is_selected'=>1,'item_order'=>$index+1]);
            $id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_invoice_write_unconfirmed');
            foreach($item['taxes'] as $tax)$this->insert('item_tax',['itemid'=>$id,'rel_id'=>$invoice,'rel_type'=>'invoice','taxrate'=>$tax['rate'],'taxname'=>$tax['name']]);
        }
    }
    public function document($issuer,$actor,$invoice):InvoiceIssuerDocumentData
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');
        return $this->documents($issuer,$actor)->forPdf($invoice,$issuer);
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
            'discount_value'=>$percent>0?CompanyInvoiceAmounts::decimal($percent):(string)$row['discount_total'],'adjustment'=>(string)$row['adjustment']];
    }
    /** Current position keeps provider reductions apart from cash and the issued invoice total. */
    public function position($issuer,$actor,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);return $this->transaction(function()use($issuer,$actor,$invoice):array{$this->authorize($issuer,$actor,'view');return (new CompanyInvoiceBalance($this->db,$this->prefix))->inOwned($issuer,$actor,$invoice);});
    }
    public function payments($issuer,$actor,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$this->authorize($issuer,$actor,'view');
        return $this->transaction(function()use($issuer,$actor,$invoice):array{$position=(new CompanyInvoiceBalance($this->db,$this->prefix))->inOwned($issuer,$actor,$invoice);
        $resource=$this->rows('SELECT currency FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]??null;if(!$resource)throw new DomainException('company_invoice_access_denied');(new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($resource['currency']);
        $rows=$this->rows('SELECT p.id,p.amount,p.date,p.paymentmode,p.transactionid,p.note FROM '.$this->prefix.'invoicepaymentrecords p JOIN '.$this->prefix.'invoices i ON i.id=p.invoiceid WHERE p.invoiceid=? AND i.issuer_id=? ORDER BY p.id',[$invoice,$issuer]);
        foreach($rows as &$row){$row['bank_components']=$position['components'][(int)$row['id']]??null;$reversed=0;$row['reversals']=[];
            if($this->db->table_exists($this->prefix.'company_credit_entries'))$row['reversals']=$this->rows("SELECT amount,book_date,reference,reason FROM {$this->prefix}company_credit_entries WHERE issuer_id=? AND invoice_id=? AND kind='manual_reverse' AND native_id=? ORDER BY created_at,request_key",[$issuer,$invoice,$row['id']]);
            foreach($row['reversals'] as $entry){$n=CompanyInvoiceAmounts::minor((string)$entry['amount']);if($n>999999999999999-$reversed)throw new DomainException('company_credit_balance_review');$reversed+=$n;}
            $row['bank_reversal']=$position['reversals'][(int)$row['id']]??null;$externalReversed=$row['bank_reversal']===null?0:CompanyInvoiceAmounts::minor($row['bank_reversal']['cash_principal_reversed']);$remaining=CompanyInvoiceAmounts::minor((string)$row['amount']);if($remaining>999999999999999-$reversed)throw new DomainException('company_credit_balance_review');$row['original_amount']=CompanyInvoiceAmounts::decimal($remaining+$reversed);if($externalReversed>$remaining)throw new DomainException('company_invoice_balance_review');$row['native_amount']=$row['amount'];$row['effective_amount']=CompanyInvoiceAmounts::decimal($remaining-$externalReversed);$row['manual_reversed_amount']=CompanyInvoiceAmounts::decimal($reversed);$row['external_reversed_amount']=CompanyInvoiceAmounts::decimal($externalReversed);$row['reversed_amount']=CompanyInvoiceAmounts::decimal($reversed+$externalReversed);
        }unset($row);
        $this->authorize($issuer,$actor,'view');return $rows;});
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
    private function fingerprint(int $issuer,int $invoice):string
    {
        $row=$this->rows('SELECT id,clientid,currency,date,duedate,subtotal,total_tax,discount_type,discount_percent,discount_total,adjustment,total,status,clientnote FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]??null;
        if(!$row)throw new DomainException('company_invoice_access_denied');
        (new CompanyCurrencyUnits($this->db,$this->prefix))->resolveRead($row['currency']);
        $items=$this->rows("SELECT id,description,qty,rate FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$invoice]);
        $taxes=$this->rows("SELECT itemid,taxname,taxrate FROM ".$this->prefix."item_tax WHERE rel_id=? AND rel_type='invoice' ORDER BY id",[$invoice]);
        return hash('sha256',json_encode([$row,$items,$taxes],JSON_THROW_ON_ERROR));
    }
    public function change($issuer,$actor,$invoice,string $revision,?array $input=null):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$data=$input===null?null:self::input($input);
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_invoice_stale');
        $this->transaction(function()use($issuer,$actor,$invoice,$revision,$data):void{
            $this->authorize($issuer,$actor,'edit');$this->authorize($issuer,$actor,'view');
            $row=$this->rows('SELECT id,clientid,currency,status,date,total FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
            if(!$row)throw new DomainException('company_invoice_access_denied');
            if(!$this->rows('SELECT invoice_id FROM '.$this->prefix.'company_invoice_requests WHERE invoice_id=? AND issuer_id=?',[$invoice,$issuer]))throw new DomainException('company_invoice_immutable');
            if($data===null&&(int)$row['status']===5)return; // Repeated cancellation does not erase history.
            if(!hash_equals($revision,$this->fingerprint($issuer,$invoice)))throw new DomainException('company_invoice_stale');
            if(!in_array((int)$row['status'],[1,4],true))throw new DomainException('company_invoice_immutable');
            (new CompanyCurrencyUnits($this->db,$this->prefix))->assertWrite($row['currency']);
            $before=['date'=>$row['date'],'total'=>self::decimal(self::cents($row['total'])),'status'=>(int)$row['status'],'revision'=>$revision];
            foreach(['bb_origins','asaas_payment_origins'] as $table)if($this->db->table_exists($this->prefix.$table)&&$this->rows('SELECT id FROM '.$this->prefix.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new DomainException('company_invoice_provider_review_required');
            if((new CompanyBankReceiptComponents($this->db,$this->prefix))->forInvoiceInOwned($issuer,$actor,$invoice))throw new DomainException('company_invoice_immutable');
            foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column])if($this->rows('SELECT amount FROM '.$this->prefix.$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice]))throw new DomainException('company_invoice_immutable');
            if($data===null){IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'invoices SET status=5 WHERE id=? AND issuer_id=?',[$invoice,$issuer]);}
            else{
                if((int)$row['clientid']!==$data['client_id']||(int)$row['currency']!==$data['currency'])throw new DomainException('company_invoice_immutable');
                IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'invoices SET date=?,duedate=?,subtotal=?,total_tax=?,discount_type=?,discount_percent=?,discount_total=?,adjustment=?,total=?,clientnote=? WHERE id=? AND issuer_id=?',[$data['date'],$data['due_date'],$data['subtotal'],$data['tax'],$data['discount_type'],$data['discount_percent'],$data['discount_total'],$data['adjustment'],$data['total'],$data['note'],$invoice,$issuer]);
                IssuerStorage::query($this->db,"DELETE FROM ".$this->prefix."item_tax WHERE rel_id=? AND rel_type='invoice'",[$invoice]);
                IssuerStorage::query($this->db,"DELETE FROM ".$this->prefix."itemable WHERE rel_id=? AND rel_type='invoice'",[$invoice]);$this->items($invoice,$data['items']);
            }
            $this->audit($issuer,$actor,$invoice,$data===null?'company_invoice_cancelled':'company_invoice_updated');$this->authorize($issuer,$actor,'edit');
            $stored=$this->rows('SELECT total,status FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]??[];
            if(!$stored||($data===null?(int)$stored['status']!==5:self::cents($stored['total'])!==self::cents($data['total'])))throw new RuntimeException('issuer_invoice_write_unconfirmed');
            $after=['date'=>$data===null?$row['date']:$data['date'],'total'=>self::decimal(self::cents($stored['total'])),'status'=>(int)$stored['status'],'revision'=>$this->fingerprint($issuer,$invoice)];
            $this->journal($issuer,$actor,$invoice,(int)$row['currency'],$data===null?'invoice_cancelled':'invoice_updated',$before,$after,$data===null?gmdate('Y-m-d'):$data['date'],hash('sha256','company-journal:invoice-'.($data===null?'cancelled':'updated').':'.$issuer.':'.$invoice.':'.$revision));
        });
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
