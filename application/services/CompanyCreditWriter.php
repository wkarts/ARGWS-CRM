<?php

declare(strict_types=1);
namespace app\services;
require_once __DIR__.'/CompanyCreditSchema.php';
require_once __DIR__.'/CompanyInvoiceAmounts.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/issuers/InvoiceIssuerDocuments.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/CompanyCurrencyUnits.php';
require_once __DIR__.'/CompanyFinancialJournal.php';
/** Named-company credit and reversal entries share the native financial writer and installation lock. */
trait CompanyCreditWriter
{
    private function creditRows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function creditAuth(int $issuer,int $actor,array $caps):void
    {
        $acl=new \IssuerResourceAccess($this->db,db_prefix());foreach($caps as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new \DomainException('company_credit_access_denied');
    }
    private function creditTransaction(int $issuer,int $actor,array $caps,callable $work)
    {
        $this->creditAuth($issuer,$actor,$caps);\CompanyCreditSchema::assertValid($this->db,db_prefix());
        $tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','issuers_client_links','clients','invoices','issuers_invoice_documents','creditnotes','creditnote_refunds','credits','currencies','options','itemable','item_tax','issuers_sequences','invoicepaymentrecords','company_payment_receipts','company_credit_documents','company_credit_entries','issuers_audit'];
        foreach(['bb_origins','asaas_payment_origins'] as $table)if($this->db->table_exists(db_prefix().$table))$tables[]=$table;
        $journal=new \CompanyFinancialJournal($this->db,db_prefix());$tables=array_values(array_unique(array_merge($tables,(new \CompanyCurrencyUnits($this->db,db_prefix()))->tables(),$journal->tables('credit'),$journal->tables('credit_entry'),$journal->tables('payment'))));
        return \IssuerTransaction::run($this->db,db_prefix(),$tables,function()use($issuer,$actor,$caps,$work){(new \IssuerResourceAccess($this->db,db_prefix()))->lockFinancialIdentity($issuer,$actor);$this->creditAuth($issuer,$actor,$caps);$result=$work();$this->creditAuth($issuer,$actor,$caps);return $result;});
    }
    private static function creditText($value,int $max,bool $required=false):string
    {
        if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)||($required&&trim($value)===''))throw new \DomainException('company_credit_invalid_input');return trim($value);
    }
    private static function creditDate($value):string
    {
        $date=is_string($value)?\DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;if(!$date||$date->format('Y-m-d')!==$value)throw new \DomainException('company_credit_invalid_input');return $value;
    }
    private static function creditKey(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new \DomainException('company_credit_invalid_input');}
    private function creditInsert(string $table,array $fields):int
    {
        \IssuerStorage::query($this->db,'INSERT INTO '.db_prefix().$table.' (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));return (int)$this->db->insert_id();
    }
    private function creditAudit(int $issuer,int $actor,int $subject,string $event,string $key):void
    {
        $meta=json_encode(['request_key'=>$key],JSON_THROW_ON_ERROR);$id=$this->creditInsert('issuers_audit',['actor_id'=>$actor,'event'=>$event,'issuer_id'=>$issuer,'subject_id'=>$subject,'created_at'=>date('Y-m-d H:i:s'),'metadata_json'=>$meta]);
        if($id<1||!$this->creditRows('SELECT id FROM '.db_prefix().'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$id,$issuer,$subject,$meta]))throw new \RuntimeException('issuer_credit_write_unconfirmed');
    }
    /** Reuses item/tax/discount arithmetic and captures issuer/customer data inside the same writer transaction. */
    public function issueCompanyCredit($issuer,$actor,array $input,string $key):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::creditKey($key);
        if(array_diff(array_keys($input),['client_id','currency','date','items','discount_mode','discount_value','discount_type','adjustment','note']))throw new \DomainException('company_credit_invalid_input');
        if(!is_array($input['items']??null))throw new \DomainException('company_credit_invalid_input');
        $data=\CompanyInvoiceAmounts::calculate($input['items'],array_intersect_key($input,array_flip(['discount_mode','discount_value','discount_type','adjustment'])));
        $data+=['client_id'=>\IssuerPolicy::id($input['client_id']??null),'currency'=>\IssuerPolicy::id($input['currency']??null),'date'=>self::creditDate($input['date']??null),'note'=>self::creditText($input['note']??'',20000)];$hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
        return $this->creditTransaction($issuer,$actor,[['credit_notes','view'],['credit_notes','create'],['customers','view']],function()use($issuer,$actor,$key,$data,$hash):int{
            $p=db_prefix();$receipt=$this->creditRows('SELECT issuer_id,actor_id,credit_id,input_hash FROM '.$p.'company_credit_documents WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($receipt){if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||!hash_equals($receipt['input_hash'],$hash))throw new \DomainException('company_credit_request_conflict');$this->ownedCredit($issuer,(int)$receipt['credit_id']);return (int)$receipt['credit_id'];}
            $client=$this->creditRows('SELECT c.userid,c.company,c.vat,o.tax_id FROM '.$p.'clients c JOIN '.$p.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND (o.owner_issuer_id=? OR EXISTS(SELECT 1 FROM '.$p.'issuers_client_links l WHERE l.client_id=c.userid AND l.issuer_id=?)) FOR UPDATE',[$data['client_id'],$issuer,$issuer])[0]??null;
            if(!$client)throw new \DomainException('company_credit_access_denied');$tax=\IssuerPolicy::taxId((string)($client['vat']??''),false)??'';if($tax!==($client['tax_id']??''))throw new \DomainException('company_credit_identity_changed');
            $unit=(new \CompanyCurrencyUnits($this->db,$p))->assertWrite($data['currency']);
            $company=$this->creditRows('SELECT * FROM '.$p.'issuers_registry WHERE id=? AND active=1 FOR UPDATE',[$issuer])[0]??null;if(!$company)throw new \DomainException('company_credit_access_denied');
            $settings=new \CompanySettingsStorage($this->db,$p);$get=fn($name)=>$settings->get($issuer,$name);$raw=json_decode($company['settings_json'],true,32,JSON_THROW_ON_ERROR);
            $profile=['address'=>$get('invoice_company_address'),'city'=>$get('invoice_company_city'),'state'=>$get('company_state'),'postcode'=>$get('invoice_company_postal_code'),'country_code'=>strtoupper($get('invoice_company_country_code')),'phone'=>$get('invoice_company_phonenumber'),'email'=>$get('smtp_email'),'prefix'=>$get('credit_note_prefix'),'terms'=>$get('predefined_terms_credit_note'),'padding'=>6,'logo_inline'=>$raw['company_brand_assets']['logo']??'','signature_inline'=>$raw['company_brand_assets']['signature']??''];
            $company['settings_json']=json_encode(['document_profile'=>$profile],JSON_THROW_ON_ERROR);$identity=\InvoiceIssuerDocuments::identity($company);
            $sequence=$this->creditRows("SELECT next_number FROM {$p}issuers_sequences WHERE issuer_id=? AND series='CREDIT' FOR UPDATE",[$issuer])[0]??null;$number=$sequence?(int)$sequence['next_number']:1;if($number<1||$number>2147483647)throw new \DomainException('company_credit_sequence_exhausted');
            if($sequence)\IssuerStorage::query($this->db,"UPDATE {$p}issuers_sequences SET next_number=? WHERE issuer_id=? AND series='CREDIT'",[$number+1,$issuer]);else $this->creditInsert('issuers_sequences',['issuer_id'=>$issuer,'series'=>'CREDIT','next_number'=>2]);
            $formatted=$identity['prefix'].str_pad((string)$number,6,'0',STR_PAD_LEFT);
            $id=$this->creditInsert('creditnotes',['issuer_id'=>$issuer,'clientid'=>$data['client_id'],'number'=>$number,'prefix'=>$identity['prefix'],'formatted_number'=>$formatted,'number_format'=>1,'datecreated'=>date('Y-m-d H:i:s'),'date'=>$data['date'],'currency'=>$data['currency'],'subtotal'=>$data['subtotal'],'total_tax'=>$data['tax'],'total'=>$data['total'],'adjustment'=>$data['adjustment'],'addedfrom'=>$actor,'status'=>1,'clientnote'=>$data['note'],'terms'=>$identity['terms'],'discount_percent'=>$data['discount_percent'],'discount_total'=>$data['discount_total'],'discount_type'=>$data['discount_type'],'include_shipping'=>0,'show_shipping_on_credit_note'=>0]);if($id<1)throw new \RuntimeException('issuer_credit_write_unconfirmed');
            foreach($data['items'] as $i=>$item){$itemId=$this->creditInsert('itemable',['rel_id'=>$id,'rel_type'=>'credit_note','description'=>$item['description'],'long_description'=>'','qty'=>$item['qty'],'rate'=>$item['rate'],'unit'=>'','item_order'=>$i+1,'is_optional'=>0,'is_selected'=>1]);foreach($item['taxes'] as $t)$this->creditInsert('item_tax',['itemid'=>$itemId,'rel_id'=>$id,'rel_type'=>'credit_note','taxrate'=>$t['rate'],'taxname'=>$t['name']]);}
            $this->creditInsert('company_credit_documents',['credit_id'=>$id,'issuer_id'=>$issuer,'actor_id'=>$actor,'request_key'=>$key,'input_hash'=>$hash,'number'=>$number,'identity_json'=>json_encode($identity,JSON_THROW_ON_ERROR),'recipient_json'=>json_encode(['client_id'=>$data['client_id'],'name'=>self::creditText($client['company'],191,true),'tax_id'=>$tax],JSON_THROW_ON_ERROR),'items_json'=>json_encode($data,JSON_THROW_ON_ERROR),'created_at'=>date('Y-m-d H:i:s')]);
            if(!$this->creditRows('SELECT d.credit_id FROM '.$p.'company_credit_documents d JOIN '.$p.'creditnotes c ON c.id=d.credit_id AND c.issuer_id=d.issuer_id WHERE d.request_key=? AND d.input_hash=? AND c.total=?',[$key,$hash,$data['total']]))throw new \RuntimeException('issuer_credit_write_unconfirmed');$this->creditAudit($issuer,$actor,$id,'company_credit_issued',$key);$this->creditJournal($issuer,$actor,'credit_issued','credit',$id,$data['currency'],$data['date'],$data['total'],[],['date'=>$data['date'],'total'=>$data['total'],'status'=>1,'available'=>$data['total']],hash('sha256','company-journal:credit-issued:'.$key));return $id;
        });
    }
    private function ownedCredit(int $issuer,int $credit):array
    {
        $row=$this->creditRows('SELECT c.* FROM '.db_prefix().'creditnotes c JOIN '.db_prefix().'company_credit_documents d ON d.credit_id=c.id AND d.issuer_id=c.issuer_id WHERE c.id=? AND c.issuer_id=? FOR UPDATE',[$credit,$issuer])[0]??null;if(!$row)throw new \DomainException('company_credit_access_denied');(new \CompanyCurrencyUnits($this->db,db_prefix()))->resolveRead($row['currency']);return $row;
    }
    private function creditAvailable(array $credit):int
    {
        $used=0;$p=db_prefix();$totals=['apply'=>0,'refund'=>0];
        foreach([['credits','credit_id','apply'],['creditnote_refunds','credit_note_id','refund']] as [$table,$column,$kind]){
            foreach($this->creditRows('SELECT * FROM '.$p.$table.' WHERE '.$column.'=? FOR UPDATE',[$credit['id']]) as $row){
                $n=self::minorUnits($row['amount']);if($n>self::minorUnits($credit['total'])-$used)throw new \DomainException('company_credit_balance_review');$used+=$n;$totals[$kind]+=$n;
                $parent=$this->creditRows('SELECT amount,request_key,invoice_id FROM '.$p.'company_credit_entries WHERE issuer_id=? AND credit_id=? AND kind=? AND native_id=? FOR UPDATE',[$credit['issuer_id'],$credit['id'],$kind,$row['id']]);
                if(count($parent)!==1)throw new \DomainException('company_credit_balance_review');
                $expected=self::minorUnits($parent[0]['amount']);$reverseKind=$kind==='apply'?'unapply':'refund_reverse';
                foreach($this->creditRows('SELECT amount FROM '.$p.'company_credit_entries WHERE parent_key=? AND issuer_id=? AND credit_id=? AND kind=? FOR UPDATE',[$parent[0]['request_key'],$credit['issuer_id'],$credit['id'],$reverseKind]) as $reversal){$r=self::minorUnits($reversal['amount']);if($r>$expected)throw new \DomainException('company_credit_balance_review');$expected-=$r;}
                if($expected!==$n)throw new \DomainException('company_credit_balance_review');
                if($kind==='apply'){$invoice=$this->creditRows('SELECT issuer_id,clientid,currency FROM '.$p.'invoices WHERE id=?',[$row['invoice_id']])[0]??[];if((int)($invoice['issuer_id']??0)!==(int)$credit['issuer_id']||(int)$invoice['clientid']!==(int)$credit['clientid']||(int)$invoice['currency']!==(int)$credit['currency']||(int)$parent[0]['invoice_id']!==(int)$row['invoice_id'])throw new \DomainException('company_credit_balance_review');}
            }
            $ledger=0;foreach($this->creditRows('SELECT kind,amount FROM '.$p.'company_credit_entries WHERE issuer_id=? AND credit_id=? AND kind IN (?,?) FOR UPDATE',[$credit['issuer_id'],$credit['id'],$kind,$kind==='apply'?'unapply':'refund_reverse']) as $entry){$n=self::minorUnits($entry['amount']);$ledger+=$entry['kind']===$kind?$n:-$n;if(abs($ledger)>999999999999999)throw new \DomainException('company_credit_balance_review');}
            if($ledger!==$totals[$kind])throw new \DomainException('company_credit_balance_review');
        }return self::minorUnits($credit['total'])-$used;
    }
    private function creditInvoice(int $issuer,int $invoice):array
    {
        $p=db_prefix();$row=$this->creditRows('SELECT i.* FROM '.$p.'invoices i JOIN '.$p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;if(!$row)throw new \DomainException('company_credit_access_denied');(new \CompanyCurrencyUnits($this->db,$p))->resolveRead($row['currency']);
        foreach(['bb_origins','asaas_payment_origins'] as $table)if($this->db->table_exists($p.$table)&&$this->creditRows('SELECT id FROM '.$p.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('company_credit_provider_review_required');if(!in_array((int)$row['status'],[1,2,3,4],true))throw new \DomainException('company_credit_invalid_state');return $row;
    }
    private function invoiceRemainder(array $invoice):int
    {
        $left=self::minorUnits($invoice['total']);foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column])foreach($this->creditRows('SELECT amount FROM '.db_prefix().$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice['id']]) as $row){$amount=self::minorUnits($row['amount']);if($amount>$left)throw new \DomainException('company_credit_balance_review');$left-=$amount;}return $left;
    }
    private function refreshCredit(array $credit):void
    {
        $remaining=$this->creditAvailable($credit);\IssuerStorage::query($this->db,'UPDATE '.db_prefix().'creditnotes SET status=? WHERE id=? AND issuer_id=?',[$remaining===0?2:1,$credit['id'],$credit['issuer_id']]);
        if((int)$this->ownedCredit((int)$credit['issuer_id'],(int)$credit['id'])['status']!==($remaining===0?2:1))throw new \RuntimeException('issuer_credit_write_unconfirmed');
    }
    private function refreshInvoice(array $invoice):void
    {
        $left=$this->invoiceRemainder($invoice);$status=$left===0?2:($left<self::minorUnits($invoice['total'])?3:(!empty($invoice['duedate'])&&$invoice['duedate']<date('Y-m-d')?4:1));\IssuerStorage::query($this->db,'UPDATE '.db_prefix().'invoices SET status=? WHERE id=? AND issuer_id=?',[$status,$invoice['id'],$invoice['issuer_id']]);
        $check=$this->creditRows('SELECT status FROM '.db_prefix().'invoices WHERE id=? AND issuer_id=?',[$invoice['id'],$invoice['issuer_id']])[0]??[];if((int)($check['status']??0)!==$status)throw new \RuntimeException('issuer_credit_write_unconfirmed');
    }
    /** New entries use exact positive magnitudes; reversal kind and parent point to immutable original evidence. */
    public function moveCompanyCredit($issuer,$actor,string $kind,array $input,string $key):string
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);self::creditKey($key);
        if(!in_array($kind,['apply','unapply','refund','refund_reverse','manual_reverse'],true)||array_diff(array_keys($input),['credit_id','invoice_id','parent_key','payment_id','amount','date','reference','reason']))throw new \DomainException('company_credit_invalid_input');
        $reverse=in_array($kind,['unapply','refund_reverse','manual_reverse'],true);$amount=self::minorUnits($input['amount']??null);if($amount<1)throw new \DomainException('company_credit_invalid_input');
        $data=['credit_id'=>isset($input['credit_id'])?\IssuerPolicy::id($input['credit_id']):null,'invoice_id'=>isset($input['invoice_id'])?\IssuerPolicy::id($input['invoice_id']):null,'parent_key'=>$input['parent_key']??null,'payment_id'=>isset($input['payment_id'])?\IssuerPolicy::id($input['payment_id']):null,'amount'=>\CompanyInvoiceAmounts::decimal($amount),'date'=>self::creditDate($input['date']??null),'reference'=>self::creditText($input['reference']??null,191,true),'reason'=>self::creditText($input['reason']??null,4000,true)];
        if($data['date']>date('Y-m-d'))throw new \DomainException('company_credit_invalid_input');if($data['parent_key']!==null){if(!is_string($data['parent_key']))throw new \DomainException('company_credit_invalid_input');self::creditKey($data['parent_key']);}
        if($kind==='manual_reverse'?($data['credit_id']!==null||$data['payment_id']===null||$data['parent_key']!==null||$data['invoice_id']===null):($data['credit_id']===null||$data['payment_id']!==null||($kind==='apply'&&($data['invoice_id']===null||$data['parent_key']!==null))||($kind==='refund'&&($data['invoice_id']!==null||$data['parent_key']!==null))||($reverse&&$data['parent_key']===null)||($kind==='unapply'&&$data['invoice_id']===null)||($kind==='refund_reverse'&&$data['invoice_id']!==null)))throw new \DomainException('company_credit_invalid_input');
        $caps=$kind==='manual_reverse'?[['invoices','view'],['payments','delete']]:[['credit_notes','view'],['credit_notes','edit'],['payments',$reverse?'delete':'create']];if(in_array($kind,['apply','unapply'],true))$caps[]=['invoices','view'];
        $hash=hash('sha256',json_encode([$kind,$data],JSON_THROW_ON_ERROR));
        return $this->creditTransaction($issuer,$actor,$caps,function()use($issuer,$actor,$kind,$data,$key,$hash,$amount):string{
            $p=db_prefix();$old=$this->creditRows('SELECT issuer_id,actor_id,input_hash,kind FROM '.$p.'company_credit_entries WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor||$old['kind']!==$kind||!hash_equals($old['input_hash'],$hash))throw new \DomainException('company_credit_request_conflict');if($data['credit_id']!==null)$this->ownedCredit($issuer,$data['credit_id']);if($data['invoice_id']!==null){$resource=$this->creditRows('SELECT currency FROM '.$p.'invoices WHERE id=? AND issuer_id=?',[$data['invoice_id'],$issuer])[0]??null;if(!$resource)throw new \DomainException('company_credit_access_denied');(new \CompanyCurrencyUnits($this->db,$p))->resolveRead($resource['currency']);}return $key;}
            $ref=hash('sha256',$data['reference']);if($this->creditRows('SELECT request_key FROM '.$p.'company_credit_entries WHERE issuer_id=? AND kind=? AND reference_hash=?',[$issuer,$kind,$ref]))throw new \DomainException('company_credit_reference_conflict');
            $credit=$data['credit_id']!==null?$this->ownedCredit($issuer,$data['credit_id']):null;$invoice=$data['invoice_id']!==null?$this->creditInvoice($issuer,$data['invoice_id']):null;$native=0;
            if(($credit&&$data['date']<$credit['date'])||($invoice&&$data['date']<$invoice['date']))throw new \DomainException('company_credit_invalid_input');
            if($credit&&(int)$credit['status']===3)throw new \DomainException('company_credit_invalid_state');
            $units=new \CompanyCurrencyUnits($this->db,$p);if($credit)$units->assertWrite($credit['currency']);if($invoice)$units->assertWrite($invoice['currency']);
            $before=['date'=>$data['date'],'credit_id'=>$data['credit_id'],'invoice_id'=>$data['invoice_id'],'credit_available'=>$credit?\CompanyInvoiceAmounts::decimal($this->creditAvailable($credit)):null,'invoice_balance'=>$invoice?\CompanyInvoiceAmounts::decimal($this->invoiceRemainder($invoice)):null];
            if($kind==='apply'){
                if((int)$credit['clientid']!==(int)$invoice['clientid']||(int)$credit['currency']!==(int)$invoice['currency'])throw new \DomainException('company_credit_target_mismatch');if($amount>$this->creditAvailable($credit)||$amount>$this->invoiceRemainder($invoice))throw new \DomainException('company_credit_exceeds_balance');
                $native=$this->creditInsert('credits',['invoice_id'=>$invoice['id'],'credit_id'=>$credit['id'],'staff_id'=>$actor,'date'=>$data['date'],'date_applied'=>date('Y-m-d H:i:s'),'amount'=>$data['amount']]);
            }elseif($kind==='refund'){
                if($amount>$this->creditAvailable($credit))throw new \DomainException('company_credit_exceeds_balance');$native=$this->creditInsert('creditnote_refunds',['credit_note_id'=>$credit['id'],'staff_id'=>$actor,'refunded_on'=>$data['date'],'payment_mode'=>'company_manual','note'=>$data['reason'],'amount'=>$data['amount'],'created_at'=>date('Y-m-d H:i:s')]);
            }elseif($kind==='manual_reverse'){
                $row=$this->creditRows("SELECT p.* FROM {$p}invoicepaymentrecords p JOIN {$p}company_payment_receipts r ON r.payment_id=p.id AND r.invoice_id=p.invoiceid WHERE p.id=? AND p.invoiceid=? AND p.paymentmode='company_manual' AND r.issuer_id=? FOR UPDATE",[$data['payment_id'],$invoice['id'],$issuer])[0]??null;
                if(!$row||$amount>self::minorUnits($row['amount'])||$data['date']<$row['date'])throw new \DomainException('company_credit_invalid_reversal');$native=(int)$row['id'];$projected=\CompanyInvoiceAmounts::decimal(self::minorUnits($row['amount'])-$amount);\IssuerStorage::query($this->db,'UPDATE '.$p.'invoicepaymentrecords SET amount=? WHERE id=? AND invoiceid=?',[$projected,$native,$invoice['id']]);
            }else{
                $parent=$this->creditRows('SELECT * FROM '.$p.'company_credit_entries WHERE request_key=? AND issuer_id=? AND credit_id=? FOR UPDATE',[$data['parent_key'],$issuer,$credit['id']])[0]??null;$expected=$kind==='unapply'?'apply':'refund';if(!$parent||$parent['kind']!==$expected||($parent['invoice_id']===null?null:(int)$parent['invoice_id'])!==$data['invoice_id']||$data['date']<$parent['book_date'])throw new \DomainException('company_credit_invalid_reversal');
                $table=$kind==='unapply'?'credits':'creditnote_refunds';$column=$kind==='unapply'?'credit_id':'credit_note_id';$row=$this->creditRows('SELECT * FROM '.$p.$table.' WHERE id=? AND '.$column.'=? FOR UPDATE',[$parent['native_id'],$credit['id']])[0]??null;
                if(!$row||$amount>self::minorUnits($row['amount'])||($kind==='unapply'&&(int)$row['invoice_id']!==$data['invoice_id']))throw new \DomainException('company_credit_invalid_reversal');$native=(int)$parent['native_id'];\IssuerStorage::query($this->db,'UPDATE '.$p.$table.' SET amount=? WHERE id=? AND '.$column.'=?',[$projected=\CompanyInvoiceAmounts::decimal(self::minorUnits($row['amount'])-$amount),$native,$credit['id']]);
            }
            if($native>0){$target=match($kind){'apply','unapply'=>'credits','refund','refund_reverse'=>'creditnote_refunds','manual_reverse'=>'invoicepaymentrecords'};$projection=$this->creditRows('SELECT amount FROM '.$p.$target.' WHERE id=?',[$native])[0]??[];if(!isset($projection['amount'])||self::minorUnits($projection['amount'])!==self::minorUnits($projected??$data['amount']))throw new \RuntimeException('issuer_credit_write_unconfirmed');}
            if($native<1)throw new \RuntimeException('issuer_credit_write_unconfirmed');
            $entry=['request_key'=>$key,'issuer_id'=>$issuer,'actor_id'=>$actor,'kind'=>$kind,'credit_id'=>$data['credit_id'],'invoice_id'=>$data['invoice_id'],'native_id'=>$native,'parent_key'=>$data['parent_key'],'amount'=>$data['amount'],'input_hash'=>$hash,'reference_hash'=>$ref,'reference'=>$data['reference'],'book_date'=>$data['date'],'reason'=>$data['reason'],'created_at'=>date('Y-m-d H:i:s')];$this->creditInsert('company_credit_entries',$entry);
            if($credit)$this->refreshCredit($credit);if($invoice)$this->refreshInvoice($invoice);
            if(!$this->creditRows('SELECT request_key FROM '.$p.'company_credit_entries WHERE request_key=? AND input_hash=?',[$key,$hash]))throw new \RuntimeException('issuer_credit_write_unconfirmed');$this->creditAudit($issuer,$actor,$credit?(int)$credit['id']:(int)$invoice['id'],'company_credit_'.$kind,$key);
            $after=['date'=>$data['date'],'credit_id'=>$data['credit_id'],'invoice_id'=>$data['invoice_id'],'native_id'=>$native,'amount'=>$data['amount'],'credit_available'=>$credit?\CompanyInvoiceAmounts::decimal($this->creditAvailable($credit)):null,'invoice_balance'=>$invoice?\CompanyInvoiceAmounts::decimal($this->invoiceRemainder($invoice)):null,'parent_key'=>$data['parent_key']];
            $eventKind=match($kind){'apply'=>'credit_applied','unapply'=>'credit_unapplied','refund'=>'credit_refunded','refund_reverse'=>'credit_refund_reversed','manual_reverse'=>'receipt_reversed'};$this->creditJournal($issuer,$actor,$eventKind,$kind==='manual_reverse'?'payment':'credit_entry',$kind==='manual_reverse'?$native:$key,(int)($credit['currency']??$invoice['currency']),$data['date'],$data['amount'],$before,$after,hash('sha256','company-journal:credit-entry:'.$key));return $key;
        });
    }
    private function creditJournal(int $issuer,int $actor,string $kind,string $type,$source,int $currency,string $date,string $amount,array $before,array $after,string $key):void
    {
        $unit=(new \CompanyCurrencyUnits($this->db,db_prefix()))->resolveRead($currency);(new \CompanyFinancialJournal($this->db,db_prefix()))->recordInOwned($issuer,$actor,['kind'=>$kind,'source_type'=>$type,'source_id'=>$source,'book_date'=>$date,'currency'=>$unit['iso_code'],'currency_basis'=>$unit['basis'],'amount'=>$amount,'before'=>$before,'after'=>$after],$key);
    }
    public function voidCompanyCredit($issuer,$actor,$credit,string $key):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$credit=\IssuerPolicy::id($credit);self::creditKey($key);$this->creditTransaction($issuer,$actor,[['credit_notes','view'],['credit_notes','delete']],function()use($issuer,$actor,$credit,$key):void{$row=$this->ownedCredit($issuer,$credit);if((int)$row['status']===3)return;(new \CompanyCurrencyUnits($this->db,db_prefix()))->assertWrite($row['currency']);if($this->creditRows('SELECT request_key FROM '.db_prefix().'company_credit_entries WHERE credit_id=?',[$credit]))throw new \DomainException('company_credit_has_history');\IssuerStorage::query($this->db,'UPDATE '.db_prefix().'creditnotes SET status=3 WHERE id=? AND issuer_id=?',[$credit,$issuer]);$check=$this->ownedCredit($issuer,$credit);if((int)$check['status']!==3)throw new \RuntimeException('issuer_credit_write_unconfirmed');$this->creditAudit($issuer,$actor,$credit,'company_credit_voided',$key);$this->creditJournal($issuer,$actor,'credit_cancelled','credit',$credit,(int)$row['currency'],gmdate('Y-m-d'),\CompanyInvoiceAmounts::decimal(self::minorUnits($row['total'])),['date'=>$row['date'],'total'=>\CompanyInvoiceAmounts::decimal(self::minorUnits($row['total'])),'status'=>(int)$row['status']],['date'=>$row['date'],'total'=>\CompanyInvoiceAmounts::decimal(self::minorUnits($row['total'])),'status'=>3,'available'=>'0.00'],hash('sha256','company-journal:credit-cancelled:'.$issuer.':'.$credit));});
    }
}
