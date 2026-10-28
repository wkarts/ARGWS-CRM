<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
require_once __DIR__.'/NativeCompanyCustomerFileSchema.php';
require_once __DIR__.'/NativeCompanyFileRows.php';
require_once __DIR__.'/NativeCompanyFileContent.php';
require_once __DIR__.'/NativeCompanyUploadPreferences.php';
require_once __DIR__.'/NativeCompanyLegacyCustomerContent.php';
/** The original contact file actions, using the same files IDs, shares and private content. */
final class NativePortalCustomerFiles
{
 private ?array$allocation=null;private ?int$uploaded=null;
 public function __construct(private$db,private string$p,private CompanyPortal$portal,private array$binding,private int$issuer){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
 public function tables():array{return['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','clients','contacts','files','shared_customer_files','company_customer_file_contents','issuers_audit','options'];}
 private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private function identity():array{
  $who=$this->portal->invoiceIdentity($this->binding,$this->issuer,true);if(!$who)throw new DomainException('native_portal_files_denied');
  if(!$this->db->field_exists('email_verified_at',$this->p.'contacts'))throw new RuntimeException('issuer_portal_verification_schema_required');
  $verified=$this->rows('SELECT email_verified_at FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$who['contact_id'],$who['client_id']]);
  if(count($verified)!==1||$verified[0]['email_verified_at']===null)throw new DomainException('native_portal_verification_required');
  if($this->portal->invoiceIdentity($this->binding,$this->issuer,true)!==$who)throw new DomainException('native_portal_files_denied');return$who;
 }
 private function policy():array{
  $this->identity();$store=new CompanySettingsStorage($this->db,$this->p);$out=[];
  foreach(['only_own_files_contacts'=>'0','allow_contact_to_delete_files'=>'0','allowed_files'=>NativeCompanyUploadPreferences::defaultValue('allowed_files'),'enable_google_picker'=>'0','google_api_key'=>'','google_client_id'=>'','dropbox_app_key'=>'']as$key=>$default)$out[$key]=$store->exists($this->issuer,$key)?$store->get($this->issuer,$key):$default;
  foreach(['only_own_files_contacts','allow_contact_to_delete_files','enable_google_picker']as$key)if(!in_array($out[$key],['0','1'],true))throw new DomainException('native_portal_files_policy_invalid');NativeCompanyUploadPreferences::prepare(['allowed_files'=>$out['allowed_files']]);
  foreach(['google_api_key','google_client_id','dropbox_app_key']as$key)if(!preg_match('/^[A-Za-z0-9_.-]{0,255}$/D',$out[$key]))throw new DomainException('native_portal_files_policy_invalid');$this->identity();return$out;
 }
 private function source(int$id,bool$content=false,bool$inactive=false):array{
  NativeCompanyCustomerFileSchema::assertValid($this->db,$this->p);$who=$this->identity();
  $r=$this->rows('SELECT f.*,o.issuer_id,o.client_id,o.revision,o.active owned_active,o.content_hash,o.byte_size,o.mime'.($content?',o.content':'').' FROM '.$this->p.'files f LEFT JOIN '.$this->p.'company_customer_file_contents o ON o.file_id=f.id WHERE f.id=? AND f.rel_type=? AND f.rel_id=?',[$id,'customer',$who['client_id']])[0]??null;
  if(!$r)throw new DomainException('native_portal_files_denied');
  if($r['issuer_id']!==null){if((int)$r['issuer_id']!==$this->issuer||(int)$r['client_id']!==$who['client_id']||(!$inactive&&(int)$r['owned_active']!==1))throw new DomainException('native_portal_files_denied');$r['source_kind']='blob';}
  else{$primary=(new IssuerResourceAccess($this->db,$this->p))->primaryId();$owner=$this->rows('SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$who['client_id']]);if($primary!==$this->issuer||$owner!==[])throw new DomainException('native_portal_files_denied');$r['source_kind']='legacy_fs';}
  if((int)$r['visible_to_customer']!==1||!$this->rows('SELECT file_id FROM '.$this->p.'shared_customer_files WHERE file_id=? AND contact_id=?',[$id,$who['contact_id']]))throw new DomainException('native_portal_files_denied');
  $this->identity();return$r;
 }
 private function revision(array$row):string{unset($row['content']);return hash('sha256',json_encode([$this->issuer,$row],JSON_THROW_ON_ERROR));}
 private function dto(array$r,array$who,array$policy):array{return['id'=>(int)$r['id'],'rel_id'=>(int)$r['rel_id'],'file_name'=>$r['file_name'],'filetype'=>$r['filetype'],'external'=>$r['external']??'','external_link'=>$r['external_link']??'','thumbnail_link'=>'','attachment_key'=>$r['attachment_key'],'dateadded'=>$r['dateadded'],'contact_id'=>(int)$r['contact_id'],'revision'=>$this->revision($r),'can_delete'=>$policy['allow_contact_to_delete_files']==='1'&&(int)$r['contact_id']===$who['contact_id']];}
 public function page():array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),function(){ $who=$this->identity();$policy=$this->policy();$ids=$this->rows('SELECT f.id FROM '.$this->p.'files f JOIN '.$this->p.'shared_customer_files s ON s.file_id=f.id AND s.contact_id=? WHERE f.rel_type=? AND f.rel_id=? AND f.visible_to_customer=1 ORDER BY f.dateadded DESC,f.id DESC',[$who['contact_id'],'customer',$who['client_id']]);$out=[];foreach($ids as$r){try{$out[]=$this->dto($this->source((int)$r['id']),$who,$policy);}catch(DomainException$e){if($e->getMessage()!=='native_portal_files_denied')throw$e;}}if($this->policy()!==$policy||$this->identity()!==$who)throw new DomainException('native_portal_files_denied');return['files'=>$out,'policy'=>$policy,'contact_id'=>$who['contact_id']];});}
 public function read(string$key):array{
  if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new DomainException('native_portal_files_denied');
  return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($key){$who=$this->identity();$ids=$this->rows('SELECT id FROM '.$this->p.'files WHERE attachment_key=? AND rel_type=? AND rel_id=?',[$key,'customer',$who['client_id']]);if(count($ids)!==1)throw new DomainException('native_portal_files_denied');$r=$this->source((int)$ids[0]['id'],true);if(($r['external']??'')!=='')throw new DomainException('native_portal_file_external');
   if($r['source_kind']==='legacy_fs')$bytes=NativeCompanyLegacyCustomerContent::read($who['client_id'],$r['file_name']);else{if(!is_string($r['content'])||strlen($r['content'])!==(int)$r['byte_size']||!hash_equals($r['content_hash'],hash('sha256',$r['content'])))throw new RuntimeException('native_customer_file_corrupt');$bytes=['content'=>$r['content'],'mime'=>$r['mime']];}
   $after=$this->source((int)$r['id'],true);if($this->revision($after)!==$this->revision($r))throw new DomainException('native_portal_files_stale');return$bytes+['filename'=>$r['file_name'],'revision'=>$this->revision($r)];
  });
 }
 public function upload(string$key,string$name,string$bytes):int{
  $policy=$this->policy();[$name,$mime,$bytes]=NativeCompanyFileContent::normalize($name,$bytes,$policy['allowed_files'],NativeCompanyFileContent::maximum());return$this->create($key,$name,$mime,$bytes,'','',$policy);
 }
 public function external(string$key,string$name,string$provider,string$url):int{
  $policy=$this->policy();$parts=parse_url($url);$hosts=$provider==='gdrive'?['drive.google.com','docs.google.com']:($provider==='dropbox'?['www.dropbox.com','dropbox.com','dl.dropboxusercontent.com']:[]);
  if(!$hosts||!$parts||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||!in_array(strtolower($parts['host']??''),$hosts,true)||strlen($url)>4096||preg_match('/[\x00-\x20\x7f]/',$url)||$name===''||strlen($name)>191||basename($name)!==$name||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name))throw new DomainException('native_portal_files_invalid');
  if($provider==='gdrive'&&($policy['enable_google_picker']!=='1'||$policy['google_api_key']===''||$policy['google_client_id']==='')||$provider==='dropbox'&&$policy['dropbox_app_key']==='')throw new DomainException('native_portal_files_denied');return$this->create($key,$name,'application/octet-stream',null,$provider,$url,$policy);
 }
 private function create(string$key,string$name,string$mime,?string$bytes,string$provider,string$url,array$policy):int{
  if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_portal_files_invalid');require_once __DIR__.'/NativeCompanyCustomerUploadMail.php';require_once __DIR__.'/NativeCompanyNotifications.php';$notice=new NativeCompanyCustomerUploadMail($this->db,$this->p);$tables=array_values(array_unique(array_merge($this->tables(),$notice->tables(),NativeCompanyNotifications::tables('customer_file'))));
  try{return IssuerTransaction::run($this->db,$this->p,$tables,function()use($key,$name,$mime,$bytes,$provider,$url,$policy,$notice){$who=$this->identity();if($this->policy()!==$policy)throw new DomainException('native_portal_files_policy_changed');NativeCompanyCustomerFileSchema::assertValid($this->db,$this->p);$hash=hash('sha256',$bytes??$url);$request=hash('sha256',json_encode([$who['client_id'],$who['contact_id'],$name,$mime,$hash,$provider,$url],JSON_THROW_ON_ERROR));
   $old=$this->rows('SELECT file_id,request_hash FROM '.$this->p.'company_customer_file_contents WHERE issuer_id=? AND request_key=?',[$this->issuer,$key])[0]??null;if($old){$row=$this->source((int)$old['file_id']);if((int)$row['contact_id']!==$who['contact_id']||!hash_equals($old['request_hash'],$request)||!$this->rows('SELECT file_id FROM '.$this->p.NativeCompanyCustomerUploadMailSchema::OUTBOX.' WHERE issuer_id=? AND file_id=?',[$this->issuer,(int)$old['file_id']]))throw new DomainException('native_portal_file_conflict');return(int)$old['file_id'];}
   $usage=$this->rows('SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$this->p.'company_customer_file_contents WHERE issuer_id=? AND client_id=?',[$this->issuer,$who['client_id']])[0];if((int)$usage['n']>=100||(int)$usage['bytes']+strlen($bytes??'')>104857600)throw new DomainException('native_customer_file_limit');
   $fields=['rel_id'=>$who['client_id'],'rel_type'=>'customer','file_name'=>$name,'filetype'=>strlen($mime)<=40?$mime:'application/octet-stream','visible_to_customer'=>1,'external'=>$provider,'external_link'=>$url,'thumbnail_link'=>'','staffid'=>0,'contact_id'=>$who['contact_id'],'task_comment_id'=>0,'dateadded'=>gmdate('Y-m-d H:i:s')];$this->allocation=$fields;$id=NativeCompanyFileRows::insertContactInOwned($this->db,$this->p,$fields,$this);$this->allocation=null;$nonce=bin2hex(random_bytes(32));
   IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_customer_file_contents(file_id,issuer_id,client_id,request_key,request_hash,content_hash,byte_size,mime,content,revision,active,write_nonce) VALUES(?,?,?,?,?,?,?,?,?,1,1,?)',[$id,$this->issuer,$who['client_id'],$key,$request,$hash,strlen($bytes??''),$mime,$bytes,$nonce]);$stored=$this->rows('SELECT * FROM '.$this->p.'company_customer_file_contents WHERE file_id=?',[$id])[0]??[];
   if(($stored['write_nonce']??null)!==$nonce||($stored['request_hash']??null)!==$request||($stored['content_hash']??null)!==$hash||($stored['content']??null)!==$bytes||(int)($stored['byte_size']??-1)!==strlen($bytes??''))throw new RuntimeException('native_portal_file_write_unconfirmed');
   $contacts=$policy['only_own_files_contacts']==='1'?[$who['contact_id']]:array_map('intval',array_column($this->rows('SELECT id FROM '.$this->p.'contacts WHERE userid=? ORDER BY id',[$who['client_id']]),'id'));foreach($contacts as$contact)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'shared_customer_files(file_id,contact_id) VALUES(?,?)',[$id,$contact]);$actual=array_map('intval',array_column($this->rows('SELECT contact_id FROM '.$this->p.'shared_customer_files WHERE file_id=? ORDER BY contact_id',[$id]),'contact_id'));sort($contacts,SORT_NUMERIC);if($actual!==$contacts)throw new RuntimeException('native_portal_file_write_unconfirmed');
   $this->uploaded=$id;$this->assertUploadedInOwned($this->db,$this->p,$this->issuer,$id);$notice->prepareInOwned($this->issuer,$id,$this);(new NativeCompanyNotifications($this->db,$this->p))->recordCustomerFileInOwnedTransaction($this->issuer,$id,$this);$this->audit($id,'native_portal_customer_file_uploaded',$who);if($this->policy()!==$policy||$this->identity()!==$who)throw new DomainException('native_portal_files_denied');return$id;
  });}finally{$this->allocation=null;$this->uploaded=null;}
 }
 public function assertUploadedInOwned($db,string$p,int$issuer,int$file):void{if($db!==$this->db||$p!==$this->p||$issuer!==$this->issuer||$this->uploaded!==$file)throw new DomainException('native_portal_file_allocation_denied');IssuerTransaction::assertOwned($db,$p,$this->tables());$who=$this->identity();$r=$this->source($file);if((int)$r['contact_id']!==$who['contact_id']||(int)$r['staffid']!==0||$r['source_kind']!=='blob')throw new DomainException('native_portal_file_allocation_denied');}
 private function audit(int$file,string$event,array$who):void{
  $meta=json_encode(['actor_kind'=>'contact','contact_id'=>$who['contact_id'],'client_id'=>$who['client_id'],'nonce'=>bin2hex(random_bytes(32))],JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');
  IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,metadata_json,created_at) VALUES(0,?,?,?,?,?)',[$event,$this->issuer,$file,$meta,$now]);$id=(int)$this->db->insert_id();$r=$this->rows('SELECT event,issuer_id,subject_id,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$id])[0]??[];
  if(($r['event']??null)!==$event||(int)($r['issuer_id']??0)!==$this->issuer||(int)($r['subject_id']??0)!==$file||($r['metadata_json']??null)!==$meta)throw new RuntimeException('native_portal_file_audit_unconfirmed');
 }
 /** Native contact deletion revokes visibility; it never removes audit-linked bytes. */
 public function delete(int$id,string$revision):void{
  if($id<1||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('native_portal_files_denied');
  IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($id,$revision){$who=$this->identity();$policy=$this->policy();$r=$this->source($id);if($policy['allow_contact_to_delete_files']!=='1'||(int)$r['contact_id']!==$who['contact_id'])throw new DomainException('native_portal_files_denied');if(!hash_equals($this->revision($r),$revision))throw new DomainException('native_portal_files_stale');
   IssuerStorage::query($this->db,'UPDATE '.$this->p.'files SET visible_to_customer=0 WHERE id=? AND contact_id=? AND visible_to_customer=1',[$id,$who['contact_id']]);$after=$this->rows('SELECT visible_to_customer FROM '.$this->p.'files WHERE id=?',[$id])[0]??[];if((int)($after['visible_to_customer']??1)!==0)throw new RuntimeException('native_portal_file_write_unconfirmed');
   if($r['source_kind']==='blob'){$nonce=bin2hex(random_bytes(32));IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_customer_file_contents SET active=0,revision=revision+1,write_nonce=? WHERE file_id=? AND issuer_id=? AND revision=? AND active=1',[$nonce,$id,$this->issuer,$r['revision']]);$stored=$this->rows('SELECT active,revision,write_nonce FROM '.$this->p.'company_customer_file_contents WHERE file_id=?',[$id])[0]??[];if((int)($stored['active']??1)!==0||(int)($stored['revision']??0)!==(int)$r['revision']+1||($stored['write_nonce']??null)!==$nonce)throw new RuntimeException('native_portal_file_write_unconfirmed');}
   $this->audit($id,'native_portal_customer_file_removed',$who);if($this->identity()!==$who||$this->policy()!==$policy)throw new DomainException('native_portal_files_denied');
  });
 }
 public function assertFileAllocationInOwned($db,string$p,array$fields):void{
  if($db!==$this->db||$p!==$this->p||$this->allocation===null||$fields!==$this->allocation)throw new DomainException('native_portal_file_allocation_denied');IssuerTransaction::assertOwned($db,$p,$this->tables());$who=$this->identity();if(($fields['rel_id']??null)!==$who['client_id']||($fields['contact_id']??null)!==$who['contact_id']||($fields['staffid']??null)!==0)throw new DomainException('native_portal_file_allocation_denied');
 }
}
