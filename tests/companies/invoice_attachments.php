<?php
/** Private invoice files, explicit portal visibility and native session/permission authority. */
require __DIR__.'/portal_invoices.php';
require_once APPPATH.'services/CompanyInvoiceAttachments.php';
if($db->driver()==='mysql'){
    require_once APPPATH.'migrations/423_version_423.php';$m=new Migration_Version_423();$m->db=$db;$m->up();$m->up();
}else{
    $db->query("CREATE TABLE {$p}company_invoice_attachments(file_key varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,actor_id int NOT NULL,invoice_id int NOT NULL,filename varchar(191) NOT NULL,mime varchar(40) NOT NULL,content_hash varchar(64) NOT NULL,byte_size int unsigned NOT NULL,content blob NOT NULL,portal_visible tinyint NOT NULL,active tinyint NOT NULL,created_at datetime NOT NULL)");
}
unset($db->data_cache['table_names']);$files=new CompanyInvoiceAttachments($db,$p);
$sessionA=$portal->login($a,$person['email'],'changed-password','password_verify');
$picture=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAMAAAACCAIAAAASFvFNAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgwAQAABQAAWX1h1kAAAAASUVORK5CYII=');
$fa=hash('sha256','private-file-a');$fb=hash('sha256','visible-file-a');$fc=hash('sha256','visible-file-b');
// Use fresh emitted invoices; the earlier B fixture intentionally cancelled its first invoice.
$ia=$s->create($a,7,$input,hash('sha256','attachment-invoice-a'));$ib=$s->create($b,7,array_replace($input,['client_id'=>$cb]),hash('sha256','attachment-invoice-b'));
essentialCheck($files->upload($a,7,$ia,$fa,'evidencia.png',$picture)===$fa&&$files->upload($a,7,$ia,$fa,'evidencia.png',$picture)===$fa,'upload retry persists one immutable private blob');
$files->upload($a,7,$ia,$fb,'evidencia-publicada.pdf',$pdf,true);$files->upload($b,7,$ib,$fc,'outra-empresa.pdf',$pdf,true);
essentialCheck(count($files->page($a,7,$ia))===2&&count($files->page($b,7,$ib))===1,'attachment metadata A/B separated before hydration');
essentialThrows(fn()=>$files->download($b,7,$ia,$fa),'company_invoice_attachment_denied','foreign company parent denied');
essentialThrows(fn()=>$files->download($a,7,$ib,$fa),'company_invoice_attachment_denied','foreign invoice parent denied even with own file key');
essentialThrows(fn()=>$files->download($a,7,$ia,$fc),'company_invoice_attachment_denied','own invoice plus foreign file key denied');
essentialThrows(fn()=>$files->upload($a,7,42,hash('sha256','legacy-file'),'legacy.pdf',$pdf),'company_invoice_attachment_denied','named file adapter never adopts NULL legacy invoice');
essentialThrows(fn()=>$files->upload($b,7,$ib,$fa,'evidencia.png',$picture),'company_invoice_attachment_conflict','same request key cannot change origin or target');
essentialThrows(fn()=>$files->upload($a,7,$ia,$fa,'different-name.png',$picture),'company_invoice_attachment_conflict','immutable request filename cannot be changed on retry');
$download=$files->download($a,7,$ia,$fa);essentialCheck($download['mime']==='image/png'&&str_starts_with($download['content'],"\x89PNG")&&$download['content_hash']===hash('sha256',$download['content']),'download returns verified bounded PNG bytes after audit');
essentialThrows(fn()=>$files->portalDownload($documents,$sessionA,$a,$ia,$fa),'company_invoice_attachment_denied','default-private file cannot be exposed through portal direct POST');
$visible=$files->portalDownload($documents,$sessionA,$a,$ia,$fb);essentialCheck($visible['content']===$pdf,'explicitly visible PDF downloads through private contact permission');
$list=$documents->page($sessionA,$a);$listed=array_values(array_filter($list['items'],fn($row)=>(int)$row['id']===$ia))[0];essentialCheck(array_column($listed['attachments'],'file_key')===[$fb],'portal metadata contains only explicitly visible own file');
essentialThrows(fn()=>$files->portalDownload($documents,$sessionA,$a,$ib,$fc),'company_portal_invoice_unavailable','portal direct foreign invoice ID denied');
essentialThrows(fn()=>$files->portalDownload($documents,$sessionA,$b,$ib,$fc),'company_portal_invoice_unavailable','portal session cannot follow administrative company selector');
essentialThrows(fn()=>$files->portalDownload($documents,$sessionB,$b,$ib,$fc),'company_portal_invoice_unavailable','B native contact without financial permission remains denied');
$permission=$access->page($b,7,$cb)[$contactB];$access->set($b,7,$cb,$contactB,$permission['revision'],'1');essentialCheck($files->portalDownload($documents,$sessionB,$b,$ib,$fc)['content']===$pdf,'independently authorized B contact can download own file with same email');
$db->query("UPDATE {$p}invoices SET status=6 WHERE id=?",[$ia]);essentialThrows(fn()=>$files->portalDownload($documents,$sessionA,$a,$ia,$fb),'company_portal_invoice_unavailable','draft invoice never releases file bytes');$db->query("UPDATE {$p}invoices SET status=1 WHERE id=?",[$ia]);
$permission=$access->page($a,7,$ca)[$contactA];$access->set($a,7,$ca,$contactA,$permission['revision'],'0');essentialThrows(fn()=>$files->portalDownload($documents,$sessionA,$a,$ia,$fb),'company_portal_invoice_unavailable','permission revocation invalidates existing file session');$permission=$access->page($a,7,$ca)[$contactA];$access->set($a,7,$ca,$contactA,$permission['revision'],'1');
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12",[$a]);
essentialCheck(count($files->page($a,12,$ia))===2,'staff with invoice view can list only authorized parent metadata');
essentialThrows(fn()=>$files->upload($a,12,$ia,hash('sha256','viewer-upload'),'viewer.pdf',$pdf),'company_invoice_attachment_denied','invoice view does not grant file upload');
essentialThrows(fn()=>$files->hide($a,12,$ia,$fa,$download['content_hash']),'company_invoice_attachment_denied','invoice view does not grant hiding file');
essentialCheck($files->download($a,12,$ia,$fa)['content']===$download['content'],'invoice view grants authorized own-parent download');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12",[$a]);essentialThrows(fn()=>$files->download($a,12,$ia,$fa),'company_invoice_attachment_denied','membership revocation denies existing file key');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$files->download($a,7,$ia,$fa),'issuer_storage_failed','file download audit failure prevents bytes');essentialThrows(fn()=>$files->portalDownload($documents,$sessionA,$a,$ia,$fb),'issuer_storage_failed','portal file audit failure prevents bytes');
$failKey=hash('sha256','file-audit-fail');essentialThrows(fn()=>$files->upload($a,7,$ia,$failKey,'failed.pdf',$pdf),'issuer_storage_failed','upload audit failure rolls back content and receipt');$db->failOn=null;essentialCheck(!$db->query("SELECT file_key FROM {$p}company_invoice_attachments WHERE file_key=?",[$failKey])->row_array(),'failed upload leaves no orphan private blob');
foreach([['../path.pdf',$pdf],['unsafe.svg','<svg></svg>'],['fake.pdf','<?php echo 1;'],['big.pdf',str_repeat('x',2097153)],["bad\r\n.pdf",$pdf],['large.png',str_repeat('x',524289)]] as [$name,$body])essentialThrows(fn()=>$files->upload($a,7,$ia,hash('sha256',$name),$name,$body),'company_invoice_attachment_invalid','unsafe path/type/size rejected');
$db->query("UPDATE {$p}company_invoice_attachments SET content=? WHERE file_key=?",['tampered',$fa]);essentialThrows(fn()=>$files->download($a,7,$ia,$fa),'issuer_invoice_attachment_corrupt','binary corruption is not returned as a valid attachment');$db->query("UPDATE {$p}company_invoice_attachments SET content=? WHERE file_key=?",[$download['content'],$fa]);
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$files->hide($a,7,$ia,$fb,hash('sha256',$pdf)),'issuer_storage_failed','hide audit failure rolls back visibility state');$db->failOn=null;essentialCheck($files->portalDownload($documents,$sessionA,$a,$ia,$fb)['content']===$pdf,'failed hide preserves earlier authorized file');
$files->hide($a,7,$ia,$fb,hash('sha256',$pdf));$files->hide($a,7,$ia,$fb,hash('sha256',$pdf));essentialCheck(count($files->page($a,7,$ia))===1,'hide retry is idempotent, retaining immutable file history');essentialThrows(fn()=>$files->portalDownload($documents,$sessionA,$a,$ia,$fb),'company_invoice_attachment_denied','hidden file direct URL cannot release bytes');
essentialCheck($files->upload($a,7,$ia,$fb,'evidencia-publicada.pdf',$pdf,true)===$fb&&count($files->page($a,7,$ia))===1,'old upload retry cannot resurrect a hidden file');
// Explicit primary company works without treating NULL legacy documents as its own.
$principal=(new IssuerResourceAccess($db,$p))->primaryId();$settings->saveIdentity($principal,7,['legal_name'=>'Synthetic principal','tax_id'=>'11222333000181','profile'=>['invoice_company_address'=>'Synthetic principal street','invoice_company_city'=>'Synthetic city','company_state'=>'BA','invoice_company_country_code'=>'BR','invoice_company_postal_code'=>'00000-000','invoice_company_phonenumber'=>'']]);
$cp=$customers->create($principal,7,['company'=>'Private principal customer']);$ip=$s->create($principal,7,array_replace($input,['client_id'=>$cp]),hash('sha256','principal-file-invoice'));$fp=hash('sha256','principal-file');$files->upload($principal,7,$ip,$fp,'principal.pdf',$pdf,true);
essentialCheck($files->download($principal,7,$ip,$fp)['content']===$pdf,'named primary attachment uses its persisted owner, independent of legacy NULL');
essentialThrows(fn()=>$files->download($a,7,$ip,$fp),'company_invoice_attachment_denied','secondary company cannot read named primary file');
$pc=$contacts->save($principal,7,$cp,['firstname'=>'Synthetic','lastname'=>'Principal','email'=>'principal@example.invalid']);$db->query("UPDATE {$p}contacts SET password=? WHERE id=?",[$password,$pc]);$pp=$access->page($principal,7,$cp)[$pc];$access->set($principal,7,$cp,$pc,$pp['revision'],'1');$ps=$portal->login($principal,'principal@example.invalid','synthetic-portal-password','password_verify');essentialCheck($files->portalDownload($documents,$ps,$principal,$ip,$fp)['content']===$pdf,'primary private portal file follows native contact permission and own document');
essentialThrows(fn()=>$files->upload($a,7,$ia,$fa,'evidencia.png',$picture,true),'company_invoice_attachment_conflict','retry cannot silently publish an originally private file');
// Quotas cover both count and actual stored bytes, including hidden history.
$quota=$s->create($a,7,$input,hash('sha256','file-count-quota'));
for($i=0;$i<20;$i++)$files->upload($a,7,$quota,hash('sha256','file-count-'.$i),'small.png',$picture);
essentialThrows(fn()=>$files->upload($a,7,$quota,hash('sha256','file-count-excess'),'excess.png',$picture),'company_invoice_attachment_limit','twenty uploads bound the lifetime attachment count');
$byteQuota=$s->create($a,7,$input,hash('sha256','file-byte-quota'));$maxPdf=$pdf.str_repeat(' ',2097152-strlen($pdf));
for($i=0;$i<10;$i++)$files->upload($a,7,$byteQuota,hash('sha256','file-bytes-'.$i),'maximum.pdf',$maxPdf);
essentialThrows(fn()=>$files->upload($a,7,$byteQuota,hash('sha256','file-byte-excess'),'excess.png',$picture),'company_invoice_attachment_limit','twenty MiB real private content cannot be exceeded');unset($maxPdf);
if($db->driver()==='mysql'){
    $db->query("ALTER TABLE {$p}company_invoice_attachments ALTER COLUMN portal_visible SET DEFAULT 1");essentialThrows(fn()=>CompanyInvoiceAttachmentSchema::ensure($db,$p),'issuer_invoice_attachment_schema_invalid','default-public schema is rejected');$db->query("ALTER TABLE {$p}company_invoice_attachments ALTER COLUMN portal_visible DROP DEFAULT");CompanyInvoiceAttachmentSchema::ensure($db,$p);
}
echo 'Private invoice attachments/portal: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'); no external storage/provider.'.PHP_EOL;
