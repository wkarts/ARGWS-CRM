<?php
require __DIR__.'/native_estimate_form_create.php';
require_once APPPATH.'services/NativeCompanyResourceTags.php';
NativeCompanyWorkTagsSchema::ensure($db,$p);
$nativeSeed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(['tags','taggables'] as $nativeTable)if(!$db->table_exists($p.$nativeTable)){
 if(!preg_match('/CREATE TABLE `tbl'.preg_quote($nativeTable,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$nativeSeed,$match))throw new RuntimeException('Native tags schema missing');
 $sql=str_replace('`tbl'.$nativeTable.'`','`'.$p.$nativeTable.'`',$match[0]);
 $sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
 if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);
 $db->query($sql);
}
unset($db->data_cache['table_names']);
$start=$GLOBALS['essentialChecks'];$fresh=$sequence->readForCreation($a,7,'estimates');
$tagged=$wire;$tagged['_native_estimate_request_key']=hash('sha256','native-estimate-tags');$tagged['_native_estimate_number_revision']=$fresh['revision'];$tagged['number']=$fresh['next_number'];$tagged['tags']='Urgente,Equipe';
$result=$form->create($a,7,$tagged);$tags=new NativeCompanyResourceTags($db,$p);
essentialCheck(array_column($tags->read($a,7,'estimate',$result['id'])['tags'],'name')===['Urgente','Equipe'],'estimate tags are persisted in the actual owned catalogue');
essentialCheck($form->create($a,7,$tagged)===$result,'tagged creation is idempotent');
$different=$tagged;$different['tags']='Outro';essentialThrows(fn()=>$form->create($a,7,$different),'company_sales_conflict','tagged retry cannot alter the source payload');
essentialThrows(fn()=>$tags->read($b,7,'estimate',$result['id']),'native_resource_reference_denied','other company cannot read tags');
$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_sales_documents')->row()->n;$fresh=$sequence->readForCreation($a,7,'estimates');$failing=$tagged;$failing['_native_estimate_request_key']=hash('sha256','native-estimate-tags-failure');$failing['_native_estimate_number_revision']=$fresh['revision'];$failing['number']=$fresh['next_number'];$failing['tags']='Failing';$db->failOn='INSERT INTO '.$p.'company_work_tag_links';
essentialThrows(fn()=>$form->create($a,7,$failing),'issuer_storage_failed','tag-link failure aborts estimate');$db->failOn=null;
essentialCheck($sequence->readForCreation($a,7,'estimates')===$fresh&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_sales_documents')->row()->n===$count,'tag failure rolls back document and sequence');
echo 'Native estimate tag creation: '.($GLOBALS['essentialChecks']-$start)." checks.\n";
$review=$sales->review($a,7,$result['source_id'],'copy');$intent=['source_revision'=>$review['source_revision'],'destination_revision'=>$review['destination_revision'],'reason'=>'Synthetic current tags copy','copy_confirmed'=>'1'];
$beforeTags=$tags->read($a,7,'estimate',$result['id']);
IssuerTransaction::run($db,$p,array_merge(NativeCompanyResourceTags::tableNames(),['company_sales_documents']),fn()=>$tags->replaceInOwnedTransaction($a,7,'estimate',$result['id'],$beforeTags['revision'],['Atualizada']));
essentialThrows(fn()=>$sales->copy($a,7,$result['source_id'],$review['payload'],$intent,hash('sha256','native-estimate-copy-stale-tags')),'company_sales_stale','copy review detects changed source tags');
$review=$sales->review($a,7,$result['source_id'],'copy');essentialCheck(($review['payload']['native_tags']??null)===['Atualizada'],'copy review uses current catalogue instead of creation snapshot');
$intent=['source_revision'=>$review['source_revision'],'destination_revision'=>$review['destination_revision'],'reason'=>'Synthetic current tags copy','copy_confirmed'=>'1'];$copy=$sales->copy($a,7,$result['source_id'],$review['payload'],$intent,hash('sha256','native-estimate-copy-current-tags'));
essentialCheck(array_column($tags->read($a,7,'estimate',NativeCompanyResourceId::encode('company_estimate',$copy))['tags'],'name')===['Atualizada'],'copy stores current tags in destination catalogue');
echo "Native estimate copy current tags: 3 checks.\n";
