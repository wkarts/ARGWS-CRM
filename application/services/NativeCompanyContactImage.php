<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomerAudit.php';
require_once __DIR__.'/CompanyNativeContacts.php';
require_once __DIR__.'/NativeCompanyContactImageSchema.php';
/** Avatar bytes are private database content; never a public upload pathname. */
final class NativeCompanyContactImage
{
    public const MAX_BYTES=2097152;public const MAX_DIMENSION=2048;public const MAX_PIXELS=4194304;
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function tables():array{return array_merge(CompanyNativeContacts::TABLES,['company_native_contact_images']);}
    public static function normalize(string $bytes):array
    {
        if(strlen($bytes)<16||strlen($bytes)>self::MAX_BYTES||!function_exists('imagecreatefromstring'))throw new DomainException('company_contact_image_invalid');
        $image=null;$target=null;
        set_error_handler(static function($level,$message){throw new ErrorException('Invalid image',0,$level);});
        try{
            $info=getimagesizefromstring($bytes);if(!$info||!in_array($info['mime']??'', ['image/png','image/jpeg','image/webp'],true)||$info[0]<1||$info[1]<1||$info[0]>self::MAX_DIMENSION||$info[1]>self::MAX_DIMENSION||$info[0]*$info[1]>self::MAX_PIXELS)throw new DomainException('company_contact_image_invalid');
            $image=imagecreatefromstring($bytes);if(!$image)throw new DomainException('company_contact_image_invalid');
            $ratio=min(1,512/max($info[0],$info[1]));$width=max(1,(int)round($info[0]*$ratio));$height=max(1,(int)round($info[1]*$ratio));
            $target=imagecreatetruecolor($width,$height);imagealphablending($target,false);imagesavealpha($target,true);imagefill($target,0,0,imagecolorallocatealpha($target,0,0,0,127));
            if(!imagecopyresampled($target,$image,0,0,0,0,$width,$height,$info[0],$info[1]))throw new DomainException('company_contact_image_invalid');
            ob_start();try{if(!imagepng($target,null,6))throw new DomainException('company_contact_image_invalid');$canonical=ob_get_contents();}finally{ob_end_clean();}
            if(!is_string($canonical)||strlen($canonical)>self::MAX_BYTES)throw new DomainException('company_contact_image_invalid');
            return ['mime'=>'image/png','byte_size'=>strlen($canonical),'sha256'=>hash('sha256',$canonical),'content'=>$canonical];
        }catch(Throwable $e){throw new DomainException('company_contact_image_invalid');}
        finally{$target=null;$image=null;restore_error_handler();}
    }
    public function writeInOwned(int $issuer,int $actor,int $client,int $contact,array $image,string $cap='edit'):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables());NativeCompanyContactImageSchema::assertValid($this->db,$this->p);
        if(!in_array($cap,['create','edit'],true)||!(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,$cap==='create'?'create_contact':'edit'))throw new DomainException('company_client_access_denied');
        $owner=IssuerStorage::query($this->db,'SELECT c.id FROM '.$this->p.'contacts c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid JOIN '.$this->p.'clients u ON u.userid=c.userid AND u.active=1 WHERE c.id=? AND c.userid=? AND o.owner_issuer_id=? FOR UPDATE',[$contact,$client,$issuer])->row_array();if(!$owner)throw new DomainException('company_client_access_denied');
        $this->store($issuer,$actor,$client,$contact,$image);
    }
    private function store(int$issuer,?int$actor,int$client,int$contact,array$image,?int$contactActor=null):void
    {
        if(array_keys($image)!==['mime','byte_size','sha256','content']||$image['mime']!=='image/png'||!is_string($image['content'])||strlen($image['content'])!==$image['byte_size']||!hash_equals(hash('sha256',$image['content']),$image['sha256'])||self::normalize($image['content'])!==$image)throw new DomainException('company_contact_image_invalid');
        $old=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_native_contact_images WHERE contact_id=? FOR UPDATE',[$contact])->row_array();if($old&&((int)$old['issuer_id']!==$issuer||(int)$old['client_id']!==$client))throw new DomainException('company_client_access_denied');
        $revision=(int)($old['revision']??0)+1;if($revision>2147483647)throw new DomainException('company_contact_image_invalid');
        $data=['contact_id'=>$contact,'issuer_id'=>$issuer,'client_id'=>$client,'revision'=>$revision]+$image+['updated_by'=>$actor,'updated_at'=>gmdate('Y-m-d H:i:s'),'actor_kind'=>$actor===null?'contact':'staff','actor_contact_id'=>$actor===null?($contactActor??$contact):null];
        if($old){$fields=array_diff(array_keys($data),['contact_id']);IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_native_contact_images SET '.implode(',',array_map(fn($f)=>$f.'=?',$fields)).' WHERE contact_id=?',[...array_map(fn($f)=>$data[$f],$fields),$contact]);}
        else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_contact_images('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));
        $saved=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_native_contact_images WHERE contact_id=?',[$contact])->row_array();foreach($data as$key=>$value)if((string)($saved[$key]??'')!==(string)$value)throw new RuntimeException('issuer_contact_image_unconfirmed');
        $marker='native:'.$image['sha256'];IssuerStorage::query($this->db,'UPDATE '.$this->p.'contacts SET profile_image=? WHERE id=? AND userid=?',[$marker,$contact,$client]);if((IssuerStorage::query($this->db,'SELECT profile_image FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$contact,$client])->row_array()['profile_image']??null)!==$marker)throw new RuntimeException('issuer_contact_image_unconfirmed');
    }
    public function remove(int $issuer,int $actor,int $client,int $contact,string $revision):void
    {
        NativeCompanyContactImageSchema::assertValid($this->db,$this->p);
        IssuerTransaction::run($this->db,$this->p,self::tables(),function()use($issuer,$actor,$client,$contact,$revision):void{
            $native=new CompanyNativeContacts($this->db,$this->p);$dto=$native->get($issuer,$actor,$client,$contact);
            if(!(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,'edit'))throw new DomainException('company_client_access_denied');
            if(!hash_equals($dto['revision'],$revision))throw new DomainException('company_client_stale');
            if(empty($dto['contact']['profile_image']))return;
            if(!str_starts_with($dto['contact']['profile_image'],'native:'))throw new DomainException('company_contact_image_legacy_unmanaged');
            if($this->read($issuer,$actor,$client,$contact)===null)throw new RuntimeException('issuer_contact_image_unconfirmed');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_native_contact_images WHERE contact_id=? AND issuer_id=? AND client_id=?',[$contact,$issuer,$client]);
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'contacts SET profile_image=NULL WHERE id=? AND userid=?',[$contact,$client]);
            if(IssuerStorage::query($this->db,'SELECT contact_id FROM '.$this->p.'company_native_contact_images WHERE contact_id=?',[$contact])->row_array()||$native->get($issuer,$actor,$client,$contact)['contact']['profile_image']!==null)throw new RuntimeException('issuer_contact_image_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$contact,'native_customer_contact_image_removed');
            if(!(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,$client,'edit'))throw new DomainException('company_client_access_denied');
        });
    }
    public function read(int $issuer,int $actor,int $client,int $contact):?array
    {
        $dto=(new CompanyNativeContacts($this->db,$this->p))->get($issuer,$actor,$client,$contact);NativeCompanyContactImageSchema::assertValid($this->db,$this->p);
        $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_native_contact_images WHERE contact_id=? AND issuer_id=? AND client_id=?',[$contact,$issuer,$client])->row_array();
        if(!$row){if(str_starts_with((string)$dto['contact']['profile_image'],'native:'))throw new RuntimeException('issuer_contact_image_unconfirmed');return null;}
        if($row['mime']!=='image/png'||strlen($row['content'])!==(int)$row['byte_size']||(int)$row['byte_size']>self::MAX_BYTES||!hash_equals(hash('sha256',$row['content']),$row['sha256'])||$dto['contact']['profile_image']!=='native:'.$row['sha256'])throw new RuntimeException('issuer_contact_image_unconfirmed');
        (new CompanyNativeContacts($this->db,$this->p))->get($issuer,$actor,$client,$contact);return ['mime'=>$row['mime'],'content'=>$row['content']];
    }
    public function writePortalInOwned(int$issuer,object$writer,array$image):void{
        if(!in_array(get_class($writer),['NativePortalProfile','NativePortalContacts'],true))throw new DomainException('native_portal_profile_denied');NativeCompanyContactImageSchema::assertValid($this->db,$this->p);$writer->assertMutationInOwned($this->db,$this->p,$issuer,'contacts');$contact=$writer->reference($this->db,$this->p,$issuer,'contacts',true)['source_id'];$client=$writer->reference($this->db,$this->p,$issuer,'customers')['source_id'];
        $contactActor=get_class($writer)==='NativePortalContacts'?$writer->actorContactInOwned($this->db,$this->p,$issuer):$contact;$this->store($issuer,null,$client,$contact,$image,$contactActor);$writer->assertMutationInOwned($this->db,$this->p,$issuer,'contacts');
    }
    public function readPortal(int$issuer,object$writer):?array{
        if(!in_array(get_class($writer),['NativePortalProfile','NativePortalContacts'],true))throw new DomainException('native_portal_profile_denied');NativeCompanyContactImageSchema::assertValid($this->db,$this->p);$contact=$writer->reference($this->db,$this->p,$issuer,'contacts')['source_id'];$client=$writer->reference($this->db,$this->p,$issuer,'customers')['source_id'];$row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_native_contact_images WHERE contact_id=? AND issuer_id=? AND client_id=?',[$contact,$issuer,$client])->row_array();$marker=IssuerStorage::query($this->db,'SELECT profile_image FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$contact,$client])->row_array()['profile_image']??null;
        if(!$row){if(str_starts_with((string)$marker,'native:'))throw new RuntimeException('issuer_contact_image_unconfirmed');if($marker!==null&&$marker!==''){$bytes=$this->legacyPortalImage($issuer,$client,$contact,(string)$marker);$writer->reference($this->db,$this->p,$issuer,'contacts');return$bytes;}return null;}
        if($row['mime']!=='image/png'||strlen($row['content'])!==(int)$row['byte_size']||(int)$row['byte_size']>self::MAX_BYTES||!hash_equals(hash('sha256',$row['content']),$row['sha256'])||$marker!=='native:'.$row['sha256'])throw new RuntimeException('issuer_contact_image_unconfirmed');$writer->reference($this->db,$this->p,$issuer,'contacts');return['mime'=>$row['mime'],'content'=>$row['content']];
    }
    public function removePortalInOwned(int$issuer,object$writer):void{
        if(!in_array(get_class($writer),['NativePortalProfile','NativePortalContacts'],true))throw new DomainException('native_portal_profile_denied');$writer->assertMutationInOwned($this->db,$this->p,$issuer,'contacts');$contact=$writer->reference($this->db,$this->p,$issuer,'contacts',true)['source_id'];$client=$writer->reference($this->db,$this->p,$issuer,'customers')['source_id'];$marker=IssuerStorage::query($this->db,'SELECT profile_image FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$contact,$client])->row_array()['profile_image']??null;if(!$marker)return;if(!str_starts_with($marker,'native:')){$this->assertLegacyPortalOwner($issuer,$client,$contact);IssuerStorage::query($this->db,'UPDATE '.$this->p.'contacts SET profile_image=NULL WHERE id=? AND userid=? AND profile_image=?',[$contact,$client,$marker]);$actual=IssuerStorage::query($this->db,'SELECT profile_image FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$contact,$client])->row_array();if(!$actual||$actual['profile_image']!==null)throw new RuntimeException('issuer_contact_image_unconfirmed');$writer->assertMutationInOwned($this->db,$this->p,$issuer,'contacts');return;}if($this->readPortal($issuer,$writer)===null)throw new RuntimeException('issuer_contact_image_unconfirmed');
        IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_native_contact_images WHERE contact_id=? AND issuer_id=? AND client_id=?',[$contact,$issuer,$client]);IssuerStorage::query($this->db,'UPDATE '.$this->p.'contacts SET profile_image=NULL WHERE id=? AND userid=?',[$contact,$client]);if($this->readPortal($issuer,$writer)!==null)throw new RuntimeException('issuer_contact_image_unconfirmed');$writer->assertMutationInOwned($this->db,$this->p,$issuer,'contacts');
    }

    private function assertLegacyPortalOwner(int$issuer,int$client,int$contact):void{
        $row=IssuerStorage::query($this->db,'SELECT c.id FROM '.$this->p.'contacts c JOIN '.$this->p.'clients u ON u.userid=c.userid AND u.active=1 JOIN '.$this->p.'issuers_installation_identity i ON i.id=1 AND i.primary_issuer_id=? LEFT JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.id=? AND c.userid=? AND c.active=1 AND o.client_id IS NULL',[$issuer,$contact,$client])->row_array();if(!$row)throw new DomainException('company_contact_image_legacy_unmanaged');
    }
    private function legacyPortalImage(int$issuer,int$client,int$contact,string$name):array{
        $this->assertLegacyPortalOwner($issuer,$client,$contact);if(!defined('CONTACT_PROFILE_IMAGES_FOLDER')||$name===''||basename($name)!==$name||strlen($name)>191||preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name))throw new DomainException('company_contact_image_legacy_unmanaged');
        $configured=rtrim(CONTACT_PROFILE_IMAGES_FOLDER,DIRECTORY_SEPARATOR);$root=realpath($configured);if($root===false||is_link($configured))throw new DomainException('company_contact_image_legacy_unmanaged');$directory=$root.DIRECTORY_SEPARATOR.$contact;$path=$directory.DIRECTORY_SEPARATOR.'thumb_'.$name;
        if(is_link($directory)||is_link($path)||realpath($directory)!==$directory||realpath($path)!==$path||!is_file($path))throw new DomainException('company_contact_image_legacy_unmanaged');$before=lstat($path);if(!$before||$before['size']<16||$before['size']>self::MAX_BYTES)throw new DomainException('company_contact_image_invalid');$handle=fopen($path,'rb');if(!$handle)throw new DomainException('company_contact_image_invalid');
        try{$opened=fstat($handle);if(!$opened||$opened['ino']!==$before['ino']||$opened['dev']!==$before['dev']||($opened['mode']&0170000)!==0100000)throw new DomainException('company_contact_image_invalid');$bytes=stream_get_contents($handle,self::MAX_BYTES+1);$after=fstat($handle);if(!is_string($bytes)||strlen($bytes)!==$before['size']||!$after||$after['size']!==$before['size']||$after['mtime']!==$before['mtime']||is_link($path)||realpath($path)!==$path)throw new DomainException('company_contact_image_invalid');}finally{fclose($handle);}
        $image=self::normalize($bytes);$this->assertLegacyPortalOwner($issuer,$client,$contact);return['mime'=>$image['mime'],'content'=>$image['content']];
    }

}
