<?php
/** Real writer, synthetic data; migration metadata/engines are tested in MySQL CI. */
require __DIR__.'/issuers_core_support.php';
require_once APPPATH.'services/issuers/IssuerMasterResolution.php';
require_once APPPATH.'services/issuers/IssuerMasterResolutionSchema.php';
if (!class_exists('CI_Migration')) { #[AllowDynamicProperties] class CI_Migration {} }
register_shutdown_function('essentialCleanup');
function resolutionFixture(): array
{
    $f=essentialFixture('1.0.2',1); $db=$f->db; $p=$f->prefix;
    $db->query("ALTER TABLE {$p}staff ADD password varchar(255) DEFAULT NULL");
    $db->query("UPDATE {$p}staff SET password=? WHERE staffid=7",[password_hash('synthetic-confirmation',PASSWORD_DEFAULT)]);
    $db->query("INSERT INTO {$p}staff(staffid,active,admin,password) VALUES(19,1,1,?)",[password_hash('synthetic-other',PASSWORD_DEFAULT)]);
    IssuerCore::reconcile($db,$p);
    if ($db->driver()==='mysql') {
        require_once APPPATH.'migrations/405_version_405.php';
        $migration=new Migration_Version_405(); $migration->db=$db; $migration->up(); $migration->up();
    } else {
        // SQLite does not attest InnoDB, binary collation or MySQL metadata.
        $db->query("CREATE TABLE {$p}issuers_master_authorizations(token_hash varchar(64) NOT NULL PRIMARY KEY,staff_id int NOT NULL,expires_at bigint NOT NULL,consumed_at datetime DEFAULT NULL,reason varchar(500) NOT NULL,attempts int NOT NULL DEFAULT 0)");
    }
    return [$f,new IssuerMasterResolution($db,$p)];
}
$verify=static fn($password,$hash)=>password_verify($password,$hash);
[$f,$s]=resolutionFixture();$db=$f->db;$p=$f->prefix;
essentialCheck($s->unresolved(),'legacy unresolved without inferred ID1/admin');
essentialThrows(fn()=>$s->authorizeDesignation(7,'short'),'issuer_master_reason_required','explicit reason required');
$code=$s->authorizeDesignation(7,'Authorized synthetic installation responsibility decision');
essentialCheck(strlen($code)===64,'256-bit authorization');
$stored=$db->query("SELECT * FROM {$p}issuers_master_authorizations")->result_array()[0];
essentialCheck($stored['token_hash']===hash('sha256',$code)&&!str_contains(json_encode($stored),$code),'only digest persisted');
essentialThrows(fn()=>$s->complete(19,$code,'synthetic-other',$verify),'issuer_master_confirmation_denied','bound to authenticated subject');
essentialThrows(fn()=>$s->complete(7,$code,'wrong',$verify),'issuer_master_confirmation_denied','password required');
essentialCheck((int)$db->query("SELECT attempts FROM {$p}issuers_master_authorizations")->row()->attempts===1,'failed counter commits');
$replacement=$s->authorizeDesignation(7,'Authorized reissue after lost synthetic authorization');
essentialThrows(fn()=>$s->complete(7,$code,'synthetic-confirmation',$verify),'issuer_master_confirmation_denied','reissue revokes old code');
$db->failOn="INSERT INTO {$p}issuers_audit";
essentialThrows(fn()=>$s->complete(7,$replacement,'synthetic-confirmation',$verify),'issuer_storage_failed','audit failure rejects designation');$db->failOn=null;
essentialCheck($s->unresolved(),'identity rolls back on audit failure');
essentialCheck($db->query("SELECT consumed_at FROM {$p}issuers_master_authorizations")->row()->consumed_at===null,'authorization retryable after rollback');
$s->complete(7,$replacement,'synthetic-confirmation',$verify);
$auditCount=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='installation_master_designated'")->row()->n;
$s->complete(7,$replacement,'synthetic-confirmation',$verify);
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='installation_master_designated'")->row()->n===$auditCount,'retry idempotent');
$identity=$db->query("SELECT * FROM {$p}issuers_installation_identity WHERE id=1")->row();
essentialCheck((int)$identity->master_staff_id===7&&$identity->source==='legacy_designation','designation distinguished from installer proof');
essentialCheck((new IssuerResourceAccess($db,$p))->isMaster(7),'resolved responsibility enables ACL administration');
essentialThrows(fn()=>$s->authorizeDesignation(19,'Unauthorized replacement of resolved installation'),'issuer_identity_already_resolved','cannot replace master');
$audit=$db->query("SELECT metadata_json FROM {$p}issuers_audit WHERE event='installation_master_designated'")->row()->metadata_json;
essentialCheck(json_decode($audit,true)['historical_original_proven']===false,'no false historical claim');
essentialCheck(!str_contains($audit,$replacement)&&!str_contains($audit,'synthetic-confirmation'),'audit excludes secrets');
foreach(['active','admin'] as $flag){
    $db->query("UPDATE {$p}staff SET {$flag}=0 WHERE staffid=7");
    essentialCheck(!(new IssuerResourceAccess($db,$p))->isMaster(7),'active/admin powers rechecked immediately');
    essentialThrows(fn()=>$s->complete(7,$replacement,'synthetic-confirmation',$verify),'issuer_master_candidate_denied','retry revalidates identity');
    $db->query("UPDATE {$p}staff SET {$flag}=1 WHERE staffid=7");
}
[$f,$s]=resolutionFixture();$db=$f->db;$p=$f->prefix;
$code=$s->authorizeDesignation(7,'Authorized synthetic designation for expiry validation');
$db->query("UPDATE {$p}issuers_master_authorizations SET expires_at=0");
essentialThrows(fn()=>$s->complete(7,$code,'synthetic-confirmation',$verify),'issuer_master_confirmation_denied','expired code denied');
$code=$s->authorizeDesignation(7,'Authorized synthetic designation for rate limit validation');
for($i=0;$i<5;$i++){essentialThrows(fn()=>$s->complete(7,$code,'wrong',$verify),'issuer_master_confirmation_denied','wrong password denied');}
essentialThrows(fn()=>$s->complete(7,$code,'synthetic-confirmation',$verify),'issuer_master_confirmation_denied','five failures require reissue');
essentialCheck($s->unresolved(),'failed attempts never resolve identity');
echo 'Legacy master resolution: '.$GLOBALS['essentialChecks'].' checks passed ('.$db->driver().').'.PHP_EOL;
