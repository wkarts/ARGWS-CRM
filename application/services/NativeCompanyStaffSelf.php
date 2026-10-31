<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyStaff.php';
require_once __DIR__.'/NativeCompanyStaffAvatar.php';
/** Personal identity commands for the authenticated actor, never a tenant reset of another staff member. */
final class NativeCompanyStaffSelf
{
    private const PUBLIC_FIELDS=['staffid','firstname','lastname','email','phonenumber','facebook','linkedin','skype','default_language','email_signature','direction','profile_image','last_password_change','two_factor_auth_enabled'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return IssuerStaffAccess::TABLES;}
    private function authorize(int $i,int $a):void{if(!(new IssuerResourceAccess($this->db,$this->p))->member($i,$a))throw new DomainException('issuer_staff_denied');}
    private function source(int $i,int $a):array
    {
        $this->authorize($i,$a);$row=IssuerStorage::query($this->db,'SELECT '.implode(',',array_merge(self::PUBLIC_FIELDS,['password','active','google_auth_secret'])).' FROM '.$this->p.'staff WHERE staffid=?',[$a])->row_array();
        if(!$row||(int)$row['active']!==1||!is_string($row['password'])||$row['password']==='')throw new DomainException('issuer_staff_denied');$this->authorize($i,$a);return$row;
    }
    private static function canonical(array $row):array{return array_map(static fn($value)=>$value===null?null:(string)$value,$row);}
    private static function revision(array $row):string{return hash('sha256',json_encode(self::canonical($row),JSON_THROW_ON_ERROR));}
    public function googleSetup(int $i,int $a,string $installationUrl):array
    {
        $before=$this->page($i,$a);$url=parse_url($installationUrl);if(!is_array($url)||!in_array($url['scheme']??null,['https','http'],true)||empty($url['host'])||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment']))throw new DomainException('native_staff_two_factor_invalid');
        $branding=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'options WHERE name=?',['companyname'])->result_array();if(count($branding)>1)throw new DomainException('native_staff_two_factor_invalid');$issuer=(string)($branding[0]['value']??'');if($issuer==='')$issuer=$url['host'].(isset($url['port'])?':'.$url['port']:'').($url['path']??'').' - CRM';$issuer=str_replace(':','-',$issuer);
        $google=new \PragmaRX\Google2FA\Google2FA();$secret=$google->generateSecretKey();$uri=$google->getQRCodeUrl($issuer,$before['member']->email,$secret);
        if($this->page($i,$a)['revision']!==$before['revision']||$branding!==IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'options WHERE name=?',['companyname'])->result_array())throw new DomainException('issuer_staff_stale');return['secret'=>$secret,'uri'=>$uri];
    }
    public function verifyGoogleSetup(int $i,int $a,string $secret,string $code):bool
    {
        $before=$this->page($i,$a);if(!preg_match('/^[A-Z2-7]{16,128}$/D',$secret)||!preg_match('/^[0-9]{6}$/D',$code))throw new DomainException('native_staff_two_factor_invalid');
        $valid=(new \PragmaRX\Google2FA\Google2FA())->verifyKey($secret,$code,0);if($this->page($i,$a)['revision']!==$before['revision'])throw new DomainException('issuer_staff_stale');return$valid;
    }
    public function emailFactorAvailable():bool{return(bool)IssuerStorage::query($this->db,'SELECT emailtemplateid FROM '.$this->p.'emailtemplates WHERE issuer_id IS NULL AND slug=? AND active=1 LIMIT 1',['two-factor-authentication'])->row_array();}
    private function assertEmailFactor():void{$active=IssuerStorage::query($this->db,'SELECT emailtemplateid FROM '.$this->p.'emailtemplates WHERE issuer_id IS NULL AND slug=? AND active=1 LIMIT 1',['two-factor-authentication'])->row_array();if(!$active)throw new DomainException('native_staff_two_factor_email_unavailable');}
    public function page(int $i,int $a):array{$row=$this->source($i,$a);if($row!==$this->source($i,$a))throw new DomainException('issuer_staff_stale');return['member'=>(object)array_intersect_key($row,array_flip(self::PUBLIC_FIELDS)),'revision'=>self::revision($row)];}
    private function audit(int $i,int $a,string $event):void
    {
        $meta=json_encode(['nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,$event,$i,$a,gmdate('Y-m-d H:i:s'),$meta]);$id=(int)$this->db->insert_id();$row=IssuerStorage::query($this->db,'SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event=?',[$id,$a,$i,$a,$event])->row_array();if(($row['metadata_json']??null)!==$meta)throw new RuntimeException('issuer_staff_write_unconfirmed');
    }
    private function write(int $i,int $a,string $revision,array $values,?string $oldPassword=null,?NativeCompanyStaffAvatar $avatar=null):string
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('issuer_staff_invalid');$this->authorize($i,$a);
        $avatar?->assertFor($this->db,$this->p,$i,$a);try{$result=IssuerTransaction::run($this->db,$this->p,array_merge($this->tables(),(($values['two_factor_auth_enabled']??null)===1?['emailtemplates']:[])),function()use($i,$a,$revision,$values,$oldPassword,$avatar):string{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$before=$this->source($i,$a);if(!hash_equals($revision,self::revision($before)))throw new DomainException('issuer_staff_stale');
            if($oldPassword!==null){require_once APPPATH.'third_party/phpass.php';if(!(new PasswordHash(8,true))->CheckPassword($oldPassword,$before['password']))throw new DomainException('native_staff_old_password_invalid');}
            if(isset($values['email'])){if(StaffEmailIdentity::normalize($values['email'])!==StaffEmailIdentity::normalize($before['email'])&&!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'staff','edit'))throw new DomainException('issuer_staff_denied');$values['email']=StaffEmailIdentity::assertAvailable($this->db,$this->p,$values['email'],$a);}
            if(($values['two_factor_auth_enabled']??null)===1)$this->assertEmailFactor();
            if($avatar!==null)$values=array_merge($values,$avatar->applyPersonal());
            if(!$values)return$revision;
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'staff SET '.implode(',',array_map(static fn($key)=>$key.'=?',array_keys($values))).' WHERE staffid=?',array_merge(array_values($values),[$a]));
            $after=$this->source($i,$a);if(self::canonical($after)!==self::canonical(array_replace($before,$values)))throw new RuntimeException('issuer_staff_write_unconfirmed');$this->audit($i,$a,array_key_exists('two_factor_auth_enabled',$values)?'native_staff_self_two_factor':($oldPassword===null?'native_staff_self_profile':'native_staff_self_password'));if($after!==$this->source($i,$a))throw new DomainException('issuer_staff_stale');if(($values['two_factor_auth_enabled']??null)===1)$this->assertEmailFactor();return self::revision($after);
        });$avatar?->commitFiles();return$result;}catch(Throwable $error){$avatar?->rollbackFiles();throw$error;}
    }
    public function saveProfile(int $i,int $a,string $revision,array $input,?NativeCompanyStaffAvatar $avatar=null):string{return$this->write($i,$a,$revision,(new CompanyStaff($this->db,$this->p))->selfProfileInput($input),null,$avatar);}
    /** Same login-wide 2FA identity, reauthenticated; never company SMTP configuration. */
    public function twoFactor(int $i,int $a,string $revision,string $old,string $mode,?string $secret,?string $code,callable $encrypt):string
    {
        if(!in_array($mode,['off','email','google'],true)||$old===''||strlen($old)>128)throw new DomainException('issuer_staff_invalid');
        require_once APPPATH.'third_party/phpass.php';$row=$this->source($i,$a);if(!(new PasswordHash(8,true))->CheckPassword($old,$row['password']))throw new DomainException('native_staff_old_password_invalid');
        $values=['two_factor_auth_enabled'=>['off'=>0,'email'=>1,'google'=>2][$mode]];
        if($mode==='google'){
            if($secret===null&&$code===null&&(int)$row['two_factor_auth_enabled']===2)return$this->write($i,$a,$revision,[],$old);
            if(!is_string($secret)||!preg_match('/^[A-Z2-7]{16,128}$/D',$secret)||!is_string($code)||!preg_match('/^[0-9]{6}$/D',$code))throw new DomainException('native_staff_two_factor_invalid');
            $google=new \PragmaRX\Google2FA\Google2FA();if(!$google->verifyKey($secret,$code,0))throw new DomainException('native_staff_two_factor_invalid');
            $cipher=$encrypt($secret);if(!is_string($cipher)||$cipher===''||hash_equals($secret,$cipher))throw new RuntimeException('native_staff_two_factor_encryption_failed');$values['google_auth_secret']=$cipher;
        }elseif($secret!==null||$code!==null)throw new DomainException('native_staff_two_factor_invalid');
        if($mode==='email')$this->assertEmailFactor();
        return$this->write($i,$a,$revision,$values,$old);
    }
    public function changePassword(int $i,int $a,string $revision,string $old,string $password,string $repeat):string
    {
        if($old===''||strlen($old)>128||$password!==$repeat||strlen($password)<12||strlen($password)>128||str_contains($password,"\0"))throw new DomainException('issuer_staff_invalid');
        require_once APPPATH.'third_party/phpass.php';$row=$this->source($i,$a);$hasher=new PasswordHash(8,true);if(!$hasher->CheckPassword($old,$row['password']))throw new DomainException('native_staff_old_password_invalid');
        return$this->write($i,$a,$revision,['password'=>$hasher->HashPassword($password),'last_password_change'=>gmdate('Y-m-d H:i:s')],$old);
    }
}
