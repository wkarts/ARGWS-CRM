<?php
require __DIR__.'/native_contacts.php';
require APPPATH.'services/NativeCompanyContactImage.php';
$start=$GLOBALS['essentialChecks'];
if($db->driver()==='sqlite')$db->query("CREATE TABLE {$p}company_native_contact_images(contact_id INTEGER PRIMARY KEY NOT NULL,issuer_id INT NOT NULL,client_id INT NOT NULL,revision INT NOT NULL,mime TEXT NOT NULL,byte_size INT NOT NULL,sha256 TEXT NOT NULL,content BLOB NOT NULL,updated_by INT NOT NULL,updated_at TEXT NOT NULL)");else NativeCompanyContactImageSchema::ensure($db,$p);
unset($db->data_cache['table_names']);$image=imagecreatetruecolor(12,8);imagefilledrectangle($image,0,0,11,7,imagecolorallocate($image,24,60,90));ob_start();imagepng($image);$bytes=ob_get_clean();$image=null;
$canonical=NativeCompanyContactImage::normalize($bytes.'<script>synthetic</script>');essentialCheck($canonical['mime']==='image/png'&&!str_contains($canonical['content'],'script')&&$canonical===NativeCompanyContactImage::normalize($canonical['content']),'GD decode/reencode strips trailing metadata and is canonical');
foreach(['<svg xmlns="http://www.w3.org/2000/svg"></svg>',str_repeat('x',2097153),substr($bytes,0,20)]as$bad)essentialThrows(fn()=>NativeCompanyContactImage::normalize($bad),'company_contact_image_invalid','non-image/oversize/truncated input denied');
$large=imagecreatetruecolor(2049,1);ob_start();imagepng($large);$oversize=ob_get_clean();$large=null;essentialThrows(fn()=>NativeCompanyContactImage::normalize($oversize),'company_contact_image_invalid','pixel dimensions checked before decoding image');
$images=new NativeCompanyContactImage($db,$p);$before=$native->get($a,7,101,$na);$edit['password']='';$edit['title']=$before['contact']['title'];
$native->save($a,7,101,$edit,'password_hash',$na,$before['revision'],fn($id,$cap)=>$images->writeInOwned($a,7,101,$id,$canonical,$cap),NativeCompanyContactImage::tables());
essentialCheck($images->read($a,7,101,$na)['content']===$canonical['content'],'actual private avatar roundtrips through owner-bound storage');
essentialThrows(fn()=>$images->read($b,7,101,$na),'company_client_access_denied','shared/foreign issuer cannot read contact avatar');
essentialThrows(fn()=>$images->writeInOwned($a,7,101,$na,$canonical),'issuer_owned_transaction_required','image cannot write outside contact transaction');
$previous=$db->query("SELECT * FROM {$p}company_native_contact_images WHERE contact_id=?",[$na])->row_array();$dto=$native->get($a,7,101,$na);
$db->failOn='UPDATE '.$p.'contacts SET profile_image';essentialThrows(fn()=>$native->save($a,7,101,$edit,'password_hash',$na,$dto['revision'],fn($id,$cap)=>$images->writeInOwned($a,7,101,$id,$canonical,$cap),NativeCompanyContactImage::tables()),'issuer_storage_failed','marker failure rolls back avatar and contact changes');$db->failOn=null;
essentialCheck($db->query("SELECT * FROM {$p}company_native_contact_images WHERE contact_id=?",[$na])->row_array()===$previous,'failed avatar revision does not leak partial blob');
$db->query("UPDATE {$p}company_native_contact_images SET content=? WHERE contact_id=?",['tampered',$na]);essentialThrows(fn()=>$images->read($a,7,101,$na),'issuer_contact_image_unconfirmed','tampered stored avatar rejected before output');
echo 'Native contact private image: '.($GLOBALS['essentialChecks']-$start).' checks passed; GD actual, no filesystem uploads.'.PHP_EOL;

$db->query("UPDATE {$p}company_native_contact_images SET content=? WHERE contact_id=?",[$canonical['content'],$na]);$dto=$native->get($a,7,101,$na);
$images->remove($a,7,101,$na,$dto['revision']);essentialCheck($images->read($a,7,101,$na)===null,'owner can remove private image without filesystem deletion');
essentialThrows(fn()=>$images->remove($a,7,101,$na,$dto['revision']),'company_client_stale','stale image remove cannot overwrite newer contact');
