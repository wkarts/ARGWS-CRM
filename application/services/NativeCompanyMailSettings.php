<?php
declare(strict_types=1);
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';
require_once __DIR__.'/CompanyGoogleMailOAuth.php';
require_once __DIR__.'/CompanyMicrosoftMailOAuth.php';
require_once __DIR__.'/CompanyMailTransportPolicy.php';
require_once __DIR__.'/CompanyPortalMailConfig.php';
/** Existing email Settings wire, bound to one issuer. No transport, token exchange or secret hydration on GET. */
final class NativeCompanyMailSettings
{
    private const DEFAULTS=['mail_engine'=>'phpmailer','email_protocol'=>'smtp','smtp_host'=>'','smtp_port'=>'587','smtp_encryption'=>'tls','smtp_email'=>'','smtp_username'=>'','smtp_password'=>'','smtp_email_charset'=>'utf-8','bcc_emails'=>'','email_signature'=>'','email_header'=>'','email_footer'=>'','email_queue_enabled'=>'0','email_queue_skip_with_attachments'=>'1','google_mail_client_id'=>'','google_mail_client_secret'=>'','microsoft_mail_client_id'=>'','microsoft_mail_client_secret'=>'','microsoft_mail_azure_tenant_id'=>''];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function fields():array{return array_keys(self::DEFAULTS);}
    public function tables():array{return array_values(array_unique(array_merge((new CompanyGoogleMailOAuth($this->db,$this->p))->tables(),(new CompanyMicrosoftMailOAuth($this->db,$this->p))->tables(),(new CompanyMailTransportPolicy($this->db,$this->p))->tables(),(new CompanyPortalMailConfig($this->db,$this->p))->tables())));}
    private function access(int $issuer,int $actor,string $cap):void{$acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()===$issuer||!$acl->can($issuer,$actor,'settings',$cap))throw new DomainException('native_mail_settings_denied');}
    private function profile(int $issuer,string $provider):?array
    {
        $columns=$provider==='google'?'client_id,client_secret_cipher,sender_email,config_revision,epoch,status':'tenant_id,client_id,client_secret_cipher,sender_email,config_revision,epoch,status';
        return IssuerStorage::query($this->db,'SELECT '.$columns.' FROM '.$this->p.'company_'.$provider.'_mail_accounts WHERE issuer_id=? FOR UPDATE',[$issuer])->row_array()?:null;
    }
    public function pageInOwnedTransaction(int $issuer,int $actor):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->access($issuer,$actor,'view');
        CompanyGoogleMailOAuthSchema::assertValid($this->db,$this->p);CompanyMicrosoftMailOAuthSchema::assertValid($this->db,$this->p);
        $store=new CompanySettingsStorage($this->db,$this->p);$values=[];$raw=[];
        foreach(self::DEFAULTS as$key=>$default){$raw[$key]=$store->exists($issuer,$key)?$store->get($issuer,$key):null;$values[$key]=$raw[$key]??$default;}
        $google=$this->profile($issuer,'google');$microsoft=$this->profile($issuer,'microsoft');
        foreach(['google'=>$google,'microsoft'=>$microsoft]as$provider=>$profile){$values[$provider.'_mail_client_id']=$profile['client_id']??'';$values[$provider.'_mail_client_secret']='';}
        $values['microsoft_mail_azure_tenant_id']=$microsoft['tenant_id']??'';$values['smtp_password']='';
        $transport=(new CompanyMailTransportPolicy($this->db,$this->p))->page($issuer,$actor);$portal=(new CompanyPortalMailConfig($this->db,$this->p))->page($issuer,$actor);
        $this->access($issuer,$actor,'view');
        return ['transport'=>$transport,'portal'=>$portal,'values'=>$values,'revision'=>hash('sha256',json_encode([$issuer,$raw,$google,$microsoft,$transport,$portal],JSON_THROW_ON_ERROR)),
            'google_revision'=>$google===null?0:(int)$google['config_revision'],'microsoft_revision'=>$microsoft===null?0:(int)$microsoft['config_revision'],
            'secret_configured'=>['smtp'=>($raw['smtp_password']??'')!=='','google'=>($google['client_secret_cipher']??'')!=='','microsoft'=>($microsoft['client_secret_cipher']??'')!==''],
            'provider_status'=>['google'=>$google['status']??'unconfigured','microsoft'=>$microsoft['status']??'unconfigured']];
    }
    public function page(int $issuer,int $actor):array{$this->access($issuer,$actor,'view');return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->pageInOwnedTransaction($issuer,$actor));}
    public static function prepare(array $values):array
    {
        if(array_diff(array_keys($values),self::fields())||array_diff(self::fields(),array_keys($values)))throw new DomainException('native_mail_settings_invalid');
        foreach($values as$key=>$value)if(!is_string($value)||strlen($value)>60000||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('native_mail_settings_invalid');
        if(!in_array($values['mail_engine'],['phpmailer','codeigniter'],true)||!in_array($values['email_protocol'],['smtp','google','microsoft'],true)||($values['email_protocol']!=='smtp'&&$values['mail_engine']!=='phpmailer'))throw new DomainException('native_mail_settings_invalid');
        foreach(['email_queue_enabled','email_queue_skip_with_attachments']as$key)if(!in_array($values[$key],['0','1'],true))throw new DomainException('native_mail_settings_invalid');
        if(!in_array(strtolower($values['smtp_email_charset']),['utf-8','iso-8859-1','windows-1252'],true))throw new DomainException('native_mail_settings_invalid');
        $smtp=array_intersect_key($values,array_flip(CompanySettingsCatalog::fields('email')));CompanySettingsCatalog::prepare('email',$smtp);
        foreach(['smtp_password','google_mail_client_secret','microsoft_mail_client_secret']as$key)if(strlen($values[$key])>4096)throw new DomainException('native_mail_settings_invalid');
        foreach(['google_mail_client_id','microsoft_mail_client_id','microsoft_mail_azure_tenant_id']as$key)if(strlen($values[$key])>255||preg_match('/[\x00-\x20\x7f]/',$values[$key]))throw new DomainException('native_mail_settings_invalid');
        if($values['bcc_emails']!=='')foreach(explode(',',$values['bcc_emails'])as$email)if(!filter_var(trim($email),FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new DomainException('native_mail_settings_invalid');
        foreach(CompanySettingsCatalog::prepare('email_content',array_intersect_key($values,array_flip(CompanySettingsCatalog::fields('email_content'))))as$key=>$value)$values[$key]=$value;
        return$values;
    }
    public function saveInOwnedTransaction(int $issuer,int $actor,string $revision,array $values,callable $encrypt,callable $decrypt):array
    {
        $this->access($issuer,$actor,'edit');IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$values=self::prepare($values);$before=$this->pageInOwnedTransaction($issuer,$actor);
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!hash_equals($before['revision'],$revision))throw new DomainException('native_mail_settings_stale');
        $options=$values;foreach(['google_mail_client_id','google_mail_client_secret','microsoft_mail_client_id','microsoft_mail_client_secret','microsoft_mail_azure_tenant_id']as$key)unset($options[$key]);
        if($options['smtp_password']==='')unset($options['smtp_password']);else{
            try{$cipher=$encrypt($options['smtp_password']);}catch(Throwable $e){throw new DomainException('company_settings_encryption_failed');}
            if(!is_string($cipher)||$cipher===''||hash_equals($cipher,$options['smtp_password']))throw new DomainException('company_settings_encryption_failed');$options['smtp_password']=$cipher;
        }
        (new CompanySettingsStorage($this->db,$this->p))->updateBatchInOwnedTransaction($issuer,$actor,$options);
        foreach(['google','microsoft']as$provider){
            $id=$values[$provider.'_mail_client_id'];$secret=$values[$provider.'_mail_client_secret'];
            if($id===''){if($secret!==''||$before['values'][$provider.'_mail_client_id']!==''||$values['email_protocol']===$provider)throw new DomainException('native_mail_settings_invalid');continue;}
            if($provider==='google')(new CompanyGoogleMailOAuth($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,$before['google_revision'],$id,$secret,$encrypt,$decrypt);
            else(new CompanyMicrosoftMailOAuth($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,$before['microsoft_revision'],$values['microsoft_mail_azure_tenant_id'],$id,$secret,$encrypt,$decrypt);
        }
        $mode=$values['email_protocol']==='smtp'?'native':$values['email_protocol'];$revisionNow=$mode==='native'?0:(int)$this->profile($issuer,$mode)['config_revision'];
        (new CompanyMailTransportPolicy($this->db,$this->p))->selectConfiguredInOwnedTransaction($issuer,$actor,$before['transport']['revision'],$mode,$revisionNow);
        (new CompanyPortalMailConfig($this->db,$this->p))->selectConfiguredInOwnedTransaction($issuer,$actor,$before['portal']['revision'],$mode,$revisionNow);
        $this->access($issuer,$actor,'edit');return$this->pageInOwnedTransaction($issuer,$actor);
    }
    public function save(int $issuer,int $actor,string $revision,array $values,callable $encrypt,callable $decrypt):array{$this->access($issuer,$actor,'edit');return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->saveInOwnedTransaction($issuer,$actor,$revision,$values,$encrypt,$decrypt));}
}
