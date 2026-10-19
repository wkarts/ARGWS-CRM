<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/CompanyAssets.php';
require_once __DIR__.'/CompanySalesExtrasSchema.php';
require_once __DIR__.'/CompanyPortal.php';

/** Immutable private blobs; no filesystem path, external URL, legacy upload or public token. */
final class CompanySalesFiles
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_sales_file_denied');}
    private function authorize(int $issuer,int $actor,int $document,string $cap):void
    {
        $ref=$this->rows('SELECT kind FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND id=?',[$issuer,$document])[0]??null;$acl=new IssuerResourceAccess($this->db,$this->p);if(!$ref||!in_array($ref['kind'],['estimates','proposals'],true)||!$acl->can($issuer,$actor,$ref['kind'],'view')||!$acl->can($issuer,$actor,$ref['kind'],$cap))throw new DomainException('company_sales_file_denied');
    }
    private function parent(int $issuer,int $document):array
    {
        $row=$this->rows('SELECT d.id,d.state,d.kind,d.client_id FROM '.$this->p.'company_sales_documents d JOIN '.$this->p.'issuers_registry r ON r.id=d.issuer_id AND r.active=1 WHERE d.id=? AND d.issuer_id=? FOR UPDATE',[$document,$issuer])[0]??null;if(!$row)throw new DomainException('company_sales_file_denied');return $row;
    }
    private function transaction(callable $work)
    {
        CompanySalesExtrasSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','company_sales_documents','company_sales_files','issuers_audit','issuers_clients','clients','contacts','contact_permissions'],$work);
    }
    private function audit(int $issuer,int $actor,int $document,string $key,string $event,array $extra=[]):void
    {
        $metadata=json_encode(['file_key'=>$key]+$extra,JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$document,date('Y-m-d H:i:s'),$metadata]);$id=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[$id,$issuer,$document,$event,$metadata]))throw new RuntimeException('issuer_sales_file_unconfirmed');
    }
    private static function normalize(string $name,string $bytes):array
    {
        if($name===''||strlen($name)>191||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name)||trim($name)!==$name||str_starts_with($name,'.')||strlen($bytes)<8||strlen($bytes)>2097152)throw new DomainException('company_sales_file_invalid');
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if($extension==='pdf'&&$mime==='application/pdf'&&preg_match('/^%PDF-[12]\.[0-9][\r\n]/',$bytes))return [$name,'application/pdf',$bytes];
        if(!in_array($extension,['png','jpg','jpeg'],true)||!in_array($mime,['image/png','image/jpeg'],true))throw new DomainException('company_sales_file_invalid');
        try{$inline=CompanyAssets::normalize($bytes);}catch(DomainException $invalid){throw new DomainException('company_sales_file_invalid');}
        $name=pathinfo($name,PATHINFO_FILENAME).'.png';if(strlen($name)>191)throw new DomainException('company_sales_file_invalid');return [$name,'image/png',base64_decode(substr($inline,strlen('data:image/png;base64,')),true)];
    }
    public function upload($issuer,$actor,$document,string $key,string $name,string $bytes,bool $visible=false):string
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$document=IssuerPolicy::id($document);self::key($key);$this->authorize($issuer,$actor,$document,'edit');
        [$name,$mime,$bytes]=self::normalize($name,$bytes);$hash=hash('sha256',$bytes);
        return $this->transaction(function()use($issuer,$actor,$document,$key,$name,$mime,$bytes,$hash,$visible):string{
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,$document,'edit');$parent=$this->parent($issuer,$document);
            $origin=$this->rows('SELECT issuer_id,actor_id,document_id FROM '.$this->p.'company_sales_files WHERE file_key=? FOR UPDATE',[$key])[0]??null;
            if($origin){
                foreach(['issuer_id'=>$issuer,'actor_id'=>$actor,'document_id'=>$document] as $field=>$value)if((int)$origin[$field]!==$value)throw new DomainException('company_sales_file_conflict');
                $old=$this->rows('SELECT filename,mime,content_hash,portal_visible FROM '.$this->p.'company_sales_files WHERE file_key=? AND issuer_id=? AND document_id=?',[$key,$issuer,$document])[0]??null;if(!$old)throw new RuntimeException('issuer_sales_file_unconfirmed');
                if((int)$old['portal_visible']!==(int)$visible)throw new DomainException('company_sales_file_conflict');foreach(['filename'=>$name,'mime'=>$mime,'content_hash'=>$hash] as $field=>$value)if(!hash_equals($old[$field],$value))throw new DomainException('company_sales_file_conflict');return $key;
            }
            if(!in_array($parent['state'],['open','accepted','converted'],true))throw new DomainException('company_sales_file_denied');
            $usage=$this->rows('SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$this->p.'company_sales_files WHERE issuer_id=? AND document_id=?',[$issuer,$document])[0];
            if((int)$usage['n']>=20||(int)$usage['bytes']+strlen($bytes)>20971520)throw new DomainException('company_sales_file_limit');
            $fields=['file_key'=>$key,'issuer_id'=>$issuer,'actor_id'=>$actor,'document_id'=>$document,'filename'=>$name,'mime'=>$mime,'content_hash'=>$hash,'byte_size'=>strlen($bytes),'content'=>$bytes,'portal_visible'=>(int)$visible,'active'=>1,'created_at'=>date('Y-m-d H:i:s')];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_sales_files (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));
            $row=$this->rows('SELECT * FROM '.$this->p.'company_sales_files WHERE file_key=? AND issuer_id=? AND document_id=?',[$key,$issuer,$document])[0]??[];
            if((int)($row['byte_size']??0)!==strlen($bytes)||!hash_equals($hash,hash('sha256',$row['content']??'')))throw new RuntimeException('issuer_sales_file_unconfirmed');
            foreach($fields as $field=>$value)if((string)($row[$field]??'')!==(string)$value)throw new RuntimeException('issuer_sales_file_unconfirmed');
            $this->audit($issuer,$actor,$document,$key,'company_sales_file_uploaded',['sha256'=>$hash,'portal_visible'=>$visible]);$this->authorize($issuer,$actor,$document,'edit');return $key;
        });
    }
    public function page($issuer,$actor,$document):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$document=IssuerPolicy::id($document);$this->authorize($issuer,$actor,$document,'view');
        return $this->transaction(function()use($issuer,$actor,$document):array{$this->authorize($issuer,$actor,$document,'view');$this->parent($issuer,$document);return $this->rows('SELECT file_key,filename,mime,byte_size,content_hash,portal_visible,created_at FROM '.$this->p.'company_sales_files WHERE issuer_id=? AND document_id=? AND active=1 ORDER BY created_at,file_key LIMIT 20',[$issuer,$document]);});
    }
    private function file(int $issuer,int $document,string $key,bool $portal):array
    {
        $row=$this->rows('SELECT filename,mime,byte_size,content_hash,content,portal_visible FROM '.$this->p.'company_sales_files WHERE file_key=? AND issuer_id=? AND document_id=? AND active=1'.($portal?' AND portal_visible=1':'').' FOR UPDATE',[$key,$issuer,$document])[0]??null;
        if(!$row)throw new DomainException('company_sales_file_denied');
        if(!in_array($row['mime'],['application/pdf','image/png'],true)||(int)$row['byte_size']<8||(int)$row['byte_size']>2097152||strlen($row['content'])!==(int)$row['byte_size']||!hash_equals($row['content_hash'],hash('sha256',$row['content'])))throw new RuntimeException('issuer_sales_file_corrupt');return $row;
    }
    public function download($issuer,$actor,$document,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$document=IssuerPolicy::id($document);self::key($key);$this->authorize($issuer,$actor,$document,'view');
        return $this->transaction(function()use($issuer,$actor,$document,$key):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,$document,'view');$this->parent($issuer,$document);$row=$this->file($issuer,$document,$key,false);$this->audit($issuer,$actor,$document,$key,'company_sales_file_downloaded',['sha256'=>$row['content_hash']]);$this->authorize($issuer,$actor,$document,'view');return $row;});
    }
    public function hide($issuer,$actor,$document,string $key,string $hash):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$document=IssuerPolicy::id($document);self::key($key);$this->authorize($issuer,$actor,$document,'edit');
        $this->transaction(function()use($issuer,$actor,$document,$key,$hash):void{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,$document,'edit');$this->parent($issuer,$document);$prior=$this->rows('SELECT active,content_hash FROM '.$this->p.'company_sales_files WHERE file_key=? AND issuer_id=? AND document_id=? FOR UPDATE',[$key,$issuer,$document])[0]??null;if($prior&&(int)$prior['active']===0&&hash_equals($prior['content_hash'],$hash))return;$row=$this->file($issuer,$document,$key,false);if(!hash_equals($row['content_hash'],$hash))throw new DomainException('company_sales_file_conflict');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_sales_files SET active=0 WHERE file_key=? AND issuer_id=? AND document_id=?',[$key,$issuer,$document]);if($this->rows('SELECT file_key FROM '.$this->p.'company_sales_files WHERE file_key=? AND active=1',[$key]))throw new RuntimeException('issuer_sales_file_unconfirmed');$this->audit($issuer,$actor,$document,$key,'company_sales_file_hidden');$this->authorize($issuer,$actor,$document,'edit');});
    }
    private static function assertSource(array $row,int $issuer,int $client):void
    {
        $payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);$context=json_decode($row['context_json'],true,32,JSON_THROW_ON_ERROR);$encode=fn($value)=>json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!is_array($payload)||!is_array($context)||!hash_equals($row['origin_hash'],hash('sha256',$encode($context)))||!hash_equals($row['input_hash'],hash('sha256',$encode([$row['kind'],$payload])))||(int)($context['issuer_id']??0)!==$issuer||(int)($context['recipient']['client_id']??0)!==$client||(int)($payload['client_id']??0)!==$client||(int)($payload['currency']??0)!==(int)$row['currency'])throw new DomainException('company_sales_file_denied');
    }
    private function portalIdentity(CompanyPortal $portal,array $session,int $issuer,int $document):array
    {
        $parent=$this->parent($issuer,$document);$who=$portal->invoiceIdentity($session,$issuer,true);$permission=match($parent['kind']){'estimates'=>2,'proposals'=>4,default=>0};if(!$who||!in_array($parent['state'],['accepted','converted'],true)||(int)$parent['client_id']!==$who['client_id']||count($this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=? FOR UPDATE',[$who['contact_id'],$permission]))!==1)throw new DomainException('company_sales_file_denied');$source=$this->rows('SELECT kind,currency,payload_json,context_json,origin_hash,input_hash FROM '.$this->p.'company_sales_documents WHERE id=? AND issuer_id=? AND client_id=? FOR UPDATE',[$document,$issuer,$who['client_id']])[0]??null;if(!$source)throw new DomainException('company_sales_file_denied');self::assertSource($source,$issuer,$who['client_id']);return $who;
    }
    public function portalPage(CompanyPortal $portal,array $session,$issuer,$document):array
    {
        $issuer=IssuerPolicy::id($issuer);$document=IssuerPolicy::id($document);return $this->transaction(function()use($portal,$session,$issuer,$document){$this->portalIdentity($portal,$session,$issuer,$document);$r=$this->rows('SELECT file_key,filename,mime,byte_size FROM '.$this->p.'company_sales_files WHERE issuer_id=? AND document_id=? AND active=1 AND portal_visible=1 ORDER BY created_at,file_key LIMIT 20',[$issuer,$document]);$this->portalIdentity($portal,$session,$issuer,$document);return $r;});
    }
    /** Bounded portal metadata batch; every persisted parent and native kind grant is checked before listing children. */
    public function portalPages(CompanyPortal $portal,array $session,$issuer,array $documents):array
    {
        $issuer=IssuerPolicy::id($issuer);if(count($documents)>200)throw new DomainException('company_sales_file_denied');$ids=array_values(array_unique(array_map([IssuerPolicy::class,'id'],$documents)));if(!$ids)return [];
        return $this->transaction(function()use($portal,$session,$issuer,$ids){$who=$portal->invoiceIdentity($session,$issuer,true);if(!$who)throw new DomainException('company_sales_file_denied');$parents=$this->rows('SELECT id,kind,state,client_id FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND id IN ('.implode(',',array_fill(0,count($ids),'?')).') FOR UPDATE',array_merge([$issuer],$ids));if(count($parents)!==count($ids))throw new DomainException('company_sales_file_denied');$grants=[];foreach($this->rows('SELECT permission_id,COUNT(*) n FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id IN (2,4) GROUP BY permission_id',[$who['contact_id']]) as $grant)$grants[(int)$grant['permission_id']]=(int)$grant['n'];foreach($parents as $parent){$permission=match($parent['kind']){'estimates'=>2,'proposals'=>4,default=>0};if((int)$parent['client_id']!==$who['client_id']||!in_array($parent['state'],['accepted','converted'],true)||($grants[$permission]??0)!==1)throw new DomainException('company_sales_file_denied');}$sources=$this->rows('SELECT kind,currency,payload_json,context_json,origin_hash,input_hash FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND client_id=? AND id IN ('.implode(',',array_fill(0,count($ids),'?')).') FOR UPDATE',array_merge([$issuer,$who['client_id']],$ids));if(count($sources)!==count($ids))throw new DomainException('company_sales_file_denied');foreach($sources as $source)self::assertSource($source,$issuer,$who['client_id']);$files=$this->rows('SELECT document_id,file_key,filename,mime,byte_size FROM '.$this->p.'company_sales_files WHERE issuer_id=? AND document_id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND active=1 AND portal_visible=1 ORDER BY created_at,file_key LIMIT 4001',array_merge([$issuer],$ids));$out=[];foreach($files as $file){$parent=(int)$file['document_id'];if(count($out[$parent]??[])>=20)throw new RuntimeException('issuer_sales_file_corrupt');unset($file['document_id']);$out[$parent][]=$file;}return $out;});
    }
    public function portalDownload(CompanyPortal $portal,array $session,$issuer,$document,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$document=IssuerPolicy::id($document);self::key($key);return $this->transaction(function()use($portal,$session,$issuer,$document,$key){$who=$this->portalIdentity($portal,$session,$issuer,$document);$row=$this->file($issuer,$document,$key,true);$this->audit($issuer,0,$document,$key,'company_portal_sales_file_downloaded',['contact_id'=>$who['contact_id'],'sha256'=>$row['content_hash']]);$this->portalIdentity($portal,$session,$issuer,$document);return $row;});
}
}
