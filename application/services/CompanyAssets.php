<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Bounded company-owned raster images. No public path, legacy upload handler or remote URL. */
final class CompanyAssets
{
    private const KEY='company_brand_assets';
    public function __construct(private $db,private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid table prefix');
    }
    private function authorize(int $issuer,int $actor,string $cap):void
    {
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'settings',$cap))throw new DomainException('company_asset_access_denied');
    }
    private static function kind(string $kind):void
    {
        if(!in_array($kind,['logo','logo_dark','favicon','signature'],true))throw new DomainException('company_asset_invalid_kind');
    }
    private function document(int $issuer):object
    {
        $row=IssuerStorage::query($this->db,'SELECT settings_json FROM '.$this->prefix.'issuers_registry WHERE id=? AND active=1',[$issuer])->row_array();
        if(!$row)throw new DomainException('company_asset_access_denied');
        $doc=json_decode($row['settings_json']);
        if(!$doc instanceof stdClass||json_last_error()!==JSON_ERROR_NONE)throw new DomainException('company_asset_corrupt');
        if(property_exists($doc,self::KEY)&&!$doc->{self::KEY} instanceof stdClass)throw new DomainException('company_asset_corrupt');
        foreach(get_object_vars($doc->{self::KEY}??new stdClass()) as $kind=>$value){self::kind($kind);self::validateStored($value);}
        return $doc;
    }
    private static function validateStored($value):string
    {
        if(!is_string($value)||strlen($value)>700000||!preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#D',$value,$m))throw new DomainException('company_asset_corrupt');
        $bytes=base64_decode($m[1],true);$info=$bytes===false?false:@getimagesizefromstring($bytes);
        if($bytes===false||strlen($bytes)>524288||!$info||($info['mime']??'')!=='image/png'||$info[0]>1600||$info[1]>1600||$info[0]*$info[1]>2000000)throw new DomainException('company_asset_corrupt');
        return $value;
    }
    public static function normalize(string $bytes):string
    {
        if(strlen($bytes)<8||strlen($bytes)>524288)throw new DomainException('company_asset_invalid_image');
        $info=@getimagesizefromstring($bytes);
        if(!$info||!in_array($info['mime']??'', ['image/png','image/jpeg'],true)||$info[0]<1||$info[1]<1||$info[0]>1600||$info[1]>1600||$info[0]*$info[1]>2000000)throw new DomainException('company_asset_invalid_image');
        if(!function_exists('imagecreatefromstring'))throw new RuntimeException('issuer_asset_codec_unavailable');
        $image=@imagecreatefromstring($bytes);if(!$image)throw new DomainException('company_asset_invalid_image');
        ob_start();
        try{imagealphablending($image,false);imagesavealpha($image,true);if(!imagepng($image,null,6))throw new RuntimeException('issuer_asset_codec_failed');$png=ob_get_contents();}
        finally{ob_end_clean();unset($image);}
        if(!is_string($png)||strlen($png)>524288)throw new DomainException('company_asset_invalid_image');
        return self::validateStored('data:image/png;base64,'.base64_encode($png));
    }
    private static function fingerprint(object $doc):string
    {
        $assets=get_object_vars($doc->{self::KEY}??new stdClass());ksort($assets);
        return hash('sha256',json_encode($assets,JSON_THROW_ON_ERROR));
    }
    public function view($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');$doc=$this->document($issuer);
        $result=['revision'=>self::fingerprint($doc),'assets'=>get_object_vars($doc->{self::KEY}??new stdClass())];
        $this->authorize($issuer,$actor,'view');return $result;
    }
    /** Header branding only: active company members never receive signatures or settings. */
    public function brandingForStaff(int $issuer,int $actor):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);
        if(!$acl->member($issuer,$actor))throw new DomainException('company_asset_access_denied');
        $doc=$this->document($issuer);$assets=$doc->{self::KEY}??new stdClass();$result=[];
        foreach(['logo','logo_dark','favicon']as$kind)$result[$kind]=$assets->{$kind}??'';
        if(!$acl->member($issuer,$actor))throw new DomainException('company_asset_access_denied');return$result;
    }
    /** Native public header only, sealed to this issuer; signatures are never projected. */
    public function brandingForPortal($ci,int $issuer):array
    {
        if(!class_exists('PortalRequestScope',false)||!PortalRequestScope::sealedFor($ci)||PortalRequestScope::issuerId()!==$issuer||$ci->db!==$this->db)throw new DomainException('company_asset_access_denied');
        $doc=$this->document($issuer);$assets=$doc->{self::KEY}??new stdClass();$result=['logo'=>$assets->logo??'','logo_dark'=>$assets->logo_dark??''];
        require_once __DIR__.'/CompanySettingsStorage.php';$store=new CompanySettingsStorage($this->db,$this->prefix);
        if($store->isPrimary($issuer))foreach(['logo','logo_dark']as$kind){
            if($result[$kind]!=='')continue;$name=$store->get($issuer,$kind==='logo'?'company_logo':'company_logo_dark');if($name==='')continue;
            if(strlen($name)>191||basename($name)!==$name||!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/D',$name))throw new DomainException('company_asset_corrupt');
            $root=defined('COMPANY_FILES_FOLDER')?COMPANY_FILES_FOLDER:(defined('FCPATH')?FCPATH.'uploads/company/':'');$real=realpath($root);$path=$real===false?false:realpath($real.DIRECTORY_SEPARATOR.$name);
            if($real===false||$path===false||is_link(rtrim($root,DIRECTORY_SEPARATOR))||is_link($real.DIRECTORY_SEPARATOR.$name)||dirname($path)!==$real||!is_file($path))throw new DomainException('company_asset_corrupt');
            $f=fopen($path,'rb');if(!$f)throw new DomainException('company_asset_corrupt');try{$before=fstat($f);if(!$before||$before['size']>524288)throw new DomainException('company_asset_corrupt');$bytes=stream_get_contents($f,524289);$after=fstat($f);if($before!==$after||!is_string($bytes)||strlen($bytes)!==$before['size'])throw new DomainException('company_asset_corrupt');}finally{fclose($f);}
            $result[$kind]=self::normalize($bytes);
        }
        if(!PortalRequestScope::sealedFor($ci)||PortalRequestScope::issuerId()!==$issuer)throw new DomainException('company_asset_access_denied');$this->document($issuer);return$result;
    }
    /** Explicit origin only; used after invoice/create ACL inside the owner transaction. */
    public function invoiceProfileInOwnedTransaction(int $issuer,int $actor):array
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,['issuers_registry','issuers_installation_identity','issuers_resource_permissions','issuers_staff','staff']);
        if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'invoices','create'))throw new DomainException('company_asset_access_denied');
        $assets=$this->document($issuer)->{self::KEY}??new stdClass();
        return ['logo_inline'=>$assets->logo??'','signature_inline'=>$assets->signature??''];
    }
    public function download($issuer,$actor,string $kind,string $revision):string
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);self::kind($kind);$this->authorize($issuer,$actor,'view');
        return IssuerTransaction::run($this->db,$this->prefix,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff'],function()use($issuer,$actor,$kind,$revision):string{
            (new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$doc=$this->document($issuer);
            if(!hash_equals(self::fingerprint($doc),$revision))throw new DomainException('company_asset_stale');
            $inline=$doc->{self::KEY}->{$kind}??null;if(!is_string($inline))throw new DomainException('company_asset_missing');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[$actor,'company_asset_'.$kind.'_downloaded',$issuer,date('Y-m-d H:i:s')]);
            $id=(int)$this->db->insert_id();if($id<1||!IssuerStorage::query($this->db,'SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=?',[$id,$issuer,$actor])->row_array())throw new RuntimeException('issuer_asset_write_unconfirmed');
            $this->authorize($issuer,$actor,'view');return base64_decode(substr($inline,strlen('data:image/png;base64,')),true);
        });
    }
    public function tables():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff'];}
    public function save($issuer,$actor,string $kind,?string $bytes,string $revision):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);self::kind($kind);
        $this->authorize($issuer,$actor,'edit');$this->authorize($issuer,$actor,'view');
        IssuerTransaction::run($this->db,$this->prefix,$this->tables(),fn()=>$this->saveBatchInOwnedTransaction($issuer,$actor,[$kind=>$bytes],$revision));
    }
    /** Existing Settings multipart form can commit its options and all images atomically. */
    public function saveBatchInOwnedTransaction(int $issuer,int $actor,array $changes,string $revision):void
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables());
        $this->authorize($issuer,$actor,'edit');$this->authorize($issuer,$actor,'view');
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||count($changes)>4)throw new DomainException('company_asset_stale');
        $normalized=[];foreach($changes as$kind=>$bytes){self::kind($kind);if($bytes!==null&&!is_string($bytes))throw new DomainException('company_asset_invalid_image');$normalized[$kind]=$bytes===null?null:self::normalize($bytes);}
        (new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);
        $doc=$this->document($issuer);if(!hash_equals(self::fingerprint($doc),$revision))throw new DomainException('company_asset_stale');
        if($changes===[])return;
        $assets=$doc->{self::KEY}??new stdClass();
        foreach($normalized as$kind=>$inline){if($inline===null)unset($assets->{$kind});else$assets->{$kind}=$inline;}
        $doc->{self::KEY}=$assets;$json=json_encode($doc,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'issuers_registry SET settings_json=? WHERE id=?',[$json,$issuer]);
        if(self::fingerprint($this->document($issuer))!==self::fingerprint($doc))throw new RuntimeException('issuer_asset_write_unconfirmed');
        foreach($normalized as$kind=>$inline){
            $event='company_asset_'.$kind.($inline===null?'_removed':'_saved');$now=date('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,NULL,?)',[$actor,$event,$issuer,$now]);
            $id=(int)$this->db->insert_id();
            if($id<1||!IssuerStorage::query($this->db,'SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND event=? AND subject_id IS NULL AND created_at=?',[$id,$issuer,$actor,$event,$now])->row_array())throw new RuntimeException('issuer_asset_write_unconfirmed');
        }
        $this->authorize($issuer,$actor,'edit');$this->authorize($issuer,$actor,'view');
    }
}
