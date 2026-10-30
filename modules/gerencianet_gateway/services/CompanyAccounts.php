<?php

declare(strict_types=1);
namespace Efi;
require_once __DIR__.'/CompanySchema.php';
require_once __DIR__.'/EfiCertificateStore.php';
require_once APPPATH.'services/issuers/IssuerTransaction.php';
require_once APPPATH.'services/issuers/IssuerResourceAccess.php';

/** No global options: each encrypted envelope binds its immutable company/account/environment. */
final class CompanyAccounts
{
    public function __construct(private $db,private string $p){CompanySchema::statements($p);}
    public static function tables():array{return array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','modules','issuers_audit'],CompanySchema::tables());}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function module():void
    {
        $rows=$this->rows('SELECT active,installed_version FROM '.$this->p.'modules WHERE module_name=?',['gerencianet_gateway']);
        if(count($rows)!==1||(int)$rows[0]['active']!==1||$rows[0]['installed_version']!=='5.1.5')throw new \DomainException('efi_company_module_required');
    }
    public function authorize(int $issuer,int $actor,string $action):void
    {
        \IssuerPolicy::id($issuer);\IssuerPolicy::id($actor);$this->module();$acl=new \IssuerResourceAccess($this->db,$this->p);
        $requirements=match($action){'view','configuration'=>[['settings','view']],'configure'=>[['settings','view'],['settings','edit']], 'issue'=>[['invoices','view'],['invoices','edit'],['customers','view']], 'observe'=>[['invoices','view']], 'settle'=>[['invoices','view'],['payments','create']],default=>throw new \DomainException('efi_company_access_denied')};
        foreach($requirements as [$resource,$cap])if(!$acl->can($issuer,$actor,$resource,$cap))throw new \DomainException('efi_company_access_denied');
    }
    private function run(callable $operation){CompanySchema::ensure($this->db,$this->p,false);return \IssuerTransaction::run($this->db,$this->p,self::tables(),$operation);}
    private static function revision(string $value):void{if(!preg_match('/^[a-f0-9]{64}$/D',$value))throw new \DomainException('efi_company_stale');}
    private static function environment(string $value):void{if(!in_array($value,['sandbox','production'],true))throw new \DomainException('efi_company_environment_invalid');}
    private function audit(int $issuer,int $actor,string $event,int $subject):void
    {
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$event,$issuer,$subject,gmdate('Y-m-d H:i:s')]);
        $id=(int)$this->db->insert_id();if($id<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND event=?',[$id,$issuer,$event]))throw new \RuntimeException('issuer_efi_audit_unconfirmed');
    }
    public function accountInOwned(int $issuer,int $actor,int $account,?string $revision=null,bool $requireEnabled=true):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,self::tables());$this->authorize($issuer,$actor,'observe');
        $row=$this->rows('SELECT * FROM '.$this->p.'efi_company_accounts WHERE id=? AND issuer_id=? FOR UPDATE',[$account,$issuer])[0]??null;
        if(!$row)throw new \DomainException('efi_company_access_denied');
        if($revision!==null&&(!hash_equals((string)$row['revision'],$revision)))throw new \DomainException('efi_company_stale');
        if($requireEnabled){$acl=new \IssuerResourceAccess($this->db,$this->p);if((int)$row['enabled']!==1||!$acl->can($issuer,(int)$row['updated_by'],'settings','edit')||!$acl->can($issuer,(int)$row['updated_by'],'settings','view'))throw new \DomainException('efi_company_account_revoked');}
        return $row;
    }
    public static function credentials(array $row,$encryption):array
    {
        $plain=$encryption->decrypt($row['credentials_cipher']??'');
        if(!is_string($plain)||$plain==='')throw new \DomainException('efi_company_credentials_unavailable');
        try{$value=json_decode($plain,true,16,JSON_THROW_ON_ERROR);}catch(\Throwable $e){throw new \DomainException('efi_company_credentials_unavailable');}
        if(!is_array($value)||array_keys($value)!==['issuer_id','environment','account_hash','client_id','client_secret','pix_key','certificate_hash']||(int)$value['issuer_id']!==(int)$row['issuer_id']||$value['environment']!==$row['environment']||$value['account_hash']!==$row['account_hash']||!is_string($value['client_id'])||!hash_equals(hash('sha256',$value['client_id']),$row['account_hash'])||!is_string($value['client_secret'])||$value['client_secret']===''||!is_string($value['pix_key'])||$value['certificate_hash']!==hash('sha256',(string)($row['certificate_cipher']??'')))throw new \DomainException('efi_company_credentials_unavailable');
        return array_intersect_key($value,array_flip(['client_id','client_secret','pix_key']));
    }
    /** Explicit save only. Empty certificate preserves it; credentials are never displayed or generated. */
    public function save(int $issuer,int $actor,string $environment,string $expectedRevision,string $clientId,string $secret,string $pixKey,?string $certificateCipher,$encryption):int
    {
        $this->authorize($issuer,$actor,'configure');
        self::environment($environment);
        foreach([$clientId,$secret] as $value)if($value===''||strlen($value)>4096||preg_match('/[\x00-\x20\x7f]/',$value))throw new \DomainException('efi_company_credentials_invalid');
        if(strlen($pixKey)>191||preg_match('/[\x00-\x1f\x7f]/',$pixKey))throw new \DomainException('efi_company_credentials_invalid');
        $hash=hash('sha256',$clientId);
        if($certificateCipher!==null){$file=\EfiCertificateStore::temporary($certificateCipher,$encryption);unlink($file);}
        return $this->run(function()use($issuer,$actor,$environment,$expectedRevision,$hash,$clientId,$secret,$pixKey,$certificateCipher,$encryption):int{
            $this->authorize($issuer,$actor,'configure');$old=$this->rows('SELECT * FROM '.$this->p.'efi_company_accounts WHERE issuer_id=? AND environment=? FOR UPDATE',[$issuer,$environment])[0]??null;
            if($old){self::revision($expectedRevision);if(!hash_equals($old['revision'],$expectedRevision)||!hash_equals($old['account_hash'],$hash))throw new \DomainException('efi_company_stale');}
            elseif($expectedRevision!=='new')throw new \DomainException('efi_company_stale');
            $owner=$this->rows('SELECT issuer_id FROM '.$this->p.'efi_company_accounts WHERE account_hash=? AND environment=? FOR UPDATE',[$hash,$environment])[0]??null;if($owner&&(int)$owner['issuer_id']!==$issuer)throw new \DomainException('efi_company_account_owned');
            if($old&&$certificateCipher===null){self::credentials($old,$encryption);$certificateCipher=$old['certificate_cipher'];}
            $plain=json_encode(['issuer_id'=>$issuer,'environment'=>$environment,'account_hash'=>$hash,'client_id'=>$clientId,'client_secret'=>$secret,'pix_key'=>$pixKey,'certificate_hash'=>hash('sha256',(string)$certificateCipher)],JSON_THROW_ON_ERROR);
            $cipher=$encryption->encrypt($plain);if(!is_string($cipher)||$cipher===''||$cipher===$plain||$encryption->decrypt($cipher)!==$plain)throw new \DomainException('efi_company_encryption_failed');
            $revision=bin2hex(random_bytes(32));$now=gmdate('Y-m-d H:i:s');
            if($old){$id=(int)$old['id'];\IssuerStorage::query($this->db,'UPDATE '.$this->p.'efi_company_accounts SET credentials_cipher=?,certificate_cipher=?,revision=?,enabled=1,updated_by=?,updated_at=? WHERE id=? AND issuer_id=? AND revision=?',[$cipher,$certificateCipher??$old['certificate_cipher'],$revision,$actor,$now,$id,$issuer,$expectedRevision]);}
            else{\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'efi_company_accounts (issuer_id,environment,account_hash,credentials_cipher,certificate_cipher,revision,enabled,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,?,?,1,?,?,?,?)',[$issuer,$environment,$hash,$cipher,$certificateCipher,$revision,$actor,$actor,$now,$now]);$id=(int)$this->db->insert_id();}
            $saved=$this->rows('SELECT id,revision FROM '.$this->p.'efi_company_accounts WHERE id=? AND issuer_id=?',[$id,$issuer])[0]??null;if(!$saved||$saved['revision']!==$revision)throw new \RuntimeException('issuer_efi_account_unconfirmed');
            $this->audit($issuer,$actor,$old?'efi_account_rotated':'efi_account_created',$id);return $id;
        });
    }
    public function revoke(int $issuer,int $actor,int $account,string $revision):void
    {
        self::revision($revision);$this->run(function()use($issuer,$actor,$account,$revision):void{$this->authorize($issuer,$actor,'configure');
            $row=$this->rows('SELECT id,revision FROM '.$this->p.'efi_company_accounts WHERE id=? AND issuer_id=? FOR UPDATE',[$account,$issuer])[0]??null;if(!$row||!hash_equals($row['revision'],$revision))throw new \DomainException('efi_company_stale');
            $new=bin2hex(random_bytes(32));\IssuerStorage::query($this->db,'UPDATE '.$this->p.'efi_company_accounts SET enabled=0,revision=?,updated_by=?,updated_at=? WHERE id=? AND issuer_id=? AND revision=?',[$new,$actor,gmdate('Y-m-d H:i:s'),$account,$issuer,$revision]);
            $check=$this->rows('SELECT enabled,revision FROM '.$this->p.'efi_company_accounts WHERE id=? AND issuer_id=?',[$account,$issuer])[0]??null;if(!$check||(int)$check['enabled']!==0||$check['revision']!==$new)throw new \RuntimeException('issuer_efi_account_unconfirmed');$this->audit($issuer,$actor,'efi_account_revoked',$account);
        });
    }
    public function choices(int $issuer,int $actor):array
    {
        return $this->run(function()use($issuer,$actor):array{$this->authorize($issuer,$actor,'observe');return $this->rows('SELECT id,environment,revision,enabled FROM '.$this->p.'efi_company_accounts WHERE issuer_id=? ORDER BY environment',[$issuer]);});
    }
    public function overview(int $issuer,int $actor):array
    {
        return $this->run(function()use($issuer,$actor):array{$this->authorize($issuer,$actor,'configuration');return $this->rows('SELECT id,environment,account_hash,revision,enabled,created_by,updated_by,created_at,updated_at FROM '.$this->p.'efi_company_accounts WHERE issuer_id=? ORDER BY environment',[$issuer]);});
    }
}
