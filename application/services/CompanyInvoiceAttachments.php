<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/CompanyAssets.php';
require_once __DIR__.'/CompanyInvoiceAttachmentSchema.php';
require_once __DIR__.'/NativeCompanyFileContent.php';

/** Immutable private blobs; no filesystem path, external URL, legacy upload or public token. */
final class CompanyInvoiceAttachments
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_invoice_attachment_denied');}
    private function authorize(int $issuer,int $actor,string $cap):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'invoices',$cap))throw new DomainException('company_invoice_attachment_denied');
    }
    private function parent(int $issuer,int $invoice):array
    {
        $row=$this->rows('SELECT i.id,i.status FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_registry r ON r.id=i.issuer_id AND r.active=1 JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
        if(!$row)throw new DomainException('company_invoice_attachment_denied');return $row;
    }
    private function nativeParent(int $issuer,int $actor,int $invoice):array
    {
        require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';$identity=(new NativeCompanyInvoiceIdentity($this->db,$this->p))->resolve($issuer,$actor,$invoice);
        if($identity['lineage']==='named_company')return$this->parent($issuer,$invoice);
        $row=$this->rows('SELECT id,status,issuer_id FROM '.$this->p.'invoices WHERE id=? FOR UPDATE',[$invoice])[0]??null;if(!$row||$row['issuer_id']!==null||(new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$issuer)throw new DomainException('company_invoice_attachment_denied');return$row;
    }
    public static function tableNames():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','invoices','issuers_invoice_documents','company_invoice_attachments','issuers_audit','issuers_clients','clients','contacts','contact_permissions'];}
    private function transaction(callable $work)
    {
        CompanyInvoiceAttachmentSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,self::tableNames(),$work);
    }
    private function audit(int $issuer,int $actor,int $invoice,string $key,string $event,array $extra=[]):void
    {
        $metadata=json_encode(['file_key'=>$key,'write_nonce'=>bin2hex(random_bytes(16))]+$extra,JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$invoice,date('Y-m-d H:i:s'),$metadata]);$id=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[$id,$issuer,$invoice,$event,$metadata]))throw new RuntimeException('issuer_invoice_attachment_unconfirmed');
    }
    private static function normalize(string $name,string $bytes):array
    {
        if($name===''||strlen($name)>191||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name)||trim($name)!==$name||str_starts_with($name,'.')||strlen($bytes)<8||strlen($bytes)>2097152)throw new DomainException('company_invoice_attachment_invalid');
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if($extension==='pdf'&&$mime==='application/pdf'&&preg_match('/^%PDF-[12]\.[0-9][\r\n]/',$bytes))return [$name,'application/pdf',$bytes];
        if(!in_array($extension,['png','jpg','jpeg'],true)||!in_array($mime,['image/png','image/jpeg'],true))throw new DomainException('company_invoice_attachment_invalid');
        try{$inline=CompanyAssets::normalize($bytes);}catch(DomainException $invalid){throw new DomainException('company_invoice_attachment_invalid');}
        $name=pathinfo($name,PATHINFO_FILENAME).'.png';if(strlen($name)>191)throw new DomainException('company_invoice_attachment_invalid');return [$name,'image/png',base64_decode(substr($inline,strlen('data:image/png;base64,')),true)];
    }
    public function upload($issuer,$actor,$invoice,string $key,string $name,string $bytes,bool $visible=false):string
    {
        return $this->transaction(fn():string=>$this->uploadInOwned($issuer,$actor,$invoice,$key,$name,$bytes,$visible));
    }
    public function uploadInOwned($issuer,$actor,$invoice,string $key,string $name,string $bytes,bool $visible=false):string
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tableNames());
        CompanyInvoiceAttachmentSchema::assertValid($this->db,$this->p);
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);self::key($key);$this->authorize($issuer,$actor,'edit');
        [$name,$mime,$bytes]=self::normalize($name,$bytes);return $this->persistInOwned($issuer,$actor,$invoice,$key,$name,$mime,$bytes,$visible);
    }
    public function uploadNativeInOwned(int $issuer,int $actor,int $invoice,string $key,string $name,string $bytes,string $allowed,int $maximum):string
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tableNames());self::key($key);$this->authorize($issuer,$actor,'edit');
        [$name,$mime,$bytes]=NativeCompanyFileContent::normalize($name,$bytes,$allowed,$maximum);
        if(strlen($bytes)>16777215)CompanyInvoiceAttachmentSchema::assertNativeCapacity($this->db,$this->p);
        return $this->persistInOwned($issuer,$actor,$invoice,$key,$name,$mime,$bytes,false,true);
    }
    private function persistInOwned(int $issuer,int $actor,int $invoice,string $key,string $name,string $mime,string $bytes,bool $visible,bool $nativeLineage=false):string
    {
        $hash=hash('sha256',$bytes);
        return (function()use($issuer,$actor,$invoice,$key,$name,$mime,$bytes,$hash,$visible,$nativeLineage):string{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'edit');$parent=$nativeLineage?$this->nativeParent($issuer,$actor,$invoice):$this->parent($issuer,$invoice);
            $origin=$this->rows('SELECT issuer_id,actor_id,invoice_id FROM '.$this->p.'company_invoice_attachments WHERE file_key=? FOR UPDATE',[$key])[0]??null;
            if($origin){
                foreach(['issuer_id'=>$issuer,'actor_id'=>$actor,'invoice_id'=>$invoice] as $field=>$value)if((int)$origin[$field]!==$value)throw new DomainException('company_invoice_attachment_conflict');
                $old=$this->rows('SELECT filename,mime,content_hash,portal_visible FROM '.$this->p.'company_invoice_attachments WHERE file_key=? AND issuer_id=? AND invoice_id=?',[$key,$issuer,$invoice])[0]??null;if(!$old)throw new RuntimeException('issuer_invoice_attachment_unconfirmed');
                if((int)$old['portal_visible']!==(int)$visible)throw new DomainException('company_invoice_attachment_conflict');foreach(['filename'=>$name,'mime'=>$mime,'content_hash'=>$hash] as $field=>$value)if(!hash_equals($old[$field],$value))throw new DomainException('company_invoice_attachment_conflict');return $key;
            }
            if(!in_array((int)$parent['status'],[1,2,3,4],true))throw new DomainException('company_invoice_attachment_denied');
            $usage=$this->rows('SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$this->p.'company_invoice_attachments WHERE issuer_id=? AND invoice_id=?',[$issuer,$invoice])[0];
            if((int)$usage['n']>=20||(int)$usage['bytes']+strlen($bytes)>20971520)throw new DomainException('company_invoice_attachment_limit');
            $fields=['file_key'=>$key,'issuer_id'=>$issuer,'actor_id'=>$actor,'invoice_id'=>$invoice,'filename'=>$name,'mime'=>$mime,'content_hash'=>$hash,'byte_size'=>strlen($bytes),'content'=>$bytes,'portal_visible'=>(int)$visible,'active'=>1,'created_at'=>date('Y-m-d H:i:s')];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_invoice_attachments (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
            $row=$this->rows('SELECT byte_size,content FROM '.$this->p.'company_invoice_attachments WHERE file_key=? AND issuer_id=? AND invoice_id=?',[$key,$issuer,$invoice])[0]??[];
            if((int)($row['byte_size']??0)!==strlen($bytes)||!hash_equals($hash,hash('sha256',$row['content']??'')))throw new RuntimeException('issuer_invoice_attachment_unconfirmed');
            $this->audit($issuer,$actor,$invoice,$key,'company_invoice_attachment_uploaded',['sha256'=>$hash,'portal_visible'=>$visible]);$this->authorize($issuer,$actor,'edit');return $key;
        })();
    }
    public function page($issuer,$actor,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$this->authorize($issuer,$actor,'view');
        return $this->transaction(function()use($issuer,$actor,$invoice):array{$this->authorize($issuer,$actor,'view');$this->parent($issuer,$invoice);return $this->rows('SELECT file_key,filename,mime,byte_size,content_hash,portal_visible,created_at FROM '.$this->p.'company_invoice_attachments WHERE issuer_id=? AND invoice_id=? AND active=1 ORDER BY created_at,file_key LIMIT 20',[$issuer,$invoice]);});
    }
    private function file(int $issuer,int $invoice,string $key,bool $portal):array
    {
        $row=$this->rows('SELECT filename,mime,byte_size,content_hash,content,portal_visible FROM '.$this->p.'company_invoice_attachments WHERE file_key=? AND issuer_id=? AND invoice_id=? AND active=1'.($portal?' AND portal_visible=1':'').' FOR UPDATE',[$key,$issuer,$invoice])[0]??null;
        if(!$row)throw new DomainException('company_invoice_attachment_denied');
        if(!in_array($row['mime'],['application/pdf','image/png','text/plain','application/octet-stream'],true)||(int)$row['byte_size']<1||(int)$row['byte_size']>20971520||strlen($row['content'])!==(int)$row['byte_size']||!hash_equals($row['content_hash'],hash('sha256',$row['content'])))throw new RuntimeException('issuer_invoice_attachment_corrupt');return $row;
    }
    public function download($issuer,$actor,$invoice,string $key):array
    {
        return $this->transaction(fn():array=>$this->downloadInOwned($issuer,$actor,$invoice,$key));
    }
    public function downloadInOwned($issuer,$actor,$invoice,string $key,bool $nativeLineage=false):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tableNames());
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);self::key($key);$this->authorize($issuer,$actor,'view');
        return (function()use($issuer,$actor,$invoice,$key,$nativeLineage):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$nativeLineage?$this->nativeParent($issuer,$actor,$invoice):$this->parent($issuer,$invoice);$row=$this->file($issuer,$invoice,$key,false);$this->audit($issuer,$actor,$invoice,$key,'company_invoice_attachment_downloaded',['sha256'=>$row['content_hash']]);$this->authorize($issuer,$actor,'view');return $row;})();
    }
    public function hide($issuer,$actor,$invoice,string $key,string $hash):void
    {
        $this->transaction(fn()=>$this->hideInOwned($issuer,$actor,$invoice,$key,$hash));
    }
    public function hideInOwned($issuer,$actor,$invoice,string $key,string $hash,bool $nativeLineage=false):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tableNames());
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);self::key($key);$this->authorize($issuer,$actor,'edit');
        (function()use($issuer,$actor,$invoice,$key,$hash,$nativeLineage):void{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'edit');$nativeLineage?$this->nativeParent($issuer,$actor,$invoice):$this->parent($issuer,$invoice);$prior=$this->rows('SELECT active,content_hash FROM '.$this->p.'company_invoice_attachments WHERE file_key=? AND issuer_id=? AND invoice_id=? FOR UPDATE',[$key,$issuer,$invoice])[0]??null;if($prior&&(int)$prior['active']===0&&hash_equals($prior['content_hash'],$hash))return;$row=$this->file($issuer,$invoice,$key,false);if(!hash_equals($row['content_hash'],$hash))throw new DomainException('company_invoice_attachment_conflict');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_invoice_attachments SET active=0 WHERE file_key=? AND issuer_id=? AND invoice_id=?',[$key,$issuer,$invoice]);if($this->rows('SELECT file_key FROM '.$this->p.'company_invoice_attachments WHERE file_key=? AND active=1',[$key]))throw new RuntimeException('issuer_invoice_attachment_unconfirmed');$this->audit($issuer,$actor,$invoice,$key,'company_invoice_attachment_hidden');$this->authorize($issuer,$actor,'edit');})();
    }
    public function inspectInOwned(int $issuer,int $actor,int $invoice,string $key,bool $nativeLineage=false):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tableNames());self::key($key);$this->authorize($issuer,$actor,'view');$nativeLineage?$this->nativeParent($issuer,$actor,$invoice):$this->parent($issuer,$invoice);return $this->file($issuer,$invoice,$key,false);
    }
    public function visibilityInOwned(int $issuer,int $actor,int $invoice,string $key,string $hash,bool $visible,bool $nativeLineage=false):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tableNames());self::key($key);
        (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'edit');$nativeLineage?$this->nativeParent($issuer,$actor,$invoice):$this->parent($issuer,$invoice);$row=$this->file($issuer,$invoice,$key,false);
        if(!hash_equals($row['content_hash'],$hash))throw new DomainException('company_invoice_attachment_conflict');
        if((bool)$row['portal_visible']===$visible)return;
        IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_invoice_attachments SET portal_visible=? WHERE file_key=? AND issuer_id=? AND invoice_id=? AND portal_visible=?',[(int)$visible,$key,$issuer,$invoice,(int)$row['portal_visible']]);
        $after=$this->file($issuer,$invoice,$key,false);if((bool)$after['portal_visible']!==$visible)throw new RuntimeException('issuer_invoice_attachment_unconfirmed');
        $this->audit($issuer,$actor,$invoice,$key,'company_invoice_attachment_visibility',['visible'=>$visible]);$this->authorize($issuer,$actor,'edit');
    }
    public function portalDownload(CompanyPortalInvoices $portal,array $session,$issuer,$invoice,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$invoice=IssuerPolicy::id($invoice);self::key($key);
        return $this->transaction(function()use($portal,$session,$issuer,$invoice,$key):array{
            $who=$portal->attachmentIdentityInOwnedTransaction($session,$issuer,$invoice);$row=$this->file($issuer,$invoice,$key,true);
            $this->audit($issuer,0,$invoice,$key,'company_portal_attachment_downloaded',['contact_id'=>$who['contact_id'],'sha256'=>$row['content_hash']]);$portal->attachmentIdentityInOwnedTransaction($session,$issuer,$invoice);return $row;
        });
    }
}
