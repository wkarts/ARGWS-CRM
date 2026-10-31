<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyStaff.php';
/** Immutable image files in the ORIGINAL public staff avatar namespace. No tenant filesystem fallback. */
final class NativeCompanyStaffAvatar
{
    private array $files=[];private array $mutation=[];private ?int $appliedStaff=null;
    private function __construct(private $db,private string $p,private int $i,private int $a,private ?int $target,private bool $personal,private ?array $images){}
    public static function prepare($db,string $p,int $i,int $a,?int $target,?string $bytes,bool $personal=false):self
    {
        $acl=new IssuerResourceAccess($db,$p);
        if($personal){if($target!==$a||!$acl->member($i,$a))throw new DomainException('issuer_staff_denied');}
        else{if(!$acl->can($i,$a,'staff','view')||!$acl->can($i,$a,'staff',$target===null?'create':'edit'))throw new DomainException('issuer_staff_denied');if($target!==null&&!(new CompanyStaff($db,$p))->get($i,$a,$target)['profile_editable'])throw new DomainException('issuer_staff_shared_identity');}
        $images=null;
        if($bytes!==null){
            if(strlen($bytes)<8||strlen($bytes)>2097152||!extension_loaded('gd'))throw new DomainException('native_staff_avatar_invalid');
            $info=@getimagesizefromstring($bytes);if(!$info||!in_array($info[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG],true)||$info[0]<1||$info[1]<1||$info[0]>4000||$info[1]>4000||$info[0]*$info[1]>12000000)throw new DomainException('native_staff_avatar_invalid');
            $image=@imagecreatefromstring($bytes);if(!$image)throw new DomainException('native_staff_avatar_invalid');$images=[];
            try{foreach(['thumb'=>320,'small'=>96]as$kind=>$size){$ratio=min($size/$info[0],$size/$info[1],1);$w=max(1,(int)floor($info[0]*$ratio));$h=max(1,(int)floor($info[1]*$ratio));$out=imagecreatetruecolor($w,$h);imagealphablending($out,false);imagesavealpha($out,true);imagefill($out,0,0,imagecolorallocatealpha($out,0,0,0,127));if(!imagecopyresampled($out,$image,0,0,0,0,$w,$h,$info[0],$info[1]))throw new RuntimeException('native_staff_avatar_encode_failed');ob_start();$ok=imagepng($out,null,6);$encoded=ob_get_clean();imagedestroy($out);if(!$ok||!is_string($encoded)||strlen($encoded)>2097152)throw new RuntimeException('native_staff_avatar_encode_failed');$images[$kind]=$encoded;}}finally{imagedestroy($image);}
        }
        return new self($db,$p,$i,$a,$target,$personal,$images);
    }
    public function __debugInfo():array{return[];}
    public function __serialize():array{throw new LogicException('native_staff_avatar_not_serializable');}
    public function input():array{return['target'=>$this->target,'personal'=>$this->personal,'images'=>$this->images===null?null:array_map(static fn($v)=>hash('sha256',$v),$this->images)];}
    public function assertFor($db,string $p,int $i,int $a):void{if($db!==$this->db||$p!==$this->p||$i!==$this->i||$a!==$this->a)throw new DomainException('issuer_staff_denied');}
    private function directory(int $staff):string
    {
        if(!defined('STAFF_PROFILE_IMAGES_FOLDER'))throw new RuntimeException('native_staff_avatar_path_invalid');$root=rtrim(STAFF_PROFILE_IMAGES_FOLDER,'/');$real=realpath($root);if($real===false||$real!==$root||is_link($root))throw new RuntimeException('native_staff_avatar_path_invalid');$path=$root.'/'.$staff;
        if(!is_dir($path)&&!mkdir($path,0755))throw new RuntimeException('native_staff_avatar_path_invalid');if(is_link($path)||realpath($path)!==$path)throw new RuntimeException('native_staff_avatar_path_invalid');return$path;
    }
    private function persist(int $staff):array
    {
        $this->appliedStaff=$staff;$name=null;if($this->images!==null){$dir=$this->directory($staff);$name=bin2hex(random_bytes(24)).'.png';foreach($this->images as$kind=>$bytes){$path=$dir.'/'.$kind.'_'.$name;$stream=fopen($path,'x+b');if($stream===false)throw new RuntimeException('native_staff_avatar_write_failed');$stat=fstat($stream);$this->files[$path]=['hash'=>hash('sha256',$bytes),'dev'=>$stat['dev'],'ino'=>$stat['ino']];try{if(fwrite($stream,$bytes)!==strlen($bytes)||!fflush($stream))throw new RuntimeException('native_staff_avatar_write_failed');}finally{fclose($stream);}if(is_link($path)||!hash_equals($this->files[$path]['hash'],hash_file('sha256',$path)))throw new RuntimeException('native_staff_avatar_write_failed');}}
        IssuerStorage::query($this->db,'UPDATE '.$this->p.'staff SET profile_image=? WHERE staffid=?',[$name,$staff]);$row=IssuerStorage::query($this->db,'SELECT profile_image FROM '.$this->p.'staff WHERE staffid=?',[$staff])->row_array();if(!$row||$row['profile_image']!==$name)throw new RuntimeException('issuer_staff_write_unconfirmed');return$this->mutation=['profile_image'=>$name];
    }
    public function apply(int $staff,bool $created,CompanyStaff $writer):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$writer->tables());if($this->personal||($created?$this->target!==null:$this->target!==$staff))throw new DomainException('issuer_staff_denied');
        if($created)$writer->assertCreatedInOwned($this->db,$this->p,$this->i,$this->a,'staff',$staff);elseif(!$writer->get($this->i,$this->a,$staff)['profile_editable'])throw new DomainException('issuer_staff_shared_identity');return$this->persist($staff);
    }
    public function applyPersonal():array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,IssuerStaffAccess::TABLES);
        if(!$this->personal||$this->target!==$this->a||!(new IssuerResourceAccess($this->db,$this->p))->member($this->i,$this->a))throw new DomainException('issuer_staff_denied');
        return$this->persist($this->a);
    }
    public function mutation():array{return$this->mutation;}
    /** Remove only new random files this exact attempt created, never any historical avatar. */
    public function commitFiles():void{$this->files=[];}
    public function rollbackFiles():void
    {
        // A commit acknowledgement may be uncertain. Never remove an image still referenced by storage.
        if($this->appliedStaff!==null&&isset($this->mutation['profile_image'])){try{$row=IssuerStorage::query($this->db,'SELECT profile_image FROM '.$this->p.'staff WHERE staffid=?',[$this->appliedStaff])->row_array();if(($row['profile_image']??null)===$this->mutation['profile_image'])return;}catch(Throwable $unknown){return;}}
        foreach($this->files as$path=>$expected){if(!is_file($path)||is_link($path))continue;$stat=stat($path);if($stat['dev']===$expected['dev']&&$stat['ino']===$expected['ino']){if(!unlink($path))throw new RuntimeException('native_staff_avatar_cleanup_required');}}
        $this->files=[];
    }
}
