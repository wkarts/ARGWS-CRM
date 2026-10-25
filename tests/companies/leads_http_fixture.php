<?php
/** Finite synthetic setup/readback, only against the authorized disposable native installation. */
if(PHP_SAPI!=='cli'||!in_array(getenv('ISSUERS_BROWSER_DB_NAME'),['argws_upgrade_ci','argws_fresh_ci'],true))exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');require APPPATH.'config/app-config.php';
if(APP_DB_NAME!==getenv('ISSUERS_BROWSER_DB_NAME')||!preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME))exit(2);
require dirname(__DIR__).'/support/BbPdoDatabase.php';require APPPATH.'services/CompanyLeadsApi.php';require APPPATH.'services/issuers/IssuerRegistry.php';function db_prefix(){return 'tbl';}
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);$identity=$db->query('SELECT master_staff_id,primary_issuer_id,source FROM tblissuers_installation_identity WHERE id=1')->row_array();
// BEGIN exact upgraded-fixture owner proof (test-only, read-only).
if (($identity['source'] ?? null) === 'owner_attestation') {
    $audit = $db->query("SELECT actor_id,subject_id,issuer_id,metadata_json FROM tblissuers_audit WHERE event='original_admin_attested'")->result_array();
    $meta = count($audit) === 1 ? json_decode($audit[0]['metadata_json'], true) : null;
    if (getenv('ISSUERS_BROWSER_DB_NAME') !== 'argws_upgrade_ci'
        || (int)($identity['master_staff_id'] ?? 0) !== 17
        || count($audit) !== 1 || (string)$audit[0]['actor_id'] !== '0'
        || (int)$audit[0]['subject_id'] !== 17 || $audit[0]['issuer_id'] !== null
        || !is_array($meta) || ($meta['proof'] ?? null) !== 'installation_owner_attestation'
        || ($meta['confirmation_reference'] ?? null) !== 'test:explicit-owner-confirmation'
        || (int)($meta['primary_issuer_id'] ?? 0) !== (int)$identity['primary_issuer_id']
        || (int)$identity['primary_issuer_id'] !== 1) {
        throw new RuntimeException('Exact upgraded fixture owner attestation and audit required');
    }
}
// END exact upgraded-fixture owner proof.
if((int)$identity['master_staff_id']!==17||!in_array($identity['source'],['installer','legacy_designation','owner_attestation'],true)||(int)$db->query('SELECT version FROM tblmigrations')->row()->version<454)throw new RuntimeException('Actual installed454 with preserved master17 required');CompanyLeadsSchema::assertValid($db,'tbl');$s=new CompanyLeads($db,'tbl');$mode=$argv[1]??'';$value=null;
if($mode==='prepare'){
    $registry=new IssuerRegistry($db,'tbl');$owners=[];$reused=[];
    // These are the finite native pipeline identities, also used by Staff and recurrence.
    // Their legal names belong to preceding scenarios and must never be renamed here.
    foreach(['a'=>['HTTP Leads A','11222333000181'],'b'=>['HTTP Leads B','52998224725']] as $label=>[$name,$tax]){
        $rows=$db->query('SELECT * FROM tblissuers_registry WHERE tax_id=?',[$tax])->result_array();
        if(count($rows)>1)throw new RuntimeException('Ambiguous native fixture issuer');
        if($rows){$row=$rows[0];$owners[$label]=(int)$row['id'];$reused[$label]=$row;}
        else{$owners[$label]=$registry->saveIssuer(17,['legal_name'=>$name,'tax_id'=>$tax]);$row=$db->query('SELECT * FROM tblissuers_registry WHERE id=?',[$owners[$label]])->row_array();}
        if(!$row||(string)$row['tax_id']!==$tax||(int)$row['active']!==1||$row['identity_status']!=='complete'||$owners[$label]===(int)$identity['primary_issuer_id'])throw new RuntimeException('Native fixture issuer identity changed');
    }
    $owners['principal']=(int)$identity['primary_issuer_id'];if(count(array_unique($owners))!==3)throw new RuntimeException('Distinct A/B/principal fixture identities required');$email='leads-reader-454@example.invalid';$reader=$db->query('SELECT staffid,admin,active,password FROM tblstaff WHERE email=?',[$email])->result_array();
    if(count($reader)>1)throw new RuntimeException('Ambiguous synthetic reader');if(!$reader){$db->query('INSERT INTO tblstaff(email,firstname,lastname,password,datecreated,active,admin) VALUES(?,?,?,?,?,1,0)',[$email,'Leads','Read only',password_hash('synthetic-leads-reader-454',PASSWORD_BCRYPT),gmdate('Y-m-d H:i:s')]);$readerId=(int)$db->insert_id();}else{$readerId=(int)$reader[0]['staffid'];if((int)$reader[0]['admin']!==0||(int)$reader[0]['active']!==1||!password_verify('synthetic-leads-reader-454',$reader[0]['password']))throw new RuntimeException('Synthetic reader identity changed');}
    $db->query('INSERT IGNORE INTO tblissuers_staff(issuer_id,staff_id,can_view) VALUES(?,?,1)',[$owners['a'],$readerId]);$db->query("INSERT IGNORE INTO tblissuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,'leads','view')",[$owners['a'],$readerId]);$db->query('INSERT IGNORE INTO tblissuers_staff_defaults(staff_id,issuer_id) VALUES(?,?)',[$readerId,$owners['a']]);
    foreach($reused as $row)if($db->query('SELECT * FROM tblissuers_registry WHERE id=?',[$row['id']])->row_array()!==$row)throw new RuntimeException('Existing native fixture issuer was changed');
    if($db->query('SELECT master_staff_id,primary_issuer_id,source FROM tblissuers_installation_identity WHERE id=1')->row_array()!==$identity)throw new RuntimeException('Installation identity was changed');
    $value=['owners'=>$owners,'master'=>17,'reader_id'=>$readerId,'reader_email'=>$email,'identity'=>$identity,'reused_issuers'=>$reused,'csrf_cookie_name'=>defined('APP_CSRF_COOKIE_NAME')?APP_CSRF_COOKIE_NAME:'csrf_cookie_name'];
}elseif($mode==='configuration'){$value=$s->configuration(IssuerPolicy::id($argv[2]??null),17);}
elseif($mode==='inspect'){$i=IssuerPolicy::id($argv[2]??null);$lead=$s->get($i,17,IssuerPolicy::id($argv[3]??null));$value=['lead'=>$lead];if($lead['converted_client_id']!==null){$value['owner']=$db->query('SELECT * FROM tblissuers_clients WHERE client_id=?',[$lead['converted_client_id']])->row_array();$value['contact']=$db->query('SELECT id,userid,firstname,lastname,email,phonenumber,active,is_primary,password,invoice_emails,estimate_emails,credit_note_emails,contract_emails,task_emails,project_emails,ticket_emails FROM tblcontacts WHERE id=?',[$lead['converted_contact_id']])->row_array();$value['sharing']=$db->query('SELECT * FROM tblissuers_client_links WHERE client_id=?',[$lead['converted_client_id']])->result_array();$value['portal']=$db->query('SELECT token_hash FROM tblcompany_portal_tokens WHERE contact_id=?',[$lead['converted_contact_id']])->result_array();}}
elseif($mode==='worker'){$value=$s->processAllNotices(100);}
elseif($mode==='issue'){$i=IssuerPolicy::id($argv[2]??null);$scope=$argv[3]??'all';$scopes=$scope==='all'?array_keys(CompanyLeadsApi::SCOPES):[$scope];$value=(new CompanyApiTokens($db,'tbl'))->issue($i,17,'Synthetic Leads HTTP',$scopes,1,bin2hex(random_bytes(32)));}
elseif($mode==='revoke'){$i=IssuerPolicy::id($argv[2]??null);$token=$argv[3]??'';(new CompanyApiTokens($db,'tbl'))->revoke($i,17,$token);$value=['revoked'=>true];}
elseif($mode==='sessions'){$rows=$db->query('SELECT * FROM tblsessions ORDER BY id')->result_array();$value=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];}
elseif($mode==='financial'){$value=[];foreach(['invoices'=>'id','invoicepaymentrecords'=>'id','creditnotes'=>'id','expenses'=>'id','leads'=>'id'] as $t=>$order){$rows=$db->query('SELECT * FROM tbl'.$t.' ORDER BY '.$order)->result_array();$value[$t]=['count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];}}
else throw new RuntimeException('Unknown finite synthetic fixture action');echo json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE).PHP_EOL;
