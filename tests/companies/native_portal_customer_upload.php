<?php
require __DIR__.'/native_portal_customer_files.php';
require_once APPPATH.'services/NativeCompanyCustomerUploadMail.php';
require_once APPPATH.'services/NativeCompanyNotifications.php';
$db->query($db->driver()==='mysql'?"CREATE TABLE IF NOT EXISTS {$p}mail_queue(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,issuer_id INT) ENGINE=InnoDB":"CREATE TABLE IF NOT EXISTS {$p}mail_queue(id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INT)");
NativeCompanyHeaderSchema::ensure($db,$p);
NativeCompanyCustomerUploadMailSchema::ensure($db,$p);
if($db->driver()==='mysql'){
    NativeCompanyMailDispatchFence::ensure($db,$p);
    CompanyPortalMailSchema::ensure($db,$p);
    $db->query("CREATE TABLE IF NOT EXISTS {$p}customer_admins(customer_id INT,staff_id INT,date_assigned DATETIME) ENGINE=InnoDB");
    $db->query("CREATE TABLE IF NOT EXISTS {$p}emailtemplates(emailtemplateid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,issuer_id INT,slug VARCHAR(100),language VARCHAR(40)) ENGINE=InnoDB");
    unset($db->data_cache['table_names']);
}
$start=$GLOBALS['essentialChecks'];
$uploaded=$reader->upload(hash('sha256','contact-upload-positive'),'contact-upload.pdf',$bytes);
essentialCheck($uploaded>0,'actual contact upload allocates native files row');
$row=$db->query('SELECT * FROM '.$p.'files WHERE id=?',[$uploaded])->row_array();
essentialCheck((int)$row['staffid']===0&&(int)$row['contact_id']===901,'native file uses actual contact identity rather than staff impersonation');
essentialCheck($db->query('SELECT state FROM '.$p.NativeCompanyCustomerUploadMailSchema::OUTBOX.' WHERE file_id=?',[$uploaded])->row()->state==='pending','upload commits durable pending mail, not sent');
$notices=$db->query("SELECT * FROM {$p}company_native_notifications WHERE source_kind='customer_file' AND source_id=?",[$uploaded])->result_array();
essentialCheck(count($notices)>0,'actual upload records native staff notifications');
foreach($notices as$n)essentialCheck($n['actor_kind']==='contact'&&$n['actor_id']===null&&(int)$n['contact_actor_id']===901,'notification retains actual contact author');
essentialCheck($reader->upload(hash('sha256','contact-upload-positive'),'contact-upload.pdf',$bytes)===$uploaded,'replay uses same file, notice and outbox');
echo 'Native portal customer upload: '.($GLOBALS['essentialChecks']-$start).' actual SQLite checks passed.'.PHP_EOL;
$start=$GLOBALS['essentialChecks'];$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'files')->row()->n;$outboxes=(int)$db->query('SELECT COUNT(*) n FROM '.$p.NativeCompanyCustomerUploadMailSchema::OUTBOX)->row()->n;
$db->query($db->driver()==='mysql'?"CREATE TRIGGER {$p}ignore_upload_notice BEFORE INSERT ON {$p}company_native_notifications FOR EACH ROW BEGIN IF NEW.source_kind='customer_file' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic notice failure'; END IF; END":"CREATE TRIGGER {$p}ignore_upload_notice BEFORE INSERT ON {$p}company_native_notifications WHEN NEW.source_kind='customer_file' BEGIN SELECT RAISE(IGNORE); END");essentialThrows(fn()=>$reader->upload(hash('sha256','ignored-notification'),'ignored.pdf',$bytes),($db->driver()==='mysql'?'issuer_storage_failed':'issuer_notification_write_unconfirmed'),'ignored notification INSERT rolls back new file and outbox');$db->query("DROP TRIGGER {$p}ignore_upload_notice");essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'files')->row()->n===$count&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.NativeCompanyCustomerUploadMailSchema::OUTBOX)->row()->n===$outboxes,'no partially recorded upload after event failure');
$db->query($db->driver()==='mysql'?"CREATE TRIGGER {$p}revoke_upload_contact AFTER INSERT ON {$p}company_native_notifications FOR EACH ROW UPDATE {$p}contacts SET active=IF(NEW.source_kind='customer_file',0,active) WHERE id=901":"CREATE TRIGGER {$p}revoke_upload_contact AFTER INSERT ON {$p}company_native_notifications WHEN NEW.source_kind='customer_file' BEGIN UPDATE {$p}contacts SET active=0 WHERE id=901; END");try{$reader->upload(hash('sha256','revoked-during-event'),'revoked.pdf',$bytes);throw new LogicException('revoked upload accepted');}catch(DomainException$e){essentialCheck(true,'contact revocation during event is rejected');}$db->query("DROP TRIGGER {$p}revoke_upload_contact");essentialCheck((int)$db->query('SELECT active FROM '.$p.'contacts WHERE id=901')->row()->active===1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'files')->row()->n===$count,'contact and upload rollback together');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,NativeCompanyNotifications::tables('customer_file'),fn()=>(new NativeCompanyNotifications($db,$p))->recordCustomerFileInOwnedTransaction(41,$uploaded,$reader)),'native_portal_file_allocation_denied','retained writer cannot generate event for existing file');
$n=new NativeCompanyNotifications($db,$p);essentialCheck($n->read(82,51)['items']===[],'other issuer has no customer upload notification');
$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=52');essentialThrows(fn()=>$n->read(41,52),'issuer_access_denied','revoked responsible cannot read upload notification');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=52');
echo 'Native portal upload notification negatives: '.($GLOBALS['essentialChecks']-$start).' additional actual SQLite checks passed.'.PHP_EOL;
$start=$GLOBALS['essentialChecks'];$deleteId=$reader->upload(hash('sha256','native-delete-own'),'delete-own.pdf',$bytes);$find=static function($id)use($reader){foreach($reader->page()['files']as$r)if($r['id']===$id)return$r;throw new LogicException('file missing');};$deleteRow=$find($deleteId);
essentialThrows(fn()=>$reader->delete($deleteId,$deleteRow['revision']),'native_portal_files_denied','default contact delete option does not authorize deletion');$db->query(($db->driver()==='mysql'?'INSERT IGNORE':'INSERT OR IGNORE')." INTO {$p}issuers_resource_permissions VALUES(41,12,'settings','edit')");(new CompanySettingsStorage($db,$p))->mutate(41,12,'update','allow_contact_to_delete_files','1');
essentialThrows(fn()=>$reader->delete($deleteId,str_repeat('a',64)),'native_portal_files_stale','deletion binds the current file and sharing revision');$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$reader->delete($deleteId,$deleteRow['revision']),'issuer_storage_failed','audit failure rolls back native visibility and owned active flag');$db->failOn=null;essentialCheck($find($deleteId)['revision']===$deleteRow['revision'],'failed delete keeps exact prior revision');$reader->delete($deleteId,$deleteRow['revision']);essentialThrows(fn()=>$reader->read($deleteRow['attachment_key']),'native_portal_files_denied','deleted contact file is no longer available by key');essentialCheck($db->query('SELECT content FROM '.$p.'company_customer_file_contents WHERE file_id=?',[$deleteId])->row()->content===$bytes,'delete retains blob provenance');
echo 'Native general contact deletion: '.($GLOBALS['essentialChecks']-$start).' actual SQLite checks passed.'.PHP_EOL;
