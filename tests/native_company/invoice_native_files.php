<?php
require __DIR__.'/invoice_attachment_owned.php';
require_once APPPATH.'services/NativeCompanyInvoiceFiles.php';
if(!$db->table_exists($p.'files'))$db->query("CREATE TABLE {$p}files(id INTEGER PRIMARY KEY AUTOINCREMENT,rel_id INT,rel_type VARCHAR(20),file_name VARCHAR(191),filetype VARCHAR(191),visible_to_customer INT,external VARCHAR(40),external_link TEXT,thumbnail_link TEXT,staffid INT,contact_id INT,task_comment_id INT,dateadded DATETIME,attachment_key VARCHAR(32) UNIQUE)");
NativeCompanyInvoiceFileSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$nativeFiles=new NativeCompanyInvoiceFiles($db,$p);$request=hash('sha256','real-native-file');
$n=$nativeFiles->upload($a,7,$ia,$request,'native.pdf',$pdf);$again=$nativeFiles->upload($a,7,$ia,$request,'native.pdf',$pdf);
essentialCheck($n['id']===$again['id']&&strlen($n['attachment_key'])===32,'real file ID and native download key replay without duplicate');
essentialCheck(NativeCompanyInvoiceFiles::nativeMetadata($n)['can_delete']===true,'upload receipt preserves authorized uploader delete control');
$f=$db->query("SELECT * FROM {$p}files WHERE id=?",[$n['id']])->row_array();essentialCheck($f['rel_type']==='invoice'&&(int)$f['rel_id']===$ia,'projection uses actual native files metadata');
essentialCheck($nativeFiles->readKey($a,7,$n['attachment_key'])['content']===$pdf,'native key read verifies private authority bytes');
essentialThrows(fn()=>$nativeFiles->read($b,7,$n['id']),'native_invoice_file_denied','other issuer cannot hydrate same native file ID');
essentialThrows(fn()=>$nativeFiles->read($a,7,$n['id'],2),'native_invoice_file_conflict','stale revision rejected before content');
$rev=$nativeFiles->visibility($a,7,$n['id'],1,true);essentialCheck($rev===2&&$nativeFiles->read($a,7,$n['id'],2)['portal_visible']==1,'visibility metadata and financial authority atomic');
essentialThrows(fn()=>$nativeFiles->visibility($a,7,$n['id'],1,false),'native_invoice_file_conflict','stale visibility does not update any state');
if($db->driver()==='sqlite'){
    $db->query("CREATE TRIGGER native_projection_ignore BEFORE INSERT ON {$p}company_invoice_file_projection BEGIN SELECT RAISE(IGNORE); END");
    $bad=hash('sha256','projection-ignore');$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}files")->row_array()['n'];
    essentialThrows(fn()=>$nativeFiles->upload($a,7,$ia,$bad,'ignore.pdf',$pdf),'native_invoice_file_denied','ignored projection cannot publish file identity');
    essentialCheck(!$db->query("SELECT file_key FROM {$p}company_invoice_attachments WHERE file_key=?",[$bad])->row_array()&&(int)$db->query("SELECT COUNT(*) n FROM {$p}files")->row_array()['n']===$before,'ignored projection rolls back both metadata and blob');
    $db->query('DROP TRIGGER native_projection_ignore');
}
echo "Native invoice files identity/visibility/rollback PASS\n";
require_once APPPATH.'services/NativeCompanyInvoiceFileBackfill.php';
$blobBefore=$db->query("SELECT * FROM {$p}company_invoice_attachments ORDER BY file_key")->result_array();
if($db->driver()==='sqlite'){
    $before=(int)$db->query("SELECT COUNT(*) n FROM {$p}files")->row_array()['n'];
    $db->query("CREATE TRIGGER native_backfill_ignore BEFORE INSERT ON {$p}company_invoice_file_projection BEGIN SELECT RAISE(IGNORE); END");
    essentialThrows(fn()=>NativeCompanyInvoiceFileBackfill::run($db,$p),'issuer_native_invoice_file_backfill_unconfirmed','backfill ignored receipt rolls back all new identities');
    essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}files")->row_array()['n']===$before,'failed backfill leaves no metadata');$db->query('DROP TRIGGER native_backfill_ignore');
}
NativeCompanyInvoiceFileBackfill::run($db,$p);$projected=$db->query("SELECT * FROM {$p}company_invoice_file_projection ORDER BY file_id")->result_array();NativeCompanyInvoiceFileBackfill::run($db,$p);
essentialCheck($projected===$db->query("SELECT * FROM {$p}company_invoice_file_projection ORDER BY file_id")->result_array(),'backfill exact replay preserves native IDs and revisions');
essentialCheck($blobBefore===$db->query("SELECT * FROM {$p}company_invoice_attachments ORDER BY file_key")->result_array(),'backfill does not rewrite historical blobs/public keys/visibility');
essentialCheck(count($nativeFiles->page($a,7,$ia))===count($files->page($a,7,$ia)),'native preview retains all historical active private attachments');
echo "Native invoice file backfill atomic/replay/history PASS\n";
$text=$nativeFiles->upload($a,7,$ia,hash('sha256','native-text'),'notes.txt','Plain file notes');essentialCheck($nativeFiles->read($a,7,$text['id'])['mime']==='text/plain','native configured TXT uses same private authority and actual file identity');
essentialThrows(fn()=>$nativeFiles->upload($a,7,$ia,hash('sha256','native-executable'),'bad.php','<?php echo 1;'),'native_file_type_not_allowed','configured native executable stays forbidden');
$largePdf="%PDF-1.4\n".str_repeat('x',2097152);$largeKey=hash('sha256','native-explicit-large');
IssuerTransaction::run($db,$p,CompanyInvoiceAttachments::tableNames(),fn()=>$files->uploadNativeInOwned($a,7,$ia,$largeKey,'large.pdf',$largePdf,'.pdf',20971520));
essentialCheck($files->download($a,7,$ia,$largeKey)['content']===$largePdf,'explicit native larger configured PDF remains verified downloadable');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,CompanyInvoiceAttachments::tableNames(),fn()=>$files->uploadNativeInOwned($a,7,$ia,hash('sha256','native-too-large'),'large.pdf',$largePdf,'.pdf',2097152)),'native_file_content_invalid','configured 2MiB still refuses oversized file');
echo "Native invoice configured file types and size policy PASS\n";
$externalKey=hash('sha256','native-external');$external=$nativeFiles->addExternal($a,7,$ia,$externalKey,'Reference','gdrive','https://drive.google.com/file/d/synthetic/view');
essentialCheck($nativeFiles->addExternal($a,7,$ia,$externalKey,'Reference','gdrive','https://drive.google.com/file/d/synthetic/view')['id']===$external['id'],'external reference replay uses same real files ID');
essentialThrows(fn()=>$nativeFiles->readKey($a,7,$external['attachment_key']),'native_file_external_no_content','external reference never becomes a server fetch or fake content');
essentialThrows(fn()=>$nativeFiles->addExternal($a,7,$ia,hash('sha256','ssrf'),'Reference','gdrive','https://127.0.0.1/private'),'native_file_external_invalid','external localhost source denied');
essentialThrows(fn()=>$nativeFiles->addExternal($a,7,$ia,hash('sha256','userinfo'),'Reference','gdrive','https://x@drive.google.com/file'),'native_file_external_invalid','external userinfo denied');
essentialThrows(fn()=>$nativeFiles->read($b,7,$external['id']),'native_invoice_file_denied','external reference remains issuer-bound');
$externalRevision=$nativeFiles->visibility($a,7,$external['id'],1,true);essentialCheck($externalRevision===2,'external visibility uses same revision contract');
$meta=NativeCompanyInvoiceFiles::nativeMetadata($nativeFiles->read($a,7,$external['id']));essentialCheck($meta['external']==='gdrive'&&$meta['external_link']==='https://drive.google.com/file/d/synthetic/view'&&!isset($meta['content']),'native external DTO preserves safe original link without blob');
echo "Native external references: 7 checks PASS, no network performed\n";
$nativeFiles->remove($a,7,$external['id'],2);$nativeFiles->remove($a,7,$external['id'],2);
essentialThrows(fn()=>$nativeFiles->read($a,7,$external['id']),'native_invoice_file_conflict','deleted external metadata cannot be read despite retained native files history');
$nativeFiles->remove($a,7,$text['id'],1);essentialCheck(!$db->query("SELECT file_key FROM {$p}company_invoice_attachments WHERE file_key=? AND active=1",[$text['file_key']])->row_array(),'native delete retains but hides private backend content');
echo "Native file removal/replay preserves history PASS\n";
$legacyInvoiceBefore=$db->query("SELECT * FROM {$p}invoices WHERE id=42")->row_array();
$legacyFile=$nativeFiles->upload($principal,7,42,hash('sha256','native-null-legacy'),'legacy-new.txt','New private attachment');
essentialCheck($nativeFiles->read($principal,7,$legacyFile['id'])['content']==='New private attachment'&&$db->query("SELECT * FROM {$p}invoices WHERE id=42")->row_array()===$legacyInvoiceBefore,'new primary NULL upload has explicit private origin without rewriting invoice');
essentialThrows(fn()=>$nativeFiles->read($a,7,$legacyFile['id']),'native_invoice_file_denied','named company cannot follow principal native file identity');
essentialThrows(fn()=>$nativeFiles->upload($a,7,42,hash('sha256','named-null-denied'),'x.txt','No adoption'),'company_invoice_access_denied','named native upload cannot adopt NULL invoice');
echo "Native primary NULL attachment explicit lineage PASS\n";
$root=sys_get_temp_dir().'/native-invoice-history-'.bin2hex(random_bytes(8));mkdir($root);mkdir($root.'/42');if(!defined('INVOICE_ATTACHMENTS_FOLDER'))define('INVOICE_ATTACHMENTS_FOLDER',$root.'/');
file_put_contents($root.'/42/old.txt','Historical file bytes');
try{
    $video=pack('N',24).'ftypisom'.pack('N',512).'isomiso2';
    file_put_contents($root.'/42/clip.mp4',$video);file_put_contents($root.'/42/clip.bin',$video);
    essentialCheck(NativeCompanyLegacyInvoiceContent::read(42,'clip.mp4')['mime']==='video/mp4','historical native video MIME verified by bytes and matching extension');
    essentialCheck(NativeCompanyLegacyInvoiceContent::read(42,'clip.bin')['mime']==='application/octet-stream','video bytes with unsupported extension remain forced-download');
    file_put_contents($root.'/42/clip.mp4','<html><script>alert(1)</script></html>');
    essentialCheck(NativeCompanyLegacyInvoiceContent::read(42,'clip.mp4')['mime']==='application/octet-stream','HTML named as video cannot receive video MIME');
    unlink($root.'/42/clip.mp4');unlink($root.'/42/clip.bin');
    $historical=IssuerTransaction::run($db,$p,NativeCompanyInvoiceFiles::tables(),fn()=>NativeCompanyFileRows::insertInOwned($db,$p,['rel_id'=>42,'rel_type'=>'invoice','file_name'=>'old.txt','filetype'=>'text/plain','visible_to_customer'=>1,'external'=>null,'external_link'=>null,'thumbnail_link'=>null,'staffid'=>7,'contact_id'=>0,'task_comment_id'=>0,'dateadded'=>'2020-01-01 00:00:00']));
    NativeCompanyInvoiceFileBackfill::run($db,$p);NativeCompanyInvoiceFileBackfill::run($db,$p);
    $legacy=$nativeFiles->read($principal,7,$historical);essentialCheck($legacy['content']==='Historical file bytes','proven primary native historical file reads canonical old path');
    essentialThrows(fn()=>$nativeFiles->read($a,7,$historical),'native_invoice_file_denied','historical native file never follows another issuer');
    rename($root.'/42/old.txt',$root.'/original.txt');symlink($root.'/original.txt',$root.'/42/old.txt');
    essentialThrows(fn()=>$nativeFiles->read($principal,7,$historical),'native_invoice_file_denied','historical symlink never releases bytes');unlink($root.'/42/old.txt');rename($root.'/original.txt',$root.'/42/old.txt');
    $db->query("UPDATE {$p}files SET file_name='../old.txt' WHERE id=?",[$historical]);essentialThrows(fn()=>$nativeFiles->read($principal,7,$historical),'native_invoice_file_denied','metadata path tamper fails fingerprint before filesystem');$db->query("UPDATE {$p}files SET file_name='old.txt' WHERE id=?",[$historical]);
    $nativeFiles->remove($principal,7,$historical,1);essentialCheck(file_get_contents($root.'/42/old.txt')==='Historical file bytes','historical delete preserves physical bytes');
    essentialThrows(fn()=>$nativeFiles->readKey($principal,7,$legacy['attachment_key']),'native_invoice_file_conflict','revoked historical authority blocks old native key');
    essentialCheck((int)$db->query("SELECT visible_to_customer FROM {$p}files WHERE id=?",[$historical])->row_array()['visible_to_customer']===0,'historical revoke also removes old public visibility');
}finally{if(is_link($root.'/42/old.txt')||is_file($root.'/42/old.txt'))unlink($root.'/42/old.txt');if(is_file($root.'/original.txt'))unlink($root.'/original.txt');rmdir($root.'/42');rmdir($root);}
echo "Native primary historical filesystem lineage/links/revocation PASS\n";

$db->trans_begin();essentialThrows(fn()=>CompanyInvoiceAttachmentSchema::ensureNativeCapacity($db,$p),'issuer_outer_transaction_not_allowed','capacity DDL never enters caller transaction');$db->trans_rollback();

// Native deletion requires invoice authority AND uploader/admin, not edit alone.
$policyFile=$nativeFiles->upload($a,7,$ia,hash('sha256','delete-uploader-policy'),'policy.pdf',$pdf);
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12",[$a]);
foreach(['view','edit'] as $cap)if(!$db->query("SELECT staff_id FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature='invoices' AND capability=?",[$a,$cap])->row_array())$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'invoices',?)",[$a,$cap]);
$policyRows=$nativeFiles->page($a,12,$ia);$policyRow=array_values(array_filter($policyRows,fn($row)=>$row['id']===$policyFile['id']))[0];
essentialCheck($policyRow['can_delete']===false,'editor who is not uploader has no delete control');
essentialThrows(fn()=>$nativeFiles->remove($a,12,$policyFile['id'],1),'native_invoice_file_delete_denied','edit grant alone cannot delete another uploader file');
$db->query("UPDATE {$p}staff SET admin=1 WHERE staffid=12");
$policyRows=$nativeFiles->page($a,12,$ia);$policyRow=array_values(array_filter($policyRows,fn($row)=>$row['id']===$policyFile['id']))[0];essentialCheck($policyRow['can_delete']===true,'persisted admin plus invoice authority can delete');
$nativeFiles->remove($a,12,$policyFile['id'],1);$db->query("UPDATE {$p}staff SET admin=0 WHERE staffid=12");
essentialThrows(fn()=>$nativeFiles->remove($a,12,$policyFile['id'],1),'native_invoice_file_delete_denied','idempotent replay does not bypass revoked deletion authority');
echo "Native invoice uploader/admin deletion policy PASS\n";
