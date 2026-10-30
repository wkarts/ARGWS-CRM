<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';
require_once __DIR__.'/CompanyMailConfig.php';
require_once __DIR__.'/CompanyGoogleMailOAuth.php';
require_once __DIR__.'/CompanyMailTransportPolicy.php';
require_once __DIR__.'/CompanyEmailTemplates.php';
require_once __DIR__.'/CompanyInvoiceDeliverySchema.php';

/** An explicit notification, never a public invoice token or proof of SMTP delivery. */
final class CompanyInvoiceDelivery
{
    public const HEADER='companyInvoiceDelivery';
    public const PAYMENT_HEADER='companyInvoicePaymentNotice';
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        foreach([['invoices','view'],['invoices','edit'],['customers','view']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('company_invoice_delivery_denied');
    }
    private function transaction(callable $work,array $extraTables=[])
    {
        CompanyInvoiceDeliverySchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','contacts','contact_permissions','invoices','itemable','item_tax','issuers_invoice_documents','emailtemplates','options','mail_queue','company_invoice_deliveries','issuers_audit'],CompanyGoogleMailOAuth::participantTables($this->db,$this->p),CompanyMailTransportPolicy::participantTables($this->db,$this->p),$extraTables),$work);
    }
    private function parent(int $issuer,int $invoice):array
    {
        $row=$this->rows('SELECT i.id,i.clientid,i.status,d.formatted_number,r.legal_name FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id JOIN '.$this->p.'issuers_registry r ON r.id=i.issuer_id AND r.active=1 JOIN '.$this->p.'clients c ON c.userid=i.clientid AND c.active=1 JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
        if(!$row)throw new DomainException('company_invoice_delivery_denied');return $row;
    }
    private function recipientsFor(int $client):array
    {
        return $this->rows('SELECT c.id,c.firstname,c.lastname,c.email FROM '.$this->p.'contacts c WHERE c.userid=? AND c.active=1 AND c.invoice_emails=1 AND (SELECT COUNT(*) FROM '.$this->p.'contact_permissions p WHERE p.userid=c.id AND p.permission_id=1)=1 ORDER BY c.id LIMIT 101',[$client]);
    }
    private static function recipientHash(array $row):string
    {
        return hash('sha256',json_encode([$row['id'],$row['firstname'],$row['lastname'],$row['email']],JSON_THROW_ON_ERROR));
    }
    public function state($issuer,$actor,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$this->authorize($issuer,$actor);
        return $this->transaction(function()use($issuer,$actor,$invoice):array{
            $this->authorize($issuer,$actor);$row=$this->parent($issuer,$invoice);
            $contacts=in_array((int)$row['status'],[1,2,3,4],true)?$this->recipientsFor((int)$row['clientid']):[];
            $history=$this->rows("SELECT d.created_at,COALESCE(q.status,'archived') status FROM ".$this->p.'company_invoice_deliveries d LEFT JOIN '.$this->p.'mail_queue q ON q.id=d.queue_id AND q.issuer_id=d.issuer_id WHERE d.issuer_id=? AND d.invoice_id=? ORDER BY d.created_at DESC,d.queue_id DESC LIMIT 20',[$issuer,$invoice]);
            return ['recipients'=>array_slice($contacts,0,100),'recipients_truncated'=>count($contacts)>100,'history'=>$history];
        });
    }
    public function queue($issuer,$actor,$invoice,$contact,string $revision,string $requestKey,string $baseUrl,callable $decrypt,?CompanyExpenseRecurringDeliverySource $source=null):int
    {return $this->queueOwnedSource($issuer,$actor,$invoice,$contact,$revision,$requestKey,$baseUrl,$decrypt,$source);}
    /** Fixed invoice-schedule veto; does not confer document or recipient authority. */
    public function queueInvoiceRecurring($issuer,$actor,$invoice,$contact,string $revision,string $requestKey,string $baseUrl,callable $decrypt,CompanyInvoiceRecurringDeliverySource $source):int
    {return $this->queueOwnedSource($issuer,$actor,$invoice,$contact,$revision,$requestKey,$baseUrl,$decrypt,$source);}
    /** Queue one overdue notice after revalidating the owned invoice and recipient. Queueing is not delivery. */
    public function queueOverdueNotice($issuer,$actor,$invoice,$contact,string $revision,string $requestKey,string $baseUrl,callable $decrypt):int
    {
        $contact=IssuerPolicy::id($contact);$invoice=IssuerPolicy::id($invoice);if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_invoice_delivery_invalid');
        $key=hash('sha256','overdue-notice:'.$requestKey.':'.$invoice.':'.$contact);
        return $this->queueOwnedSource($issuer,$actor,$invoice,$contact,$revision,$key,$baseUrl,$decrypt,null,null,'invoice-overdue-notice');
    }
    /** Queue one payment notice for one authorized contact. Queueing is not delivery. */
    public function queuePaymentNotice($issuer,$actor,$invoice,$contact,string $revision,string $paymentKey,string $baseUrl,callable $decrypt):int
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$paymentKey))throw new DomainException('company_invoice_delivery_invalid');
        $contact=IssuerPolicy::id($contact);$key=hash('sha256','payment-notice:'.$paymentKey.':'.$contact);
        return $this->queueOwnedSource($issuer,$actor,$invoice,$contact,$revision,$key,$baseUrl,$decrypt,null,$paymentKey);
    }
    private function paymentNotice(int $issuer,int $actor,int $invoice,string $key):array
    {
        $r=$this->rows('SELECT n.*,p.invoiceid,p.amount,p.date,p.paymentmode,p.paymentmethod,p.transactionid,p.note,r.input_hash FROM '.$this->p.'company_payment_notice_intents n JOIN '.$this->p.'company_payment_receipts r ON r.request_key=n.request_key AND r.issuer_id=n.issuer_id AND r.invoice_id=n.invoice_id AND r.payment_id=n.payment_id JOIN '.$this->p.'invoicepaymentrecords p ON p.id=n.payment_id WHERE n.request_key=? AND n.issuer_id=? AND n.actor_id=? AND n.invoice_id=? FOR UPDATE',[$key,$issuer,$actor,$invoice])[0]??null;
        if(!$r||(int)$r['email_requested']!==1||$r['email_status']!=='needs_review'||(int)$r['invoiceid']!==$invoice)throw new DomainException('company_payment_notice_denied');
        $minor=\app\services\InvoicePaymentWriter::minorUnits($r['amount']);$amount=intdiv($minor,100).'.'.str_pad((string)($minor%100),2,'0',STR_PAD_LEFT);
        $data=['invoiceid'=>$invoice,'amount'=>$amount,'paymentmode'=>(string)$r['paymentmode'],'paymentmethod'=>$r['paymentmethod'],'date'=>$r['date'],'transactionid'=>$r['transactionid'],'note'=>$r['note']];
        if(!hash_equals($r['input_hash'],hash('sha256',json_encode($data,JSON_THROW_ON_ERROR))))throw new DomainException('company_payment_notice_denied');
        if($this->db->table_exists($this->p.'company_credit_entries')&&$this->rows("SELECT request_key FROM {$this->p}company_credit_entries WHERE issuer_id=? AND kind='manual_reverse' AND native_id=?",[$issuer,$r['payment_id']]))throw new DomainException('company_payment_notice_denied');
        $r['amount']=$amount;
        return $r;
    }
    private function paymentTables():array
    {
        $tables=['company_payment_notice_intents','company_payment_notice_deliveries','company_payment_receipts','invoicepaymentrecords'];if($this->db->table_exists($this->p.'company_credit_entries'))$tables[]='company_credit_entries';return $tables;
    }
    private function queueOwnedSource($issuer,$actor,$invoice,$contact,string $revision,string $requestKey,string $baseUrl,callable $decrypt,CompanyExpenseRecurringDeliverySource|CompanyInvoiceRecurringDeliverySource|null $source,?string $paymentKey=null,?string $templateSlug=null):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$contact=IssuerPolicy::id($contact);$this->authorize($issuer,$actor);
        foreach([$revision,$requestKey] as $value)if(!preg_match('/^[a-f0-9]{64}$/D',$value))throw new DomainException('company_invoice_delivery_invalid');
        $parts=parse_url($baseUrl);
        if(!$parts||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!isset($parts['host'])||(($parts['scheme']??'')!=='https'&&!(($parts['scheme']??'')==='http'&&in_array($parts['host'],['127.0.0.1','localhost','[::1]'],true))))throw new DomainException('company_invoice_delivery_invalid');
        return $this->transaction(function()use($issuer,$actor,$invoice,$contact,$revision,$requestKey,$baseUrl,$decrypt,$source,$paymentKey,$templateSlug):int{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor);if($source)$source->assertFor($this->db,$this->p,$issuer,$actor,$invoice,$contact);$row=$this->parent($issuer,$invoice);
            $payment=$paymentKey===null?null:$this->paymentNotice($issuer,$actor,$invoice,$paymentKey);
            $recipient=null;foreach($this->recipientsFor((int)$row['clientid']) as $candidate)if((int)$candidate['id']===$contact)$recipient=$candidate;
            if(!$recipient||!filter_var($recipient['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$recipient['email']))throw new DomainException('company_invoice_delivery_denied');
            $fingerprint=self::recipientHash($recipient);$receipt=$this->rows('SELECT * FROM '.$this->p.'company_invoice_deliveries WHERE request_key=? FOR UPDATE',[$requestKey])[0]??null;
            if($receipt){
                foreach(['issuer_id'=>$issuer,'actor_id'=>$actor,'invoice_id'=>$invoice,'contact_id'=>$contact] as $key=>$value)if((int)$receipt[$key]!==$value)throw new DomainException('company_invoice_delivery_conflict');
                if($paymentKey!==null){$binding=$this->rows('SELECT * FROM '.$this->p.'company_payment_notice_deliveries WHERE queue_id=?',[$receipt['queue_id']])[0]??null;if(!$binding||(int)$binding['issuer_id']!==$issuer||(int)$binding['contact_id']!==$contact||$binding['notice_key']!==$paymentKey||$binding['delivery_key']!==$requestKey)throw new DomainException('company_invoice_delivery_conflict');}
                if(!hash_equals($receipt['revision'],$revision)||!hash_equals($receipt['recipient_hash'],$fingerprint))throw new DomainException('company_invoice_delivery_conflict');if($source)$source->assertFor($this->db,$this->p,$issuer,$actor,$invoice,$contact);return (int)$receipt['queue_id'];
            }
            if(!in_array((int)$row['status'],[1,2,3,4],true))throw new DomainException('company_invoice_delivery_denied');
            if(!hash_equals((new CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,$invoice),$revision))throw new DomainException('company_invoice_stale');
            $store=new CompanySettingsStorage($this->db,$this->p);$config=CompanyMailConfig::build($this->db,$this->p,$issuer,$decrypt,null,'document_notifications');
            $template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$issuer,$templateSlug??($payment===null?'invoice-send-to-client':'invoice-payment-recorded'),'portuguese_br');
            if(!$template||(int)$template->active!==1||($payment===null&&!str_contains($template->message,'{invoice_link}')))throw new DomainException('company_invoice_delivery_template_required');
            $from=trim($store->get($issuer,'smtp_email'));if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('company_invoice_delivery_sender_required');
            $link=rtrim($baseUrl,'/').'/company_portal/index/'.$issuer;
            $plain=['{companyname}'=>$row['legal_name'],'{contact_firstname}'=>$recipient['firstname'],'{contact_lastname}'=>$recipient['lastname'],'{contact_email}'=>$recipient['email'],'{invoice_number}'=>$row['formatted_number'],'{invoice_status}'=>[1=>'Em aberto',2=>'Paga',3=>'Parcialmente paga',4=>'Vencida'][(int)$row['status']],'{invoice_link}'=>$link,'{crm_url}'=>$link];
            if($payment!==null){$plain['{payment_total}']=$payment['amount'];$plain['{payment_date}']=$payment['date'];}
            $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html=array_map($escape,$plain);$html['{email_signature}']=$store->get($issuer,'email_signature');
            $message=strtr($store->get($issuer,'email_header').$template->message.$store->get($issuer,'email_footer'),$html);
            $subject=strtr($template->subject,$plain);$fromName=strtr($template->fromname,$plain);if(preg_match('/[\r\n]/',$subject.$fromName))throw new DomainException('company_invoice_delivery_template_required');
            if((int)$template->plaintext===1)$message=nl2br($escape(html_entity_decode(strip_tags(str_replace(['<br>','<br />','<br/>'],"\n",$message)),ENT_QUOTES,'UTF-8')."\n".$link));
            $headers=$config['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];
            $headers[self::HEADER]=$requestKey;if($source)$headers[$source::HEADER]=$source->marker();if($paymentKey!==null)$headers[self::PAYMENT_HEADER]=$paymentKey;
            $fields=['engine'=>$config['useragent'],'email'=>$recipient['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8')."\n".$link,'headers'=>serialize($headers),'attachments'=>'','status'=>'pending','date'=>date('Y-m-d H:i:s'),'issuer_id'=>$issuer];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$this->db->insert_id();
            if($queue<1)throw new RuntimeException('issuer_invoice_delivery_unconfirmed');
            $written=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$issuer])[0]??[];
            foreach($fields as $key=>$value)if((string)($written[$key]??'')!==(string)$value)throw new RuntimeException('issuer_invoice_delivery_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_invoice_deliveries(request_key,issuer_id,actor_id,invoice_id,contact_id,revision,recipient_hash,queue_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$requestKey,$issuer,$actor,$invoice,$contact,$revision,$fingerprint,$queue,date('Y-m-d H:i:s')]);
            if($paymentKey!==null){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_payment_notice_deliveries(queue_id,issuer_id,notice_key,delivery_key,contact_id) VALUES(?,?,?,?,?)',[$queue,$issuer,$paymentKey,$requestKey,$contact]);$binding=$this->rows('SELECT * FROM '.$this->p.'company_payment_notice_deliveries WHERE queue_id=?',[$queue])[0]??null;if(!$binding||(int)$binding['issuer_id']!==$issuer||(int)$binding['contact_id']!==$contact||$binding['notice_key']!==$paymentKey||$binding['delivery_key']!==$requestKey)throw new RuntimeException('issuer_invoice_delivery_unconfirmed');}
            $receipt=$this->rows('SELECT queue_id FROM '.$this->p.'company_invoice_deliveries WHERE request_key=?',[$requestKey])[0]??[];if((int)($receipt['queue_id']??0)!==$queue)throw new RuntimeException('issuer_invoice_delivery_unconfirmed');
            (new CompanyMailTransportPolicy($this->db,$this->p))->bindQueueInOwnedTransaction($issuer,$actor,$queue,'invoice',$requestKey);
            $metadata=json_encode(['queue_id'=>$queue,'contact_id'=>$contact],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,"INSERT INTO ".$this->p."issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,'company_invoice_notification_queued',?,?,?,?)",[$actor,$issuer,$invoice,date('Y-m-d H:i:s'),$metadata]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$audit,$issuer,$invoice,$metadata]))throw new RuntimeException('issuer_invoice_delivery_unconfirmed');$this->authorize($issuer,$actor);if($source)$source->assertFor($this->db,$this->p,$issuer,$actor,$invoice,$contact);
            if($paymentKey!==null){
                if($this->paymentNotice($issuer,$actor,$invoice,$paymentKey)!==$payment)throw new DomainException('company_payment_notice_denied');
                if($this->parent($issuer,$invoice)!==$row||!hash_equals((new CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,$invoice),$revision))throw new DomainException('company_invoice_stale');
                $afterTemplate=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$issuer,'invoice-payment-recorded','portuguese_br');if(!$afterTemplate||(int)$afterTemplate->active!==1||(array)$afterTemplate!==(array)$template)throw new DomainException('company_invoice_delivery_template_required');
                $final=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$issuer])[0]??[];foreach($fields as$key=>$value)if((string)($final[$key]??'')!==(string)$value)throw new RuntimeException('issuer_invoice_delivery_unconfirmed');
                $binding=$this->rows('SELECT * FROM '.$this->p.'company_payment_notice_deliveries WHERE queue_id=?',[$queue])[0]??null;if(!$binding||(int)$binding['issuer_id']!==$issuer||(int)$binding['contact_id']!==$contact||$binding['notice_key']!==$paymentKey||$binding['delivery_key']!==$requestKey)throw new RuntimeException('issuer_invoice_delivery_unconfirmed');
                $current=null;foreach($this->recipientsFor((int)$row['clientid'])as$candidate)if((int)$candidate['id']===$contact)$current=$candidate;if(!$current||self::recipientHash($current)!==$fingerprint)throw new DomainException('company_invoice_delivery_denied');
            }
            return $queue;
        },array_merge($source?$source->tables():[],$paymentKey===null?[]:$this->paymentTables()));
    }
    /** Called by the unscoped worker after claim, before initializing SMTP. No network inside transaction. */
    public function assertDispatch(object $email,string $requestKey):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_invoice_delivery_denied');
        $hasPayments=$this->db->table_exists($this->p.'company_payment_notice_deliveries');
        $this->transaction(function()use($email,$requestKey):void{
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_invoice_deliveries WHERE request_key=? AND queue_id=? FOR UPDATE',[$requestKey,$email->id])[0]??null;
            if(!$receipt||(int)($email->issuer_id??0)!==(int)$receipt['issuer_id']||$email->cc!==''||$email->bcc!==''||$email->attachments!=='')throw new DomainException('company_invoice_delivery_denied');
            $issuer=(int)$receipt['issuer_id'];$actor=(int)$receipt['actor_id'];$invoice=(int)$receipt['invoice_id'];$this->authorize($issuer,$actor);$row=$this->parent($issuer,$invoice);
            if(!in_array((int)$row['status'],[1,2,3,4],true)||!hash_equals($receipt['revision'],(new CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,$invoice)))throw new DomainException('company_invoice_stale');
            $recipient=null;foreach($this->recipientsFor((int)$row['clientid']) as $candidate)if((int)$candidate['id']===(int)$receipt['contact_id'])$recipient=$candidate;
            if(!$recipient||$recipient['email']!==$email->email||!hash_equals($receipt['recipient_hash'],self::recipientHash($recipient)))throw new DomainException('company_invoice_delivery_denied');
            if($this->db->table_exists($this->p.'company_payment_notice_deliveries')){
                $bindings=$this->rows('SELECT * FROM '.$this->p.'company_payment_notice_deliveries WHERE queue_id=?',[$email->id]);
                $headers=@unserialize($email->headers,['allowed_classes'=>false]);if(!is_array($headers))throw new DomainException('company_invoice_delivery_denied');$marker=$headers[self::PAYMENT_HEADER]??null;
                if($bindings){$binding=$bindings[0];if(count($bindings)!==1||!is_string($marker)||$marker!==$binding['notice_key']||$binding['delivery_key']!==$requestKey||(int)$binding['issuer_id']!==$issuer||(int)$binding['contact_id']!==(int)$receipt['contact_id']||$requestKey!==hash('sha256','payment-notice:'.$marker.':'.$receipt['contact_id']))throw new DomainException('company_payment_notice_denied');$this->paymentNotice($issuer,$actor,$invoice,$marker);$template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$issuer,'invoice-payment-recorded','portuguese_br');if(!$template||(int)$template->active!==1)throw new DomainException('company_invoice_delivery_template_required');}
                elseif($marker!==null)throw new DomainException('company_payment_notice_denied');
            }
            (new CompanyMailTransportPolicy($this->db,$this->p))->assertQueueInOwnedTransaction($issuer,$actor,(int)$email->id,'invoice',$requestKey);
        },$hasPayments?$this->paymentTables():[]);
    }
}
