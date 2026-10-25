<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyFileMessageSchema.php';
require_once __DIR__.'/NativeCompanyMailDispatchFence.php';
require_once __DIR__.'/NativeCompanyCustomerFiles.php';
require_once __DIR__.'/CompanyPortalMailConfig.php';
require_once __DIR__.'/CompanyPortalMailIdentity.php';
/** Native send-file queue: explicit recipient, canonical file identity and a durable single-use dispatch fence. */
final class NativeCompanyFileMessage
{
    public const HEADER='X-Argws-Native-File-Message';
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function backend(string$source):object
    {if($source==='customer')return new NativeCompanyCustomerFiles($this->db,$this->p);if($source==='invoice'){require_once __DIR__.'/NativeCompanyInvoiceFiles.php';return new NativeCompanyInvoiceFiles($this->db,$this->p);}throw new DomainException('native_file_mail_denied');}
    public function tables(string$source):array{return array_values(array_unique(array_merge($this->backend($source)->tables(),(new CompanyPortalMailConfig($this->db,$this->p))->tables(),NativeCompanyMailDispatchFence::tables(),NativeCompanyFileMessageSchema::tables())));}
    private function file(int$i,int$a,string$source,int$id,int$revision):array
    {
        if($source==='invoice'&&!(new IssuerResourceAccess($this->db,$this->p))->can($i,$a,'invoices','edit'))throw new DomainException('native_file_mail_denied');
        $file=$this->backend($source)->readForDispatchInOwned($i,$a,$id,$revision);if(!isset($file['content'],$file['content_hash'])||!hash_equals($file['content_hash'],hash('sha256',$file['content'])))throw new DomainException('native_file_mail_denied');return$file;
    }
    private static function input(string$email,string$subject,string$message,string$key):array
    {
        if(strlen($email)>191||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email.$subject)||$subject===''||strlen($subject)>998||strlen($message)>100000||preg_match('//u',$subject.$message)!==1||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_file_mail_invalid');
        require_once APPPATH.'vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';$cfg=HTMLPurifier_Config::createDefault();$cfg->set('Cache.DefinitionImpl',null);$cfg->set('HTML.Allowed','p,br,strong,em,b,i,u,a[href|title],ul,ol,li,span');$cfg->set('URI.AllowedSchemes',['https'=>true,'http'=>true,'mailto'=>true]);$body=(new HTMLPurifier($cfg))->purify(nl2br($message));return[$email,$subject,$body];
    }
    private static function binding(array$b):string
    {
        if(array_keys($b)!==['issuer','actor','source','file','revision','key','content','recipient','configuration','payload','expires']||!in_array($b['source'],['customer','invoice'],true))throw new DomainException('native_file_mail_denied');
        foreach(['issuer','actor','file','revision','expires']as$k)if(!is_int($b[$k])||$b[$k]<1)throw new DomainException('native_file_mail_denied');foreach(['key','content','recipient','configuration','payload']as$k)if(!is_string($b[$k])||!preg_match('/^[a-f0-9]{64}$/D',$b[$k]))throw new DomainException('native_file_mail_denied');return hash('sha256',json_encode($b,JSON_THROW_ON_ERROR));
    }
    private static function payload(object$email,array$headers):string{unset($headers[self::HEADER]);$copy=clone$email;$copy->headers=serialize($headers);return CompanyPortalMailIdentity::payload($copy);}
    public static function attachments(string$engine,array$file):string
    {
        $attachments=$engine==='codeigniter'?[['name'=>['',$file['filename']],'disposition'=>'attachment','type'=>$file['mime'],'content'=>chunk_split(base64_encode($file['content'])),'multipart'=>'mixed']]:[[$file['content'],$file['filename'],$file['filename'],'base64',$file['mime'],true,'attachment',$file['filename']]];return base64_encode(serialize($attachments));
    }
    public function queue(int$i,int$a,string$source,int$id,int$revision,string$key,string$email,string$subject,string$message,callable$decrypt):int
    {
        [$email,$subject,$message]=self::input($email,$subject,$message,$key);$request=hash('sha256',json_encode([$i,$a,$source,$id,$revision,$email,$subject,$message],JSON_THROW_ON_ERROR));
        return IssuerTransaction::run($this->db,$this->p,$this->tables($source),function()use($i,$a,$source,$id,$revision,$key,$email,$subject,$message,$decrypt,$request):int{
            NativeCompanyFileMessageSchema::assertValid($this->db,$this->p);$file=$this->file($i,$a,$source,$id,$revision);$old=$this->rows('SELECT * FROM '.$this->p.'company_native_file_mail_requests WHERE issuer_id=? AND request_key=?',[$i,$key])[0]??null;if($old){if(!hash_equals($request,$old['request_hash']))throw new DomainException('native_file_mail_conflict');return(int)$old['queue_id'];}
            $config=new CompanyPortalMailConfig($this->db,$this->p);$state=$config->stateInOwnedTransaction($i);$transport=$config->buildInOwnedTransaction($i,$decrypt);$store=new CompanySettingsStorage($this->db,$this->p);$from=$store->get($i,'smtp_email');$fromName=$store->get($i,'companyname');if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from.$fromName))throw new DomainException('native_file_mail_denied');
            $headers=$transport['useragent']==='phpmailer'?['from'=>$from,'fromName'=>$fromName,'subject'=>$subject]:['From'=>mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n").' <'.$from.'>','Subject'=>mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"),'Content-Type'=>'text/html; charset=UTF-8'];
            $fields=['engine'=>$transport['useragent'],'email'=>$email,'cc'=>'','bcc'=>'','message'=>$message,'alt_message'=>html_entity_decode(strip_tags($message),ENT_QUOTES,'UTF-8'),'headers'=>serialize($headers),'attachments'=>self::attachments($transport['useragent'],$file),'status'=>'pending','date'=>gmdate('Y-m-d H:i:s'),'issuer_id'=>$i];
            $b=['issuer'=>$i,'actor'=>$a,'source'=>$source,'file'=>$id,'revision'=>$revision,'key'=>$key,'content'=>$file['content_hash'],'recipient'=>hash('sha256',$email),'configuration'=>$state['configuration_hash'],'payload'=>self::payload((object)$fields,$headers),'expires'=>time()+86400];$hash=self::binding($b);$headers[self::HEADER]=$b;$fields['headers']=serialize($headers);
            $pdo=$this->db->pdo??$this->db->conn_id??null;if(!($pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')){$packet=$this->rows('SELECT @@max_allowed_packet bytes')[0]['bytes']??0;$size=1048576;foreach($fields as$value)$size+=strlen((string)$value);if(!is_numeric($packet)||(int)$packet<$size)throw new DomainException('native_file_mail_packet_limit');}
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'mail_queue (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$queue=(int)$this->db->insert_id();if(method_exists($this->db,'affected_rows')&&(int)$this->db->affected_rows()!==1)throw new RuntimeException('native_file_mail_unconfirmed');CompanyPortalMailIdentity::confirmed($this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$queue,$i])[0]??[],$fields);
            $nonce=bin2hex(random_bytes(32));IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_file_mail_requests(queue_id,issuer_id,request_key,request_hash,binding_hash,write_nonce) VALUES(?,?,?,?,?,?)',[$queue,$i,$key,$request,$hash,$nonce]);$receipt=$this->rows('SELECT * FROM '.$this->p.'company_native_file_mail_requests WHERE queue_id=?',[$queue])[0]??[];if(($receipt['write_nonce']??null)!==$nonce)throw new RuntimeException('native_file_mail_unconfirmed');
            NativeCompanyMailDispatchFence::prepareInOwnedTransaction($this->db,$this->p,$i,$queue,'file',$hash);NativeCompanyCustomerAudit::record($this->db,$this->p,$i,$a,$id,'native_file_mail_queued');$this->stateInOwned((object)($fields+['id'=>$queue]),$headers);return$queue;
        });
    }
    public static function assertQueueRouting($db,string$p,object$email,array$headers):void
    {
        $fence=NativeCompanyMailDispatchFence::find($db,$p,$email->id);$binding=$headers[self::HEADER]??null;if($binding===null&&(!$fence||$fence['kind']!=='file'))return;
        if(!is_array($binding)||!$fence||$fence['kind']!=='file'||(int)$email->issuer_id!==$binding['issuer']||!hash_equals($fence['binding_hash'],self::binding($binding)))throw new DomainException('native_file_mail_denied');
    }
    public function state(object$email,array$headers):array
    {self::assertQueueRouting($this->db,$this->p,$email,$headers);$b=$headers[self::HEADER]??null;if(!is_array($b))throw new DomainException('native_file_mail_denied');return IssuerTransaction::run($this->db,$this->p,$this->tables($b['source']),fn()=>$this->stateInOwned($email,$headers));}
    public function stateInOwned(object$email,array$headers):array
    {
        $b=$headers[self::HEADER]??null;if(!is_array($b))throw new DomainException('native_file_mail_denied');$hash=self::binding($b);IssuerTransaction::assertOwned($this->db,$this->p,$this->tables($b['source']));self::assertQueueRouting($this->db,$this->p,$email,$headers);if($b['expires']<time())throw new DomainException('native_file_mail_denied');
        $stored=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$email->id,$b['issuer']])[0]??null;$receipt=$this->rows('SELECT * FROM '.$this->p.'company_native_file_mail_requests WHERE queue_id=? AND issuer_id=?',[$email->id,$b['issuer']])[0]??null;
        if(!$stored||!$receipt||$receipt['request_key']!==$b['key']||!hash_equals($receipt['binding_hash'],$hash)||!hash_equals($b['payload'],self::payload($email,$headers))||!hash_equals(CompanyPortalMailIdentity::payload((object)$stored),CompanyPortalMailIdentity::payload($email))||!hash_equals($b['recipient'],hash('sha256',$email->email)))throw new DomainException('native_file_mail_denied');
        $fence=NativeCompanyMailDispatchFence::find($this->db,$this->p,$email->id);if(!in_array($fence['state']??null,['prepared','dispatched'],true))throw new DomainException('company_mail_dispatch_review_required');
        $file=$this->file($b['issuer'],$b['actor'],$b['source'],$b['file'],$b['revision']);if(!hash_equals($b['content'],$file['content_hash'])||$email->attachments!==self::attachments($email->engine,$file))throw new DomainException('native_file_mail_denied');$state=(new CompanyPortalMailConfig($this->db,$this->p))->stateInOwnedTransaction($b['issuer']);if(!hash_equals($b['configuration'],$state['configuration_hash']))throw new DomainException('native_file_mail_stale');return$state+['issuer_id'=>$b['issuer']];
    }
    public static function claimDispatch($db,string$p,object$email,array$headers):?array
    {
        self::assertQueueRouting($db,$p,$email,$headers);if(!isset($headers[self::HEADER]))return null;$b=$headers[self::HEADER];$service=new self($db,$p);return IssuerTransaction::run($db,$p,$service->tables($b['source']),function()use($db,$p,$email,$headers,$b,$service){$service->stateInOwned($email,$headers);$hash=self::binding($b);$attempt=NativeCompanyMailDispatchFence::claimInOwnedTransaction($db,$p,$b['issuer'],(int)$email->id,'file',$hash);return['issuer'=>$b['issuer'],'queue'=>(int)$email->id,'hash'=>$hash,'attempt'=>$attempt];});
    }
    public static function completeDispatch($db,string$p,array$permit,bool$sent):void
    {if(array_keys($permit)!==['issuer','queue','hash','attempt'])throw new DomainException('native_file_mail_denied');NativeCompanyMailDispatchFence::complete($db,$p,$permit['issuer'],$permit['queue'],'file',$permit['hash'],$permit['attempt'],$sent);}
    public static function transportForQueue($db,string$p,object$email,array$headers,callable$decrypt,callable$encrypt):array
    {
        $state=(new self($db,$p))->state($email,$headers);$i=$state['issuer_id'];if($state['kind']==='native')return CompanyMailConfig::build($db,$p,$i,$decrypt);
        require_once __DIR__.'/NativeCompanyFileMailDispatchSource.php';require_once __DIR__.'/CompanyGoogleMailOAuthTokenProvider.php';$source=new NativeCompanyFileMailDispatchSource($db,$p,$email,$headers);
        if($state['kind']==='microsoft')return(new CompanyMicrosoftMailOAuth($db,$p))->transportConfig($i,$encrypt,$decrypt,$source);
        $config=CompanyMailConfig::build($db,$p,$i,$decrypt,$encrypt,'google_notifications');$oauth=new CompanyGoogleMailOAuth($db,$p);$profile=$oauth->transportProfile($i);if(!$profile)throw new DomainException('native_file_mail_denied');$config['company_google_mail_provider']=new CompanyGoogleMailOAuthTokenProvider($oauth,$i,$state['epoch'],$profile['sender_email'],$encrypt,$decrypt,$source);return$config;
    }
}
