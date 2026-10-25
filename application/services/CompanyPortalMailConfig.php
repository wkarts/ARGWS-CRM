<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortalMailSchema.php';
require_once __DIR__.'/CompanyMailConfig.php';
require_once __DIR__.'/CompanyGoogleMailOAuth.php';
require_once __DIR__.'/CompanyMicrosoftMailOAuth.php';

/** Portal recovery is an explicit opt-in, separate from the existing document connection. */
final class CompanyPortalMailConfig
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return array_values(array_unique(array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','options','issuers_audit'],array_keys(CompanyPortalMailSchema::definitions()),CompanyGoogleMailOAuth::participantTables($this->db,$this->p),CompanyMicrosoftMailOAuth::participantTables($this->db,$this->p))));}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int$issuer,int$actor,string$cap):void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'settings',$cap))throw new DomainException('company_portal_mail_denied');}
    public function page($issuer,$actor):array{$issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');CompanyPortalMailSchema::assertValid($this->db,$this->p);$row=$this->rows('SELECT mode,revision FROM '.$this->p.'company_portal_mail_preferences WHERE issuer_id=?',[$issuer])[0]??null;return ['mode'=>$row['mode']??'native','revision'=>(int)($row['revision']??0)];}
    public function save($issuer,$actor,int$revision,string$mode,int$accountRevision):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);if(!in_array($mode,['native','google','microsoft'],true))throw new DomainException('company_portal_mail_invalid');$this->authorize($issuer,$actor,'edit');CompanyPortalMailSchema::assertValid($this->db,$this->p);
        IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$revision,$mode,$accountRevision){$this->authorize($issuer,$actor,'edit');$row=$this->rows('SELECT * FROM '.$this->p.'company_portal_mail_preferences WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;if((int)($row['revision']??0)!==$revision)throw new DomainException('company_portal_mail_stale');if(in_array($mode,['google','microsoft'],true)){$account=$mode==='google'?$this->googleAccount($issuer):$this->microsoftAccount($issuer);if((int)$account['config_revision']!==$accountRevision)throw new DomainException('company_portal_mail_stale');}$now=date('Y-m-d H:i:s');if(!$row)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_portal_mail_preferences(issuer_id,mode,revision,saved_by,created_at,updated_at) VALUES(?,?,1,?,?,?)',[$issuer,$mode,$actor,$now,$now]);elseif($row['mode']!==$mode||(int)$row['saved_by']!==$actor)IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_portal_mail_preferences SET mode=?,revision=revision+1,saved_by=?,updated_at=? WHERE issuer_id=?',[$mode,$actor,$now,$issuer]);else return;
            $check=$this->rows('SELECT * FROM '.$this->p.'company_portal_mail_preferences WHERE issuer_id=?',[$issuer])[0]??[];if(($check['mode']??'')!==$mode||(int)($check['revision']??0)!==$revision+1||(int)($check['saved_by']??0)!==$actor)throw new RuntimeException('issuer_portal_mail_write_unconfirmed');IssuerStorage::query($this->db,"INSERT INTO ".$this->p."issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,'company_portal_mail_transport_saved',?,NULL,?)",[$actor,$issuer,$now]);$id=(int)$this->db->insert_id();if(!$this->rows("SELECT id FROM ".$this->p."issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND event='company_portal_mail_transport_saved' AND subject_id IS NULL AND created_at=?",[$id,$actor,$issuer,$now]))throw new RuntimeException('issuer_portal_mail_write_unconfirmed');$this->authorize($issuer,$actor,'edit');});
    }
    /** Native Settings selection is not an authorization grant; every dispatch validates the live account. */
    public function selectConfiguredInOwnedTransaction(int $issuer,int $actor,int $revision,string $mode,int $accountRevision):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->authorize($issuer,$actor,'edit');CompanyPortalMailSchema::assertValid($this->db,$this->p);
        if(!in_array($mode,['native','google','microsoft'],true)||$revision<0||$revision>=2147483647)throw new DomainException('company_portal_mail_invalid');
        $row=$this->rows('SELECT * FROM '.$this->p.'company_portal_mail_preferences WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;
        if((int)($row['revision']??0)!==$revision)throw new DomainException('company_portal_mail_stale');
        if($mode!=='native'){$account=$this->rows('SELECT config_revision FROM '.$this->p.'company_'.$mode.'_mail_accounts WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;if(!$account||(int)$account['config_revision']!==$accountRevision)throw new DomainException('company_portal_mail_stale');}
        if($row&&$row['mode']===$mode&&(int)$row['saved_by']===$actor)return;
        $now=gmdate('Y-m-d H:i:s');
        if(!$row)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_portal_mail_preferences(issuer_id,mode,revision,saved_by,created_at,updated_at) VALUES(?,?,1,?,?,?)',[$issuer,$mode,$actor,$now,$now]);
        else IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_portal_mail_preferences SET mode=?,revision=revision+1,saved_by=?,updated_at=? WHERE issuer_id=?',[$mode,$actor,$now,$issuer]);
        $check=$this->rows('SELECT * FROM '.$this->p.'company_portal_mail_preferences WHERE issuer_id=?',[$issuer])[0]??[];
        if(($check['mode']??null)!==$mode||(int)($check['revision']??0)!==$revision+1||(int)($check['saved_by']??0)!==$actor)throw new RuntimeException('issuer_portal_mail_write_unconfirmed');
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[$actor,'native_portal_mail_transport_selected',$issuer,$now]);$connection=$this->db->conn_id??$this->db->pdo??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$affected=$this->rows($sqlite?'SELECT changes() AS affected':'SELECT ROW_COUNT() AS affected')[0]??[];if((string)($affected['affected']??'')!=='1')throw new RuntimeException('issuer_mail_transport_write_unconfirmed');$id=(int)$this->db->insert_id();
        if($id<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND event=? AND created_at=?',[$id,$actor,$issuer,'native_portal_mail_transport_selected',$now]))throw new RuntimeException('issuer_portal_mail_write_unconfirmed');
        $this->authorize($issuer,$actor,'edit');
    }
    private function googleAccount(int$issuer):array
    {
        if(!CompanyGoogleMailOAuthSchema::present($this->db,$this->p))throw new DomainException('company_google_mail_connection_required');$row=$this->rows('SELECT issuer_id,status,epoch,config_revision,connected_by FROM '.$this->p.'company_google_mail_accounts WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;$profile=(new CompanyGoogleMailOAuth($this->db,$this->p))->transportProfile($issuer);if(!$row||!$profile||$row['status']!=='active')throw new DomainException('company_google_mail_connection_required');$this->authorize($issuer,(int)$row['connected_by'],'edit');return$row;
    }
    private function microsoftAccount(int$issuer):array
    {
        if(!CompanyMicrosoftMailOAuthSchema::present($this->db,$this->p))throw new DomainException('company_microsoft_mail_connection_required');$row=$this->rows('SELECT issuer_id,status,epoch,config_revision,connected_by FROM '.$this->p.'company_microsoft_mail_accounts WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;$profile=(new CompanyMicrosoftMailOAuth($this->db,$this->p))->transportProfile($issuer);if(!$row||!$profile||$row['status']!=='active')throw new DomainException('company_microsoft_mail_connection_required');$this->authorize($issuer,(int)$row['connected_by'],'edit');return$row;
    }
    public function stateInOwnedTransaction(int$issuer):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$row=$this->rows('SELECT * FROM '.$this->p.'company_portal_mail_preferences WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;$mode=$row['mode']??'native';if(!in_array($mode,['native','google','microsoft'],true))throw new DomainException('company_portal_mail_invalid');if($row)$this->authorize($issuer,(int)$row['saved_by'],'edit');$account=$mode==='google'?$this->googleAccount($issuer):($mode==='microsoft'?$this->microsoftAccount($issuer):null);
        $values=['mode'=>$mode,'revision'=>(int)($row['revision']??0),'saved_by'=>(int)($row['saved_by']??0)];$store=new CompanySettingsStorage($this->db,$this->p);foreach(['mail_engine','email_protocol','smtp_host','smtp_username','smtp_email','smtp_password','smtp_port','smtp_encryption','smtp_email_charset','microsoft_mail_client_id','microsoft_mail_client_secret','microsoft_mail_refresh_token','microsoft_mail_azure_tenant_id','google_mail_client_id','google_mail_client_secret','google_mail_refresh_token']as$key)$values[$key]=$store->get($issuer,$key);if($account)$values[$mode.'_epoch']=$mode==='google'?(new CompanyGoogleMailOAuth($this->db,$this->p))->fingerprint($issuer):(new CompanyMicrosoftMailOAuth($this->db,$this->p))->fingerprint($issuer);
        return ['kind'=>$mode,'epoch'=>$account?(int)$account['epoch']:0,'configuration_hash'=>hash('sha256',json_encode($values,JSON_THROW_ON_ERROR))];
    }
    public function buildInOwnedTransaction(int$issuer,callable$decrypt):array{$state=$this->stateInOwnedTransaction($issuer);return $state['kind']==='microsoft'?(new CompanyMicrosoftMailOAuth($this->db,$this->p))->transportConfig($issuer,null,$decrypt):CompanyMailConfig::build($this->db,$this->p,$issuer,$decrypt,null,$state['kind']==='google'?'google_notifications':'native');}
    public static function forQueue($db,string$p,object$email,string$key,string$secret,callable$decrypt,callable$encrypt):array
    {
        require_once __DIR__.'/CompanyPortalMailDispatchSource.php';require_once __DIR__.'/CompanyGoogleMailOAuthTokenProvider.php';
        $state=(new CompanyPortalMailQueueConsumer($db,$p,$secret))->state($email,$key);$issuer=$state['issuer_id'];
        if($state['kind']==='native')return CompanyMailConfig::build($db,$p,$issuer,$decrypt);
        if($state['kind']==='microsoft')return(new CompanyMicrosoftMailOAuth($db,$p))->transportConfig($issuer,$encrypt,$decrypt,new CompanyPortalMailDispatchSource($db,$p,$email,$key,$secret));
        $config=CompanyMailConfig::build($db,$p,$issuer,$decrypt,$encrypt,'google_notifications');$oauth=new CompanyGoogleMailOAuth($db,$p);$profile=$oauth->transportProfile($issuer);if(!$profile)throw new DomainException('company_google_mail_connection_required');
        $config['company_google_mail_provider']=new CompanyGoogleMailOAuthTokenProvider($oauth,$issuer,$state['epoch'],$profile['sender_email'],$encrypt,$decrypt,new CompanyPortalMailDispatchSource($db,$p,$email,$key,$secret));return$config;
    }
}
