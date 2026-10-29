<?php
require __DIR__.'/native_contacts.php';
require APPPATH.'services/NativeCompanyContactImage.php';
$start=$GLOBALS['essentialChecks'];
NativeCompanyContactImageSchema::ensure($db,$p);
unset($db->data_cache['table_names']);$image=imagecreatetruecolor(12,8);imagefilledrectangle($image,0,0,11,7,imagecolorallocate($image,24,60,90));ob_start();imagepng($image);$bytes=ob_get_clean();$image=null;
$canonical=NativeCompanyContactImage::normalize($bytes.'<script>synthetic</script>');essentialCheck($canonical['mime']==='image/png'&&!str_contains($canonical['content'],'script')&&$canonical===NativeCompanyContactImage::normalize($canonical['content']),'GD decode/reencode strips trailing metadata and is canonical');
foreach(['<svg xmlns="http://www.w3.org/2000/svg"></svg>',str_repeat('x',2097153),substr($bytes,0,20)]as$bad)essentialThrows(fn()=>NativeCompanyContactImage::normalize($bad),'company_contact_image_invalid','non-image/oversize/truncated input denied');
$large=imagecreatetruecolor(2049,1);ob_start();imagepng($large);$oversize=ob_get_clean();$large=null;essentialThrows(fn()=>NativeCompanyContactImage::normalize($oversize),'company_contact_image_invalid','pixel dimensions checked before decoding image');
$images=new NativeCompanyContactImage($db,$p);$before=$native->get($a,7,101,$na);$edit['password']='';$edit['title']=$before['contact']['title'];
$native->save($a,7,101,$edit,'password_hash',$na,$before['revision'],fn($id,$cap)=>$images->writeInOwned($a,7,101,$id,$canonical,$cap),NativeCompanyContactImage::tables());
essentialCheck($images->read($a,7,101,$na)['content']===$canonical['content'],'actual private avatar roundtrips through owner-bound storage');
essentialThrows(fn()=>$images->read($b,7,101,$na),'company_client_access_denied','shared/foreign issuer cannot read contact avatar');
// Exercise the original admin controller with real stored image bytes. Framework
// context/output are doubles; this is not an authenticated HTTP/browser test.
#[AllowDynamicProperties] class AdminController {}
class CompanyRequestScope {
    public static int $issuer; public static int $primaryIssuer=0; public static int $actor=7; public static bool $primary=false;
    public static function nativeFor($ci){return $ci instanceof Clients;}
    public static function context(){return new class {
        function issuerId(){return CompanyRequestScope::$primary?CompanyRequestScope::$primaryIssuer:CompanyRequestScope::$issuer;}
        function staffId(){return CompanyRequestScope::$actor;}
        function isPrimary(){return CompanyRequestScope::$primary;}
    };}
    public static function assertNativeMutation($ci){if($ci->input->method(true)!=='POST'||$ci->input->post('_native_company_csrf')!=='synthetic-native-csrf')throw new DomainException('native_csrf_denied');}
    public static function invalidateCaches($ci){}
}
function show_error($message,$status=500){$GLOBALS['nativeAvatarStatus']=$status;}
function show_404(){$GLOBALS['nativeAvatarStatus']=404;}
require APPPATH.'controllers/admin/Clients.php';
$avatarController=new Clients();$avatarController->db=$db;
$avatarController->input=new class {public string $verb='GET';public array $body=[];function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}function post($key=null,$filter=false){return $key===null?$this->body:($this->body[$key]??null);}};
$avatarController->output=new class {
    public int $status=200;public string $body='';public string $type='';public array $headers=[];
    function set_status_header($value){$this->status=$value;return $this;}
    function set_header($value){$this->headers[]=$value;return $this;}
    function set_content_type($value){$this->type=$value;return $this;}
    function set_output($value){$this->body=$value;return $this;}
};
$avatarBefore=$db->query('SELECT * FROM '.$p.'company_native_contact_images WHERE contact_id=?',[$na])->row_array();
CompanyRequestScope::$primaryIssuer=(new IssuerResourceAccess($db,$p))->primaryId();
CompanyRequestScope::$issuer=$a;$avatarController->contact_profile_image('101',(string)$na);
essentialCheck($avatarController->output->body===$canonical['content']&&$avatarController->output->type==='image/png','Original contact avatar controller returns owner bytes and PNG MIME');
foreach(['Cache-Control: private, no-store','X-Content-Type-Options: nosniff',"Content-Security-Policy: default-src 'none'; sandbox"] as $header)essentialCheck(in_array($header,$avatarController->output->headers,true),'private image response retains protective header');
$avatarController->input->verb='HEAD';$avatarController->contact_profile_image('101',(string)$na);essentialCheck($avatarController->output->body==='','HEAD never returns image bytes');
$avatarController->input->verb='POST';$GLOBALS['nativeAvatarStatus']=200;$avatarController->contact_profile_image('101',(string)$na);essentialCheck($GLOBALS['nativeAvatarStatus']===405,'original image endpoint rejects mutation verb');
$avatarController->input->verb='GET';$avatarController->output->body='';$avatarController->output->status=200;CompanyRequestScope::$issuer=$b;$avatarController->contact_profile_image('101',(string)$na);essentialCheck($avatarController->output->status===403&&$avatarController->output->body!==$canonical['content'],'shared identity cannot access owner image through direct controller');
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$actor=999;$avatarController->output->status=200;$avatarController->contact_profile_image('101',(string)$na);essentialCheck($avatarController->output->status===403&&$avatarController->output->body!==$canonical['content'],'actor without issuer grant is denied');
// The principal uses the same official reader. This fixture has only an owned
// secondary customer, so the primary actor is denied by the ownership fence;
// the controller must not fall back to the legacy image endpoint or return a
// secondary image.
CompanyRequestScope::$actor=7;CompanyRequestScope::$primary=true;$GLOBALS['nativeAvatarStatus']=200;$avatarController->output->status=200;$avatarController->output->body='';$avatarController->contact_profile_image('101',(string)$na);essentialCheck(in_array($GLOBALS['nativeAvatarStatus'],[403,404],true)&&$avatarController->output->body!==$canonical['content'],'principal image route uses the official ownership fence');CompanyRequestScope::$primary=false;
essentialCheck($db->query('SELECT * FROM '.$p.'company_native_contact_images WHERE contact_id=?',[$na])->row_array()===$avatarBefore,'image requests never modify the stored bytes or metadata');

essentialThrows(fn()=>$images->writeInOwned($a,7,101,$na,$canonical),'issuer_owned_transaction_required','image cannot write outside contact transaction');
$previous=$db->query("SELECT * FROM {$p}company_native_contact_images WHERE contact_id=?",[$na])->row_array();$dto=$native->get($a,7,101,$na);
$db->failOn='UPDATE '.$p.'contacts SET profile_image';essentialThrows(fn()=>$native->save($a,7,101,$edit,'password_hash',$na,$dto['revision'],fn($id,$cap)=>$images->writeInOwned($a,7,101,$id,$canonical,$cap),NativeCompanyContactImage::tables()),'issuer_storage_failed','marker failure rolls back avatar and contact changes');$db->failOn=null;
essentialCheck($db->query("SELECT * FROM {$p}company_native_contact_images WHERE contact_id=?",[$na])->row_array()===$previous,'failed avatar revision does not leak partial blob');
$db->query("UPDATE {$p}company_native_contact_images SET content=? WHERE contact_id=?",['tampered',$na]);essentialThrows(fn()=>$images->read($a,7,101,$na),'issuer_contact_image_unconfirmed','tampered stored avatar rejected before output');
echo 'Native contact private image: '.($GLOBALS['essentialChecks']-$start).' checks passed; GD actual, no filesystem uploads.'.PHP_EOL;

$db->query("UPDATE {$p}company_native_contact_images SET content=? WHERE contact_id=?",[$canonical['content'],$na]);$dto=$native->get($a,7,101,$na);
$images->remove($a,7,101,$na,$dto['revision']);essentialCheck($images->read($a,7,101,$na)===null,'owner can remove private image without filesystem deletion');
essentialThrows(fn()=>$images->remove($a,7,101,$na,$dto['revision']),'company_client_stale','stale image remove cannot overwrite newer contact');
$dto=$native->get($a,7,101,$na);$native->save($a,7,101,$edit,'password_hash',$na,$dto['revision'],fn($id,$cap)=>$images->writeInOwned($a,7,101,$id,$canonical,$cap),NativeCompanyContactImage::tables());
$dto=$native->get($a,7,101,$na);$avatarController->input->verb='POST';$avatarController->input->body=['client_id'=>'101','revision'=>$dto['revision']];$avatarController->output->status=200;
$avatarController->delete_contact_profile_image((string)$na);essentialCheck($avatarController->output->status>=400&&$images->read($a,7,101,$na)!==null,'image removal requires native CSRF');
$avatarController->input->body['_native_company_csrf']='synthetic-native-csrf';CompanyRequestScope::$issuer=$b;$avatarController->output->status=200;$avatarController->delete_contact_profile_image((string)$na);
essentialCheck($avatarController->output->status===403&&$images->read($a,7,101,$na)!==null,'other issuer cannot remove owner image');
CompanyRequestScope::$issuer=$a;$avatarController->output->status=200;$avatarController->delete_contact_profile_image((string)$na);
essentialCheck((json_decode($avatarController->output->body,true)['success']??false)&&$images->read($a,7,101,$na)===null,'owner removes image through original controller');
$avatarController->output->status=200;$avatarController->delete_contact_profile_image((string)$na);essentialCheck($avatarController->output->status===409,'stale image removal is rejected');
