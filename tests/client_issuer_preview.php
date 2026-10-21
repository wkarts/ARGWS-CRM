<?php
require __DIR__.'/support/client_issuer_test_support.php';
class App_Controller { public $db; public $load; }
class PreviewDenied extends RuntimeException {}
function show_404(){throw new PreviewDenied();}
function force_download($path,$data){$GLOBALS['preview_downloads'][]=$path;}
function is_staff_logged_in(){return $GLOBALS['preview_staff']??false;}
function is_client_logged_in(){return $GLOBALS['preview_client']??false;}
function is_customer_admin($id){return false;}
function total_rows($table,$where){return count(get_instance()->db->where($where)->get($table)->result_array());}
require __DIR__.'/../application/controllers/Download.php';
$root=sys_get_temp_dir().'/client_previews_'.bin2hex(random_bytes(4));mkdir($root);define('FCPATH',$root.'/');
$GLOBALS['client_test_upload_paths']=['customer'=>$root.'/uploads/clients/','invoice'=>$root.'/uploads/invoices/'];
foreach([1,2,3] as $id){mkdir($root.'/uploads/clients/'.$id,0777,true);file_put_contents($root.'/uploads/clients/'.$id.'/proof.jpg','synthetic');file_put_contents($root.'/uploads/clients/'.$id.'/proof_thumb.jpg','synthetic thumb');}
mkdir($root.'/uploads/company',0777,true);file_put_contents($root.'/uploads/company/logo.jpg','synthetic logo');
$pdo->exec("UPDATE q_files SET file_name='proof.jpg' WHERE id IN(1,2); INSERT INTO q_files VALUES(4,3,'customer','proof.jpg','',1); CREATE TABLE q_shared_customer_files(file_id INT,contact_id INT); INSERT INTO q_shared_customer_files VALUES(1,10)");
$download=(new ReflectionClass(Download::class))->newInstanceWithoutConstructor();$download->db=$model->db;
$guard=new ReflectionMethod(Download::class,'assert_legacy_preview_path');
$download->load=new class { public function model($name) {} };
$pdo->exec("ALTER TABLE q_files ADD attachment_key TEXT; UPDATE q_files SET attachment_key='customer-'||id");
function allowedPreview($path,$message){global $download,$guard;$guard->invoke($download,$path);check(true,$message);}
function deniedPreview($path,$message){global $download,$guard;try{$guard->invoke($download,$path);}catch(PreviewDenied $e){check(true,$message);return;}throw new RuntimeException($message);}
try{
 foreach(['proof.jpg','proof_thumb.jpg'] as $file){
  deniedPreview($root.'/uploads/clients/1/'.$file,'anonymous cannot preview legacy customer file');
  $GLOBALS['preview_staff']=true;allowedPreview($root.'/uploads/clients/1/'.$file,'authorized staff preview preserved');
  foreach([2,3] as $id)deniedPreview($root.'/uploads/clients/'.$id.'/'.$file,'private X/Y blocked before preview');
  $GLOBALS['preview_staff']=false;$GLOBALS['preview_client']=true;allowedPreview($root.'/uploads/clients/1/'.$file,'shared own contact preview preserved');
  $pdo->exec('DELETE FROM q_shared_customer_files');deniedPreview($root.'/uploads/clients/1/'.$file,'revoked contact preview blocked');
  $pdo->exec('INSERT INTO q_shared_customer_files VALUES(1,10)');$GLOBALS['preview_client']=false;
 }
 $GLOBALS['preview_staff']=true;$GLOBALS['client_test_staff_can']=false;deniedPreview($root.'/uploads/clients/1/proof.jpg','staff without customer permission denied');
 $GLOBALS['client_test_staff_can']=true;file_put_contents($root.'/uploads/clients/1/orphan.jpg','synthetic orphan');deniedPreview($root.'/uploads/clients/1/orphan.jpg','file without attachment row denied');unlink($root.'/uploads/clients/1/orphan.jpg');
 $GLOBALS['preview_staff']=false;allowedPreview($root.'/uploads/company/logo.jpg','public company logo unchanged');
 // Execute the public controller entrypoint, not only the preview guard.
 foreach([false,true] as $staff){
  $GLOBALS['preview_staff']=$staff;
  foreach([1,2,4] as $id){
   $GLOBALS['preview_downloads']=[];$GLOBALS['effects']=[];
   try{$download->file('sales_attachment','customer-'.$id);throw new RuntimeException('customer key reached sales download');}
   catch(PreviewDenied $e){check(true,'sales route rejects legacy/private X/private Y customer keys');}
   check($GLOBALS['preview_downloads']===[] && !in_array('download_file_path',$GLOBALS['effects'],true),'rejection occurs before path filter or output');
  }
 }
 foreach(['estimate','proposal','credit_note','debit_note'] as $index=>$type){
  $id=50+$index;$key='synthetic-sales-'.$type;
  $pdo->prepare("INSERT INTO q_files VALUES(?,1,?,'synthetic.pdf','',1,?)")->execute([$id,$type,$key]);
  $GLOBALS['preview_staff']=false;$GLOBALS['preview_downloads']=[];
  $download->file('sales_attachment',$key);
  check(count($GLOBALS['preview_downloads'])===1,'existing '.$type.' sales attachment route preserved');
 }
 $pdo->exec("INSERT INTO q_files VALUES(99,1,'task','private.txt','',1,'synthetic-task')");
 try{$download->file('sales_attachment','synthetic-task');throw new RuntimeException('task key reached sales download');}
 catch(PreviewDenied $e){check(true,'other non-sales attachment type rejected');}

}finally{
 foreach([1,2,3] as $id){foreach(['proof.jpg','proof_thumb.jpg'] as $file)unlink($root.'/uploads/clients/'.$id.'/'.$file);rmdir($root.'/uploads/clients/'.$id);}
 unlink($root.'/uploads/company/logo.jpg');rmdir($root.'/uploads/company');rmdir($root.'/uploads/clients');rmdir($root.'/uploads');rmdir($root);
}
echo "Client preview boundaries: $n checks passed (real guard/SQL and synthetic local files).\n";
