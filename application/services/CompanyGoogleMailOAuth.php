<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyGoogleMailOAuthSchema.php';
require_once __DIR__.'/CompanyGoogleMailOAuthClient.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Explicit-origin Google SMTP for the existing invoice/sales notification queues only. */
final class CompanyGoogleMailOAuth
{
    public function __construct(private $db,private string $p,private ?CompanyGoogleMailOAuthClient $client=null,private $clock=null){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$this->client??=new CompanyGoogleMailOAuthClient();}
    private function now():int{return $this->clock!==null?($this->clock)():time();}
    private function stamp(?int $time=null):string{return gmdate('Y-m-d H:i:s',$time??$this->now());}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array{return array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff','options'],array_keys(CompanyGoogleMailOAuthSchema::definitions()));}
    public static function participantTables($db,string $p):array{return CompanyGoogleMailOAuthSchema::present($db,$p)?array_keys(CompanyGoogleMailOAuthSchema::definitions()):[];}
    private function tx(callable $work){CompanyGoogleMailOAuthSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
    private function auth(int $issuer,int $actor,string $cap='edit'):void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'settings',$cap))throw new DomainException('company_google_mail_access_denied');}
    private function row(int $issuer):?array{return $this->rows('SELECT * FROM '.$this->p.'company_google_mail_accounts WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;}
    private function audit(int $issuer,int $actor,string $event):void
    {
        $event='company_google_mail_'.$event;$now=$this->stamp();IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[$actor,$event,$issuer,$now]);$id=(int)$this->db->insert_id();
        if(!$id||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND event=? AND subject_id IS NULL AND created_at=?',[$id,$actor,$issuer,$event,$now]))throw new RuntimeException('issuer_google_mail_write_unconfirmed');
    }
    private static function confirmed(array $actual,array $expected):void{foreach($expected as$key=>$value)if(!array_key_exists($key,$actual)||(string)$actual[$key]!== (string)$value)throw new RuntimeException('issuer_google_mail_write_unconfirmed');}
    private static function text($value,int $max):string{if(!is_string($value)||$value===''||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x20\x7f]/',$value))throw new DomainException('company_google_mail_invalid');return$value;}
    private static function seal(callable $encrypt,string $value):string{try{$cipher=$encrypt($value);}catch(Throwable$error){throw new DomainException('company_google_mail_secret_unavailable');}if(!is_string($cipher)||$cipher===''||hash_equals($cipher,$value))throw new DomainException('company_google_mail_secret_unavailable');return$cipher;}
    private static function open(callable $decrypt,string $cipher):string{try{$plain=$decrypt($cipher);}catch(Throwable$error){throw new DomainException('company_google_mail_secret_unavailable');}if(!is_string($plain)||$plain==='')throw new DomainException('company_google_mail_secret_unavailable');return$plain;}
    public static function redirectUri(string $configured):string
    {
        $admin=defined('ADMIN_URI')?trim((string)ADMIN_URI,'/'):'admin';if(!preg_match('/^[A-Za-z0-9_-]+$/D',$admin))throw new DomainException('company_google_mail_https_required');
        $parts=parse_url($configured);if(!is_array($parts)||($parts['scheme']??'')!=='https'||!isset($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||strlen($configured)>1000||preg_match('/[\x00-\x20\x7f]/',$configured)||!filter_var($parts['host'],FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)||(!str_ends_with($parts['path']??'','/'.$admin.'/settings/google_mail_oauth')&&!str_ends_with($parts['path']??'','/'.$admin.'/company_google_mail_oauth')&&!str_ends_with($parts['path']??'','/'.$admin.'/smtp_oauth_google/token'))||preg_match('~(?:\\\\|%|(?:^|/)\.\.(?:/|$))~',$parts['path']??''))throw new DomainException('company_google_mail_https_required');return$configured;
    }
    private function sender(int $issuer):string{$email=(new CompanySettingsStorage($this->db,$this->p))->get($issuer,'smtp_email');if(!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new DomainException('company_google_mail_sender_required');return strtolower($email);}
    private function active(array $row,?int $epoch=null):void
    {
        if($row['status']!=='active'||($epoch!==null&&(int)$row['epoch']!==$epoch)||$row['refresh_cipher']===''||$row['access_cipher']===''||!hash_equals($row['sender_email'],$this->sender((int)$row['issuer_id'])))throw new DomainException('company_google_mail_connection_required');$this->auth((int)$row['issuer_id'],(int)$row['connected_by']);
    }
    public function view($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->auth($issuer,$actor,'view');return$this->tx(function()use($issuer,$actor){$this->auth($issuer,$actor,'view');$row=$this->row($issuer);$sender=$this->sender($issuer);$attempt=$this->rows('SELECT status,expires_at FROM '.$this->p.'company_google_mail_states WHERE issuer_id=? AND actor_id=? ORDER BY created_at DESC,state_hash DESC LIMIT 1',[$issuer,$actor])[0]??null;
            return ['issuer_id'=>$issuer,'sender_email'=>$sender,'client_id'=>$row['client_id']??'','secret_configured'=>$row!==null&&$row['client_secret_cipher']!=='','status'=>$row['status']??'unconfigured','revision'=>$row===null?0:(int)$row['config_revision'],'epoch'=>$row===null?0:(int)$row['epoch'],'attempt'=>$attempt];});
    }
    public function save($issuer,$actor,int $revision,string $clientId,string $secret,callable $encrypt,callable $decrypt):void
    {
        $this->tx(fn()=>$this->saveInOwnedTransaction($issuer,$actor,$revision,$clientId,$secret,$encrypt,$decrypt));
    }
    public function saveInOwnedTransaction($issuer,$actor,int $revision,string $clientId,string $secret,callable $encrypt,callable $decrypt):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);self::text($clientId,255);if(!preg_match('/^[A-Za-z0-9._-]{10,230}\.apps\.googleusercontent\.com$/D',$clientId))throw new DomainException('company_google_mail_invalid');if($secret!=='')self::text($secret,4096);$this->auth($issuer,$actor);
        CompanyGoogleMailOAuthSchema::assertValid($this->db,$this->p);IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->auth($issuer,$actor);$row=$this->row($issuer);if(($row===null?0:(int)$row['config_revision'])!==$revision)throw new DomainException('company_google_mail_stale');$sender=$this->sender($issuer);if($secret===''&&$row===null)throw new DomainException('company_google_mail_secret_required');$cipher=$secret===''?$row['client_secret_cipher']:(($row!==null&&hash_equals(self::open($decrypt,$row['client_secret_cipher']),$secret))?$row['client_secret_cipher']:self::seal($encrypt,$secret));
            if($row!==null&&$row['client_id']===$clientId&&$row['client_secret_cipher']===$cipher&&$row['sender_email']===$sender)return;
            $now=$this->stamp();if($row===null){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_google_mail_accounts(issuer_id,client_id,client_secret_cipher,sender_email,config_revision,epoch,token_version,status,connected_by,subject_hash,refresh_cipher,access_cipher,access_expires_at,lease_key,lease_expires_at,created_at,updated_at) VALUES(?,?,?,?,1,1,0,?,0,?,?,?,?,?,?,?,?)',[$issuer,$clientId,$cipher,$sender,'smtp','','','','1970-01-01 00:00:00','','1970-01-01 00:00:00',$now,$now]);}
            else IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_google_mail_accounts SET client_id=?,client_secret_cipher=?,sender_email=?,config_revision=config_revision+1,epoch=epoch+1,status=?,connected_by=0,subject_hash=?,refresh_cipher=?,access_cipher=?,access_expires_at=?,lease_key=?,lease_expires_at=?,updated_at=? WHERE issuer_id=?',[$clientId,$cipher,$sender,$row['status']==='smtp'?'smtp':'revoked','','','','1970-01-01 00:00:00','','1970-01-01 00:00:00',$now,$issuer]);
            IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_states SET status='revoked',verifier_cipher='',consumed_at=? WHERE issuer_id=? AND status IN ('pending','exchanging')",[$now,$issuer]);$check=$this->row($issuer);if(!$check||$check['client_id']!==$clientId||$check['client_secret_cipher']!==$cipher||(int)$check['config_revision']!==$revision+1)throw new RuntimeException('issuer_google_mail_write_unconfirmed');self::confirmed($check,['sender_email'=>$sender,'epoch'=>$row===null?1:(int)$row['epoch']+1,'status'=>$row===null||$row['status']==='smtp'?'smtp':'revoked','connected_by'=>0,'subject_hash'=>'','refresh_cipher'=>'','access_cipher'=>'','lease_key'=>'']);$this->audit($issuer,$actor,'credentials_saved');
    }
    public function begin($issuer,$actor,int $revision,string $redirect,callable $encrypt):string
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$redirect=self::redirectUri($redirect);$this->auth($issuer,$actor);return$this->tx(function()use($issuer,$actor,$revision,$redirect,$encrypt){$this->auth($issuer,$actor);$row=$this->row($issuer);if(!$row||(int)$row['config_revision']!==$revision||$row['sender_email']!==$this->sender($issuer))throw new DomainException('company_google_mail_stale');$recent=(int)($this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_google_mail_states WHERE issuer_id=? AND actor_id=? AND created_at>?',[$issuer,$actor,$this->stamp($this->now()-900)])[0]['n']??0);if($recent>=5)throw new DomainException('company_google_mail_rate_limited');$state=bin2hex(random_bytes(32));$verifier=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');$challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');$now=$this->stamp();$stateExpiry=$this->stamp($this->now()+900);
            IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_states SET status='revoked',verifier_cipher='',consumed_at=? WHERE issuer_id=? AND actor_id=? AND status='pending'",[$now,$issuer,$actor]);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_google_mail_states(state_hash,issuer_id,actor_id,config_revision,redirect_uri,verifier_cipher,status,expires_at,created_at,consumed_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[hash('sha256',$state),$issuer,$actor,$revision,$redirect,self::seal($encrypt,$verifier),'pending',$stateExpiry,$now,'1970-01-01 00:00:00']);self::confirmed($this->rows('SELECT * FROM '.$this->p.'company_google_mail_states WHERE state_hash=? AND issuer_id=? AND actor_id=?',[hash('sha256',$state),$issuer,$actor])[0]??[],['state_hash'=>hash('sha256',$state),'issuer_id'=>$issuer,'actor_id'=>$actor,'config_revision'=>$revision,'redirect_uri'=>$redirect,'status'=>'pending','expires_at'=>$stateExpiry]);$this->audit($issuer,$actor,'authorization_started');
            return CompanyGoogleMailOAuthClient::AUTH.'?'.http_build_query(['client_id'=>$row['client_id'],'redirect_uri'=>$redirect,'response_type'=>'code','scope'=>'openid email '.CompanyGoogleMailOAuthClient::MAIL_SCOPE,'access_type'=>'offline','prompt'=>'consent','state'=>$state,'code_challenge'=>$challenge,'code_challenge_method'=>'S256'],'','&',PHP_QUERY_RFC3986);});
    }
    private static function tokens(array $token,bool $needsRefresh):array
    {
        if(($token['token_type']??'')!=='Bearer'||!is_int($token['expires_in']??null)||$token['expires_in']<60||$token['expires_in']>86400||(($needsRefresh||isset($token['scope']))&&(!is_string($token['scope']??null)||!in_array(CompanyGoogleMailOAuthClient::MAIL_SCOPE,preg_split('/\s+/',$token['scope']),true))))throw new DomainException('company_google_mail_token_invalid');try{$access=self::text($token['access_token']??null,8192);$refresh=$token['refresh_token']??'';if($needsRefresh||$refresh!=='')$refresh=self::text($refresh,8192);}catch(DomainException$error){throw new DomainException('company_google_mail_token_invalid');}return['access'=>$access,'refresh'=>$refresh,'expires'=>$token['expires_in']];
    }
    private function identity(array $identity,string $email):string{if(($identity['email_verified']??null)!==true||!is_string($identity['email']??null)||!hash_equals($email,strtolower($identity['email']))||!is_string($identity['sub']??null)||$identity['sub']===''||strlen($identity['sub'])>255||preg_match('/[\x00-\x20\x7f]/',$identity['sub']))throw new DomainException('company_google_mail_identity_mismatch');return hash('sha256',$identity['sub']);}
    public function callback($issuer,$actor,string $state,string $code,callable $encrypt,callable $decrypt):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);if(!preg_match('/^[a-f0-9]{64}$/D',$state))throw new DomainException('company_google_mail_state_invalid');self::text($code,4096);$this->auth($issuer,$actor);$hash=hash('sha256',$state);
        $claim=$this->tx(function()use($issuer,$actor,$hash){$this->auth($issuer,$actor);$row=$this->row($issuer);$s=$this->rows('SELECT * FROM '.$this->p.'company_google_mail_states WHERE state_hash=? AND issuer_id=? AND actor_id=? FOR UPDATE',[$hash,$issuer,$actor])[0]??null;if(!$row||!$s||$s['status']!=='pending'||$s['expires_at']<=$this->stamp()||(int)$s['config_revision']!==(int)$row['config_revision']||$row['sender_email']!==$this->sender($issuer))throw new DomainException('company_google_mail_state_invalid');$consumed=$this->stamp();IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_states SET status='exchanging',consumed_at=? WHERE state_hash=? AND status='pending'",[$consumed,$hash]);self::confirmed($this->rows('SELECT status,consumed_at FROM '.$this->p.'company_google_mail_states WHERE state_hash=?',[$hash])[0]??[],['status'=>'exchanging','consumed_at'=>$consumed]);$this->audit($issuer,$actor,'authorization_claimed');return['account'=>$row,'state'=>$s];});
        try{$a=$claim['account'];$s=$claim['state'];$token=self::tokens($this->client->token(['client_id'=>$a['client_id'],'client_secret'=>self::open($decrypt,$a['client_secret_cipher']),'redirect_uri'=>$s['redirect_uri'],'grant_type'=>'authorization_code','code'=>$code,'code_verifier'=>self::open($decrypt,$s['verifier_cipher'])]),true);$subject=$this->identity($this->client->identity($token['access']),$a['sender_email']);$accessCipher=self::seal($encrypt,$token['access']);$refreshCipher=self::seal($encrypt,$token['refresh']);
            $this->tx(function()use($issuer,$actor,$hash,$claim,$subject,$accessCipher,$refreshCipher,$token){$this->auth($issuer,$actor);$current=$this->row($issuer);$state=$this->rows('SELECT status,config_revision,expires_at FROM '.$this->p.'company_google_mail_states WHERE state_hash=? AND issuer_id=? AND actor_id=? FOR UPDATE',[$hash,$issuer,$actor])[0]??null;if(!$current||!$state||$state['status']!=='exchanging'||$state['expires_at']<=$this->stamp()||(int)$current['config_revision']!==(int)$claim['account']['config_revision']||(int)$current['epoch']!==(int)$claim['account']['epoch']||$current['status']!==$claim['account']['status']||$current['sender_email']!==$this->sender($issuer))throw new DomainException('company_google_mail_stale');$epoch=(int)$current['epoch']+1;$tokenExpiry=$this->stamp($this->now()+$token['expires']-30);IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_accounts SET status='active',epoch=?,token_version=token_version+1,connected_by=?,subject_hash=?,access_cipher=?,refresh_cipher=?,access_expires_at=?,lease_key='',lease_expires_at='1970-01-01 00:00:00',updated_at=? WHERE issuer_id=?",[$epoch,$actor,$subject,$accessCipher,$refreshCipher,$tokenExpiry,$this->stamp(),$issuer]);IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_states SET status='applied',verifier_cipher='' WHERE state_hash=?",[$hash]);$check=$this->row($issuer);if($check['access_cipher']!==$accessCipher||$check['refresh_cipher']!==$refreshCipher||(int)$check['epoch']!==$epoch)throw new RuntimeException('issuer_google_mail_write_unconfirmed');self::confirmed($check,['status'=>'active','connected_by'=>$actor,'subject_hash'=>$subject,'access_expires_at'=>$tokenExpiry,'lease_key'=>'','token_version'=>(int)$current['token_version']+1]);self::confirmed($this->rows('SELECT status,verifier_cipher FROM '.$this->p.'company_google_mail_states WHERE state_hash=?',[$hash])[0]??[],['status'=>'applied','verifier_cipher'=>'']);$this->audit($issuer,$actor,'connected');});
        }catch(Throwable$error){$this->closeAttempt($issuer,$actor,$hash);if($error instanceof DomainException)throw$error;throw new RuntimeException('company_google_mail_remote_unavailable');}
    }
    private function closeAttempt(int $issuer,int $actor,string $hash):void{try{$this->tx(function()use($issuer,$actor,$hash){IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_states SET status='review',verifier_cipher='' WHERE state_hash=? AND issuer_id=? AND actor_id=? AND status='exchanging'",[$hash,$issuer,$actor]);$this->audit($issuer,$actor,'authorization_unconfirmed');});}catch(Throwable$ignored){/* Durable exchanging state is already one-use and remains closed. */}}
    public function revoke($issuer,$actor,int $revision):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->auth($issuer,$actor);$this->tx(function()use($issuer,$actor,$revision){$this->auth($issuer,$actor);$row=$this->row($issuer);if(!$row||(int)$row['config_revision']!==$revision)throw new DomainException('company_google_mail_stale');IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_accounts SET status='revoked',epoch=epoch+1,refresh_cipher='',access_cipher='',lease_key='',updated_at=? WHERE issuer_id=?",[$this->stamp(),$issuer]);IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_states SET status='revoked',verifier_cipher='' WHERE issuer_id=? AND status IN ('pending','exchanging')",[$issuer]);self::confirmed($this->row($issuer),['status'=>'revoked','epoch'=>(int)$row['epoch']+1,'refresh_cipher'=>'','access_cipher'=>'','lease_key'=>'']);$this->audit($issuer,$actor,'revoked');});
    }

    /** Called only after the caller has proved the durable document origin. Never a secret digest. */
    public function fingerprint($issuer):?string
    {
        $issuer=IssuerPolicy::id($issuer);if(!CompanyGoogleMailOAuthSchema::present($this->db,$this->p))return null;
        $row=$this->rows('SELECT issuer_id,config_revision,epoch,status,sender_email,connected_by FROM '.$this->p.'company_google_mail_accounts WHERE issuer_id=?',[$issuer])[0]??null;
        if($row===null)return null;foreach(['issuer_id','config_revision','epoch','connected_by']as$key)$row[$key]=(int)$row[$key];return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));
    }
    public function transportProfile($issuer):?array
    {
        $issuer=IssuerPolicy::id($issuer);if(!CompanyGoogleMailOAuthSchema::present($this->db,$this->p))return null;
        $row=$this->rows('SELECT issuer_id,epoch,status,sender_email FROM '.$this->p.'company_google_mail_accounts WHERE issuer_id=?',[$issuer])[0]??null;
        if(!$row||$row['status']==='smtp')return null;
        if($row['status']!=='active'||$row['sender_email']!==$this->sender($issuer))throw new DomainException('company_google_mail_connection_required');
        return ['issuer_id'=>$issuer,'epoch'=>(int)$row['epoch'],'sender_email'=>$row['sender_email']];
    }
    public function assertReady(int $issuer,int $epoch):void
    {
        $this->tx(function()use($issuer,$epoch){$row=$this->row($issuer);if(!$row)throw new DomainException('company_google_mail_connection_required');$this->active($row,$epoch);});
    }
    /** Network refresh occurs after the durable lease commits, outside every database transaction. */
    public function accessToken(int $issuer,int $epoch,callable $encrypt,callable $decrypt):string
    {
        $claim=$this->tx(function()use($issuer,$epoch,$decrypt){$row=$this->row($issuer);if(!$row)throw new DomainException('company_google_mail_connection_required');$this->active($row,$epoch);
            if($row['access_expires_at']>$this->stamp($this->now()+30))return ['cached'=>self::open($decrypt,$row['access_cipher'])];
            if($row['lease_key']!==''){
                if($row['lease_expires_at']>$this->stamp())throw new DomainException('company_google_mail_refresh_busy');
                IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_accounts SET status='review',epoch=epoch+1,access_cipher='',refresh_cipher='',lease_key='',updated_at=? WHERE issuer_id=?",[$this->stamp(),$issuer]);self::confirmed($this->row($issuer),['status'=>'review','epoch'=>$epoch+1,'access_cipher'=>'','refresh_cipher'=>'','lease_key'=>'']);$this->audit($issuer,(int)$row['connected_by'],'refresh_unconfirmed');return ['unconfirmed'=>true];
            }
            $lease=bin2hex(random_bytes(32));$leaseExpiry=$this->stamp($this->now()+90);IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_google_mail_accounts SET lease_key=?,lease_expires_at=? WHERE issuer_id=?',[$lease,$leaseExpiry,$issuer]);self::confirmed($this->row($issuer),['lease_key'=>$lease,'lease_expires_at'=>$leaseExpiry]);return ['row'=>$row,'lease'=>$lease];});
        if(isset($claim['unconfirmed']))throw new DomainException('company_google_mail_refresh_unconfirmed');
        if(isset($claim['cached']))return $claim['cached'];
        try{$row=$claim['row'];$token=self::tokens($this->client->token(['client_id'=>$row['client_id'],'client_secret'=>self::open($decrypt,$row['client_secret_cipher']),'grant_type'=>'refresh_token','refresh_token'=>self::open($decrypt,$row['refresh_cipher'])]),false);
            $subject=$this->identity($this->client->identity($token['access']),$row['sender_email']);if(!hash_equals($row['subject_hash'],$subject))throw new DomainException('company_google_mail_identity_mismatch');
            $access=self::seal($encrypt,$token['access']);$refresh=$token['refresh']===''?$row['refresh_cipher']:self::seal($encrypt,$token['refresh']);
            return $this->tx(function()use($issuer,$epoch,$claim,$token,$access,$refresh){$current=$this->row($issuer);if(!$current)throw new DomainException('company_google_mail_connection_required');$this->active($current,$epoch);
                if(!hash_equals($current['lease_key'],$claim['lease'])||$current['lease_expires_at']<=$this->stamp()||(int)$current['token_version']!==(int)$claim['row']['token_version']||(int)$current['config_revision']!==(int)$claim['row']['config_revision'])throw new DomainException('company_google_mail_stale');
                $tokenExpiry=$this->stamp($this->now()+$token['expires']-30);IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_accounts SET access_cipher=?,refresh_cipher=?,access_expires_at=?,token_version=token_version+1,lease_key='',lease_expires_at='1970-01-01 00:00:00',updated_at=? WHERE issuer_id=?",[$access,$refresh,$tokenExpiry,$this->stamp(),$issuer]);$check=$this->row($issuer);if($check['access_cipher']!==$access||$check['refresh_cipher']!==$refresh||(int)$check['token_version']!==(int)$current['token_version']+1)throw new RuntimeException('issuer_google_mail_write_unconfirmed');self::confirmed($check,['status'=>'active','epoch'=>$epoch,'lease_key'=>'','access_expires_at'=>$tokenExpiry,'connected_by'=>$current['connected_by'],'subject_hash'=>$current['subject_hash']]);$this->audit($issuer,(int)$current['connected_by'],'token_refreshed');return $token['access'];});
        }catch(Throwable$error){try{$this->tx(function()use($issuer,$claim){$row=$this->row($issuer);if($row&&hash_equals($row['lease_key'],$claim['lease'])){IssuerStorage::query($this->db,"UPDATE ".$this->p."company_google_mail_accounts SET status='review',epoch=epoch+1,access_cipher='',refresh_cipher='',lease_key='',updated_at=? WHERE issuer_id=?",[$this->stamp(),$issuer]);$this->audit($issuer,(int)$row['connected_by'],'refresh_unconfirmed');}});}catch(Throwable$ignored){}if($error instanceof DomainException)throw$error;throw new RuntimeException('company_google_mail_remote_unavailable');}
    }
    private function queueSource(int $issuer,int $actor,int $queue,string $kind,string $key):void
    {
        if(!in_array($kind,['invoice','sales','client_statement'],true)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_google_mail_queue_invalid');
        $table=match($kind){'invoice'=>'company_invoice_deliveries','sales'=>'company_sales_deliveries','client_statement'=>'company_client_statement_deliveries'};
        IssuerTransaction::assertOwned($this->db,$this->p,[$table,'mail_queue']);
        if(!$this->rows('SELECT d.queue_id FROM '.$this->p.$table.' d JOIN '.$this->p.'mail_queue q ON q.id=d.queue_id AND q.issuer_id=d.issuer_id WHERE d.request_key=? AND d.issuer_id=? AND d.actor_id=? AND d.queue_id=? FOR UPDATE',[$key,$issuer,$actor,$queue]))throw new DomainException('company_google_mail_queue_invalid');
    }
    public function bindQueueInOwnedTransaction(int $issuer,int $actor,int $queue,string $kind,string $key):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,array_keys(CompanyGoogleMailOAuthSchema::definitions()));$this->queueSource($issuer,$actor,$queue,$kind,$key);$profile=$this->transportProfile($issuer);if(!$profile)return;$row=$this->row($issuer);$this->active($row,$profile['epoch']);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_google_mail_queue_bindings(queue_id,issuer_id,actor_id,source_kind,request_key,epoch,created_at) VALUES(?,?,?,?,?,?,?)',[$queue,$issuer,$actor,$kind,$key,$profile['epoch'],$this->stamp()]);
        $this->assertQueueInOwnedTransaction($issuer,$actor,$queue,$kind,$key);
    }
    public function assertQueueInOwnedTransaction(int $issuer,int $actor,int $queue,string $kind,string $key):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,array_keys(CompanyGoogleMailOAuthSchema::definitions()));$this->queueSource($issuer,$actor,$queue,$kind,$key);
        $binding=$this->rows('SELECT * FROM '.$this->p.'company_google_mail_queue_bindings WHERE queue_id=? AND issuer_id=? FOR UPDATE',[$queue,$issuer])[0]??null;$profile=$this->transportProfile($issuer);
        if(!$profile){if($binding)throw new DomainException('company_google_mail_queue_stale');return;}
        if(!$binding||(int)$binding['actor_id']!==$actor||$binding['source_kind']!==$kind||!hash_equals($binding['request_key'],$key)||(int)$binding['epoch']!==$profile['epoch'])throw new DomainException('company_google_mail_queue_stale');$this->active($this->row($issuer),$profile['epoch']);
    }
}
