<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyCustomerAccess.php';
require_once __DIR__.'/NativeCompanyCustomerFileSchema.php';
require_once __DIR__.'/NativeCompanyFileContent.php';
require_once __DIR__.'/NativeCompanyUploadPreferences.php';
require_once __DIR__.'/NativeCompanyFileRows.php';
/** Original customer files/IDs, private content and exact customer/issuer authority. */
final class NativeCompanyCustomerFiles
{
    public const MAX_BYTES=20971520;
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','issuers_clients','staff','clients','contacts','files','shared_customer_files','company_customer_file_contents','issuers_audit'];}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int$i,int$a,int$c,string$cap):void{if(!(new CompanyCustomerAccess($this->db,$this->p))->can($i,$a,$c,$cap))throw new DomainException('company_client_access_denied');}
    public static function normalize(string$name,string$bytes,string$allowed,int$maximum):array
    {return NativeCompanyFileContent::normalize($name,$bytes,$allowed,$maximum);}
    private static function external(string$provider,string$url):string
    {
        $parts=parse_url($url);if(!in_array($provider,['gdrive','dropbox'],true)||!$parts||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||strlen($url)>4096||preg_match('/[\x00-\x20\x7f]/',$url))throw new DomainException('native_customer_file_invalid');$host=strtolower($parts['host']??'');$allowed=$provider==='gdrive'?['drive.google.com','docs.google.com']:['www.dropbox.com','dropbox.com','dl.dropboxusercontent.com'];if(!in_array($host,$allowed,true))throw new DomainException('native_customer_file_invalid');return$url;
    }
    private function file(int$i,int$a,int$id,string$cap='view',bool$active=true,bool$withContent=false):array
    {
        NativeCompanyCustomerFileSchema::assertValid($this->db,$this->p);$r=$this->rows('SELECT f.*,o.issuer_id,o.client_id,o.request_key,o.request_hash,o.content_hash,o.byte_size,o.mime,'.($withContent?'o.content,':'').'o.revision,o.active owned_active,o.write_nonce FROM '.$this->p.'files f JOIN '.$this->p.'company_customer_file_contents o ON o.file_id=f.id WHERE f.id=? AND o.issuer_id=?',[$id,$i])[0]??null;
        if(!$r||$r['rel_type']!=='customer'||(int)$r['rel_id']!==(int)$r['client_id']||($active&&(int)$r['owned_active']!==1))throw new DomainException('company_client_access_denied');$this->authorize($i,$a,(int)$r['client_id'],$cap);return$r;
    }
    public function upload(int$i,int$a,int$c,string$key,string$name,string$bytes):int{$this->authorize($i,$a,$c,'edit');$config=NativeCompanyUploadPreferences::read($this->db,$this->p,$i,$a);[$name,$mime,$bytes]=self::normalize($name,$bytes,$config['allowed_files'],NativeCompanyFileContent::maximum());return$this->create($i,$a,$c,$key,$name,$mime,$bytes,'','',$config);}
    public function addExternal(int$i,int$a,int$c,string$key,string$name,string$provider,string$url):int
    {
        if($name===''||strlen($name)>191||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name))throw new DomainException('native_customer_file_invalid');return$this->create($i,$a,$c,$key,$name,'application/octet-stream',null,$provider,self::external($provider,$url));
    }
    private function create(int$i,int$a,int$c,string$key,string$name,string$mime,?string$bytes,string$provider,string$url,?array$config=null):int
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_customer_file_invalid');$this->authorize($i,$a,$c,'edit');$hash=hash('sha256',$bytes??$url);$request=hash('sha256',json_encode([$c,$a,$name,$mime,$hash,$provider,$url],JSON_THROW_ON_ERROR));
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$c,$key,$name,$mime,$bytes,$provider,$url,$hash,$request,$config):int{
            $this->authorize($i,$a,$c,'edit');if($config!==null&&NativeCompanyUploadPreferences::read($this->db,$this->p,$i,$a)!==$config)throw new DomainException('native_file_configuration_changed');NativeCompanyCustomerFileSchema::assertValid($this->db,$this->p);$old=$this->rows('SELECT file_id,request_hash FROM '.$this->p.'company_customer_file_contents WHERE issuer_id=? AND request_key=?',[$i,$key])[0]??null;if($old){$this->file($i,$a,(int)$old['file_id'],'edit');if(!hash_equals($old['request_hash'],$request))throw new DomainException('native_customer_file_conflict');return(int)$old['file_id'];}
            $usage=$this->rows('SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$this->p.'company_customer_file_contents WHERE issuer_id=? AND client_id=?',[$i,$c])[0];if((int)$usage['n']>=100||(int)$usage['bytes']+strlen($bytes??'')>104857600)throw new DomainException('native_customer_file_limit');
            $fields=['rel_id'=>$c,'rel_type'=>'customer','file_name'=>$name,'filetype'=>strlen($mime)<=40?$mime:'application/octet-stream','visible_to_customer'=>0,'external'=>$provider,'external_link'=>$url,'thumbnail_link'=>'','staffid'=>$a,'contact_id'=>0,'task_comment_id'=>0,'dateadded'=>gmdate('Y-m-d H:i:s')];
            $id=NativeCompanyFileRows::insertInOwned($this->db,$this->p,$fields);
            $nonce=bin2hex(random_bytes(32));IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_customer_file_contents(file_id,issuer_id,client_id,request_key,request_hash,content_hash,byte_size,mime,content,revision,active,write_nonce) VALUES(?,?,?,?,?,?,?,?,?,1,1,?)',[$id,$i,$c,$key,$request,$hash,strlen($bytes??''),$mime,$bytes,$nonce]);$stored=$this->file($i,$a,$id,'edit',true,true);if($stored['write_nonce']!==$nonce||$stored['content_hash']!==$hash||($bytes!==null&&!hash_equals($hash,hash('sha256',(string)$stored['content']))))throw new RuntimeException('issuer_file_write_unconfirmed');NativeCompanyCustomerAudit::record($this->db,$this->p,$i,$a,$id,'native_customer_file_added');$this->authorize($i,$a,$c,'edit');return$id;
        });
    }
    private function shares(int$id,int$c):array{return$this->rows('SELECT s.contact_id,c.firstname,c.lastname FROM '.$this->p.'shared_customer_files s JOIN '.$this->p.'contacts c ON c.id=s.contact_id WHERE s.file_id=? AND c.userid=? ORDER BY s.contact_id',[$id,$c]);}
    private function dto(array$r):array{$shares=$this->shares((int)$r['id'],(int)$r['client_id']);unset($r['content'],$r['request_hash'],$r['write_nonce']);$r['shared_with']=$shares;$r['total_shares']=count($shares);$r['download_url']='download/file/client/'.$r['attachment_key'];$r['upload_path']='';if($r['external']==='')unset($r['external']);return$r;}
    public function page(int$i,int$a,int$c):array
    {
        $this->authorize($i,$a,$c,'view');$ids=$this->rows('SELECT file_id FROM '.$this->p.'company_customer_file_contents WHERE issuer_id=? AND client_id=? AND active=1 ORDER BY file_id',[$i,$c]);$out=[];foreach($ids as$r)$out[]=$this->dto($this->file($i,$a,(int)$r['file_id']));$this->authorize($i,$a,$c,'view');return$out;
    }
    public function readKey(int$i,int$a,string$key):array
    {if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new DomainException('company_client_access_denied');$row=$this->rows('SELECT f.id FROM '.$this->p.'files f JOIN '.$this->p.'company_customer_file_contents o ON o.file_id=f.id WHERE f.attachment_key=? AND o.issuer_id=?',[$key,$i]);if(count($row)!==1)throw new DomainException('company_client_access_denied');return$this->read($i,$a,(int)$row[0]['id']);}
    public function read(int$i,int$a,int$id):array
    {
        $r=$this->file($i,$a,$id,'view',true,true);if($r['external']!=='')throw new DomainException('native_customer_file_external');if(!hash_equals($r['content_hash'],hash('sha256',(string)$r['content']))||(int)$r['byte_size']!==strlen((string)$r['content']))throw new DomainException('native_customer_file_corrupt');$this->authorize($i,$a,(int)$r['client_id'],'view');return['filename'=>$r['file_name'],'mime'=>$r['mime'],'content'=>$r['content'],'revision'=>(int)$r['revision']];
    }
    public function portalRead(CompanyPortal$portal,array$binding,int$i,string$key):array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new DomainException('company_client_access_denied');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($portal,$binding,$i,$key):array{
            $who=$portal->invoiceIdentity($binding,$i,true);if(!$who)throw new DomainException('company_client_access_denied');
            $rows=$this->rows('SELECT f.*,o.content,o.content_hash,o.mime,o.byte_size,o.client_id,o.revision FROM '.$this->p.'files f JOIN '.$this->p.'company_customer_file_contents o ON o.file_id=f.id JOIN '.$this->p.'shared_customer_files s ON s.file_id=f.id AND s.contact_id=? JOIN '.$this->p.'issuers_clients owner ON owner.client_id=o.client_id AND owner.owner_issuer_id=o.issuer_id WHERE f.attachment_key=? AND o.issuer_id=? AND o.client_id=? AND f.rel_type=? AND f.rel_id=o.client_id AND f.visible_to_customer=1 AND o.active=1',[$who['contact_id'],$key,$i,$who['client_id'],'customer']);
            if(count($rows)!==1)throw new DomainException('company_client_access_denied');$r=$rows[0];if(($r['external']??'')!==''||!hash_equals($r['content_hash'],hash('sha256',(string)$r['content']))||(int)$r['byte_size']!==strlen((string)$r['content']))throw new DomainException('native_customer_file_corrupt');if(!$portal->invoiceIdentity($binding,$i,true))throw new DomainException('company_client_access_denied');return['filename'=>$r['file_name'],'mime'=>$r['mime'],'content'=>$r['content'],'revision'=>(int)$r['revision']];
        });
    }
    public function describe(int$i,int$a,int$id,string$cap='view'):array{return$this->dto($this->file($i,$a,$id,$cap));}
    public function readForDispatchInOwned(int$i,int$a,int$id,int$revision):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$r=$this->file($i,$a,$id,'edit');if((int)$r['revision']!==$revision)throw new DomainException('company_client_stale');$file=$this->read($i,$a,$id);$this->authorize($i,$a,(int)$r['client_id'],'edit');return$file+['file_id'=>$id,'client_id'=>(int)$r['client_id'],'issuer_id'=>$i,'content_hash'=>$r['content_hash']];
    }
    public function share(int$i,int$a,int$id,int$revision,bool$visible,array$contacts,?int$expectedClient=null):int
    {
        if(count($contacts)>1000)throw new DomainException('native_customer_file_invalid');$contacts=array_values(array_unique(array_map([IssuerPolicy::class,'id'],$contacts)));sort($contacts,SORT_NUMERIC);
        return$this->mutate($i,$a,$id,$revision,function($r)use($visible,$contacts,$expectedClient):void{if($expectedClient!==null&&(int)$r['client_id']!==$expectedClient)throw new DomainException('company_client_access_denied');foreach($contacts as$contact)if(!$this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=? AND userid=? AND active=1',[$contact,$r['client_id']]))throw new DomainException('company_client_access_denied');IssuerStorage::query($this->db,'UPDATE '.$this->p.'files SET visible_to_customer=? WHERE id=?',[(int)$visible,$r['id']]);IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'shared_customer_files WHERE file_id=?',[$r['id']]);foreach($contacts as$contact)IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'shared_customer_files(file_id,contact_id) VALUES(?,?)',[$r['id'],$contact]);$actual=array_map('intval',array_column($this->shares((int)$r['id'],(int)$r['client_id']),'contact_id'));foreach($contacts as$contact)if(!$this->rows('SELECT id FROM '.$this->p.'contacts WHERE id=? AND userid=? AND active=1',[$contact,$r['client_id']]))throw new DomainException('company_client_access_denied');if($actual!==$contacts||(int)$this->rows('SELECT visible_to_customer FROM '.$this->p.'files WHERE id=?',[$r['id']])[0]['visible_to_customer']!==(int)$visible)throw new RuntimeException('issuer_file_write_unconfirmed');},'native_customer_file_shared');
    }
    public function delete(int$i,int$a,int$c,int$id,int$revision):void{$this->authorize($i,$a,$c,'delete_contact');$r=$this->file($i,$a,$id,'delete_contact');if((int)$r['client_id']!==$c)throw new DomainException('company_client_access_denied');$this->mutate($i,$a,$id,$revision,fn($r)=>IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_customer_file_contents SET active=0 WHERE file_id=?',[$id]),'native_customer_file_removed','delete_contact');}
    private function mutate(int$i,int$a,int$id,int$revision,callable$work,string$event,string$cap='edit'):int
    {
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$id,$revision,$work,$event,$cap):int{$r=$this->file($i,$a,$id,$cap);if((int)$r['revision']!==$revision)throw new DomainException('company_client_stale');$work($r);$nonce=bin2hex(random_bytes(32));IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_customer_file_contents SET revision=revision+1,write_nonce=? WHERE file_id=? AND revision=?',[$nonce,$id,$revision]);$after=$this->file($i,$a,$id,$cap,false);if((int)$after['revision']!==$revision+1||$after['write_nonce']!==$nonce||($event==='native_customer_file_removed'&&(int)$after['owned_active']!==0))throw new RuntimeException('issuer_file_write_unconfirmed');NativeCompanyCustomerAudit::record($this->db,$this->p,$i,$a,$id,$event);$this->authorize($i,$a,(int)$r['client_id'],$cap);return$revision+1;});
    }
}
