<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyNativeContactMail.php';
/** Non-secret native notification. Its durable fence authenticates the exact source and payload. */
final class CompanyNativeContactMessage
{
    public function __construct(private $db,private string$p,private string$secret){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($secret)<32)throw new DomainException('company_contact_mail_denied');}
    public function tables():array{return(new CompanyNativeContactMail($this->db,$this->p,$this->secret))->tables();}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function source(int$issuer,int$actor,int$client,int$contact):array
    {
        if(!(new IssuerResourceAccess($this->db,$this->p))->administrative($issuer,$actor))throw new DomainException('company_contact_mail_denied');
        $row=CompanyPortalMailIdentity::contact($this->db,$this->p,$issuer,null,$contact);
        $owner=$this->rows('SELECT c.registration_confirmed,p.is_primary FROM '.$this->p.'clients c JOIN '.$this->p.'contacts p ON p.userid=c.userid JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND p.id=? AND o.owner_issuer_id=?',[$client,$contact,$issuer])[0]??null;
        if(!$row||!$owner||!filter_var($row['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$row['email'])||(int)$row['userid']!==$client||(int)$owner['registration_confirmed']!==1||(int)$owner['is_primary']!==1)throw new DomainException('company_contact_mail_denied');
        return$row;
    }
    public static function bindingHash(array$b):string
    {
        if(array_keys($b)!==['kind','purpose','issuer','actor','client','contact','key','identity','recipient','configuration','template','payload','expires'])throw new DomainException('company_contact_mail_denied');
        if($b['kind']!=='message'||$b['purpose']!=='registration-confirmed')throw new DomainException('company_contact_mail_denied');
        foreach(['issuer','actor','client','contact','expires']as$k)if(!is_int($b[$k])||$b[$k]<1)throw new DomainException('company_contact_mail_denied');
        foreach(['key','identity','recipient','configuration','template','payload']as$k)if(!is_string($b[$k])||!preg_match('/^[a-f0-9]{64}$/D',$b[$k]))throw new DomainException('company_contact_mail_denied');
        return hash('sha256',json_encode($b,JSON_THROW_ON_ERROR));
    }
    private static function payload(object$email,array$headers):string
    {
        unset($headers[CompanyNativeContactMail::HEADER]);$copy=clone$email;$copy->headers=serialize($headers);return CompanyPortalMailIdentity::payload($copy);
    }
    private static function templateHash(object$t):string{return hash('sha256',json_encode([(string)$t->subject,(string)$t->message,(string)$t->fromname,(int)$t->plaintext,(int)$t->active],JSON_THROW_ON_ERROR));}
    /** Caller already owns the registration confirmation transaction. No password-reset token is created. */
    public function queueInOwned(int$issuer,int$actor,int$client,int$contact,string$base,callable$decrypt):?int
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$row=$this->source($issuer,$actor,$client,$contact);
        $template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$issuer,'client-registration-confirmed','portuguese_br');if(!$template||(int)$template->active!==1)return null;
        $parts=parse_url($base);if(!$parts||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!isset($parts['host'])||(($parts['scheme']??'')!=='https'&&!(($parts['scheme']??'')==='http'&&in_array($parts['host'],['localhost','127.0.0.1','[::1]'],true))))throw new DomainException('company_portal_trusted_url_required');
        $cfg=new CompanyPortalMailConfig($this->db,$this->p);$state=$cfg->stateInOwnedTransaction($issuer);$transport=$cfg->buildInOwnedTransaction($issuer,$decrypt);$store=new CompanySettingsStorage($this->db,$this->p);$from=trim($store->get($issuer,'smtp_email'));
        if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('company_portal_sender_required');
        $login=rtrim($base,'/').'/authentication/login?issuer_id='.$issuer;$plain=['{companyname}'=>$row['legal_name'],'{client_company}'=>$row['company'],'{contact_firstname}'=>$row['firstname'],'{contact_lastname}'=>$row['lastname'],'{contact_email}'=>$row['email'],'{crm_url}'=>$login,'{login_url}'=>$login];$escape=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html=array_map($escape,$plain);$html['{email_signature}']=$store->get($issuer,'email_signature');
        $message=strtr($store->get($issuer,'email_header').$template->message.$store->get($issuer,'email_footer'),$html);$subject=strtr($template->subject,$plain);$fromName=strtr($template->fromname,$plain);if(preg_match('/[\r\n]/',$subject.$fromName))throw new DomainException('company_portal_template_required');
        if((int)$template->plaintext===1)$message=nl2br($escape(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$message)),ENT_QUOTES,'UTF-8')));
        $headers=$transport['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];
        $fields=['engine'=>$transport['useragent'],'email'=>$row['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8'),'headers'=>serialize($headers),'attachments'=>'','status'=>'pending','date'=>gmdate('Y-m-d H:i:s'),'issuer_id'=>$issuer];
        $binding=['kind'=>'message','purpose'=>'registration-confirmed','issuer'=>$issuer,'actor'=>$actor,'client'=>$client,'contact'=>$contact,'key'=>bin2hex(random_bytes(32)),'identity'=>CompanyPortalMailIdentity::stamp($row,$this->secret),'recipient'=>CompanyPortalMailIdentity::recipient($row),'configuration'=>$state['configuration_hash'],'template'=>self::templateHash($template),'payload'=>self::payload((object)$fields,$headers),'expires'=>time()+86400];$headers[CompanyNativeContactMail::HEADER]=$binding;$fields['headers']=serialize($headers);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$id=(int)$this->db->insert_id();
        CompanyPortalMailIdentity::confirmed($this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$id,$issuer])[0]??[],$fields);
        NativeCompanyMailDispatchFence::prepareInOwnedTransaction($this->db,$this->p,$issuer,$id,'contact',self::bindingHash($binding));$mail=(object)($fields+['id'=>$id]);$this->stateInOwned($mail,$headers);return$id;
    }
    public function state(object$email,array$headers):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->stateInOwned($email,$headers));}
    public function stateInOwned(object$email,array$headers):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());CompanyNativeContactMail::assertQueueRouting($this->db,$this->p,$email,$headers);$b=$headers[CompanyNativeContactMail::HEADER];self::bindingHash($b);
        if($b['expires']<=time()||$email->cc!==''||$email->bcc!==''||$email->attachments!==''||!hash_equals($b['payload'],self::payload($email,$headers)))throw new DomainException('company_contact_mail_denied');
        $stored=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=? FOR UPDATE',[$email->id,$b['issuer']])[0]??null;if(!$stored||!hash_equals(CompanyPortalMailIdentity::payload((object)$stored),CompanyPortalMailIdentity::payload($email)))throw new DomainException('company_contact_mail_denied');
        $row=$this->source($b['issuer'],$b['actor'],$b['client'],$b['contact']);if($email->email!==$row['email']||!hash_equals($b['identity'],CompanyPortalMailIdentity::stamp($row,$this->secret))||!hash_equals($b['recipient'],CompanyPortalMailIdentity::recipient($row)))throw new DomainException('company_contact_mail_denied');
        $t=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$b['issuer'],'client-registration-confirmed','portuguese_br');if(!$t||(int)$t->active!==1||!hash_equals($b['template'],self::templateHash($t)))throw new DomainException('company_contact_mail_denied');
        $state=(new CompanyPortalMailConfig($this->db,$this->p))->stateInOwnedTransaction($b['issuer']);if(!hash_equals($b['configuration'],$state['configuration_hash']))throw new DomainException('company_portal_mail_stale');return$state+['issuer_id'=>$b['issuer']];
    }
    public static function transportForQueue($db,string$p,object$email,array$headers,string$secret,callable$decrypt,callable$encrypt):array
    {
        $state=(new self($db,$p,$secret))->state($email,$headers);$issuer=$state['issuer_id'];if($state['kind']==='native')return CompanyMailConfig::build($db,$p,$issuer,$decrypt);
        require_once __DIR__.'/CompanyPortalMailDispatchSource.php';require_once __DIR__.'/CompanyGoogleMailOAuthTokenProvider.php';$source=new CompanyPortalMailDispatchSource($db,$p,$email,$headers[CompanyNativeContactMail::HEADER]['key'],$secret,'contact-message');
        if($state['kind']==='microsoft')return(new CompanyMicrosoftMailOAuth($db,$p))->transportConfig($issuer,$encrypt,$decrypt,$source);
        $config=CompanyMailConfig::build($db,$p,$issuer,$decrypt,$encrypt,'google_notifications');$oauth=new CompanyGoogleMailOAuth($db,$p);$profile=$oauth->transportProfile($issuer);if(!$profile)throw new DomainException('company_google_mail_connection_required');$config['company_google_mail_provider']=new CompanyGoogleMailOAuthTokenProvider($oauth,$issuer,$state['epoch'],$profile['sender_email'],$encrypt,$decrypt,$source);return$config;
    }
}
