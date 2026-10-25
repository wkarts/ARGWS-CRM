<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyMailDispatchFence.php';
require_once __DIR__.'/CompanyPortalMailConfig.php';
require_once __DIR__.'/CompanyPortalMailIdentity.php';
/** Existing SMTP test action. Fixed message, explicit recipient, durable one-use attempt; never a background retry. */
final class NativeCompanyMailTest
{
    public const HEADER='X-Argws-Native-Smtp-Test';
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return array_values(array_unique(array_merge((new CompanyPortalMailConfig($this->db,$this->p))->tables(),NativeCompanyMailDispatchFence::tables())));}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function auth(int $i,int $a):void{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$i||!$acl->can($i,$a,'settings','edit'))throw new DomainException('native_mail_test_denied');}
    private static function binding(array $b):string
    {
        if(array_keys($b)!==['issuer','actor','configuration','recipient','payload','nonce','expires'])throw new DomainException('native_mail_test_denied');
        foreach(['issuer','actor','expires']as$key)if(!is_int($b[$key])||$b[$key]<1)throw new DomainException('native_mail_test_denied');
        foreach(['configuration','recipient','payload','nonce']as$key)if(!is_string($b[$key])||!preg_match('/^[a-f0-9]{64}$/D',$b[$key]))throw new DomainException('native_mail_test_denied');
        return hash('sha256',json_encode($b,JSON_THROW_ON_ERROR));
    }
    private static function payload(object $email,array $headers):string{unset($headers[self::HEADER]);$copy=clone$email;$copy->headers=serialize($headers);return CompanyPortalMailIdentity::payload($copy);}
    public function state(object $email,array $headers):array
    {
        $b=$headers[self::HEADER]??null;if(!is_array($b))throw new DomainException('native_mail_test_denied');$hash=self::binding($b);
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($email,$headers,$b,$hash){
            $this->auth($b['issuer'],$b['actor']);if($b['expires']<time()||(int)($email->issuer_id??0)!==$b['issuer'])throw new DomainException('native_mail_test_denied');
            $fence=NativeCompanyMailDispatchFence::find($this->db,$this->p,$email->id);
            $stored=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$email->id,$b['issuer']])[0]??null;
            if(!$stored||!$fence||$fence['kind']!=='smtp_test'||$fence['state']!=='dispatched'||$stored['status']!=='sending'||!hash_equals($fence['binding_hash'],$hash)||!hash_equals($b['recipient'],hash('sha256',$email->email))||!hash_equals($b['payload'],self::payload($email,$headers))||!hash_equals(CompanyPortalMailIdentity::payload((object)$stored),CompanyPortalMailIdentity::payload($email)))throw new DomainException('native_mail_test_denied');
            $state=(new CompanyPortalMailConfig($this->db,$this->p))->stateInOwnedTransaction($b['issuer']);if(!hash_equals($b['configuration'],$state['configuration_hash']))throw new DomainException('native_mail_test_stale');return$state+['issuer_id'=>$b['issuer']];
        });
    }
    private function affected(int $count):void{$c=$this->db->conn_id??$this->db->pdo??null;$sqlite=$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$r=$this->rows($sqlite?'SELECT changes() AS n':'SELECT ROW_COUNT() AS n')[0]??[];if((string)($r['n']??'')!==(string)$count)throw new RuntimeException('issuer_native_mail_test_write_unconfirmed');}
    public function send(int $i,int $a,string $recipient,callable $decrypt,callable $encrypt,callable $sender):bool
    {
        $this->auth($i,$a);if(strlen($recipient)>191||!filter_var($recipient,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$recipient))throw new DomainException('native_mail_test_invalid');
        $attempt=IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$recipient,$decrypt){
            $this->auth($i,$a);NativeCompanyMailDispatchFence::assertValid($this->db,$this->p);
            $policy=new CompanyPortalMailConfig($this->db,$this->p);$state=$policy->stateInOwnedTransaction($i);$transport=$policy->buildInOwnedTransaction($i,$decrypt);
            $store=new CompanySettingsStorage($this->db,$this->p);$from=$store->get($i,'smtp_email');$name=$store->get($i,'companyname');
            if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from.$name))throw new DomainException('native_mail_test_invalid');
            $subject='Teste de Configuração do SMTP';$message='Este é um e-mail de teste da empresa selecionada. Se você recebeu esta mensagem, o transporte configurado respondeu ao envio.';
            $headers=['from'=>$from,'fromName'=>$name,'subject'=>$subject];
            $fields=['engine'=>$transport['useragent'],'email'=>$recipient,'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>$message,'headers'=>serialize($headers),'attachments'=>base64_encode(serialize([])),'status'=>'pending','date'=>gmdate('Y-m-d H:i:s'),'issuer_id'=>$i];
            $b=['issuer'=>$i,'actor'=>$a,'configuration'=>$state['configuration_hash'],'recipient'=>hash('sha256',$recipient),'payload'=>self::payload((object)$fields,$headers),'nonce'=>bin2hex(random_bytes(32)),'expires'=>time()+300];$hash=self::binding($b);$headers[self::HEADER]=$b;$fields['headers']=serialize($headers);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$this->affected(1);$queue=(int)$this->db->insert_id();if($queue<1)throw new RuntimeException('issuer_native_mail_test_write_unconfirmed');
            CompanyPortalMailIdentity::confirmed($this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=?',[$queue])[0]??[],$fields);
            NativeCompanyMailDispatchFence::prepareInOwnedTransaction($this->db,$this->p,$i,$queue,'smtp_test',$hash);
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'mail_queue SET status=? WHERE id=? AND issuer_id=? AND status=?',['sending',$queue,$i,'pending']);$this->affected(1);
            $permit=NativeCompanyMailDispatchFence::claimInOwnedTransaction($this->db,$this->p,$i,$queue,'smtp_test',$hash);$fields['status']='sending';
            $stamp=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$a,'native_smtp_test_dispatched',$i,$queue,$stamp]);$this->affected(1);$audit=(int)$this->db->insert_id();if($audit<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=?',[$audit,$i,$a,$queue,'native_smtp_test_dispatched']))throw new RuntimeException('issuer_native_mail_test_write_unconfirmed');
            return ['email'=>(object)($fields+['id'=>$queue]),'headers'=>$headers,'hash'=>$hash,'permit'=>$permit];
        });
        // No transport or OAuth token refresh can occur before the claim's independent commit is confirmed.
        $sent=false;
        try{
            $state=$this->state($attempt['email'],$attempt['headers']);$config=$this->transport($attempt['email'],$attempt['headers'],$state,$decrypt,$encrypt);
            $this->state($attempt['email'],$attempt['headers']);$sent=$sender($config,$attempt['email'],$attempt['headers'])===true;
        }catch(Throwable $error){$sent=false;}
        NativeCompanyMailDispatchFence::complete($this->db,$this->p,$i,$attempt['email']->id,'smtp_test',$attempt['hash'],$attempt['permit'],$sent);
        IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($attempt,$i,$sent){
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'mail_queue SET status=? WHERE id=? AND issuer_id=? AND status=?',[$sent?'sent':'failed',$attempt['email']->id,$i,'sending']);$this->affected(1);
        });
        return$sent;
    }
    private function transport(object $email,array $headers,array $state,callable $decrypt,callable $encrypt):array
    {
        $i=$state['issuer_id'];if($state['kind']==='native')return CompanyMailConfig::build($this->db,$this->p,$i,$decrypt);
        require_once __DIR__.'/NativeCompanyMailTestSource.php';require_once __DIR__.'/CompanyGoogleMailOAuthTokenProvider.php';$source=new NativeCompanyMailTestSource($this->db,$this->p,$email,$headers);
        if($state['kind']==='microsoft')return(new CompanyMicrosoftMailOAuth($this->db,$this->p))->transportConfig($i,$encrypt,$decrypt,$source);
        $config=CompanyMailConfig::build($this->db,$this->p,$i,$decrypt,$encrypt,'google_notifications');$oauth=new CompanyGoogleMailOAuth($this->db,$this->p);$profile=$oauth->transportProfile($i);if(!$profile)throw new DomainException('native_mail_test_denied');
        $config['company_google_mail_provider']=new CompanyGoogleMailOAuthTokenProvider($oauth,$i,$state['epoch'],$profile['sender_email'],$encrypt,$decrypt,$source);return$config;
    }
}
