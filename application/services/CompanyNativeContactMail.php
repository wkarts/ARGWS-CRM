<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyCustomerAccess.php';
require_once __DIR__.'/CompanyPortalRecoveryMail.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/CompanyPortalMailQueueConsumer.php';
require_once __DIR__.'/NativeCompanyMailDispatchFence.php';
/** Native welcome/setup templates use owner-bound recovery deliveries, never plaintext passwords. */
final class CompanyNativeContactMail
{
    public const HEADER='nativeCompanyContactDelivery';
    public function __construct(private $db,private string $p,private string $secret){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($secret)<32)throw new RuntimeException('company_portal_configuration_required');}
    public function tables():array{return array_values(array_unique(array_merge((new CompanyPortalRecoveryMail($this->db,$this->p,$this->secret))->tables(),NativeCompanyMailDispatchFence::tables())));}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function queueInOwned(int $issuer,int $actor,int $client,int $contact,array $slugs,string $baseUrl,callable $decrypt,string $cap='edit'):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        if(!in_array($cap,['create','edit'],true)||!(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,$cap==='create'?'create_contact':'edit'))throw new DomainException('company_client_access_denied');
        $check=function()use($issuer,$actor,$client,$cap){if(!(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,$cap==='create'?'create_contact':'edit'))throw new DomainException('company_contact_mail_denied');};
        $this->queueAuthorized($issuer,$client,$contact,$slugs,$baseUrl,$decrypt,['issuer'=>$issuer,'actor'=>$actor,'client'=>$client,'contact'=>$contact,'cap'=>$cap],$check);
    }
    public function queueManagedInOwned(int$issuer,int$contact,object$writer,array$slugs,string$baseUrl,callable$decrypt):void
    {
        if(get_class($writer)!=='NativePortalContacts')throw new DomainException('company_contact_mail_denied');IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $session=$writer->mailAuthorityInOwned($this->db,$this->p,$issuer);$ref=$writer->reference($this->db,$this->p,$issuer,'contacts',true);if($ref['source_id']!==$contact)throw new DomainException('company_contact_mail_denied');
        $check=function()use($writer,$issuer,$contact){$writer->assertMutationInOwned($this->db,$this->p,$issuer,'contacts');if($writer->reference($this->db,$this->p,$issuer,'contacts',true)['source_id']!==$contact)throw new DomainException('company_contact_mail_denied');};
        $this->queueAuthorized($issuer,$session['client_id'],$contact,$slugs,$baseUrl,$decrypt,['kind'=>'managed','issuer'=>$issuer,'manager'=>$session['contact_id'],'stamp'=>$session['stamp'],'client'=>$session['client_id'],'contact'=>$contact],$check);
    }
    private function queueAuthorized(int$issuer,int$client,int$contact,array$slugs,string$baseUrl,callable$decrypt,array$source,callable$authorized):void
    {
        $authorized();
        if(array_diff($slugs,['new-client-created','contact-set-password'])||count($slugs)!==count(array_unique($slugs)))throw new DomainException('company_contact_invalid_input');
        if(!$slugs)return;
        $parts=parse_url($baseUrl);
        if(!$parts||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!isset($parts['host'])||(($parts['scheme']??'')!=='https'&&!(($parts['scheme']??'')==='http'&&in_array($parts['host'],['127.0.0.1','localhost','[::1]'],true))))throw new DomainException('company_portal_trusted_url_required');
        CompanyPortalMailSchema::assertValid($this->db,$this->p);
        $row=CompanyPortalMailIdentity::contact($this->db,$this->p,$issuer,null,$contact);
        if(!$row||(int)$row['userid']!==$client)throw new DomainException('company_client_access_denied');
        $templates=[];foreach($slugs as $slug){$template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$issuer,$slug,'portuguese_br');if($template&&(int)$template->active===1)$templates[]=$template;elseif($slug==='contact-set-password')throw new DomainException('company_portal_template_required');}
        if(!$templates)return;
        $configurator=new CompanyPortalMailConfig($this->db,$this->p);$state=$configurator->stateInOwnedTransaction($issuer);$config=$configurator->buildInOwnedTransaction($issuer,$decrypt);$store=new CompanySettingsStorage($this->db,$this->p);
        $from=trim($store->get($issuer,'smtp_email'));if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('company_portal_sender_required');
        $token=bin2hex(random_bytes(32));$tokenHash=hash('sha256',$token);$stamp=CompanyPortalMailIdentity::stamp($row,$this->secret);$now=date('Y-m-d H:i:s');$expires=date('Y-m-d H:i:s',time()+3600);
        $link=rtrim($baseUrl,'/').'/authentication/reset_password/0/'.$contact.'/native?issuer_id='.$issuer.'#token='.$token;
        $login=rtrim($baseUrl,'/').'/authentication/login?issuer_id='.$issuer;
        IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_portal_tokens WHERE contact_id=?',[$contact]);
        $tokenFields=['token_hash'=>$tokenHash,'issuer_id'=>$issuer,'contact_id'=>$contact,'client_id'=>$client,'identity_hash'=>$stamp,'expires_at'=>$expires,'created_at'=>$now];
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_portal_tokens (`'.implode('`,`',array_keys($tokenFields)).'`) VALUES('.implode(',',array_fill(0,count($tokenFields),'?')).')',array_values($tokenFields));
        CompanyPortalMailIdentity::confirmed($this->rows('SELECT * FROM '.$this->p.'company_portal_tokens WHERE token_hash=? AND issuer_id=?',[$tokenHash,$issuer])[0]??[],$tokenFields);
        foreach($templates as $template){
            $key=bin2hex(random_bytes(32));$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $plain=['{companyname}'=>$row['legal_name'],'{contact_firstname}'=>$row['firstname'],'{contact_lastname}'=>$row['lastname'],'{contact_email}'=>$row['email'],'{contact_password}'=>'Use o link seguro para definir sua senha.','{password}'=>'Use o link seguro para definir sua senha.','{crm_url}'=>$login,'{login_url}'=>$login,'{set_password_url}'=>$link,'{reset_password_url}'=>$link];
            $html=array_map($escape,$plain);$html['{email_signature}']=$store->get($issuer,'email_signature');
            $message=strtr($store->get($issuer,'email_header').$template->message.$store->get($issuer,'email_footer'),$html);
            // Every welcome message offers the same safe setup route, even old templates with a password placeholder.
            if(!str_contains($message,$escape($link)))$message.='<p><a href="'.$escape($link).'">Definir senha com segurança</a></p>';
            $subject=strtr($template->subject,$plain);$fromName=strtr($template->fromname,$plain);if(preg_match('/[\r\n]/',$subject.$fromName))throw new DomainException('company_portal_template_required');
            $headers=$config['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];$headers[CompanyPortalRecoveryMail::HEADER]=$key;
            $binding=$source+['key'=>$key];$headers[self::HEADER]=$binding;
            if((int)$template->plaintext===1)$message=nl2br($escape(html_entity_decode(strip_tags(str_replace(['<br>','<br />','<br/>'],"\n",$message)),ENT_QUOTES,'UTF-8')."\n".$link));
            $fields=['engine'=>$config['useragent'],'email'=>$row['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8')."\n".$link,'headers'=>serialize($headers),'attachments'=>'','status'=>'pending','date'=>$now,'issuer_id'=>$issuer];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$this->db->insert_id();if($queue<1)throw new RuntimeException('issuer_portal_mail_write_unconfirmed');
            CompanyPortalMailIdentity::confirmed($this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$issuer])[0]??[],$fields);
            $receipt=['delivery_key'=>$key,'issuer_id'=>$issuer,'queue_id'=>$queue,'contact_id'=>$contact,'client_id'=>$client,'token_hash'=>$tokenHash,'identity_hash'=>$stamp,'recipient_hash'=>CompanyPortalMailIdentity::recipient($row),'transport_kind'=>$state['kind'],'transport_epoch'=>$state['epoch'],'configuration_hash'=>$state['configuration_hash'],'payload_hash'=>CompanyPortalMailIdentity::payload((object)$fields),'expires_at'=>$expires,'created_at'=>$now];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_portal_mail_deliveries (`'.implode('`,`',array_keys($receipt)).'`) VALUES('.implode(',',array_fill(0,count($receipt),'?')).')',array_values($receipt));
            CompanyPortalMailIdentity::confirmed($this->rows('SELECT * FROM '.$this->p.'company_portal_mail_deliveries WHERE delivery_key=? AND issuer_id=?',[$key,$issuer])[0]??[],$receipt);
            NativeCompanyMailDispatchFence::prepareInOwnedTransaction($this->db,$this->p,$issuer,$queue,'contact',self::bindingHash($binding));
        }
        $current=CompanyPortalMailIdentity::contact($this->db,$this->p,$issuer,null,$contact);
        if(!$current||!hash_equals($stamp,CompanyPortalMailIdentity::stamp($current,$this->secret))||!hash_equals($state['configuration_hash'],$configurator->stateInOwnedTransaction($issuer)['configuration_hash']))throw new DomainException('company_portal_mail_stale');$authorized();
    }
    private static function bindingHash(array $binding):string
    {
        if(($binding['kind']??null)==='message'){require_once __DIR__.'/CompanyNativeContactMessage.php';return CompanyNativeContactMessage::bindingHash($binding);}
        if(($binding['kind']??null)==='managed'){
            if(array_keys($binding)!==['kind','issuer','manager','stamp','client','contact','key'])throw new DomainException('company_contact_mail_denied');
            foreach(['issuer','manager','client','contact']as$key)if(!is_int($binding[$key])||$binding[$key]<1)throw new DomainException('company_contact_mail_denied');
            foreach(['stamp','key']as$key)if(!is_string($binding[$key])||!preg_match('/^[a-f0-9]{64}$/D',$binding[$key]))throw new DomainException('company_contact_mail_denied');
            return hash('sha256',json_encode($binding,JSON_THROW_ON_ERROR));
        }
        if(array_keys($binding)!==['issuer','actor','client','contact','cap','key'])throw new DomainException('company_contact_mail_denied');
        foreach(['issuer','actor','client','contact']as$field)if(!is_int($binding[$field])||$binding[$field]<1)throw new DomainException('company_contact_mail_denied');
        if(!in_array($binding['cap'],['create','edit'],true)||!is_string($binding['key'])||!preg_match('/^[a-f0-9]{64}$/D',$binding['key']))throw new DomainException('company_contact_mail_denied');
        return hash('sha256',json_encode($binding,JSON_THROW_ON_ERROR));
    }
    public static function assertQueueRouting($db,string $p,object $email,array $headers):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        $fence=NativeCompanyMailDispatchFence::find($db,$p,$email->id);
        $binding=$headers[self::HEADER]??null;
        if(!$fence||$fence['kind']!=='contact'){if(array_key_exists(self::HEADER,$headers))throw new DomainException('company_contact_mail_denied');return;}
        if(!is_array($binding)||!hash_equals($fence['binding_hash'],self::bindingHash($binding))||(int)$fence['issuer_id']!==$binding['issuer']||(int)($email->issuer_id??0)!==$binding['issuer']||(self::isMessage($headers)?array_key_exists(CompanyPortalRecoveryMail::HEADER,$headers):($headers[CompanyPortalRecoveryMail::HEADER]??null)!==$binding['key']))throw new DomainException('company_contact_mail_denied');
    }
    public static function claimDispatch($db,string $p,object $email,array $headers,string $secret):?array
    {
        self::assertQueueRouting($db,$p,$email,$headers);if(!array_key_exists(self::HEADER,$headers))return null;
        $self=new self($db,$p,$secret);$binding=$headers[self::HEADER];$hash=self::bindingHash($binding);
        CompanyPortalMailSchema::assertValid($db,$p);NativeCompanyMailDispatchFence::assertValid($db,$p);
        return IssuerTransaction::run($db,$p,$self->tables(),static function()use($self,$db,$p,$email,$headers,$binding,$hash,$secret){
            self::assertQueueRouting($db,$p,$email,$headers);
            $stored=$self->rows('SELECT * FROM '.$p.'mail_queue WHERE id=? AND issuer_id=? FOR UPDATE',[$email->id,$binding['issuer']])[0]??null;
            if(!$stored||!hash_equals(CompanyPortalMailIdentity::payload((object)$stored),CompanyPortalMailIdentity::payload($email)))throw new DomainException('company_contact_mail_denied');
            if(self::isMessage($headers)){
                require_once __DIR__.'/CompanyNativeContactMessage.php';(new CompanyNativeContactMessage($db,$p,$secret))->stateInOwned($email,$headers);
                $attempt=NativeCompanyMailDispatchFence::claimInOwnedTransaction($db,$p,$binding['issuer'],(int)$email->id,'contact',$hash);return ['issuer'=>$binding['issuer'],'queue'=>(int)$email->id,'hash'=>$hash,'attempt'=>$attempt];
            }
            if(self::isManaged($headers))$self->assertManaged($binding);elseif(!(new CompanyCustomerAccess($db,$p))->can($binding['issuer'],$binding['actor'],$binding['client'],$binding['cap']==='create'?'create_contact':'edit'))throw new DomainException('company_contact_mail_denied');
            (new CompanyPortalMailQueueConsumer($db,$p,$secret))->stateInOwnedTransaction($email,$binding['key']);
            $receipt=$self->rows('SELECT client_id,contact_id FROM '.$p.'company_portal_mail_deliveries WHERE queue_id=? AND issuer_id=?',[$email->id,$binding['issuer']])[0]??[];
            if((int)($receipt['client_id']??0)!==$binding['client']||(int)($receipt['contact_id']??0)!==$binding['contact']||(!self::isManaged($headers)&&!(new CompanyCustomerAccess($db,$p))->can($binding['issuer'],$binding['actor'],$binding['client'],$binding['cap']==='create'?'create_contact':'edit')))throw new DomainException('company_contact_mail_denied');if(self::isManaged($headers))$self->assertManaged($binding);
            $attempt=NativeCompanyMailDispatchFence::claimInOwnedTransaction($db,$p,$binding['issuer'],(int)$email->id,'contact',$hash);
            return ['issuer'=>$binding['issuer'],'queue'=>(int)$email->id,'hash'=>$hash,'attempt'=>$attempt];
        });
    }
    public static function isManaged(array$headers):bool{return is_array($headers[self::HEADER]??null)&&($headers[self::HEADER]['kind']??null)==='managed';}
    private function assertManaged(array$b):void
    {
        self::bindingHash($b);require_once __DIR__.'/NativePortalContacts.php';$session=['issuer_id'=>$b['issuer'],'contact_id'=>$b['manager'],'client_id'=>$b['client'],'stamp'=>$b['stamp']];
        (new NativePortalContacts($this->db,$this->p,$this->secret,$b['issuer'],$session,$b['contact']))->reference($this->db,$this->p,$b['issuer'],'contacts',true);
    }
    public function managedState(object$email,array$headers):array
    {
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($email,$headers){self::assertQueueRouting($this->db,$this->p,$email,$headers);if(!self::isManaged($headers))throw new DomainException('company_contact_mail_denied');$b=$headers[self::HEADER];$this->assertManaged($b);$stored=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=? FOR UPDATE',[$email->id,$b['issuer']])[0]??null;$receipt=$this->rows('SELECT client_id,contact_id FROM '.$this->p.'company_portal_mail_deliveries WHERE queue_id=? AND issuer_id=?',[$email->id,$b['issuer']])[0]??[];if(!$stored||!hash_equals(CompanyPortalMailIdentity::payload((object)$stored),CompanyPortalMailIdentity::payload($email))||(int)($receipt['client_id']??0)!==$b['client']||(int)($receipt['contact_id']??0)!==$b['contact'])throw new DomainException('company_contact_mail_denied');$state=(new CompanyPortalMailQueueConsumer($this->db,$this->p,$this->secret))->stateInOwnedTransaction($email,$b['key']);$this->assertManaged($b);return$state;});
    }
    public static function isMessage(array$headers):bool{return is_array($headers[self::HEADER]??null)&&($headers[self::HEADER]['kind']??null)==='message';}
    public static function transportForQueue($db,string$p,object$email,array$headers,string$secret,callable$decrypt,callable$encrypt):array
    {
        if(self::isMessage($headers)){require_once __DIR__.'/CompanyNativeContactMessage.php';return CompanyNativeContactMessage::transportForQueue($db,$p,$email,$headers,$secret,$decrypt,$encrypt);}
        if(!self::isManaged($headers))throw new DomainException('company_contact_mail_denied');$self=new self($db,$p,$secret);$self->managedState($email,$headers);$issuer=$headers[self::HEADER]['issuer'];
        $state=IssuerTransaction::run($db,$p,$self->tables(),fn()=>(new CompanyPortalMailConfig($db,$p))->stateInOwnedTransaction($issuer));
        if($state['kind']==='native'){$config=CompanyMailConfig::build($db,$p,$issuer,$decrypt);$self->managedState($email,$headers);return$config;}
        require_once __DIR__.'/CompanyPortalMailDispatchSource.php';require_once __DIR__.'/CompanyGoogleMailOAuthTokenProvider.php';$source=new CompanyPortalMailDispatchSource($db,$p,$email,$headers[self::HEADER]['key'],$secret,'contact-managed');
        if($state['kind']==='microsoft')return(new CompanyMicrosoftMailOAuth($db,$p))->transportConfig($issuer,$encrypt,$decrypt,$source);
        $config=CompanyMailConfig::build($db,$p,$issuer,$decrypt,$encrypt,'google_notifications');$oauth=new CompanyGoogleMailOAuth($db,$p);$profile=$oauth->transportProfile($issuer);if(!$profile)throw new DomainException('company_google_mail_connection_required');$config['company_google_mail_provider']=new CompanyGoogleMailOAuthTokenProvider($oauth,$issuer,$state['epoch'],$profile['sender_email'],$encrypt,$decrypt,$source);$self->managedState($email,$headers);return$config;
    }
    public static function completeDispatch($db,string $p,array $permit,bool $sent):void
    {
        if(array_keys($permit)!==['issuer','queue','hash','attempt'])throw new DomainException('company_contact_mail_denied');
        NativeCompanyMailDispatchFence::complete($db,$p,$permit['issuer'],$permit['queue'],'contact',$permit['hash'],$permit['attempt'],$sent);
    }
    public function __debugInfo():array{return[];}
    public function __serialize():array{throw new LogicException('company_portal_mail_runtime_only');}
}
