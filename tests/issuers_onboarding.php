<?php
/** Synthetic PDO integration of draft resume, concurrency versions and the atomic final writer. */
require __DIR__.'/issuers_core_support.php';
require_once APPPATH.'services/CompanyOnboarding.php';
if(!class_exists('CI_Migration')){#[AllowDynamicProperties] class CI_Migration {}}
register_shutdown_function('essentialCleanup');
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;
$db->query("ALTER TABLE {$p}staff ADD firstname varchar(50) DEFAULT ''");
$db->query("ALTER TABLE {$p}staff ADD lastname varchar(50) DEFAULT ''");
$db->query("INSERT INTO {$p}staff(staffid,active,admin,firstname,lastname) VALUES(19,1,0,'Synthetic','Employee')");
IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
if($db->driver()==='mysql'){
    require_once APPPATH.'migrations/405_version_405.php';$m=new Migration_Version_405();$m->db=$db;$m->up();$m->up();
}else{
    $db->query("CREATE TABLE {$p}company_wizard_drafts(draft_key varchar(64) NOT NULL PRIMARY KEY,actor_id int NOT NULL,revision int NOT NULL,step int NOT NULL,payload_json longtext NOT NULL,issuer_id int unsigned DEFAULT NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id))");
}
$catalog=['settings'=>['view','edit'],'banco_brasil'=>['view','manage','import']];
$s=new CompanyOnboarding($db,$p,$catalog);$acl=new IssuerResourceAccess($db,$p);$primary=$acl->primaryId();
$before=essentialLegacy($f);$membersBefore=$db->query("SELECT * FROM {$p}issuers_staff WHERE staff_id=19")->result_array();
essentialCheck($s->pending(7)===null,'no draft before explicit begin');
essentialThrows(fn()=>$s->begin(19,str_repeat('a',64)),'issuer_access_denied','membership without master cannot begin');
$d=$s->begin(7,str_repeat('a',64));
essentialCheck($d['step']==1&&$d['revision']==0,'draft begins at identity');
essentialCheck($s->begin(7,str_repeat('b',64))['draft_key']===$d['draft_key'],'double submit/resume returns existing open draft');
essentialThrows(fn()=>$s->advance(7,$d['draft_key'],0,2,['dateformat'=>'Y-m-d|%Y-%m-%d','time_format'=>'24']),'issuer_wizard_invalid_step','cannot skip server step');
essentialThrows(fn()=>$s->advance(7,$d['draft_key'],0,1,['legal_name'=>'Synthetic B','tax_id'=>'invalid']),'issuer_invalid_tax_id','tax validated on server');
$identity=['legal_name'=>'Synthetic B','tax_id'=>'52998224725','profile'=>['invoice_company_city'=>'Private B']];
$d=$s->advance(7,$d['draft_key'],0,1,$identity);
essentialThrows(fn()=>$s->advance(7,$d['draft_key'],0,1,$identity),'issuer_wizard_stale','old revision cannot overwrite later step');
$d=$s->read(7,$d['draft_key']);essentialCheck($d['payload'][1]===$identity,'resume exact persisted identity');
$d=$s->advance(7,$d['draft_key'],$d['revision'],2,['dateformat'=>'d/m/Y|%d/%m/%Y','time_format'=>'24']);
essentialThrows(fn()=>$s->advance(7,$d['draft_key'],$d['revision'],3,['staff'=>[19=>['default'=>'1','resources'=>['settings'=>['delete']]]]]),'issuer_invalid_permission','unknown grant denied');
essentialThrows(fn()=>$s->advance(7,$d['draft_key'],$d['revision'],3,['staff'=>[7=>['default'=>'1','resources'=>[]]]]),'issuer_wizard_invalid_staff','master automatic access cannot be replaced by grants');
$d=$s->advance(7,$d['draft_key'],$d['revision'],3,['staff'=>[19=>['default'=>'1','resources'=>['settings'=>['view'],'banco_brasil'=>['view','import']]]]]);
essentialThrows(fn()=>$s->advance(7,$d['draft_key'],$d['revision'],4,['client_visibility'=>'shared']),'issuer_wizard_invalid_privacy','no silent sharing activation');
$d=$s->advance(7,$d['draft_key'],$d['revision'],4,['client_visibility'=>'private']);
$db->failOn="INSERT INTO {$p}issuers_audit";
essentialThrows(fn()=>$s->complete(7,$d['draft_key'],$d['revision']),'issuer_storage_failed','audit failure prevents partial company');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_registry")->row()->n===1,'failed completion rolls back company creation');
essentialCheck($s->read(7,$d['draft_key'])['issuer_id']===null,'failed completion leaves resumable draft');
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=19");
essentialThrows(fn()=>$s->complete(7,$d['draft_key'],$d['revision']),'issuer_wizard_invalid_staff','staff revocation between review and completion denied');
$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=19");
$db->failOn="UPDATE {$p}company_wizard_drafts SET issuer_id";
essentialThrows(fn()=>$s->complete(7,$d['draft_key'],$d['revision']),'issuer_storage_failed','late failure after settings and grants rolls back everything');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_registry")->row()->n===1,'late failure leaves no partial company');
essentialCheck($db->query("SELECT * FROM {$p}issuers_staff WHERE staff_id=19")->result_array()===$membersBefore,'late rollback preserves prior memberships');
$new=$s->complete(7,$d['draft_key'],$d['revision']);
essentialCheck($new!==$primary,'principal never recreated');
essentialCheck($s->complete(7,$d['draft_key'],$d['revision'])===$new,'retry completion returns same company');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_registry")->row()->n===2,'completion does not duplicate company');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_onboarding_completed'")->row()->n===1,'one completion audit');
essentialCheck($acl->can($new,19,'banco_brasil','import')&&!$acl->can($new,19,'banco_brasil','manage'),'exact new company grants');
essentialCheck($acl->can($new,7,'future_resource','future_capability'),'master covers future company without grant copies');
essentialCheck((new IssuerRegistry($db,$p))->defaultForStaff(19)===$new,'authorized default saved');
foreach($membersBefore as $old){essentialCheck($db->query("SELECT * FROM {$p}issuers_staff WHERE issuer_id=? AND staff_id=19",[$old['issuer_id']])->result_array()[0]===$old,'old membership preserved');}
$settings=new CompanySettingsStorage($db,$p);
essentialCheck($settings->get($new,'dateformat')==='d/m/Y|%d/%m/%Y','preferences use actual company settings');
essentialCheck($settings->get($new,'invoice_company_city')==='Private B','identity uses canonical settings writer');
foreach(['smtp_password','smtp_host','payment_account','connect_token'] as $key){essentialCheck($settings->get($new,$key)==='','new company inherits no secret');}
essentialCheck(essentialLegacy($f)===$before,'legacy financial/config/client rows unchanged');
essentialCheck($s->pending(7)===null,'completed draft not resumed as open');
$db->query("UPDATE {$p}staff SET admin=0 WHERE staffid=7");
essentialThrows(fn()=>$s->read(7,$d['draft_key']),'issuer_access_denied','master demotion immediately revokes draft access');
if($db->driver()==='mysql'){
    $db->query("ALTER TABLE {$p}company_wizard_drafts DROP PRIMARY KEY, ADD PRIMARY KEY(draft_key(32))");
    essentialThrows(fn()=>CompanyOnboardingSchema::ensure($db,$p),'issuer_wizard_schema_invalid','partial draft-key index rejected without data repair');
}
echo 'Company onboarding: '.$GLOBALS['essentialChecks'].' checks passed ('.$db->driver().').'.PHP_EOL;
