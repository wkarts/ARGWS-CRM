<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyExpenseSchema.php';require_once __DIR__.'/issuers/IssuerTransaction.php';require_once __DIR__.'/issuers/IssuerResourceAccess.php';require_once __DIR__.'/CompanyAssets.php';

/** Private expense evidence, separate from invoice attachments and legacy filesystem uploads. */
final class CompanyExpenseFiles
{
    private $originGuard;private array $originTables;
    public function __construct(private $db,private string $p,?callable $originGuard=null,array $originTables=[]){$this->originGuard=$originGuard;$this->originTables=$originGuard===null?[]:$originTables;if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor,string $cap):void{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'expenses','view')||!$acl->can($issuer,$actor,'expenses',$cap))throw new DomainException('company_expense_denied');}
    private static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_expense_file_invalid');}
    private function transaction(int $issuer,int $actor,string $cap,callable $work)
    {
        CompanyExpenseSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','company_expenses','company_expense_files','issuers_audit'],$this->originTables),function()use($issuer,$actor,$cap,$work){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,$cap);if($this->originGuard!==null&&($this->originGuard)('before',$issuer,$actor,null)===false)throw new DomainException('company_api_scope_denied');$result=$work();$this->authorize($issuer,$actor,$cap);if($this->originGuard!==null&&($this->originGuard)('after',$issuer,$actor,$result)===false)throw new DomainException('company_api_scope_denied');$this->authorize($issuer,$actor,$cap);return $result;});
    }
    private function parent(int $issuer,int $expense):array{$row=$this->rows('SELECT id,state FROM '.$this->p.'company_expenses WHERE id=? AND issuer_id=? FOR UPDATE',[$expense,$issuer])[0]??null;if(!$row)throw new DomainException('company_expense_denied');return $row;}
    private static function normalize(string $name,string $bytes):array
    {
        if($name===''||strlen($name)>191||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name)||trim($name)!==$name||str_starts_with($name,'.')||strlen($bytes)<8||strlen($bytes)>2097152)throw new DomainException('company_expense_file_invalid');
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if($extension==='pdf'&&$mime==='application/pdf'&&preg_match('/^%PDF-[12]\.[0-9][\r\n]/',$bytes))return [$name,'application/pdf',$bytes];
        if(!in_array($extension,['png','jpg','jpeg'],true)||!in_array($mime,['image/png','image/jpeg'],true))throw new DomainException('company_expense_file_invalid');
        try{$inline=CompanyAssets::normalize($bytes);}catch(DomainException $e){throw new DomainException('company_expense_file_invalid');}$name=pathinfo($name,PATHINFO_FILENAME).'.png';if(strlen($name)>191)throw new DomainException('company_expense_file_invalid');return [$name,'image/png',base64_decode(substr($inline,strlen('data:image/png;base64,')),true)];
    }
    private function audit(int $issuer,int $actor,int $expense,string $key,string $event):void
    {
        $json=json_encode(['file_key'=>$key],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$expense,date('Y-m-d H:i:s'),$json]);$id=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[$id,$issuer,$expense,$event,$json]))throw new RuntimeException('issuer_expense_file_unconfirmed');
    }
    public function upload($issuer,$actor,$expense,string $key,string $name,string $bytes):string
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$expense=IssuerPolicy::id($expense);self::key($key);$this->authorize($issuer,$actor,'edit');[$name,$mime,$bytes]=self::normalize($name,$bytes);$hash=hash('sha256',$bytes);
        return $this->transaction($issuer,$actor,'edit',function()use($issuer,$actor,$expense,$key,$name,$mime,$bytes,$hash):string{
            $parent=$this->parent($issuer,$expense);$prior=$this->rows('SELECT issuer_id,actor_id,expense_id,filename,mime,content_hash FROM '.$this->p.'company_expense_files WHERE file_key=? FOR UPDATE',[$key])[0]??null;
            if($prior){foreach(['issuer_id'=>$issuer,'actor_id'=>$actor,'expense_id'=>$expense,'filename'=>$name,'mime'=>$mime,'content_hash'=>$hash] as $field=>$value)if((string)$prior[$field]!== (string)$value)throw new DomainException('company_expense_file_conflict');return $key;}
            if($parent['state']!=='recorded')throw new DomainException('company_expense_immutable');$usage=$this->rows('SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM '.$this->p.'company_expense_files WHERE issuer_id=? AND expense_id=?',[$issuer,$expense])[0];if((int)$usage['n']>=20||(int)$usage['bytes']+strlen($bytes)>20971520)throw new DomainException('company_expense_file_limit');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_expense_files(file_key,issuer_id,actor_id,expense_id,filename,mime,content_hash,byte_size,content,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,1,?)',[$key,$issuer,$actor,$expense,$name,$mime,$hash,strlen($bytes),$bytes,date('Y-m-d H:i:s')]);$this->file($issuer,$expense,$key);$this->audit($issuer,$actor,$expense,$key,'company_expense_file_uploaded');$confirmed=$this->file($issuer,$expense,$key);if($this->parent($issuer,$expense)!==$parent||$confirmed['filename']!==$name||$confirmed['mime']!==$mime||$confirmed['content_hash']!==$hash||$confirmed['content']!==$bytes)throw new RuntimeException('issuer_expense_file_unconfirmed');return $key;
        });
    }
    private function file(int $issuer,int $expense,string $key):array
    {
        $row=$this->rows('SELECT filename,mime,content_hash,byte_size,content FROM '.$this->p.'company_expense_files WHERE file_key=? AND issuer_id=? AND expense_id=? AND active=1 FOR UPDATE',[$key,$issuer,$expense])[0]??null;if(!$row)throw new DomainException('company_expense_denied');if(!in_array($row['mime'],['application/pdf','image/png'],true)||(int)$row['byte_size']<8||(int)$row['byte_size']>2097152||strlen($row['content'])!==(int)$row['byte_size']||!hash_equals($row['content_hash'],hash('sha256',$row['content'])))throw new RuntimeException('issuer_expense_file_corrupt');return $row;
    }
    public function page($issuer,$actor,$expense):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$expense=IssuerPolicy::id($expense);return $this->transaction($issuer,$actor,'view',function()use($issuer,$expense):array{$this->parent($issuer,$expense);return $this->rows('SELECT file_key,filename,mime,byte_size,content_hash,created_at FROM '.$this->p.'company_expense_files WHERE issuer_id=? AND expense_id=? AND active=1 ORDER BY id LIMIT 20',[$issuer,$expense]);});
    }
    public function download($issuer,$actor,$expense,string $key):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$expense=IssuerPolicy::id($expense);self::key($key);return $this->transaction($issuer,$actor,'view',function()use($issuer,$actor,$expense,$key):array{$parent=$this->parent($issuer,$expense);$file=$this->file($issuer,$expense,$key);$this->audit($issuer,$actor,$expense,$key,'company_expense_file_downloaded');if($this->parent($issuer,$expense)!==$parent||$this->file($issuer,$expense,$key)!==$file)throw new RuntimeException('issuer_expense_file_unconfirmed');return $file;});
    }
    public function hide($issuer,$actor,$expense,string $key,string $hash):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$expense=IssuerPolicy::id($expense);self::key($key);if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new DomainException('company_expense_file_invalid');
        $this->transaction($issuer,$actor,'edit',function()use($issuer,$actor,$expense,$key,$hash):void{$this->parent($issuer,$expense);$old=$this->rows('SELECT content_hash,active FROM '.$this->p.'company_expense_files WHERE file_key=? AND issuer_id=? AND expense_id=? FOR UPDATE',[$key,$issuer,$expense])[0]??null;if(!$old||!hash_equals($old['content_hash'],$hash))throw new DomainException('company_expense_file_conflict');if((int)$old['active']===0)return;IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_expense_files SET active=0 WHERE file_key=? AND issuer_id=? AND expense_id=?',[$key,$issuer,$expense]);if($this->rows('SELECT id FROM '.$this->p.'company_expense_files WHERE file_key=? AND active=1',[$key]))throw new RuntimeException('issuer_expense_file_unconfirmed');$this->audit($issuer,$actor,$expense,$key,'company_expense_file_hidden');$again=$this->rows('SELECT active,content_hash FROM '.$this->p.'company_expense_files WHERE file_key=? AND issuer_id=? AND expense_id=? FOR UPDATE',[$key,$issuer,$expense])[0]??null;if(!$again||(int)$again['active']!==0||!hash_equals($again['content_hash'],$hash))throw new RuntimeException('issuer_expense_file_unconfirmed');});
    }
}
