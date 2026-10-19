<?php
/** Native invoice DTO/TCPDF and private portal permission; no SMTP/provider requests. */
require __DIR__.'/invoices.php';
require_once APPPATH.'services/CompanyContacts.php';
require_once APPPATH.'services/CompanyPortalInvoices.php';
require_once APPPATH.'services/CompanyContactInvoiceAccess.php';
$native=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(['contacts','contact_permissions'] as $name){
    preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);if(!$match)throw new RuntimeException('native contact fixture missing');
    $sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$match[0]);$sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
    if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}$db->query($sql);
}
if($db->driver()==='mysql'){
    require_once APPPATH.'migrations/407_version_407.php';$m=new Migration_Version_407();$m->db=$db;$m->up();$m->up();
}else{
    $db->query("CREATE TABLE {$p}company_portal_tokens(token_hash varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,contact_id int NOT NULL,client_id int NOT NULL,identity_hash varchar(64) NOT NULL,expires_at datetime NOT NULL,created_at datetime NOT NULL)");
}
unset($db->data_cache['table_names']);
$contacts=new CompanyContacts($db,$p);$access=new CompanyContactInvoiceAccess($db,$p);$portal=new CompanyPortal($db,$p,str_repeat('synthetic-',4));$documents=new CompanyPortalInvoices($db,$p,$portal);
$person=['firstname'=>'Synthetic','lastname'=>'Portal','email'=>'same@example.invalid'];$contactA=$contacts->save($a,7,$ca,$person);$contactB=$contacts->save($b,7,$cb,$person);$password=password_hash('synthetic-portal-password',PASSWORD_DEFAULT);
$db->query("UPDATE {$p}contacts SET password=? WHERE id IN (?,?)",[$password,$contactA,$contactB]);
$sessionA=$portal->login($a,$person['email'],'synthetic-portal-password','password_verify');$sessionB=$portal->login($b,$person['email'],'synthetic-portal-password','password_verify');
essentialCheck($documents->page($sessionA,$a)===null,'new contact has no implicit financial access');
essentialThrows(fn()=>$documents->document($sessionA,$a,$id),'company_portal_invoice_unavailable','direct invoice ID cannot bypass native contact permission');
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(13,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,13,1)",[$a]);
foreach(['view','edit'] as $cap)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,13,'customers',?)",[$a,$cap]);
essentialThrows(fn()=>$access->page($a,13,$ca),'company_client_access_denied','customer editor without invoice view cannot inspect or grant financial portal permission');
$permission=$access->page($a,7,$ca)[$contactA];$access->set($a,7,$ca,$contactA,$permission['revision'],'1');
essentialCheck($access->page($a,7,$ca)[$contactA]['allowed'],'staff grant uses native permission1');
$mine=$documents->page($sessionA,$a);essentialCheck(count($mine['items'])>=1&&count(array_filter($mine['items'],fn($row)=>(int)$row['id']===$id))===1,'authorized portal lists own named invoice');
essentialCheck(count(array_filter($mine['items'],fn($row)=>(int)$row['id']===42||(int)$row['id']===$ib))===0,'NULL legacy history and B invoice stay outside private portal');
essentialCheck($documents->page($sessionA,$b)===null&&$documents->page($sessionB,$b)===null,'A session and B ungranted contact cannot inherit financial permission');
essentialThrows(fn()=>$documents->document($sessionA,$b,$ib),'company_portal_invoice_unavailable','cross-company direct PDF denied');
essentialThrows(fn()=>$documents->document($sessionA,$a,$ib),'company_portal_invoice_unavailable','foreign invoice ID denied even under A context');
essentialThrows(fn()=>$documents->document($sessionA,$a,42),'company_portal_invoice_unavailable','NULL legacy invoice is never adopted into named portal');
$other=$customers->create($a,7,['company'=>'Other A private customer']);$otherInvoice=$s->create($a,7,array_replace($input,['client_id'=>$other]),str_repeat('5',64));
essentialThrows(fn()=>$documents->document($sessionA,$a,$otherInvoice),'company_portal_invoice_unavailable','same-company different customer invoice denied');
$dto=$documents->document($sessionA,$a,$id);$pdf=(new Issuer_invoice_pdf($dto))->prepare()->Output('','S');essentialCheck(str_starts_with($pdf,'%PDF-'),'portal renders real authorized TCPDF bytes');$pdfHash=hash('sha256',$pdf);$documents->confirmDownload($sessionA,$a,$id,$pdfHash);
$record=$db->query("SELECT metadata_json FROM {$p}issuers_audit WHERE event='company_portal_invoice_pdf_downloaded'")->row_array();$evidence=json_decode($record['metadata_json'],true,16,JSON_THROW_ON_ERROR);
essentialCheck($evidence['contact_id']===$contactA&&$evidence['sha256']===$pdfHash&&!str_contains($record['metadata_json'],$person['email']),'audited PDF delivery identifies contact and bytes without email or bearer');
// Identity sharing permits independent issuance in B; it never grants A's contact access to that B document.
$customers->setSharing($a,7,$ca,[$b]);$sharedB=$s->create($b,7,$input,str_repeat('6',64));
essentialThrows(fn()=>$documents->document($sessionA,$a,$sharedB),'company_portal_invoice_unavailable','shared recipient identity never shares B financial history');
essentialThrows(fn()=>$access->page($b,7,$ca),'company_client_access_denied','destination staff cannot grant permissions to owner contact');
// Native non-invoice permissions survive grant/revoke; stale editor cannot restore a revoked grant.
$db->query("INSERT INTO {$p}contact_permissions(permission_id,userid) VALUES(2,?)",[$contactA]);$permission=$access->page($a,7,$ca)[$contactA];$access->set($a,7,$ca,$contactA,$permission['revision'],'0');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}contact_permissions WHERE userid=? AND permission_id=2",[$contactA])->row()->n===1,'other native contact permissions preserved');
essentialThrows(fn()=>$access->set($a,7,$ca,$contactA,$permission['revision'],'1'),'company_client_stale','stale grant form cannot override revocation');
essentialCheck($portal->sessionIdentity($sessionA,$a)!==null&&$documents->page($sessionA,$a)===null,'financial revocation takes effect without inventing logout or sharing identity');
essentialThrows(fn()=>$documents->confirmDownload($sessionA,$a,$id,$pdfHash),'company_portal_invoice_unavailable','revocation after rendering denies PDF delivery');
$permission=$access->page($a,7,$ca)[$contactA];$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$access->set($a,7,$ca,$contactA,$permission['revision'],'1'),'issuer_storage_failed','grant and its audit roll back together');$db->failOn=null;
essentialCheck($documents->page($sessionA,$a)===null,'failed audit grants no portal financial permission');
$access->set($a,7,$ca,$contactA,$permission['revision'],'1');$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$documents->confirmDownload($sessionA,$a,$id,$pdfHash),'issuer_storage_failed','audit failure prevents authorized PDF delivery');$db->failOn=null;
$db->query("UPDATE {$p}contacts SET password=? WHERE id=?",[password_hash('changed-password',PASSWORD_DEFAULT),$contactA]);
essentialCheck($documents->page($sessionA,$a)===null,'password change invalidates existing invoice session');
echo 'Private portal invoices/PDF/native grants: '.$GLOBALS['essentialChecks'].' cumulative checks ('.$db->driver().'); no bank/SMTP calls.'.PHP_EOL;
