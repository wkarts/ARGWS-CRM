<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyContracts.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/CompanyPortalMailConfig.php';
require_once __DIR__.'/CompanyPortalMailIdentity.php';
require_once __DIR__.'/CompanyEmailTemplates.php';
require_once __DIR__.'/NativeCompanyMailDispatchFence.php';
/** Explicit contract notification through the existing private mail queue; no network during writes. */
final class NativeCompanyContractMail
{
    public const HEADER='nativeCompanyContractDelivery';
    private const EVENT='native_contract_mail_queued';
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $q,array $v=[]):array{return IssuerStorage::query($this->db,$q,$v)->result_array();}
    public function tables():array{return array_values(array_unique(array_merge(NativeCompanyMailDispatchFence::tables(),(new CompanyPortalMailConfig($this->db,$this->p))->tables(),['issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','clients','contacts','contact_permissions','company_contracts','company_contract_requests','company_portal_contracts','issuers_audit','emailtemplates'])));}
    private static function hash(array $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    private function authorize(int $issuer,int $actor,bool $lock=true):void{$a=new IssuerResourceAccess($this->db,$this->p);if($lock)$a->lockFinancialIdentity($issuer,$actor);foreach([['contracts','view'],['contracts','edit'],['customers','view']]as[$f,$c])if(!$a->can($issuer,$actor,$f,$c))throw new DomainException('native_contract_mail_denied');}
    private function source(int $issuer,int $actor,int $id,int $contact):array
    {
        $this->authorize($issuer,$actor);
        $row=$this->rows("SELECT c.* FROM {$this->p}company_contracts c JOIN {$this->p}company_portal_contracts v ON v.issuer_id=c.issuer_id AND v.contract_id=c.id AND v.visible=1 JOIN {$this->p}issuers_clients o ON o.owner_issuer_id=c.issuer_id AND o.client_id=c.client_id WHERE c.issuer_id=? AND c.id=? AND c.status='active' AND c.number IS NOT NULL FOR UPDATE",[$issuer,$id])[0]??null;
        if(!$row)throw new DomainException('native_contract_mail_denied');$snapshot=json_decode($row['snapshot_json'],true,32,JSON_THROW_ON_ERROR);if(($snapshot['issuer_id']??null)!==$issuer||($snapshot['client']['id']??null)!==(int)$row['client_id']||($snapshot['number']??null)!==(int)$row['number'])throw new DomainException('native_contract_mail_denied');
        $recipient=$this->rows("SELECT c.id,c.firstname,c.lastname,c.email FROM {$this->p}contacts c JOIN {$this->p}clients u ON u.userid=c.userid AND u.active=1 WHERE c.id=? AND c.userid=? AND c.active=1 AND (SELECT COUNT(*) FROM {$this->p}contact_permissions p WHERE p.userid=c.id AND p.permission_id=3)=1 FOR UPDATE",[$contact,$row['client_id']])[0]??null;
        if(!$recipient||!filter_var($recipient['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$recipient['email']))throw new DomainException('native_contract_mail_denied');return[$row,$recipient,$snapshot];
    }
    public function recipients(int $issuer,int $actor,int $id):array
    {
        $contract=(new CompanyContracts($this->db,$this->p))->get($issuer,$actor,$id);$this->authorize($issuer,$actor,false);$rows=$this->rows("SELECT c.id,c.firstname,c.lastname,c.email FROM {$this->p}contacts c JOIN {$this->p}issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id={$issuer} WHERE c.userid=? AND c.active=1 AND (SELECT COUNT(*) FROM {$this->p}contact_permissions p WHERE p.userid=c.id AND p.permission_id=3)=1 ORDER BY c.id LIMIT 100",[$contract['client_id']]);$this->authorize($issuer,$actor,false);return$rows;
    }
    private function receipt(int $issuer,int $id,string $key):?array
    {
        $needle=json_encode(['request_key'=>$key],JSON_THROW_ON_ERROR);$rows=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? AND JSON_VALID(metadata_json) AND JSON_EXTRACT(metadata_json,'$.request_key')=JSON_EXTRACT(?,'$.request_key') ORDER BY id",[$issuer,$id,self::EVENT,$needle]);if(count($rows)>1)throw new RuntimeException('issuer_contract_mail_unconfirmed');return$rows?json_decode($rows[0]['metadata_json'],true,32,JSON_THROW_ON_ERROR):null;
    }
    private static function binding(array $b):string
    {
        if(array_keys($b)!==['issuer','actor','contract','revision','contact','request_key'])throw new DomainException('native_contract_mail_denied');foreach(['issuer','actor','contract','revision','contact']as$key)if(!is_int($b[$key])||$b[$key]<1)throw new DomainException('native_contract_mail_denied');if(!is_string($b['request_key'])||!preg_match('/^[a-f0-9]{64}$/D',$b['request_key']))throw new DomainException('native_contract_mail_denied');return self::hash($b);
    }
    public function queue(int $issuer,int $actor,int $id,int $contact,int $revision,string $key,string $base,callable $decrypt):int
    {
        $b=['issuer'=>$issuer,'actor'=>$actor,'contract'=>$id,'revision'=>$revision,'contact'=>$contact,'request_key'=>$key];$hash=self::binding($b);$parts=parse_url($base);if(!is_array($parts)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||(($parts['scheme']??'')!=='https'&&!(($parts['scheme']??'')==='http'&&in_array($parts['host'],['localhost','127.0.0.1','[::1]'],true))))throw new DomainException('native_contract_mail_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$id,$contact,$revision,$key,$base,$decrypt,$b,$hash):int{
            [$row,$recipient,$snapshot]=$this->source($issuer,$actor,$id,$contact);$prior=$this->rows('SELECT * FROM '.$this->p.'company_contract_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($prior){if((int)$prior['issuer_id']!==$issuer||(int)$prior['actor_id']!==$actor||$prior['operation']!=='contract_mail'||!hash_equals($prior['input_hash'],$hash)||(int)$prior['result_id']!==$id)throw new DomainException('native_contract_mail_conflict');$receipt=$this->receipt($issuer,$id,$key);if(!$receipt||!hash_equals($receipt['recipient_hash'],self::hash($recipient)))throw new DomainException('native_contract_mail_conflict');return(int)$receipt['queue_id'];}
            if((int)$row['revision']!==$revision)throw new DomainException('native_contract_mail_stale');$store=new CompanySettingsStorage($this->db,$this->p);$config=new CompanyPortalMailConfig($this->db,$this->p);$state=$config->stateInOwnedTransaction($issuer);$transport=$config->buildInOwnedTransaction($issuer,$decrypt);
            $template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$issuer,'send-contract',$store->get($issuer,'active_language')?:'portuguese_br');if(!$template||(int)$template->active!==1||!str_contains($template->message,'{contract_link}'))throw new DomainException('native_contract_mail_template_required');
            $from=$store->get($issuer,'smtp_email');if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))throw new DomainException('native_contract_mail_sender_required');$url=rtrim($base,'/').'/clients/contracts/'.NativeCompanyResourceId::encode('company_contract',(string)$id).'?issuer_id='.$issuer;
            $values=['{contact_firstname}'=>$recipient['firstname'],'{contact_lastname}'=>$recipient['lastname'],'{contact_email}'=>$recipient['email'],'{contract_description}'=>$snapshot['body'],'{contract_subject}'=>$snapshot['name'],'{contract_id}'=>(string)$row['number'],'{contract_link}'=>$url,'{contract_datestart}'=>$snapshot['start_date'],'{contract_dateend}'=>$snapshot['due_date'],'{client_company}'=>$snapshot['client']['name'],'{companyname}'=>$snapshot['identity']['legal_name'],'{crm_url}'=>$url];$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html=array_map($e,$values);$html['{email_signature}']=$store->get($issuer,'email_signature');$message=strtr($store->get($issuer,'email_header').$template->message.$store->get($issuer,'email_footer'),$html);$subject=strtr($template->subject,$values);$name=strtr($template->fromname,$values);if(preg_match('/[\r\n]/',$subject.$name))throw new DomainException('native_contract_mail_invalid');if((int)$template->plaintext===1)$message=nl2br($e(html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8')));
            $headers=$transport['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$name,'subject'=>$subject]:['From'=>mb_encode_mimeheader($name,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];$headers[self::HEADER]=$b;
            $fields=['engine'=>$transport['useragent'],'email'=>$recipient['email'],'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8'),'headers'=>serialize($headers),'attachments'=>'','status'=>'pending','date'=>gmdate('Y-m-d H:i:s'),'issuer_id'=>$issuer];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$this->db->insert_id();CompanyPortalMailIdentity::confirmed($this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$issuer])[0]??[],$fields);
            $receipt=['request_key'=>$key,'queue_id'=>$queue,'source_hash'=>self::hash($row),'recipient_hash'=>self::hash($recipient),'configuration_hash'=>$state['configuration_hash'],'template_hash'=>self::hash((array)$template),'payload_hash'=>CompanyPortalMailIdentity::payload((object)$fields),'binding_hash'=>$hash];$json=json_encode($receipt,JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,self::EVENT,$issuer,$id,gmdate('Y-m-d H:i:s'),$json]);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_contract_requests(request_key,issuer_id,actor_id,input_hash,operation,result_id,created_at) VALUES(?,?,?,?,?,?,?)',[$key,$issuer,$actor,$hash,'contract_mail',$id,gmdate('Y-m-d H:i:s')]);
            if($this->receipt($issuer,$id,$key)!==$receipt||!$this->rows('SELECT result_id FROM '.$this->p.'company_contract_requests WHERE request_key=? AND issuer_id=? AND actor_id=? AND input_hash=? AND operation=? AND result_id=?',[$key,$issuer,$actor,$hash,'contract_mail',$id]))throw new RuntimeException('issuer_contract_mail_unconfirmed');NativeCompanyMailDispatchFence::prepareInOwnedTransaction($this->db,$this->p,$issuer,$queue,'contract',$hash);$this->stateInOwned((object)($fields+['id'=>$queue]),$headers);return$queue;
        });
    }
    public static function assertQueueRouting($db,string $p,object $mail,array $headers):void
    {
        $f=NativeCompanyMailDispatchFence::find($db,$p,$mail->id);$b=$headers[self::HEADER]??null;if($b===null&&(!$f||$f['kind']!=='contract'))return;if(!is_array($b)||!$f||$f['kind']!=='contract'||!hash_equals($f['binding_hash'],self::binding($b))||(int)($mail->issuer_id??0)!==$b['issuer'])throw new DomainException('native_contract_mail_denied');
    }
    private function stateInOwned(object $mail,array $headers):array
    {
        self::assertQueueRouting($this->db,$this->p,$mail,$headers);$b=$headers[self::HEADER]??null;if(!is_array($b))throw new DomainException('native_contract_mail_denied');IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());[$row,$recipient]=$this->source($b['issuer'],$b['actor'],$b['contract'],$b['contact']);$receipt=$this->receipt($b['issuer'],$b['contract'],$b['request_key']);$stored=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$mail->id,$b['issuer']])[0]??null;$state=(new CompanyPortalMailConfig($this->db,$this->p))->stateInOwnedTransaction($b['issuer']);$f=NativeCompanyMailDispatchFence::find($this->db,$this->p,$mail->id);$template=CompanyEmailTemplates::findForIssuer($this->db,$this->p,$b['issuer'],'send-contract',(new CompanySettingsStorage($this->db,$this->p))->get($b['issuer'],'active_language')?:'portuguese_br');
        if(!$receipt||!$template||!hash_equals($receipt['template_hash'],self::hash((array)$template))||!$stored||!$f||!in_array($f['state'],['prepared','dispatched'],true)||!in_array($stored['status'],['pending','sending'],true)||(int)$row['revision']!==$b['revision']||(int)$receipt['queue_id']!==(int)$mail->id||!hash_equals($receipt['source_hash'],self::hash($row))||!hash_equals($receipt['recipient_hash'],self::hash($recipient))||!hash_equals($receipt['configuration_hash'],$state['configuration_hash'])||!hash_equals($receipt['binding_hash'],self::binding($b))||!hash_equals($receipt['payload_hash'],CompanyPortalMailIdentity::payload((object)$stored))||!hash_equals($receipt['payload_hash'],CompanyPortalMailIdentity::payload($mail)))throw new DomainException('native_contract_mail_stale');return$state+['issuer_id'=>$b['issuer']];
    }
    public function state(object $mail,array $headers):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->stateInOwned($mail,$headers));}
    public static function claimDispatch($db,string $p,object $mail,array $headers):?array
    {
        self::assertQueueRouting($db,$p,$mail,$headers);if(!isset($headers[self::HEADER]))return null;$self=new self($db,$p);return IssuerTransaction::run($db,$p,$self->tables(),function()use($self,$db,$p,$mail,$headers){$state=$self->stateInOwned($mail,$headers);$hash=self::binding($headers[self::HEADER]);$attempt=NativeCompanyMailDispatchFence::claimInOwnedTransaction($db,$p,$state['issuer_id'],(int)$mail->id,'contract',$hash);return['issuer'=>$state['issuer_id'],'queue'=>(int)$mail->id,'hash'=>$hash,'attempt'=>$attempt];});
    }
    public static function completeDispatch($db,string $p,array $permit,bool $sent):void{NativeCompanyMailDispatchFence::complete($db,$p,$permit['issuer'],$permit['queue'],'contract',$permit['hash'],$permit['attempt'],$sent);}
    public static function transportForQueue($db,string $p,object $mail,array $headers,callable $decrypt,callable $encrypt):array
    {
        $state=(new self($db,$p))->state($mail,$headers);$i=$state['issuer_id'];if($state['kind']==='native')return CompanyMailConfig::build($db,$p,$i,$decrypt);require_once __DIR__.'/NativeCompanyContractMailSource.php';$source=new NativeCompanyContractMailSource($db,$p,$mail,$headers);if($state['kind']==='microsoft')return(new CompanyMicrosoftMailOAuth($db,$p))->transportConfig($i,$encrypt,$decrypt,$source);
        $config=CompanyMailConfig::build($db,$p,$i,$decrypt,$encrypt,'google_notifications');$oauth=new CompanyGoogleMailOAuth($db,$p);$profile=$oauth->transportProfile($i);if(!$profile)throw new DomainException('native_contract_mail_denied');$config['company_google_mail_provider']=new CompanyGoogleMailOAuthTokenProvider($oauth,$i,$state['epoch'],$profile['sender_email'],$encrypt,$decrypt,$source);return$config;
    }
}
