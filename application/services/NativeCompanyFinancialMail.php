<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyPaymentRecords.php';
require_once __DIR__.'/CompanyFinancialDocumentPdf.php';
require_once __DIR__.'/CompanyPortalMailConfig.php';
require_once __DIR__.'/CompanyPortalMailIdentity.php';
require_once __DIR__.'/CompanyEmailTemplates.php';
require_once __DIR__.'/NativeCompanyMailDispatchFence.php';
/** Explicit receipt/overdue messages, bound to current source, contact, template, PDF bytes and transport. */
final class NativeCompanyFinancialMail
{
    public const HEADER='nativeCompanyFinancialDelivery';
    private const EVENT='native_financial_mail_queued';
    private const FENCE='financial_document';
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $q,array $a=[]):array{return IssuerStorage::query($this->db,$q,$a)->result_array();}
    private static function hash(array $value):string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
    public function tables():array{return array_values(array_unique(array_merge((new CompanyInvoices($this->db,$this->p))->transactionParticipants(),NativeCompanyMailDispatchFence::tables(),(new CompanyPortalMailConfig($this->db,$this->p))->tables(),['contacts','contact_permissions','emailtemplates'])));}
    private function authorize(int $i,int $a,string $kind):void{
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($i,$a);
        foreach([['invoices','view'],['invoices','edit'],['customers','view']]as[$feature,$cap])if(!$acl->can($i,$a,$feature,$cap))throw new DomainException('native_financial_mail_denied');
        if($kind==='payment'&&!$acl->can($i,$a,'payments','view'))throw new DomainException('native_financial_mail_denied');
    }
    private function source(int $i,int $a,string $kind,int $id):array{
        $this->authorize($i,$a,$kind);$store=new CompanySettingsStorage($this->db,$this->p);$service=new CompanyInvoices($this->db,$this->p);
        if($kind==='payment'){
            $data=(new NativeCompanyPaymentRecords($this->db,$this->p))->readInOwned($i,$a,$id);$document=$data['invoice'];$slug='invoice-payment-recorded';$values=['{payment_total}'=>$data['payment']['effective_amount'],'{payment_date}'=>$data['payment']['date']];
            $format=$store->exists($i,'pdf_format_payment')?$store->get($i,'pdf_format_payment'):'A4-PORTRAIT';CompanyDocumentPdf::validate($format);
            $attach=$store->exists($i,'attach_invoice_to_payment_receipt_email')?$store->get($i,'attach_invoice_to_payment_receipt_email'):'0';if(!in_array($attach,['0','1'],true))throw new DomainException('native_financial_mail_invalid');
        }elseif($kind==='overdue'){
            $document=$service->documentInOwned($i,$a,$id)->data();$balance=(new CompanyInvoiceBalance($this->db,$this->p))->inOwned($i,$a,$id);$row=$this->rows('SELECT cancel_overdue_reminders FROM '.$this->p.'invoices WHERE id=? AND issuer_id=?',[$id,$i])[0]??null;
            if(!$row||(string)$row['cancel_overdue_reminders']!=='0'||!in_array($document['status'],[1,3,4],true)||$document['due_date']===null||$document['due_date']>=gmdate('Y-m-d')||CompanyInvoiceAmounts::minor($balance['receivable'])<1)throw new DomainException('native_financial_mail_not_overdue');
            $data=['invoice'=>$document,'balance'=>$balance,'revision'=>$service->revision($i,$a,$id)];$slug='invoice-overdue-notice';$values=['{invoice_duedate}'=>$document['due_date'],'{invoice_total}'=>$balance['receivable']];$format='A4-PORTRAIT';$attach='0';
        }else throw new DomainException('native_financial_mail_invalid');
        $client=(int)$document['recipient']['client_id'];$private=$this->rows('SELECT c.userid FROM '.$this->p.'clients c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=? WHERE c.userid=? AND c.active=1',[$i,$client]);if(count($private)!==1)throw new DomainException('native_financial_mail_denied');
        return ['data'=>$data,'document'=>$document,'client'=>$client,'slug'=>$slug,'values'=>$values,'format'=>$format,'attach_invoice'=>$attach==='1'];
    }
    private function contacts(int $i,int $client):array{
        return$this->rows('SELECT c.id,c.firstname,c.lastname,c.email FROM '.$this->p.'contacts c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=? WHERE c.userid=? AND c.active=1 AND c.invoice_emails=1 AND (SELECT COUNT(*) FROM '.$this->p.'contact_permissions p WHERE p.userid=c.id AND p.permission_id=1)=1 ORDER BY c.id LIMIT 101',[$i,$client]);
    }
    public function preview(int $i,int $a,string $kind,int $id):array{
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$kind,$id){$source=$this->source($i,$a,$kind,$id);$contacts=$this->contacts($i,$source['client']);if(count($contacts)>100)throw new DomainException('native_financial_mail_limit');return ['revision'=>self::hash($source),'recipients'=>$contacts];});
    }
    private static function binding(array $b):string{
        if(array_keys($b)!==['issuer','actor','kind','id','contact','revision','request_key']||!in_array($b['kind'],['payment','overdue'],true))throw new DomainException('native_financial_mail_denied');
        foreach(['issuer','actor','id','contact']as$key)if(!is_int($b[$key])||$b[$key]<1)throw new DomainException('native_financial_mail_denied');
        foreach(['revision','request_key']as$key)if(!is_string($b[$key])||!preg_match('/^[a-f0-9]{64}$/D',$b[$key]))throw new DomainException('native_financial_mail_denied');return self::hash($b);
    }
    private function receipt(int $i,string $key):?array{
        $needle=json_encode(['request_key'=>$key],JSON_THROW_ON_ERROR);$rows=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND event=? AND JSON_VALID(metadata_json) AND JSON_EXTRACT(metadata_json,'$.request_key')=JSON_EXTRACT(?,'$.request_key') ORDER BY id",[$i,self::EVENT,$needle]);if(count($rows)>1)throw new RuntimeException('issuer_financial_mail_unconfirmed');return$rows?json_decode($rows[0]['metadata_json'],true,32,JSON_THROW_ON_ERROR):null;
    }
    private function recipient(int $i,int $client,int $id):array{
        foreach($this->contacts($i,$client)as$row)if((int)$row['id']===$id){if(!filter_var($row['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$row['email']))break;return$row;}throw new DomainException('native_financial_mail_denied');
    }
    public function queue(int $i,int $a,string $kind,int $id,int $contact,string $revision,string $key,string $base,callable $decrypt):int{
        $b=['issuer'=>$i,'actor'=>$a,'kind'=>$kind,'id'=>$id,'contact'=>$contact,'revision'=>$revision,'request_key'=>$key];$hash=self::binding($b);$parts=parse_url($base);
        if(!$parts||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||preg_match('/[\x00-\x20\x7f]/',$base)||(($parts['scheme']??'')!=='https'&&!(($parts['scheme']??'')==='http'&&in_array($parts['host'],['localhost','127.0.0.1','[::1]'],true))))throw new DomainException('native_financial_mail_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$kind,$id,$contact,$revision,$key,$base,$decrypt,$b,$hash){
            $source=$this->source($i,$a,$kind,$id);$recipient=$this->recipient($i,$source['client'],$contact);$prior=$this->receipt($i,$key);
            if($prior){if(($prior['binding_hash']??null)!==$hash||($prior['recipient_hash']??null)!==self::hash($recipient))throw new DomainException('native_financial_mail_conflict');return(int)$prior['queue_id'];}
            if(!hash_equals($revision,self::hash($source)))throw new DomainException('native_financial_mail_stale');
            $store=new CompanySettingsStorage($this->db,$this->p);$config=new CompanyPortalMailConfig($this->db,$this->p);$state=$config->stateInOwnedTransaction($i);$transport=$config->buildInOwnedTransaction($i,$decrypt);
            $template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$i,$source['slug'],$store->get($i,'active_language')?:'portuguese_br');if(!$template||(int)$template->active!==1)throw new DomainException('native_financial_mail_template_required');
            $from=$store->get($i,'smtp_email');if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('native_financial_mail_sender_required');$url=rtrim($base,'/').'/company_portal/index/'.$i;$doc=$source['document'];
            $values=['{contact_firstname}'=>$recipient['firstname'],'{contact_lastname}'=>$recipient['lastname'],'{contact_email}'=>$recipient['email'],'{invoice_number}'=>$doc['number'],'{invoice_link}'=>$url,'{client_company}'=>$doc['recipient']['name'],'{companyname}'=>$doc['identity']['legal_name'],'{crm_url}'=>$url]+$source['values'];$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html=array_map($e,$values);$html['{email_signature}']=$store->get($i,'email_signature');$message=strtr($store->get($i,'email_header').$template->message.$store->get($i,'email_footer'),$html);$subject=strtr($template->subject,$values);$name=strtr($template->fromname,$values);if(preg_match('/[\r\n]/',$subject.$name))throw new DomainException('native_financial_mail_invalid');if((int)$template->plaintext===1)$message=nl2br($e(html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8')));
            $headers=$transport['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$name,'subject'=>$subject]:['From'=>mb_encode_mimeheader($name,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];$headers[self::HEADER]=$b;$attachments='';
            if($kind==='payment'){
                require_once __DIR__.'/NativeCompanyFileMessage.php';$bytes=CompanyFinancialDocumentPdf::payment($source['data'],$source['format']);if(strlen($bytes)>4194304)throw new DomainException('native_financial_mail_limit');$attachments=NativeCompanyFileMessage::attachments($transport['useragent'],['filename'=>'pagamento-'.$id.'.pdf','mime'=>'application/pdf','content'=>$bytes]);
                if($source['attach_invoice']){require_once __DIR__.'/../libraries/pdf/Issuer_invoice_pdf.php';$pdf=new Issuer_invoice_pdf((new CompanyInvoices($this->db,$this->p))->documentInOwned($i,$a,$doc['invoice_id']));$extra=$pdf->prepare()->Output('','S');if(strlen($extra)>4194304)throw new DomainException('native_financial_mail_limit');$encoded=NativeCompanyFileMessage::attachments($transport['useragent'],['filename'=>'fatura-'.$doc['invoice_id'].'.pdf','mime'=>'application/pdf','content'=>$extra]);$attachments=base64_encode(serialize(array_merge(unserialize(base64_decode($attachments),['allowed_classes'=>false]),unserialize(base64_decode($encoded),['allowed_classes'=>false]))));}
            }
            $fields=['engine'=>$transport['useragent'],'email'=>$recipient['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8'),'headers'=>serialize($headers),'attachments'=>$attachments,'status'=>'pending','date'=>gmdate('Y-m-d H:i:s'),'issuer_id'=>$i];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$this->db->insert_id();CompanyPortalMailIdentity::confirmed($this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$i])[0]??[],$fields);
            $receipt=['request_key'=>$key,'queue_id'=>$queue,'binding_hash'=>$hash,'source_hash'=>self::hash($source),'recipient_hash'=>self::hash($recipient),'configuration_hash'=>$state['configuration_hash'],'template_hash'=>self::hash((array)$template),'payload_hash'=>CompanyPortalMailIdentity::payload((object)$fields)];$json=json_encode($receipt,JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$i,$a,$id,self::EVENT,$json,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();
            if($audit<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND metadata_json=?',[$audit,$i,$a,$id,$json])||$this->receipt($i,$key)!==$receipt)throw new RuntimeException('issuer_financial_mail_unconfirmed');NativeCompanyMailDispatchFence::prepareInOwnedTransaction($this->db,$this->p,$i,$queue,self::FENCE,$hash);$this->stateInOwned((object)($fields+['id'=>$queue]),$headers);return$queue;
        });
    }
    public static function assertQueueRouting($db,string $p,object $mail,array $headers):void{
        $f=NativeCompanyMailDispatchFence::find($db,$p,$mail->id);$b=$headers[self::HEADER]??null;if($b===null&&(!$f||$f['kind']!==self::FENCE))return;
        if(!is_array($b)||!$f||$f['kind']!==self::FENCE||!hash_equals($f['binding_hash'],self::binding($b))||(int)($mail->issuer_id??0)!==$b['issuer'])throw new DomainException('native_financial_mail_denied');
    }
    private function stateInOwned(object $mail,array $headers):array{
        self::assertQueueRouting($this->db,$this->p,$mail,$headers);$b=$headers[self::HEADER]??null;if(!is_array($b))throw new DomainException('native_financial_mail_denied');IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $source=$this->source($b['issuer'],$b['actor'],$b['kind'],$b['id']);$recipient=$this->recipient($b['issuer'],$source['client'],$b['contact']);$receipt=$this->receipt($b['issuer'],$b['request_key']);$stored=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$mail->id,$b['issuer']])[0]??null;
        $state=(new CompanyPortalMailConfig($this->db,$this->p))->stateInOwnedTransaction($b['issuer']);$f=NativeCompanyMailDispatchFence::find($this->db,$this->p,$mail->id);$template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$b['issuer'],$source['slug'],(new CompanySettingsStorage($this->db,$this->p))->get($b['issuer'],'active_language')?:'portuguese_br');
        if(!$receipt||!$template||(int)$template->active!==1||!$stored||!$f||!in_array($f['state'],['prepared','dispatched'],true)||!in_array($stored['status'],['pending','sending'],true)||(int)$receipt['queue_id']!==(int)$mail->id||($receipt['binding_hash']??null)!==self::binding($b)||$receipt['source_hash']!==self::hash($source)||$b['revision']!==self::hash($source)||$receipt['recipient_hash']!==self::hash($recipient)||$receipt['template_hash']!==self::hash((array)$template)||$receipt['configuration_hash']!==$state['configuration_hash']||$receipt['payload_hash']!==CompanyPortalMailIdentity::payload((object)$stored)||$receipt['payload_hash']!==CompanyPortalMailIdentity::payload($mail))throw new DomainException('native_financial_mail_stale');
        return$state+['issuer_id'=>$b['issuer']];
    }
    public function state(object $mail,array $headers):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->stateInOwned($mail,$headers));}
    public static function claimDispatch($db,string $p,object $mail,array $headers):?array{
        self::assertQueueRouting($db,$p,$mail,$headers);if(!isset($headers[self::HEADER]))return null;$self=new self($db,$p);
        return IssuerTransaction::run($db,$p,$self->tables(),function()use($self,$db,$p,$mail,$headers){$state=$self->stateInOwned($mail,$headers);$hash=self::binding($headers[self::HEADER]);$attempt=NativeCompanyMailDispatchFence::claimInOwnedTransaction($db,$p,$state['issuer_id'],(int)$mail->id,self::FENCE,$hash);return ['issuer'=>$state['issuer_id'],'queue'=>(int)$mail->id,'hash'=>$hash,'attempt'=>$attempt];});
    }
    public static function completeDispatch($db,string $p,array $permit,bool $sent):void{NativeCompanyMailDispatchFence::complete($db,$p,$permit['issuer'],$permit['queue'],self::FENCE,$permit['hash'],$permit['attempt'],$sent);}
    public static function transportForQueue($db,string $p,object $mail,array $headers,callable $decrypt,callable $encrypt):array{
        $state=(new self($db,$p))->state($mail,$headers);$i=$state['issuer_id'];if($state['kind']==='native')return CompanyMailConfig::build($db,$p,$i,$decrypt);require_once __DIR__.'/NativeCompanyFinancialMailSource.php';$source=new NativeCompanyFinancialMailSource($db,$p,$mail,$headers);if($state['kind']==='microsoft')return(new CompanyMicrosoftMailOAuth($db,$p))->transportConfig($i,$encrypt,$decrypt,$source);
        $config=CompanyMailConfig::build($db,$p,$i,$decrypt,$encrypt,'google_notifications');$oauth=new CompanyGoogleMailOAuth($db,$p);$profile=$oauth->transportProfile($i);if(!$profile)throw new DomainException('native_financial_mail_denied');$config['company_google_mail_provider']=new CompanyGoogleMailOAuthTokenProvider($oauth,$i,$state['epoch'],$profile['sender_email'],$encrypt,$decrypt,$source);return$config;
    }
}
