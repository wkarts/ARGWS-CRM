<?php
require __DIR__.'/../client_issuer_preview.php';
require __DIR__.'/../../application/services/InvoiceIssuerScope.php';
require __DIR__.'/../../application/services/TaskClientBoundary.php';
require __DIR__.'/../../application/services/CustomerRelationBoundary.php';
function is_logged_in(){return is_staff_logged_in()||is_client_logged_in();}
$before=$n;
$pdo->exec("CREATE TABLE q_contracts(id INT,client INT); INSERT INTO q_contracts VALUES(1,1),(2,2); CREATE TABLE q_projects(id INT,clientid INT); INSERT INTO q_projects VALUES(1,1),(2,2); CREATE TABLE q_expenses(id INT,clientid INT); INSERT INTO q_expenses VALUES(1,1),(2,2); CREATE TABLE q_tickets(ticketid INT,userid INT,contactid INT); INSERT INTO q_tickets VALUES(1,1,10),(2,2,20); CREATE TABLE q_tasks(id INT,rel_type TEXT,rel_id INT); INSERT INTO q_tasks VALUES(1,'customer',1),(2,'customer',2)");
mkdir($root.'/uploads',0700,true);
try{
 foreach(['project','expense','ticket','task','contract']as$kind){
  $GLOBALS['client_test_upload_paths'][$kind]=$root.'/uploads/'.$kind.'/';
  foreach([1,2]as$id){mkdir($root.'/uploads/'.$kind.'/'.$id,0700,true);file_put_contents($root.'/uploads/'.$kind.'/'.$id.'/proof.jpg','synthetic');}
  $GLOBALS['preview_staff']=true;
  allowedPreview($root.'/uploads/'.$kind.'/1/proof.jpg','legacy '.$kind.' path retained by ownership guard');
  foreach([false,true]as$staff){$GLOBALS['preview_staff']=$staff;deniedPreview($root.'/uploads/'.$kind.'/2/proof.jpg','private '.$kind.' cannot be previewed by guessed path');}
 }
 $GLOBALS['preview_staff']=true;
 $pdo->exec("INSERT INTO q_files VALUES(201,2,'expense','proof.jpg','',1,'private-expense'),(202,2,'task','proof.jpg','',1,'private-task')");
 foreach([['expense',2],['taskattachment','private-task']]as[$kind,$id]){
  $GLOBALS['preview_downloads']=[];$GLOBALS['effects']=[];
  try{$download->file($kind,$id);throw new RuntimeException('private related file reached download');}catch(PreviewDenied $expected){}
  check($GLOBALS['preview_downloads']===[] && !in_array('download_file_path',$GLOBALS['effects'],true),'real related download rejected before path hooks or output');
 }
}finally{
 foreach(['project','expense','ticket','task','contract']as$kind){foreach([1,2]as$id){unlink($root.'/uploads/'.$kind.'/'.$id.'/proof.jpg');rmdir($root.'/uploads/'.$kind.'/'.$id);}rmdir($root.'/uploads/'.$kind);}
 rmdir($root.'/uploads');rmdir($root);
}
echo 'Related file privacy: '.($n-$before)." additional checks passed.\n";
