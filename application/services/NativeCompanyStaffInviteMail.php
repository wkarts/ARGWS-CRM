<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffInvite.php';
require_once __DIR__.'/CompanyPortalMailConfig.php';
require_once __DIR__.'/CompanyPortalMailIdentity.php';
require_once __DIR__.'/CompanyEmailTemplates.php';
require_once __DIR__.'/NativeCompanyMailDispatchFence.php';
/** Welcome control of the original staff form; one creation, one origin-bound invitation. */
final class NativeCompanyStaffInviteMail
{
    public const HEADER='nativeStaffInvitation';
    public function __construct(private $db,private string $p,private string $secret){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($secret)<32)throw new DomainException('native_staff_invite_denied');}
    public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyStaffInvite($this->db,$this->p,$this->secret))->tables(),(new CompanyPortalMailConfig($this->db,$this->p))->tables(),NativeCompanyMailDispatchFence::tables(),['emailtemplates'])));}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function payload(object $email,array $headers):string{unset($headers[self::HEADER]);$copy=clone$email;$copy->headers=serialize($headers);return CompanyPortalMailIdentity::payload($copy);}
    private static function templateHash(object $t):string{return hash('sha256',json_encode([(string)$t->subject,(string)$t->message,(string)$t->fromname,(int)$t->plaintext,(int)$t->active],JSON_THROW_ON_ERROR));}
    public static function bindingHash(array $b):string
    {
        if(array_keys($b)!==['issuer','actor','staff','request_key','identity','configuration','template','payload','expires'])throw new DomainException('native_staff_invite_denied');
        foreach(['issuer','actor','staff','expires']as$k)if(!is_int($b[$k])||$b[$k]<1)throw new DomainException('native_staff_invite_denied');
        foreach(['request_key','identity','configuration','template','payload']as$k)if(!is_string($b[$k])||!preg_match('/^[a-f0-9]{64}$/D',$b[$k]))throw new DomainException('native_staff_invite_denied');
        return hash('sha256',json_encode($b,JSON_THROW_ON_ERROR));
    }
    public function issueAndQueueInOwned(int $i,int $a,int $id,string $key,string $base,callable $decrypt,CompanyStaff $writer):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$writer->assertCreationRequestInOwned($this->db,$this->p,$i,$a,$id,$key);
        $url=parse_url($base);if(!$url||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||!isset($url['host'])||(($url['scheme']??'')!=='https'&&!(($url['scheme']??'')==='http'&&in_array($url['host'],['127.0.0.1','localhost','[::1]'],true))))throw new DomainException('company_portal_trusted_url_required');
        $t=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$i,'new-staff-created','portuguese_br');if(!$t||(int)$t->active!==1)throw new DomainException('company_portal_template_required');
        $config=new CompanyPortalMailConfig($this->db,$this->p);$state=$config->stateInOwnedTransaction($i);$transport=$config->buildInOwnedTransaction($i,$decrypt);$store=new CompanySettingsStorage($this->db,$this->p);$from=trim($store->get($i,'smtp_email'));if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('company_portal_sender_required');
        $invite=new NativeCompanyStaffInvite($this->db,$this->p,$this->secret);$issued=$invite->issueInOwned($i,$a,$id,$key,$writer);$source=$invite->deliveryStateInOwned($id,$writer);$row=$this->rows('SELECT firstname,lastname,email,datecreated FROM '.$this->p.'staff WHERE staffid=?',[$id])[0];
        $link=rtrim($base,'/').'/admin/authentication/set_password/1/'.$id.'/native#token='.$issued['token'];$login=rtrim($base,'/').'/admin/authentication';
        $plain=['{staff_firstname}'=>$row['firstname'],'{staff_lastname}'=>$row['lastname'],'{staff_email}'=>$row['email'],'{staff_datecreated}'=>$row['datecreated'],'{companyname}'=>$store->get($i,'invoice_company_name'),'{crm_url}'=>$login,'{login_url}'=>$login,'{set_password_url}'=>$link,'{password}'=>''];$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html=array_map($escape,$plain);$html['{email_signature}']=$store->get($i,'email_signature');
        $body=strtr($t->message,$html);if(!str_contains($t->message,'{set_password_url}'))$body.='<p><a href="'.$escape($link).'">Definir senha</a></p>';
        $message=$store->get($i,'email_header').$body.$store->get($i,'email_footer');$subject=strtr($t->subject,$plain);$fromName=strtr($t->fromname,$plain);if(preg_match('/[\r\n]/',$subject.$fromName))throw new DomainException('company_portal_template_required');
        if((int)$t->plaintext===1)$message=nl2br($escape(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$message)),ENT_QUOTES,'UTF-8'))).'<p>'.$escape($link).'</p>';
        $headers=$transport['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];
        $fields=['engine'=>$transport['useragent'],'email'=>$row['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8'),'headers'=>serialize($headers),'attachments'=>'','status'=>'pending','date'=>gmdate('Y-m-d H:i:s'),'issuer_id'=>$i];
        $binding=['issuer'=>$i,'actor'=>$a,'staff'=>$id,'request_key'=>$key,'identity'=>$source['receipt']['identity_hash'],'configuration'=>$state['configuration_hash'],'template'=>self::templateHash($t),'payload'=>self::payload((object)$fields,$headers),'expires'=>strtotime($issued['expires'].' UTC')];$headers[self::HEADER]=$binding;$fields['headers']=serialize($headers);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$this->db->insert_id();CompanyPortalMailIdentity::confirmed($this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$i])[0]??[],$fields);
        IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_native_staff_invites SET queue_id=? WHERE staff_id=? AND issuer_id=? AND queue_id IS NULL',[$queue,$id,$i]);if((int)($this->rows('SELECT queue_id FROM '.$this->p.'company_native_staff_invites WHERE staff_id=?',[$id])[0]['queue_id']??0)!==$queue)throw new RuntimeException('native_staff_invite_unconfirmed');
        NativeCompanyMailDispatchFence::prepareInOwnedTransaction($this->db,$this->p,$i,$queue,'staff_invite',self::bindingHash($binding));$this->stateInOwned((object)($fields+['id'=>$queue]),$headers,$writer);
        return['new_pass_key'=>$issued['marker'],'new_pass_key_requested'=>$issued['requested']];
    }
    public function stateInOwned(object $email,array $headers,?CompanyStaff $creatingWriter=null):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());self::assertQueueRouting($this->db,$this->p,$email,$headers);$b=$headers[self::HEADER]??null;if(!is_array($b))throw new DomainException('native_staff_invite_denied');self::bindingHash($b);
        if($b['expires']<=time()||$email->cc!==''||$email->bcc!==''||$email->attachments!==''||!hash_equals($b['payload'],self::payload($email,$headers)))throw new DomainException('native_staff_invite_denied');
        $stored=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$email->id,$b['issuer']])[0]??null;if(!$stored||!hash_equals(CompanyPortalMailIdentity::payload((object)$stored),CompanyPortalMailIdentity::payload($email)))throw new DomainException('native_staff_invite_denied');
        $source=(new NativeCompanyStaffInvite($this->db,$this->p,$this->secret))->deliveryStateInOwned($b['staff'],$creatingWriter);$r=$source['receipt'];if((int)$r['issuer_id']!==$b['issuer']||(int)$r['actor_id']!==$b['actor']||(int)$r['queue_id']!==(int)$email->id||$r['request_key']!==$b['request_key']||!hash_equals($r['identity_hash'],$b['identity'])||$source['identity']['email']!==$email->email)throw new DomainException('native_staff_invite_denied');
        $t=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$b['issuer'],'new-staff-created','portuguese_br');if(!$t||(int)$t->active!==1||!hash_equals($b['template'],self::templateHash($t)))throw new DomainException('native_staff_invite_denied');
        $state=(new CompanyPortalMailConfig($this->db,$this->p))->stateInOwnedTransaction($b['issuer']);if(!hash_equals($b['configuration'],$state['configuration_hash']))throw new DomainException('company_portal_mail_stale');return$state+['issuer_id'=>$b['issuer']];
    }
    public function state(object $email,array $headers):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->stateInOwned($email,$headers));}
    public static function assertQueueRouting($db,string $p,object $email,array $headers):void
    {
        $fence=NativeCompanyMailDispatchFence::find($db,$p,$email->id);$binding=$headers[self::HEADER]??null;
        if(!$fence||$fence['kind']!=='staff_invite'){if(array_key_exists(self::HEADER,$headers))throw new DomainException('native_staff_invite_denied');return;}
        if(!is_array($binding))throw new DomainException('native_staff_invite_denied');$bindingHash=self::bindingHash($binding);
        if((int)$fence['issuer_id']!==$binding['issuer']||(int)($email->issuer_id??0)!==$binding['issuer']||!hash_equals($fence['binding_hash'],$bindingHash))throw new DomainException('native_staff_invite_denied');
    }
    public static function claimDispatch($db,string $p,object $email,array $headers,string $secret):?array
    {
        self::assertQueueRouting($db,$p,$email,$headers);if(!array_key_exists(self::HEADER,$headers))return null;
        $service=new self($db,$p,$secret);$b=$headers[self::HEADER];$hash=self::bindingHash($b);
        return IssuerTransaction::run($db,$p,$service->tables(),function()use($service,$db,$p,$email,$headers,$b,$hash){
            self::assertQueueRouting($db,$p,$email,$headers);$service->stateInOwned($email,$headers);
            $attempt=NativeCompanyMailDispatchFence::claimInOwnedTransaction($db,$p,$b['issuer'],(int)$email->id,'staff_invite',$hash);
            return['issuer'=>$b['issuer'],'queue'=>(int)$email->id,'hash'=>$hash,'attempt'=>$attempt];
        });
    }
    public static function completeDispatch($db,string $p,array $permit,bool $sent):void
    {
        if(array_keys($permit)!==['issuer','queue','hash','attempt'])throw new DomainException('native_staff_invite_denied');
        NativeCompanyMailDispatchFence::complete($db,$p,$permit['issuer'],$permit['queue'],'staff_invite',$permit['hash'],$permit['attempt'],$sent);
    }
    public static function transportForQueue($db,string $p,object $email,array $headers,string $secret,callable $decrypt,callable $encrypt):array
    {
        $state=(new self($db,$p,$secret))->state($email,$headers);$issuer=$state['issuer_id'];
        if($state['kind']==='native')return CompanyMailConfig::build($db,$p,$issuer,$decrypt);
        require_once __DIR__.'/NativeCompanyStaffInviteMailSource.php';require_once __DIR__.'/CompanyGoogleMailOAuthTokenProvider.php';
        $source=new NativeCompanyStaffInviteMailSource($db,$p,$email,$headers,$secret);
        if($state['kind']==='microsoft')return(new CompanyMicrosoftMailOAuth($db,$p))->transportConfig($issuer,$encrypt,$decrypt,$source);
        $config=CompanyMailConfig::build($db,$p,$issuer,$decrypt,$encrypt,'google_notifications');$oauth=new CompanyGoogleMailOAuth($db,$p);$profile=$oauth->transportProfile($issuer);if(!$profile)throw new DomainException('company_google_mail_connection_required');
        $config['company_google_mail_provider']=new CompanyGoogleMailOAuthTokenProvider($oauth,$issuer,$state['epoch'],$profile['sender_email'],$encrypt,$decrypt,$source);return$config;
    }
    public function __debugInfo():array{return[];}
    public function __serialize():array{throw new LogicException('native_staff_invite_runtime_only');}

}
