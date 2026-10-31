<?php
require __DIR__.'/portal_positive.php';
require APPPATH.'services/NativePortalIdentity.php';
final class NativePortalTestSession {
    public array $data=['staff_logged_in'=>true,'staff_user_id'=>7];public int $rotations=0;
    public function userdata($key){return $this->data[$key]??null;}
    public function set_userdata($values){$this->data=array_replace($this->data,$values);}
    public function unset_userdata($keys){foreach((array)$keys as $key)unset($this->data[$key]);}
    public function sess_regenerate($destroy){if($destroy!==true)throw new RuntimeException('session_rotation_required');$this->rotations++;}
}
$nativePortal=new NativePortalIdentity($db,$p,str_repeat('synthetic-',4));
$binding=$nativePortal->authenticate($a,'new@example.invalid','final-new-password-a','127.0.0.10','password_verify');
essentialCheck($binding!==null,'native login authenticates explicit issuer with existing password verifier');
essentialCheck($nativePortal->authenticate($b,'new@example.invalid','final-new-password-a','127.0.0.10','password_verify')===null,'native login never infers owner from an email');
$session=new NativePortalTestSession();$nativePortal->establish($session,$a,$binding);
essentialCheck($session->rotations===1&&$nativePortal->current($session,$a)!==null,'native identity rotates session and uses original client/contact session keys');
essentialCheck($nativePortal->current($session,$b)===null,'issuer URL tamper cannot reuse native portal identity');
$original=$session->data;$session->data['contact_user_id']=$cb;
essentialCheck($nativePortal->current($session,$a)===null,'mixed native and bound contact identities denied');$session->data=$original;
$db->query("UPDATE {$p}contacts SET active=0 WHERE id=?",[$ca]);essentialCheck($nativePortal->current($session,$a)===null,'native portal revalidates contact revocation on each request');$db->query("UPDATE {$p}contacts SET active=1 WHERE id=?",[$ca]);
NativePortalIdentity::clear($session);
essentialCheck($session->userdata('staff_logged_in')===true&&$session->userdata('staff_user_id')===7&&$session->userdata('client_logged_in')===null&&$session->rotations===2,'native portal logout clears only client identity and rotates');
echo "Native portal identity adapter tests passed; route bootstrap remains separate.\n";
