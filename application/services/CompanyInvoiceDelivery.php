<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';
require_once __DIR__.'/CompanyMailConfig.php';
require_once __DIR__.'/CompanyGoogleMailOAuth.php';
require_once __DIR__.'/CompanyEmailTemplates.php';
require_once __DIR__.'/CompanyInvoiceDeliverySchema.php';

/** An explicit notification, never a public invoice token or proof of SMTP delivery. */
final class CompanyInvoiceDelivery
{
    public const HEADER='companyInvoiceDelivery';
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
        return IssuerTransaction::run($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','contacts','contact_permissions','invoices','itemable','item_tax','issuers_invoice_documents','emailtemplates','options','mail_queue','company_invoice_deliveries','issuers_audit'],CompanyGoogleMailOAuth::participantTables($this->db,$this->p),$extraTables),$work);
    }
    private function parent(int $issuer,int $invoice):array
    {
        $row=$this->rows('SELECT i.id,i.clientid,i.status,d.formatted_number,r.legal_name FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id JOIN '.$this->p.'issuers_registry r ON r.id=i.issuer_id AND r.active=1 JOIN '.$this->p.'clients c ON c.userid=i.clientid AND c.active=1 JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
        if(!$row)throw new DomainException('company_invoice_delivery_denied');return $row;
    }
    private function recipientsFor(int $client):array
    {
        return $this->rows('SELECT c.id,c.firstname,c.lastname,c.email FROM '.$this->p.'contacts c WHERE c.userid=? AND c.active=1 AND (SELECT COUNT(*) FROM '.$this->p.'contact_permissions p WHERE p.userid=c.id AND p.permission_id=1)=1 ORDER BY c.id LIMIT 101',[$client]);
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
            return ['recipients'=>array_slice($contacts,0,100),'history'=>$history];
        });
    }
    public function queue($issuer,$actor,$invoice,$contact,string $revision,string $requestKey,string $baseUrl,callable $decrypt,?CompanyExpenseRecurringDeliverySource $source=null):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$contact=IssuerPolicy::id($contact);$this->authorize($issuer,$actor);
        foreach([$revision,$requestKey] as $value)if(!preg_match('/^[a-f0-9]{64}$/D',$value))throw new DomainException('company_invoice_delivery_invalid');
        $parts=parse_url($baseUrl);
        if(!$parts||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!isset($parts['host'])||(($parts['scheme']??'')!=='https'&&!(($parts['scheme']??'')==='http'&&in_array($parts['host'],['127.0.0.1','localhost','[::1]'],true))))throw new DomainException('company_invoice_delivery_invalid');
        return $this->transaction(function()use($issuer,$actor,$invoice,$contact,$revision,$requestKey,$baseUrl,$decrypt,$source):int{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor);if($source)$source->assertFor($this->db,$this->p,$issuer,$actor,$invoice,$contact);$row=$this->parent($issuer,$invoice);
            $recipient=null;foreach($this->recipientsFor((int)$row['clientid']) as $candidate)if((int)$candidate['id']===$contact)$recipient=$candidate;
            if(!$recipient||!filter_var($recipient['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$recipient['email']))throw new DomainException('company_invoice_delivery_denied');
            $fingerprint=self::recipientHash($recipient);$receipt=$this->rows('SELECT * FROM '.$this->p.'company_invoice_deliveries WHERE request_key=? FOR UPDATE',[$requestKey])[0]??null;
            if($receipt){
                foreach(['issuer_id'=>$issuer,'actor_id'=>$actor,'invoice_id'=>$invoice,'contact_id'=>$contact] as $key=>$value)if((int)$receipt[$key]!==$value)throw new DomainException('company_invoice_delivery_conflict');
                if(!hash_equals($receipt['revision'],$revision)||!hash_equals($receipt['recipient_hash'],$fingerprint))throw new DomainException('company_invoice_delivery_conflict');if($source)$source->assertFor($this->db,$this->p,$issuer,$actor,$invoice,$contact);return (int)$receipt['queue_id'];
            }
            if(!in_array((int)$row['status'],[1,2,3,4],true))throw new DomainException('company_invoice_delivery_denied');
            if(!hash_equals((new CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,$invoice),$revision))throw new DomainException('company_invoice_stale');
            $store=new CompanySettingsStorage($this->db,$this->p);$config=CompanyMailConfig::build($this->db,$this->p,$issuer,$decrypt,null,'document_notifications');
            $template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$issuer,'invoice-send-to-client','portuguese_br');
            if(!$template||(int)$template->active!==1||!str_contains($template->message,'{invoice_link}'))throw new DomainException('company_invoice_delivery_template_required');
            $from=trim($store->get($issuer,'smtp_email'));if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('company_invoice_delivery_sender_required');
            $link=rtrim($baseUrl,'/').'/company_portal/index/'.$issuer;
            $plain=['{companyname}'=>$row['legal_name'],'{contact_firstname}'=>$recipient['firstname'],'{contact_lastname}'=>$recipient['lastname'],'{contact_email}'=>$recipient['email'],'{invoice_number}'=>$row['formatted_number'],'{invoice_status}'=>[1=>'Em aberto',2=>'Paga',3=>'Parcialmente paga',4=>'Vencida'][(int)$row['status']],'{invoice_link}'=>$link,'{crm_url}'=>$link];
            $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html=array_map($escape,$plain);$html['{email_signature}']=$store->get($issuer,'email_signature');
            $message=strtr($store->get($issuer,'email_header').$template->message.$store->get($issuer,'email_footer'),$html);
            $subject=strtr($template->subject,$plain);$fromName=strtr($template->fromname,$plain);if(preg_match('/[\r\n]/',$subject.$fromName))throw new DomainException('company_invoice_delivery_template_required');
            if((int)$template->plaintext===1)$message=nl2br($escape(html_entity_decode(strip_tags(str_replace(['<br>','<br />','<br/>'],"\n",$message)),ENT_QUOTES,'UTF-8')."\n".$link));
            $headers=$config['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];
            $headers[self::HEADER]=$requestKey;if($source)$headers[CompanyExpenseRecurringDeliverySource::HEADER]=$source->marker();
            $fields=['engine'=>$config['useragent'],'email'=>$recipient['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8')."\n".$link,'headers'=>serialize($headers),'attachments'=>'','status'=>'pending','date'=>date('Y-m-d H:i:s'),'issuer_id'=>$issuer];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$this->db->insert_id();
            if($queue<1)throw new RuntimeException('issuer_invoice_delivery_unconfirmed');
            $written=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$issuer])[0]??[];
            foreach($fields as $key=>$value)if((string)($written[$key]??'')!==(string)$value)throw new RuntimeException('issuer_invoice_delivery_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_invoice_deliveries(request_key,issuer_id,actor_id,invoice_id,contact_id,revision,recipient_hash,queue_id,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$requestKey,$issuer,$actor,$invoice,$contact,$revision,$fingerprint,$queue,date('Y-m-d H:i:s')]);
            $receipt=$this->rows('SELECT queue_id FROM '.$this->p.'company_invoice_deliveries WHERE request_key=?',[$requestKey])[0]??[];if((int)($receipt['queue_id']??0)!==$queue)throw new RuntimeException('issuer_invoice_delivery_unconfirmed');
            if(CompanyGoogleMailOAuthSchema::present($this->db,$this->p))(new CompanyGoogleMailOAuth($this->db,$this->p))->bindQueueInOwnedTransaction($issuer,$actor,$queue,'invoice',$requestKey);
            $metadata=json_encode(['queue_id'=>$queue,'contact_id'=>$contact],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,"INSERT INTO ".$this->p."issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,'company_invoice_notification_queued',?,?,?,?)",[$actor,$issuer,$invoice,date('Y-m-d H:i:s'),$metadata]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$audit,$issuer,$invoice,$metadata]))throw new RuntimeException('issuer_invoice_delivery_unconfirmed');$this->authorize($issuer,$actor);if($source)$source->assertFor($this->db,$this->p,$issuer,$actor,$invoice,$contact);return $queue;
        },$source?$source->tables():[]);
    }
    /** Called by the unscoped worker after claim, before initializing SMTP. No network inside transaction. */
    public function assertDispatch(object $email,string $requestKey):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_invoice_delivery_denied');
        $this->transaction(function()use($email,$requestKey):void{
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_invoice_deliveries WHERE request_key=? AND queue_id=? FOR UPDATE',[$requestKey,$email->id])[0]??null;
            if(!$receipt||(int)($email->issuer_id??0)!==(int)$receipt['issuer_id']||$email->cc!==''||$email->bcc!==''||$email->attachments!=='')throw new DomainException('company_invoice_delivery_denied');
            $issuer=(int)$receipt['issuer_id'];$actor=(int)$receipt['actor_id'];$invoice=(int)$receipt['invoice_id'];$this->authorize($issuer,$actor);$row=$this->parent($issuer,$invoice);
            if(!in_array((int)$row['status'],[1,2,3,4],true)||!hash_equals($receipt['revision'],(new CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,$invoice)))throw new DomainException('company_invoice_stale');
            $recipient=null;foreach($this->recipientsFor((int)$row['clientid']) as $candidate)if((int)$candidate['id']===(int)$receipt['contact_id'])$recipient=$candidate;
            if(!$recipient||$recipient['email']!==$email->email||!hash_equals($receipt['recipient_hash'],self::recipientHash($recipient)))throw new DomainException('company_invoice_delivery_denied');
            if(CompanyGoogleMailOAuthSchema::present($this->db,$this->p))(new CompanyGoogleMailOAuth($this->db,$this->p))->assertQueueInOwnedTransaction($issuer,$actor,(int)$email->id,'invoice',$requestKey);
        });
    }
}
