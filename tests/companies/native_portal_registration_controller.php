<?php
require __DIR__.'/native_portal_registration.php';
require __DIR__.'/native_portal_http_fixture.php';
require_once APPPATH.'services/NativeCompanyNotifications.php';
require_once APPPATH.'services/NativePortalRememberSchema.php';
NativePortalRememberSchema::ensure($db,$p);
// Synthetic native queue/schema parents, preserving the complete registration writer.
$db->query('CREATE TABLE '.$p.'company_portal_limits(bucket_hash varchar(64) PRIMARY KEY,window_start int NOT NULL,attempts int NOT NULL)');
$db->query('CREATE TABLE '.$p.'company_native_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER,recipient_id INTEGER,actor_kind TEXT,actor_id INTEGER,contact_actor_id INTEGER,source_kind TEXT,source_id INTEGER,resource_id INTEGER,event_key TEXT,title TEXT,body TEXT,created_at TEXT,read_at TEXT,inline_read_at TEXT,UNIQUE(issuer_id,recipient_id,event_key))');
foreach(['type','name','subject','message','fromname','fromemail']as$col)$db->query('ALTER TABLE '.$p.'emailtemplates ADD '.$col.' TEXT');foreach(['active','plaintext']as$col)$db->query('ALTER TABLE '.$p.'emailtemplates ADD '.$col.' INT NOT NULL DEFAULT 0');
$db->query('INSERT INTO '.$p.'emailtemplates(issuer_id,slug,language,active) VALUES(41,?,?,0)',['contact-verification-email','portuguese_br']);
$store->mutate(41,7,'update','allow_registration','1',null);$store->mutate(41,7,'update','customers_register_require_confirmation','1',null);
$auth->input->get=['issuer_id'=>'41'];$auth->input->post=[];$auth->input->verb='GET';$auth->router->method='register';PortalRequestScope::prepare($auth);PortalRequestScope::resolve($auth);$auth->__construct();
$start=$GLOBALS['essentialChecks'];$key=hash('sha256','actual-controller-public-register');$auth->input->verb='POST';$auth->input->post=['issuer_id'=>'41','_native_portal_csrf'=>PortalRequestScope::csrfToken($auth),'native_registration_key'=>$key,'firstname'=>'Controller','lastname'=>'Synthetic','email'=>'controller-register@example.invalid','company'=>'Controller public','password'=>'Synthetic-controller-123','passwordr'=>'Synthetic-controller-123'];
try{$auth->register();throw new RuntimeException('expected registration redirect');}catch(NativePortalRedirect$e){essentialCheck($e->status===303&&$e->url==='https://fixture.test/crm/authentication/login?issuer_id=41','actual native register redirects to original issuer login after confirmed commit');}
$r=$db->query('SELECT * FROM '.$p.'company_native_portal_registrations WHERE issuer_id=41 AND request_key=?',[$key])->row_array();essentialCheck($r&&(int)$db->query('SELECT active FROM '.$p.'contacts WHERE id=?',[$r['contact_id']])->row()->active===0,'actual controller retains explicit pending approval');
essentialCheck($db->query('SELECT email_verified_at FROM '.$p.'contacts WHERE id=?',[$r['contact_id']])->row()->email_verified_at!==null,'only explicitly disabled owned verification template skips verification');
$notifications=$db->query('SELECT * FROM '.$p.'company_native_notifications WHERE source_id=?',[$r['contact_id']])->result_array();essentialCheck(count($notifications)===1&&$notifications[0]['actor_id']===null&&$notifications[0]['actor_kind']==='contact','same transaction queues only real contact-source administrator notification');
try{$auth->register();}catch(NativePortalRedirect$e){}essentialCheck(count($db->query('SELECT id FROM '.$p.'company_native_notifications WHERE source_id=?',[$r['contact_id']])->result_array())===1,'replayed browser POST neither duplicates customer nor notification');
$auth->input->post['_native_portal_csrf']=str_repeat('0',64);essentialThrows(fn()=>$auth->register(),'native_portal_csrf_denied','actual registration rejects invalid public CSRF before writer');
echo 'Native public registration actual controller: '.($GLOBALS['essentialChecks']-$start).' real controller/writer SQLite checks passed.'.PHP_EOL;
