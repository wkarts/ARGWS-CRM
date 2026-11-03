<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyAuxiliarySchema.php';
require_once __DIR__.'/NativeCompanyMailDispatchFence.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/CompanyEmailTemplates.php';
require_once __DIR__.'/CompanyMailConfig.php';
require_once __DIR__.'/CompanyPortalMailIdentity.php';

/** Explicit issuer-bound reminder queue. No ambient session, global templates or automatic resend. */
final class NativeCompanyReminderDelivery
{
    public const HEADER='nativeCompanyReminderDelivery';
    private const CONFIG=['mail_engine','email_protocol','smtp_host','smtp_username','smtp_email','smtp_password','smtp_port','smtp_encryption','smtp_email_charset','google_mail_client_id','google_mail_client_secret','google_mail_refresh_token','microsoft_mail_client_id','microsoft_mail_client_secret','microsoft_mail_refresh_token','microsoft_mail_azure_tenant_id'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function tables():array
    {
        return array_values(array_unique(array_merge(NativeCompanyMailDispatchFence::tables(),['issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','company_reminders','company_reminder_mail_deliveries','emailtemplates','options','clients','issuers_clients','issuers_client_links','invoices','projects','tasks','tickets','contracts','leads','company_projects','company_tasks','company_standalone_tasks','company_tickets','company_contracts','company_leads'])));
    }
    public static function relationTables($db,string $p,int $issuer,int $reminder):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $row=IssuerStorage::query($db,'SELECT rel_type FROM '.$p.'company_reminders WHERE issuer_id=? AND id=?',[$issuer,$reminder])->row_array();
        if(!$row)throw new DomainException('native_reminder_delivery_denied');
        return NativeCompanyResourceReferences::transactionTables($db,$p,$row['rel_type']);
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function transportHash(int $issuer):string
    {
        $storage=new CompanySettingsStorage($this->db,$this->p);$values=[];foreach(self::CONFIG as$key)$values[$key]=$storage->get($issuer,$key);
        return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }
    private function source(int $issuer,int $id):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_reminders WHERE issuer_id=? AND id=?',[$issuer,$id])[0]??null;
        if(!$row)throw new DomainException('native_reminder_delivery_denied');
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->member($issuer,(int)$row['creator'])||!$acl->member($issuer,(int)$row['staff']))throw new DomainException('native_reminder_delivery_denied');
        $creator=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,(int)$row['creator'],$row['rel_type'],(string)$row['rel_id'],'edit');
        $recipient=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,(int)$row['staff'],$row['rel_type'],(string)$row['rel_id'],'view');
        foreach([$creator,$recipient]as$ref)if($ref['source_kind']!==$row['source_kind']||(string)$ref['source_id']!==(string)$row['source_id'])throw new DomainException('native_reminder_delivery_denied');
        $staff=$this->rows('SELECT staffid,firstname,lastname,email FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$row['staff']])[0]??null;
        if(!$staff||!filter_var($staff['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$staff['email']))throw new DomainException('native_reminder_delivery_denied');
        return [$row,$staff,$recipient];
    }
    private static function recipientHash(array $staff):string{return hash('sha256',json_encode([$staff['staffid'],$staff['firstname'],$staff['lastname'],$staff['email']],JSON_THROW_ON_ERROR));}
    /** Caller owns source, queue, config and fence in the same issuer transaction. */
    public function queueInOwned(int $issuer,int $id,string $baseUrl,callable $decrypt):?int
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables());
        [$source,$staff,$ref]=$this->source($issuer,$id);
        if((int)$source['notify_by_email']!==1)return null;
        $parts=parse_url($baseUrl);
        if(!$parts||!isset($parts['host'])||(isset($parts['user'])||isset($parts['pass']))||isset($parts['query'])||isset($parts['fragment'])||(($parts['scheme']??'')!=='https'&&!(($parts['scheme']??'')==='http'&&in_array($parts['host'],['localhost','127.0.0.1','[::1]'],true))))throw new DomainException('native_reminder_url_required');
        $existing=$this->rows('SELECT queue_id FROM '.$this->p.'company_reminder_mail_deliveries WHERE issuer_id=? AND reminder_id=? AND reminder_revision=?',[$issuer,$id,$source['revision']]);
        if($existing)throw new DomainException('native_reminder_already_queued');
        $storage=new CompanySettingsStorage($this->db,$this->p);
        $template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$issuer,'reminder-email-staff',$storage->get($issuer,'active_language')?:'portuguese_br');
        if(!$template||(int)$template->active!==1)return null; // Native disabled-template policy: notification still exists.
        $hash=$this->transportHash($issuer);$config=CompanyMailConfig::build($this->db,$this->p,$issuer,$decrypt);
        $from=trim($storage->get($issuer,'smtp_email'));if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('native_reminder_sender_required');
        $url=rtrim($baseUrl,'/').'/admin/'.ltrim($ref['url'],'/');
        $plain=['{staff_firstname}'=>$staff['firstname'],'{staff_lastname}'=>$staff['lastname'],'{staff_email}'=>$staff['email'],'{staff_reminder_relation_name}'=>$ref['name'],'{staff_reminder_relation_link}'=>$url,'{staff_reminder_description}'=>$source['description']];
        $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html=array_map($escape,$plain);$html['{email_signature}']=$storage->get($issuer,'email_signature');
        $message=strtr($storage->get($issuer,'email_header').$template->message.$storage->get($issuer,'email_footer'),$html);
        $subject=strtr($template->subject,$plain);$fromName=strtr($template->fromname,$plain);if(preg_match('/[\r\n]/',$subject.$fromName))throw new DomainException('native_reminder_template_invalid');
        if((int)$template->plaintext===1)$message=nl2br($escape(html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8')));
        $headers=$config['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];
        $binding=['issuer'=>$issuer,'reminder'=>$id,'revision'=>(int)$source['revision']];$headers[self::HEADER]=$binding;
        $fields=['engine'=>$config['useragent'],'email'=>$staff['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8'),'headers'=>serialize($headers),'attachments'=>'','status'=>'pending','date'=>gmdate('Y-m-d H:i:s'),'issuer_id'=>$issuer];
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$this->db->insert_id();
        $stored=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$issuer])[0]??[];
        foreach($fields as$key=>$value)if((string)($stored[$key]??'')!==(string)$value)throw new RuntimeException('native_reminder_queue_unconfirmed');
        $receipt=['queue_id'=>$queue,'issuer_id'=>$issuer,'reminder_id'=>$id,'reminder_revision'=>(int)$source['revision'],'creator_id'=>(int)$source['creator'],'recipient_id'=>(int)$source['staff'],'recipient_hash'=>self::recipientHash($staff),'transport_hash'=>$hash,'payload_hash'=>CompanyPortalMailIdentity::payload((object)$stored),'created_at'=>gmdate('Y-m-d H:i:s')];
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_reminder_mail_deliveries (`'.implode('`,`',array_keys($receipt)).'`) VALUES('.implode(',',array_fill(0,count($receipt),'?')).')',array_values($receipt));
        $actual=$this->rows('SELECT * FROM '.$this->p.'company_reminder_mail_deliveries WHERE queue_id=?',[$queue])[0]??[];foreach($receipt as$key=>$value)if((string)($actual[$key]??'')!==(string)$value)throw new RuntimeException('native_reminder_queue_unconfirmed');
        [$check,$person]=$this->source($issuer,$id);if($check!==$source||!hash_equals(self::recipientHash($staff),self::recipientHash($person))||!hash_equals($hash,$this->transportHash($issuer)))throw new DomainException('native_reminder_delivery_stale');
        NativeCompanyMailDispatchFence::prepareInOwnedTransaction($this->db,$this->p,$issuer,$queue,'reminder',self::bindingHash($binding));return$queue;
    }
    private static function bindingHash(array $binding):string
    {
        if(array_keys($binding)!==['issuer','reminder','revision'])throw new DomainException('native_reminder_delivery_denied');
        foreach($binding as$value)if(!is_int($value)||$value<1)throw new DomainException('native_reminder_delivery_denied');
        return hash('sha256',json_encode($binding,JSON_THROW_ON_ERROR));
    }
    public static function assertQueueRouting($db,string $p,object $email,array $headers):void
    {
        $self=new self($db,$p);$fence=NativeCompanyMailDispatchFence::find($db,$p,$email->id);
        $binding=$headers[self::HEADER]??null;
        if(!$fence||$fence['kind']!=='reminder'){if(array_key_exists(self::HEADER,$headers))throw new DomainException('native_reminder_delivery_denied');return;}
        if(!is_array($binding)||!hash_equals($fence['binding_hash'],self::bindingHash($binding))||(int)$fence['issuer_id']!==$binding['issuer']||(int)($email->issuer_id??0)!==$binding['issuer'])throw new DomainException('native_reminder_delivery_denied');
    }
    public static function claimDispatch($db,string $p,object $email,array $headers):?array
    {
        self::assertQueueRouting($db,$p,$email,$headers);if(!array_key_exists(self::HEADER,$headers))return null;
        $self=new self($db,$p);$binding=$headers[self::HEADER];$hash=self::bindingHash($binding);NativeCompanyAuxiliarySchema::assertValid($db,$p);NativeCompanyMailDispatchFence::assertValid($db,$p);
        return IssuerTransaction::run($db,$p,array_values(array_unique(array_merge(self::tables(),self::relationTables($db,$p,$binding['issuer'],$binding['reminder'])))),static function()use($self,$db,$p,$email,$binding,$hash,$headers){
            $owner=$self->rows('SELECT creator_id FROM '.$p.'company_reminder_mail_deliveries WHERE queue_id=? AND issuer_id=?',[$email->id,$binding['issuer']])[0]??null;
            if(!$owner)throw new DomainException('native_reminder_delivery_denied');
            (new IssuerResourceAccess($db,$p))->lockFinancialIdentity($binding['issuer'],(int)$owner['creator_id']);
            self::assertQueueRouting($db,$p,$email,$headers);
            $receipt=$self->rows('SELECT * FROM '.$p.'company_reminder_mail_deliveries WHERE queue_id=? AND issuer_id=?',[$email->id,$binding['issuer']])[0]??null;
            $stored=$self->rows('SELECT * FROM '.$p.'mail_queue WHERE id=? AND issuer_id=? FOR UPDATE',[$email->id,$binding['issuer']])[0]??null;
            if(!$receipt||!$stored||(int)$receipt['reminder_id']!==$binding['reminder']||(int)$receipt['reminder_revision']!==$binding['revision']||!hash_equals($receipt['payload_hash'],CompanyPortalMailIdentity::payload((object)$stored))||!hash_equals($receipt['payload_hash'],CompanyPortalMailIdentity::payload($email)))throw new DomainException('native_reminder_delivery_denied');
            [$source,$person]=$self->source($binding['issuer'],$binding['reminder']);
            if((int)$source['revision']!==$binding['revision']||(int)$source['isnotified']!==1||(int)$source['notify_by_email']!==1||(int)$source['creator']!==(int)$receipt['creator_id']||(int)$source['staff']!==(int)$receipt['recipient_id']||!hash_equals($receipt['recipient_hash'],self::recipientHash($person))||!hash_equals($receipt['transport_hash'],$self->transportHash($binding['issuer'])))throw new DomainException('native_reminder_delivery_stale');
            $attempt=NativeCompanyMailDispatchFence::claimInOwnedTransaction($db,$p,$binding['issuer'],(int)$email->id,'reminder',$hash);
            return ['issuer'=>$binding['issuer'],'queue'=>(int)$email->id,'hash'=>$hash,'attempt'=>$attempt];
        });
    }
    public static function completeDispatch($db,string $p,array $permit,bool $sent):void
    {
        if(array_keys($permit)!==['issuer','queue','hash','attempt'])throw new DomainException('native_reminder_delivery_denied');
        NativeCompanyMailDispatchFence::complete($db,$p,$permit['issuer'],$permit['queue'],'reminder',$permit['hash'],$permit['attempt'],$sent);
    }
}
