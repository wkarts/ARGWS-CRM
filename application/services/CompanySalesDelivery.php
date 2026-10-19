<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySales.php';
require_once __DIR__.'/CompanyMailConfig.php';
require_once __DIR__.'/CompanyEmailTemplates.php';
require_once __DIR__.'/CompanySalesExtrasSchema.php';

/** An explicit notification, never a public invoice token or proof of SMTP delivery. */
final class CompanySalesDelivery
{
    public const HEADER='companySalesDelivery';
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor,int $document):void
    {
        $ref=$this->rows('SELECT kind FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND id=?',[$issuer,$document])[0]??null;if(!$ref||!in_array($ref['kind'],['estimates','proposals'],true))throw new DomainException('company_sales_delivery_denied');$acl=new IssuerResourceAccess($this->db,$this->p);foreach([[$ref['kind'],'view'],[$ref['kind'],'edit'],['customers','view']] as [$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('company_sales_delivery_denied');
    }
    private function transaction(callable $work)
    {
        CompanySalesExtrasSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','contacts','contact_permissions','company_sales_documents','emailtemplates','options','mail_queue','company_sales_deliveries','issuers_audit'],$work);
    }
    private function parent(int $issuer,int $document):array
    {
        $row=$this->rows('SELECT d.id,d.kind,d.state,d.number,d.client_id AS clientid,d.payload_json,d.context_json,d.origin_hash,d.input_hash,r.legal_name FROM '.$this->p.'company_sales_documents d JOIN '.$this->p.'issuers_registry r ON r.id=d.issuer_id AND r.active=1 JOIN '.$this->p.'clients c ON c.userid=d.client_id AND c.active=1 JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=d.issuer_id WHERE d.id=? AND d.issuer_id=? FOR UPDATE',[$document,$issuer])[0]??null;if(!$row)throw new DomainException('company_sales_delivery_denied');$row['formatted_number']=($row['kind']==='estimates'?'EST':'PROP').'-'.str_pad((string)$row['number'],6,'0',STR_PAD_LEFT);return $row;
    }
    private static function permission(array $row):int{return $row['kind']==='estimates'?2:4;}
    private static function revisionFor(array $row):string{return hash('sha256',json_encode([$row['id'],$row['kind'],$row['state'],$row['number'],$row['origin_hash'],$row['input_hash']],JSON_THROW_ON_ERROR));}
    private function configurationHash(int $issuer):string
    {
        $store=new CompanySettingsStorage($this->db,$this->p);$values=[];foreach(['mail_engine','email_protocol','smtp_host','smtp_username','smtp_email','smtp_password','smtp_port','smtp_encryption','smtp_email_charset','microsoft_mail_client_id','microsoft_mail_client_secret','microsoft_mail_refresh_token','microsoft_mail_azure_tenant_id','google_mail_client_id','google_mail_client_secret','google_mail_refresh_token'] as $key)$values[$key]=$store->get($issuer,$key);return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }
    /** Known factory bodies are adapted to this authenticated portal transport without changing custom templates. */
    private static function privateFactoryMessage(object $template,string $slug,string $type):string
    {
        foreach(CompanyEmailTemplates::defaults() as $factory)if($factory['slug']===$slug&&$factory['language']===$template->language&&hash_equals($factory['message'],$template->message))return '<p>{contact_firstname} {contact_lastname},</p><p>O documento {' . $type . '_number} está disponível no portal privado desta empresa. Entre com sua conta para consultar o PDF e os anexos publicados.</p><p><a href="{' . $type . '_link}">Abrir portal autenticado</a></p><p>Esta notificação não contém PDF público ou anexos enviados por e-mail.</p><p>{email_signature}</p>';
        return $template->message;
    }
    private static function payloadHash(object $mail):string
    {
        $values=[];foreach(['engine','email','cc','bcc','message','alt_message','headers','attachments','issuer_id'] as $field)$values[$field]=(string)($mail->$field??'');return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }
    private function recipientsFor(int $client,int $permission):array
    {
        return $this->rows('SELECT c.id,c.firstname,c.lastname,c.email FROM '.$this->p.'contacts c WHERE c.userid=? AND c.active=1 AND (SELECT COUNT(*) FROM '.$this->p.'contact_permissions p WHERE p.userid=c.id AND p.permission_id=?)=1 ORDER BY c.id LIMIT 101',[$client,$permission]);
    }
    private static function recipientHash(array $row):string
    {
        return hash('sha256',json_encode([$row['id'],$row['firstname'],$row['lastname'],$row['email']],JSON_THROW_ON_ERROR));
    }
    public function state($issuer,$actor,$document):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$document=IssuerPolicy::id($document);$this->authorize($issuer,$actor,$document);
        return $this->transaction(function()use($issuer,$actor,$document):array{
            $this->authorize($issuer,$actor,$document);$row=$this->parent($issuer,$document);
            $contacts=in_array($row['state'],['accepted','converted'],true)?$this->recipientsFor((int)$row['clientid'],self::permission($row)):[];
            $history=$this->rows("SELECT d.created_at,COALESCE(q.status,'archived') status FROM ".$this->p.'company_sales_deliveries d LEFT JOIN '.$this->p.'mail_queue q ON q.id=d.queue_id AND q.issuer_id=d.issuer_id WHERE d.issuer_id=? AND d.document_id=? ORDER BY d.created_at DESC,d.queue_id DESC LIMIT 20',[$issuer,$document]);
            return ['recipients'=>array_slice($contacts,0,100),'history'=>$history,'revision'=>self::revisionFor($row)];
        });
    }
    public function queue($issuer,$actor,$document,$contact,string $revision,string $requestKey,string $baseUrl,callable $decrypt):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$document=IssuerPolicy::id($document);$contact=IssuerPolicy::id($contact);$this->authorize($issuer,$actor,$document);
        foreach([$revision,$requestKey] as $value)if(!preg_match('/^[a-f0-9]{64}$/D',$value))throw new DomainException('company_sales_delivery_invalid');
        $parts=parse_url($baseUrl);
        if(!$parts||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!isset($parts['host'])||(($parts['scheme']??'')!=='https'&&!(($parts['scheme']??'')==='http'&&in_array($parts['host'],['127.0.0.1','localhost','[::1]'],true))))throw new DomainException('company_sales_delivery_invalid');
        return $this->transaction(function()use($issuer,$actor,$document,$contact,$revision,$requestKey,$baseUrl,$decrypt):int{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,$document);$row=$this->parent($issuer,$document);
            $recipient=null;foreach($this->recipientsFor((int)$row['clientid'],self::permission($row)) as $candidate)if((int)$candidate['id']===$contact)$recipient=$candidate;
            if(!$recipient||!filter_var($recipient['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$recipient['email']))throw new DomainException('company_sales_delivery_denied');
            $fingerprint=self::recipientHash($recipient);$receipt=$this->rows('SELECT * FROM '.$this->p.'company_sales_deliveries WHERE request_key=? FOR UPDATE',[$requestKey])[0]??null;
            if($receipt){
                foreach(['issuer_id'=>$issuer,'actor_id'=>$actor,'document_id'=>$document,'contact_id'=>$contact] as $key=>$value)if((int)$receipt[$key]!==$value)throw new DomainException('company_sales_delivery_conflict');
                if(!hash_equals($receipt['revision'],$revision)||!hash_equals($receipt['recipient_hash'],$fingerprint))throw new DomainException('company_sales_delivery_conflict');return (int)$receipt['queue_id'];
            }
            if(!in_array($row['state'],['accepted','converted'],true))throw new DomainException('company_sales_delivery_denied');
            if(!hash_equals(self::revisionFor($row),$revision))throw new DomainException('company_sales_stale');
            $store=new CompanySettingsStorage($this->db,$this->p);$config=CompanyMailConfig::build($this->db,$this->p,$issuer,$decrypt);
            $type=$row['kind']==='estimates'?'estimate':'proposal';$slug=$type==='estimate'?'estimate-send-to-client':'proposal-send-to-customer';$template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$issuer,$slug,'portuguese_br');
            if(!$template||(int)$template->active!==1||!str_contains($template->message,'{'.$type.'_link}'))throw new DomainException('company_sales_delivery_template_required');
            $from=trim($store->get($issuer,'smtp_email'));if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('company_sales_delivery_sender_required');
            $link=rtrim($baseUrl,'/').'/company_portal/index/'.$issuer;
            $plain=['{companyname}'=>$row['legal_name'],'{contact_firstname}'=>$recipient['firstname'],'{contact_lastname}'=>$recipient['lastname'],'{contact_email}'=>$recipient['email'],'{'.$type.'_number}'=>$row['formatted_number'],'{'.$type.'_status}'=>$row['state']==='accepted'?'Aceito':'Convertido em fatura','{'.$type.'_link}'=>$link,'{proposal_proposal_to}'=>json_decode($row['context_json'],true,32,JSON_THROW_ON_ERROR)['recipient']['name'],'{proposal_open_till}'=>json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR)['due_date'],'{crm_url}'=>$link];
            $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html=array_map($escape,$plain);$html['{email_signature}']=$store->get($issuer,'email_signature');
            $message=strtr($store->get($issuer,'email_header').self::privateFactoryMessage($template,$slug,$type).$store->get($issuer,'email_footer'),$html);
            $subject=strtr($template->subject,$plain);$fromName=strtr($template->fromname,$plain);if(preg_match('/[\r\n]/',$subject.$fromName))throw new DomainException('company_sales_delivery_template_required');
            if((int)$template->plaintext===1)$message=nl2br($escape(html_entity_decode(strip_tags(str_replace(['<br>','<br />','<br/>'],"\n",$message)),ENT_QUOTES,'UTF-8')."\n".$link));
            $headers=$config['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];
            $headers[self::HEADER]=$requestKey;
            $fields=['engine'=>$config['useragent'],'email'=>$recipient['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8')."\n".$link,'headers'=>serialize($headers),'attachments'=>'','status'=>'pending','date'=>date('Y-m-d H:i:s'),'issuer_id'=>$issuer];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$this->db->insert_id();
            if($queue<1)throw new RuntimeException('issuer_sales_delivery_unconfirmed');
            $written=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$issuer])[0]??[];
            foreach($fields as $key=>$value)if((string)($written[$key]??'')!==(string)$value)throw new RuntimeException('issuer_sales_delivery_unconfirmed');
            $configurationHash=$this->configurationHash($issuer);$payloadHash=self::payloadHash((object)$fields);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_sales_deliveries(request_key,issuer_id,actor_id,document_id,contact_id,revision,recipient_hash,configuration_hash,payload_hash,queue_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$requestKey,$issuer,$actor,$document,$contact,$revision,$fingerprint,$configurationHash,$payloadHash,$queue,date('Y-m-d H:i:s')]);
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_sales_deliveries WHERE request_key=?',[$requestKey])[0]??[];foreach(['request_key'=>$requestKey,'issuer_id'=>$issuer,'actor_id'=>$actor,'document_id'=>$document,'contact_id'=>$contact,'revision'=>$revision,'recipient_hash'=>$fingerprint,'configuration_hash'=>$configurationHash,'payload_hash'=>$payloadHash,'queue_id'=>$queue] as $field=>$value)if((string)($receipt[$field]??'')!==(string)$value)throw new RuntimeException('issuer_sales_delivery_unconfirmed');
            $metadata=json_encode(['queue_id'=>$queue,'contact_id'=>$contact],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,"INSERT INTO ".$this->p."issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,'company_sales_notification_queued',?,?,?,?)",[$actor,$issuer,$document,date('Y-m-d H:i:s'),$metadata]);$audit=(int)$this->db->insert_id();
            if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$audit,$issuer,$document,$metadata]))throw new RuntimeException('issuer_sales_delivery_unconfirmed');$this->authorize($issuer,$actor,$document);return $queue;
        });
    }
    /** Called by the unscoped worker after claim, before initializing SMTP. No network inside transaction. */
    public function assertDispatch(object $email,string $requestKey):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_sales_delivery_denied');
        $this->transaction(function()use($email,$requestKey):void{
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_sales_deliveries WHERE request_key=? AND queue_id=? FOR UPDATE',[$requestKey,$email->id])[0]??null;
            if(!$receipt||(int)($email->issuer_id??0)!==(int)$receipt['issuer_id']||$email->cc!==''||$email->bcc!==''||$email->attachments!=='')throw new DomainException('company_sales_delivery_denied');
            $issuer=(int)$receipt['issuer_id'];$actor=(int)$receipt['actor_id'];$document=(int)$receipt['document_id'];(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);if(!hash_equals($receipt['configuration_hash'],$this->configurationHash($issuer))||!hash_equals($receipt['payload_hash'],self::payloadHash($email)))throw new DomainException('company_sales_delivery_denied');$this->authorize($issuer,$actor,$document);$row=$this->parent($issuer,$document);
            if(!in_array($row['state'],['accepted','converted'],true)||!hash_equals($receipt['revision'],self::revisionFor($row)))throw new DomainException('company_sales_stale');
            $recipient=null;foreach($this->recipientsFor((int)$row['clientid'],self::permission($row)) as $candidate)if((int)$candidate['id']===(int)$receipt['contact_id'])$recipient=$candidate;
            if(!$recipient||$recipient['email']!==$email->email||!hash_equals($receipt['recipient_hash'],self::recipientHash($recipient)))throw new DomainException('company_sales_delivery_denied');
        });
    }
}
