<?php
require __DIR__.'/portal_operations_cleanup.php';require __DIR__.'/operation_files_setup.php';require __DIR__.'/portal_operations_schema_fixture.php';
$people=new CompanyContacts($db,$p);$portal=new CompanyPortal($db,$p,str_repeat('synthetic-',4));$sessions=[];$contactIds=[];$portalReader=new CompanyPortalOperations($db,$p,$portal);$publication=new CompanyOperationPublication($db,$p);$projectWriter=new CompanyProjects($db,$p);$portalParents=[];$portalProofs=[];$pdf="%PDF-1.4\nSynthetic private publication evidence\n%%EOF\n";
foreach([[$a,$ca],[$b,$cb],[$primary,$cp]] as [$owner,$client]){
 $contact=$people->save($owner,7,$client,['firstname'=>'Portal','lastname'=>'Operation','email'=>'operations@example.invalid']);$db->query('UPDATE '.$p.'contacts SET password=? WHERE id=?',[password_hash('synthetic-operation-password',PASSWORD_DEFAULT),$contact]);$sessions[$owner]=$portal->login($owner,'operations@example.invalid','synthetic-operation-password','password_verify');$contactIds[$owner]=$contact;
 foreach(['contracts','tickets','projects'] as $kind){
  if($kind==='contracts'){$id=$contracts->create($owner,7,array_replace($contractInput,['client_id'=>$client]),$key('portal-parent-'.$kind.'-'.$owner));$contracts->transition($owner,7,$id,1,'activate',$key('portal-active-'.$owner));$rev=2;(new CompanyOperationFiles($db,$p))->upload($owner,7,'contract',$id,$rev,$key('portal-file-'.$kind.'-'.$owner),'proof.pdf',$pdf);}
  elseif($kind==='tickets'){$id=$tickets->create($owner,7,array_replace($ticketInput,['client_id'=>$client]),$key('portal-parent-'.$kind.'-'.$owner));$tickets->reply($owner,7,$id,1,'Never automatically publish internal reply',$key('portal-internal-'.$owner));$rev=2;(new CompanyOperationFiles($db,$p))->upload($owner,7,'ticket',$id,$rev,$key('portal-file-'.$kind.'-'.$owner),'proof.pdf',$pdf);}
  else{$id=$projectWriter->create($owner,7,['client_id'=>$client,'responsible_id'=>7,'name'=>'Portal project '.$owner,'description'=>'Explicit project description','start_date'=>'2026-10-19','due_date'=>'2026-11-19'],$key('portal-parent-'.$kind.'-'.$owner));$rev=1;(new CompanyProjectExtras($db,$p))->upload($owner,7,'project',$id,$rev,$key('portal-file-'.$kind.'-'.$owner),'proof.pdf',$pdf);}
  $portalParents[$kind][$owner]=$id;$portalProofs[$kind][$owner]=$key('portal-file-'.$kind.'-'.$owner);
 }
}
