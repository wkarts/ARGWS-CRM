<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/CompanyEmailTemplates.php';
require_once __DIR__.'/CompanyMailConfig.php';

/** Public identity/authentication and recovery bound to a persisted owner. No staff context or legacy hooks. */
final class CompanyPortal
{
    public function __construct(private $db,private string $prefix,private string $secret)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix)||strlen($secret)<32)throw new RuntimeException('company_portal_configuration_required');
    }
    private function rows(string $sql,array $params=[]): array{return IssuerStorage::query($this->db,$sql,$params)->result_array();}
    /** Opaque route identifier only; authentication and resource grants are still mandatory. */
    public function salesLinkHash(int $issuer,int $document,string $originHash):string
    {
        IssuerPolicy::id($issuer);IssuerPolicy::id($document);if(!preg_match('/^[a-f0-9]{64}$/D',$originHash))throw new DomainException('company_portal_sales_unavailable');
        return substr(hash_hmac('sha256',json_encode(['sales',$issuer,$document,$originHash],JSON_THROW_ON_ERROR),$this->secret),0,32);
    }
    private function transaction(callable $operation,array $extra=[])
    {
        return IssuerTransaction::run($this->db,$this->prefix,array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','contacts','clients','company_portal_tokens','issuers_audit'],$extra),$operation);
    }
    private function identityHash(array $row): string
    {
        return hash_hmac('sha256',json_encode([(int)$row['id'],(int)$row['userid'],(int)$row['issuer_id'],$row['email'],$row['password']],JSON_THROW_ON_ERROR),$this->secret);
    }
    private function contact(int $issuer,?string $email=null,?int $id=null,bool $lock=false): ?array
    {
        $p=$this->prefix;
        $origin='CASE WHEN o.client_id IS NULL THEN i.primary_issuer_id ELSE o.owner_issuer_id END';
        $rows=$this->rows("SELECT p.id,p.userid,p.email,p.firstname,p.lastname,p.password,c.company,r.id issuer_id,r.legal_name FROM {$p}contacts p JOIN {$p}clients c ON c.userid=p.userid LEFT JOIN {$p}issuers_clients o ON o.client_id=c.userid LEFT JOIN {$p}issuers_installation_identity i ON i.id=1 JOIN {$p}issuers_registry r ON r.id=($origin) WHERE r.id=? AND r.active=1 AND p.active=1 AND c.active=1".($email!==null?' AND LOWER(p.email)=?':' AND p.id=?').' ORDER BY p.id LIMIT 2'.($lock?' FOR UPDATE':''),[$issuer,$email??$id]);
        return count($rows)===1?$rows[0]:null;
    }
    /** Counts every public attempt, including unknown identities, before lookup/hash verification. */
    public function allowAttempt($issuer,string $ip,string $kind): bool
    {
        $issuer=IssuerPolicy::id($issuer);
        if(!in_array($kind,['login','recovery','reset','verification','registration','captcha'],true)||strlen($ip)>100)throw new DomainException('company_portal_invalid_input');
        if(!$this->rows('SELECT id FROM '.$this->prefix.'issuers_registry WHERE id=? AND active=1',[$issuer]))return false;
        $bucket=hash_hmac('sha256',$issuer.'|'.$kind.'|'.$ip,$this->secret);$now=time();
        return IssuerTransaction::run($this->db,$this->prefix,['issuers_lock','company_portal_limits'],function()use($bucket,$now,$kind):bool{
            IssuerStorage::query($this->db,'DELETE FROM '.$this->prefix.'company_portal_limits WHERE window_start<?',[$now-172800]);
            $row=$this->rows('SELECT window_start,attempts FROM '.$this->prefix.'company_portal_limits WHERE bucket_hash=? FOR UPDATE',[$bucket])[0]??null;
            if(!$row){
                IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'company_portal_limits(bucket_hash,window_start,attempts) VALUES(?,?,1)',[$bucket,$now]);
                $written=$this->rows('SELECT window_start,attempts FROM '.$this->prefix.'company_portal_limits WHERE bucket_hash=?',[$bucket]);
                if(count($written)!==1||(int)$written[0]['window_start']!==$now||(int)$written[0]['attempts']!==1)throw new RuntimeException('issuer_portal_throttle_unconfirmed');
                return true;
            }
            $count=(int)$row['window_start']<$now-900?1:min(1000000000,(int)$row['attempts']+1);$start=$count===1?$now:(int)$row['window_start'];
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'company_portal_limits SET attempts=?,window_start=? WHERE bucket_hash=?',[$count,$start,$bucket]);
            $written=$this->rows('SELECT window_start,attempts FROM '.$this->prefix.'company_portal_limits WHERE bucket_hash=?',[$bucket]);
            if(count($written)!==1||(int)$written[0]['window_start']!==$start||(int)$written[0]['attempts']!==$count)throw new RuntimeException('issuer_portal_throttle_unconfirmed');
            return $count<=($kind==='recovery'?5:20);
        });
    }
    public function login($issuer,string $email,string $password,callable $verify): ?array
    {
        $issuer=IssuerPolicy::id($issuer);$email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>100||strlen($password)>4096)return null;
        $row=$this->contact($issuer,$email);
        // Always verify one hash, so a missing identity does not skip the expensive operation.
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid=$verify($password,$row['password']??$dummy);
        if(!$row||!$row['password']||!$valid)return null;
        $stamp=$this->identityHash($row);
        return $this->transaction(function()use($issuer,$row,$stamp):?array{
            $current=$this->contact($issuer,null,(int)$row['id'],true);
            if(!$current||!hash_equals($stamp,$this->identityHash($current)))return null;
            $this->audit($issuer,(int)$row['id'],'company_portal_login');
            return ['issuer_id'=>$issuer,'contact_id'=>(int)$row['id'],'client_id'=>(int)$row['userid'],'stamp'=>$stamp];
        });
    }
    /** Cross-service readers must share this exact connection and tenant namespace. */
    public function assertStorageBinding($db,string $prefix):void
    {
        if($this->db!==$db||$this->prefix!==$prefix)throw new DomainException('company_portal_storage_mismatch');
    }
    /** Revalidates every request; password/email/owner changes invalidate the session stamp. */
    public function invoiceIdentity(array $session,$issuer,bool $lock=false): ?array
    {
        $issuer=IssuerPolicy::id($issuer);
        if(($session['issuer_id']??null)!==$issuer||!is_int($session['contact_id']??null)||!is_string($session['stamp']??null))return null;
        $row=$this->contact($issuer,null,$session['contact_id'],$lock);
        if(!$row||(int)$row['userid']!==($session['client_id']??null)||!hash_equals($session['stamp'],$this->identityHash($row)))return null;
        return ['contact_id'=>(int)$row['id'],'client_id'=>(int)$row['userid'],'company'=>$row['company'],'firstname'=>$row['firstname'],'lastname'=>$row['lastname'],'email'=>$row['email'],'issuer_name'=>$row['legal_name']];
    }
    public function sessionIdentity(array $session,$issuer): ?array
    {
        $row=$this->invoiceIdentity($session,$issuer);if(!$row)return null;
        unset($row['contact_id'],$row['client_id']);return $row;
    }
    /** Returns no key or account-existence flag. Token and durable queue row commit together. */
    public function requestRecovery($issuer,string $email,string $baseUrl,callable $decrypt): void
    {
        $issuer=IssuerPolicy::id($issuer);$email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>100)return;
        $parts=parse_url($baseUrl);
        if(!$parts||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!isset($parts['host'])||(($parts['scheme']??'')!=='https'&&!(($parts['scheme']??'')==='http'&&in_array($parts['host'],['127.0.0.1','localhost','[::1]'],true))))throw new DomainException('company_portal_trusted_url_required');
        $this->transaction(function()use($issuer,$email,$baseUrl,$decrypt):void{
            $row=$this->contact($issuer,$email,null,true);if(!$row)return;
            $now=date('Y-m-d H:i:s');
            $last=$this->rows('SELECT created_at FROM '.$this->prefix.'company_portal_tokens WHERE contact_id=? ORDER BY created_at DESC LIMIT 1',[$row['id']])[0]??null;
            if($last&&$last['created_at']>date('Y-m-d H:i:s',time()-60))return;
            $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
            $link=rtrim($baseUrl,'/').'/company_portal/index/'.$issuer.'#token='.$token;
            $this->queueRecovery($issuer,$row,$link,$decrypt);
            IssuerStorage::query($this->db,'DELETE FROM '.$this->prefix.'company_portal_tokens WHERE contact_id=?',[$row['id']]);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'company_portal_tokens(token_hash,issuer_id,contact_id,client_id,identity_hash,expires_at,created_at) VALUES(?,?,?,?,?,?,?)',[$hash,$issuer,$row['id'],$row['userid'],$this->identityHash($row),date('Y-m-d H:i:s',time()+3600),$now]);
            if(count($this->rows('SELECT token_hash FROM '.$this->prefix.'company_portal_tokens WHERE token_hash=?',[$hash]))!==1)throw new RuntimeException('issuer_portal_write_unconfirmed');
            $this->audit($issuer,(int)$row['id'],'company_portal_recovery_queued');
        },['mail_queue','emailtemplates','options']);
    }
    private function queueRecovery(int $issuer,array $row,string $link,callable $decrypt): void
    {
        $store=new CompanySettingsStorage($this->db,$this->prefix);$config=CompanyMailConfig::build($this->db,$this->prefix,$issuer,$decrypt);
        $template=CompanyEmailTemplates::findForIssuer($this->db,$this->prefix,$issuer,'contact-forgot-password','portuguese_br');
        if(!$template||(int)$template->active!==1)throw new DomainException('company_portal_template_required');
        $from=trim($store->get($issuer,'smtp_email'));
        if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('company_portal_sender_required');
        $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $plain=['{companyname}'=>$row['legal_name'],'{contact_firstname}'=>$row['firstname'],'{contact_lastname}'=>$row['lastname'],'{contact_email}'=>$row['email'],'{crm_url}'=>explode('#',$link,2)[0]];
        $html=array_map($escape,$plain);$html['{reset_password_url}']=$escape($link);$html['{email_signature}']=$store->get($issuer,'email_signature');
        $message=strtr($store->get($issuer,'email_header').$template->message.$store->get($issuer,'email_footer'),$html);
        // Unknown merge fields stay explicit; never invoke a global merge-field provider.
        if(!str_contains($template->message,'{reset_password_url}'))throw new DomainException('company_portal_template_required');
        $subject=strtr($template->subject,$plain);$fromName=strtr($template->fromname,$plain);
        if(preg_match('/[\r\n]/',$subject.$fromName))throw new DomainException('company_portal_template_required');
        $headers=$config['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];
        if((int)$template->plaintext===1){$message=html_entity_decode(strip_tags(str_replace(['<br>','<br />','<br/>'],"\n",$message)),ENT_QUOTES,'UTF-8');$message.="\n".$link;$message=nl2br($escape($message));}
        $fields=['engine'=>$config['useragent'],'email'=>$row['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8')."\n".$link,'headers'=>serialize($headers),'attachments'=>'','status'=>'pending','date'=>date('Y-m-d H:i:s'),'issuer_id'=>$issuer];
        IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
        $id=(int)$this->db->insert_id();
        $written=$this->rows('SELECT issuer_id,email,message FROM '.$this->prefix.'mail_queue WHERE id=?',[$id])[0]??[];
        if(($written['message']??null)!==$message||($written['email']??null)!==$row['email']||(int)($written['issuer_id']??0)!==$issuer)throw new RuntimeException('issuer_portal_write_unconfirmed');
    }
    public function reset($issuer,string $token,string $password,callable $hashPassword,?int $expectedContact=null): bool
    {
        $issuer=IssuerPolicy::id($issuer);
        if(!preg_match('/^[a-f0-9]{64}$/D',$token)||strlen($password)<12||strlen($password)>4096)return false;
        $hash=$hashPassword($password);if(!is_string($hash)||strlen($hash)<20)throw new RuntimeException('issuer_portal_hash_failed');
        return $this->transaction(function()use($issuer,$token,$hash,$expectedContact):bool{
            $key=hash('sha256',$token);$receipt=$this->rows('SELECT * FROM '.$this->prefix.'company_portal_tokens WHERE token_hash=? AND issuer_id=? FOR UPDATE',[$key,$issuer])[0]??null;
            if(!$receipt||($expectedContact!==null&&(int)$receipt['contact_id']!==$expectedContact)||$receipt['expires_at']<=date('Y-m-d H:i:s'))return false;
            $row=$this->contact($issuer,null,(int)$receipt['contact_id'],true);
            if(!$row||(int)$row['userid']!==(int)$receipt['client_id']||!hash_equals($receipt['identity_hash'],$this->identityHash($row)))return false;
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'contacts SET password=?,new_pass_key=NULL,new_pass_key_requested=NULL,last_password_change=? WHERE id=? AND userid=?',[$hash,date('Y-m-d H:i:s'),$row['id'],$row['userid']]);
            $confirmed=$this->contact($issuer,null,(int)$row['id'],true);if(!$confirmed||$confirmed['password']!==$hash)throw new RuntimeException('issuer_portal_write_unconfirmed');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->prefix.'company_portal_tokens WHERE contact_id=?',[$row['id']]);
            if($this->rows('SELECT token_hash FROM '.$this->prefix.'company_portal_tokens WHERE contact_id=?',[$row['id']]))throw new RuntimeException('issuer_portal_write_unconfirmed');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->prefix.'user_auto_login WHERE user_id=? AND staff=0',[$row['id']]);
            $this->audit($issuer,(int)$row['id'],'company_portal_password_changed');return true;
        },['user_auto_login']);
    }
    /** Complete an already-issued core password link only for the persisted, unowned principal lineage. */
    public function resetHistoricalPrimary(int $issuer,int $contact,string $key,string $password,callable $hashPassword): bool
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$key)||strlen($password)<12||strlen($password)>4096)return false;
        return $this->transaction(function()use($issuer,$contact,$key,$password,$hashPassword):bool{
            $p=$this->prefix;
            if(count($this->rows('SELECT r.id FROM '.$p.'issuers_installation_identity i JOIN '.$p.'issuers_registry r ON r.id=i.primary_issuer_id WHERE i.id=1 AND r.id=? AND r.active=1',[$issuer]))!==1)return false;
            $row=$this->contact($issuer,null,$contact,true);
            if($row)$row=array_merge($row,$this->rows('SELECT new_pass_key,new_pass_key_requested FROM '.$p.'contacts WHERE id=? AND userid=?',[$contact,$row['userid']])[0]??[]);
            if(!$row||$this->rows('SELECT client_id FROM '.$p.'issuers_clients WHERE client_id=?',[$row['userid']])||!is_string($row['new_pass_key'])||!hash_equals($row['new_pass_key'],$key))return false;
            $sent=$row['new_pass_key_requested'];$date=is_string($sent)?DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$sent,new DateTimeZone('UTC')):false;
            if(!$date||$date->format('Y-m-d H:i:s')!==$sent||$date->getTimestamp()>time()||$date->getTimestamp()<time()-3600)return false;
            $hash=$hashPassword($password);if(!is_string($hash)||strlen($hash)<20)throw new RuntimeException('issuer_portal_hash_failed');$now=gmdate('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'UPDATE '.$p.'contacts SET password=?,new_pass_key=NULL,new_pass_key_requested=NULL,last_password_change=? WHERE id=? AND userid=? AND new_pass_key=?',[$hash,$now,$contact,$row['userid'],$key]);
            $after=$this->contact($issuer,null,$contact,true);if($after)$after=array_merge($after,$this->rows('SELECT new_pass_key,new_pass_key_requested FROM '.$p.'contacts WHERE id=? AND userid=?',[$contact,$row['userid']])[0]??[]);if(!$after||$after['password']!==$hash||$after['new_pass_key']!==null||$after['new_pass_key_requested']!==null)throw new RuntimeException('issuer_portal_write_unconfirmed');
            IssuerStorage::query($this->db,'DELETE FROM '.$p.'company_portal_tokens WHERE contact_id=?',[$contact]);IssuerStorage::query($this->db,'DELETE FROM '.$p.'user_auto_login WHERE user_id=? AND staff=0',[$contact]);
            if($this->rows('SELECT token_hash FROM '.$p.'company_portal_tokens WHERE contact_id=?',[$contact])||$this->rows('SELECT user_id FROM '.$p.'user_auto_login WHERE user_id=? AND staff=0',[$contact]))throw new RuntimeException('issuer_portal_write_unconfirmed');
            $this->audit($issuer,$contact,'company_portal_historical_password_changed');return true;
        },['user_auto_login']);
    }
    private function audit(int $issuer,int $contact,string $event): void
    {
        $metadata=json_encode(['actor_kind'=>'contact','contact_id'=>$contact,'receipt_nonce'=>bin2hex(random_bytes(24))],JSON_THROW_ON_ERROR);
        $now=date('Y-m-d H:i:s');
        IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(0,?,?,?,?,?)',[$event,$issuer,$contact,$metadata,$now]);
        // A trigger may ignore an INSERT without failing the transaction. A new nonce
        // proves this operation, not a previous identical event/lastInsertId.
        $receipt=$this->rows('SELECT actor_id,event,issuer_id,subject_id,metadata_json,created_at FROM '.$this->prefix.'issuers_audit WHERE metadata_json=?',[$metadata]);
        if(count($receipt)!==1||(int)$receipt[0]['actor_id']!==0||$receipt[0]['event']!==$event||(int)$receipt[0]['issuer_id']!==$issuer||(int)$receipt[0]['subject_id']!==$contact||$receipt[0]['metadata_json']!==$metadata||$receipt[0]['created_at']!==$now)throw new RuntimeException('issuer_portal_audit_unconfirmed');
    }
}
