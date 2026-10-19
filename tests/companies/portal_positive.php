<?php
/** Real service/PDO data, synthetic credentials and a durable queue; never delivers externally. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require APPPATH.'services/CompanyPortal.php';
require APPPATH.'services/CompanyContacts.php';
require APPPATH.'services/CompanyClients.php';
register_shutdown_function('essentialCleanup');
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;
$db->query("CREATE TABLE {$p}emailtemplates(emailtemplateid ".($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').",type TEXT NOT NULL,slug VARCHAR(100) NOT NULL,language VARCHAR(40),name TEXT NOT NULL,subject TEXT NOT NULL,message TEXT NOT NULL,fromname TEXT NOT NULL,fromemail VARCHAR(100),plaintext INT NOT NULL DEFAULT 0,active INT NOT NULL DEFAULT 0,`order` INT NOT NULL)");
IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$db->query("ALTER TABLE {$p}clients ADD active int NOT NULL DEFAULT 1");
$db->query("CREATE TABLE {$p}contacts(id ".($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').",userid int NOT NULL,firstname varchar(191) NOT NULL,lastname varchar(191) NOT NULL,email varchar(100) NOT NULL,phonenumber varchar(100) NOT NULL,datecreated datetime NOT NULL,password varchar(255),new_pass_key varchar(32),new_pass_key_requested datetime,last_password_change datetime,active int NOT NULL DEFAULT 1,is_primary int NOT NULL DEFAULT 0,invoice_emails int NOT NULL DEFAULT 0,estimate_emails int NOT NULL DEFAULT 0,credit_note_emails int NOT NULL DEFAULT 0,contract_emails int NOT NULL DEFAULT 0,task_emails int NOT NULL DEFAULT 0,project_emails int NOT NULL DEFAULT 0,ticket_emails int NOT NULL DEFAULT 0)");
$db->query("CREATE TABLE {$p}user_auto_login(user_id int,staff int,key_id varchar(64))");
// Fixture queue matches the native persisted fields.
$db->query("DROP TABLE IF EXISTS {$p}mail_queue");
$db->query("CREATE TABLE {$p}mail_queue(id ".($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').",engine varchar(40),email varchar(191) NOT NULL,cc text,bcc text,message text NOT NULL,alt_message text,status varchar(20),date datetime,headers text,attachments text,issuer_id int unsigned)");
if($db->driver()==='mysql'){
    if(!class_exists('CI_Migration')){#[AllowDynamicProperties] class CI_Migration {}}
    require_once APPPATH.'migrations/407_version_407.php';$m=new Migration_Version_407();$m->db=$db;$m->up();$m->up();
}else{
    $db->query("CREATE TABLE {$p}company_portal_tokens(token_hash varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,contact_id int NOT NULL,client_id int NOT NULL,identity_hash varchar(64) NOT NULL,expires_at datetime NOT NULL,created_at datetime NOT NULL)");
    $db->query("CREATE TABLE {$p}company_portal_limits(bucket_hash varchar(64) PRIMARY KEY,window_start int NOT NULL,attempts int NOT NULL)");
}
$registry=new IssuerRegistry($db,$p);$a=$registry->saveIssuer(7,['legal_name'=>'Synthetic A','tax_id'=>'12345678000195']);$b=$registry->saveIssuer(7,['legal_name'=>'Synthetic B','tax_id'=>'52998224725']);
$store=new CompanySettingsStorage($db,$p);
foreach([$a=>'a',$b=>'b'] as $issuer=>$letter)foreach(['mail_engine'=>'phpmailer','email_protocol'=>'smtp','smtp_host'=>'smtp-'.$letter.'.invalid','smtp_port'=>'587','smtp_email'=>$letter.'@example.invalid','smtp_password'=>'encrypted-'.$letter,'email_signature'=>'Signature '.$letter] as $key=>$value)$store->mutate($issuer,7,'update',$key,$value,null);
$db->query("INSERT INTO {$p}clients(userid,company,active) VALUES(101,'Synthetic A client',1),(202,'Synthetic B client',1)");
foreach([101=>$a,202=>$b] as $client=>$issuer)$db->query("INSERT INTO {$p}issuers_clients(client_id,owner_issuer_id,tax_id,created_by,created_at) VALUES(?,?,NULL,7,?)",[$client,$issuer,date('Y-m-d H:i:s')]);
$contacts=new CompanyContacts($db,$p);$input=['firstname'=>'Synthetic','lastname'=>'Contact','email'=>'same@example.invalid'];
$ca=$contacts->save($a,7,101,$input);$cb=$contacts->save($b,7,202,$input);
essentialCheck($ca!==$cb,'equal emails have independent contacts in A/B');
essentialCheck($contacts->save($a,7,101,$input)===$ca,'repeated identical contact create is idempotent');
$clients=new CompanyClients($db,$p);$clients->setSharing($a,7,101,[$b]);
essentialThrows(fn()=>$contacts->page($b,7,101),'company_client_access_denied','identity sharing never exposes contacts');
essentialThrows(fn()=>$contacts->save($b,7,101,$input),'company_client_access_denied','destination cannot mutate owner contact');
$portal=new CompanyPortal($db,$p,str_repeat('synthetic-',4));$decrypt=fn($v)=>'decrypted:'.$v;$base='https://crm.example.invalid';
$portal->requestRecovery($a,$input['email'],$base,$decrypt);$portal->requestRecovery($b,$input['email'],$base,$decrypt);
$queue=$db->query("SELECT * FROM {$p}mail_queue ORDER BY id")->result_array();
essentialCheck(count($queue)===2&&(int)$queue[0]['issuer_id']===$a&&(int)$queue[1]['issuer_id']===$b,'durable queues preserve A/B origin');
$tokens=[];
foreach($queue as $i=>$mail){
    $issuer=$i===0?$a:$b;$letter=$i===0?'a':'b';$headers=unserialize($mail['headers'],['allowed_classes'=>false]);
    essentialCheck($headers['from']===$letter.'@example.invalid'&&str_contains($headers['fromName'],'Synthetic '.strtoupper($letter)),'sender uses canonical own-company identity');
    essentialCheck(str_contains($mail['message'],'Signature '.$letter),'signature is origin bound');
    preg_match('/#token=([a-f0-9]{64})/',$mail['message'],$match);$tokens[$issuer]=$match[1]??'';
    essentialCheck(strlen($tokens[$issuer])===64,'queue contains usable fragment URL without query bearer');
    essentialCheck($db->query("SELECT token_hash FROM {$p}company_portal_tokens WHERE issuer_id=?",[$issuer])->row()->token_hash===hash('sha256',$tokens[$issuer]),'only token digest stored in token registry');
}
$portal->requestRecovery($a,'missing@example.invalid',$base,$decrypt);$portal->requestRecovery($a,$input['email'],$base,$decrypt);
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===2,'unknown account and rapid repeated request do not enqueue');
essentialCheck(!$portal->reset($b,$tokens[$a],'synthetic-new-password-a',fn($v)=>password_hash($v,PASSWORD_DEFAULT)),'A token cannot reset B');
essentialCheck($portal->reset($a,$tokens[$a],'synthetic-new-password-a',fn($value)=>password_hash($value,PASSWORD_DEFAULT)),'A recovery completion updates password');
essentialCheck(!$portal->reset($a,$tokens[$a],'replay-password-a',fn($v)=>password_hash($v,PASSWORD_DEFAULT)),'consumed token cannot reset again');
essentialCheck($portal->login($a,$input['email'],'synthetic-new-password-a','password_verify')!==null,'positive password login binds A');
essentialCheck($portal->login($b,$input['email'],'synthetic-new-password-a','password_verify')===null,'A password does not authenticate B despite same email');
$session=$portal->login($a,$input['email'],'synthetic-new-password-a','password_verify');
essentialCheck($portal->sessionIdentity($session,$a)['company']==='Synthetic A client'&&$portal->sessionIdentity($session,$b)===null,'session cannot follow company selection');
$db->query("UPDATE {$p}company_portal_tokens SET expires_at=? WHERE issuer_id=?",[date('Y-m-d H:i:s',time()-1),$b]);
essentialCheck(!$portal->reset($b,$tokens[$b],'synthetic-new-password-b',fn($v)=>password_hash($v,PASSWORD_DEFAULT)),'expired token denied');
$before=$contacts->page($a,7,101)[0];$contacts->save($a,7,101,['firstname'=>'Changed','lastname'=>'Contact','email'=>'new@example.invalid'],$ca,$before['revision']);
essentialCheck($portal->sessionIdentity($session,$a)===null,'email change revokes existing portal session');
essentialThrows(fn()=>$contacts->save($a,7,101,$input,$ca,$before['revision']),'company_client_stale','stale contact editor cannot restore old email');
$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$portal->requestRecovery($a,'new@example.invalid',$base,$decrypt),'issuer_storage_failed','audit failure rolls back queue and token');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===2,'no orphan queued secret after audit failure');
$portal->requestRecovery($a,'new@example.invalid',$base,$decrypt);
$last=$db->query("SELECT message FROM {$p}mail_queue ORDER BY id DESC LIMIT 1")->row()->message;preg_match('/#token=([a-f0-9]{64})/',$last,$match);$token=$match[1];
$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$portal->reset($a,$token,'rollback-password-a',fn($v)=>password_hash($v,PASSWORD_DEFAULT)),'issuer_storage_failed','reset audit failure rolls back password and token consumption');$db->failOn=null;
essentialCheck($portal->login($a,'new@example.invalid','synthetic-new-password-a','password_verify')!==null,'password preserved after failed audit');
$db->query("INSERT INTO {$p}user_auto_login VALUES(?,0,'old-contact'),(?,1,'staff-same-id')",[$ca,$ca]);
essentialCheck($portal->reset($a,$token,'final-new-password-a',fn($v)=>password_hash($v,PASSWORD_DEFAULT)),'retry succeeds after transaction rollback');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}user_auto_login WHERE staff=0")->row()->n===0&&(int)$db->query("SELECT COUNT(*) n FROM {$p}user_auto_login WHERE staff=1")->row()->n===1,'reset revokes contact cookies and preserves staff');
for($i=0;$i<5;$i++)essentialCheck($portal->allowAttempt($a,'127.0.0.1','recovery'),'bounded public attempt allowed');
essentialCheck(!$portal->allowAttempt($a,'127.0.0.1','recovery'),'persistent recovery throttle enforced');
essentialCheck($portal->allowAttempt($b,'127.0.0.1','recovery'),'company throttle buckets independent');
$audits=json_encode($db->query("SELECT event,metadata_json FROM {$p}issuers_audit")->result_array());
essentialCheck(!str_contains($audits,$token)&&!str_contains($audits,'same@example.invalid'),'audit excludes bearer and email');
echo 'Owner-bound portal/contacts/recovery: '.$GLOBALS['essentialChecks'].' checks passed ('.$db->driver().'). No external delivery.'.PHP_EOL;
