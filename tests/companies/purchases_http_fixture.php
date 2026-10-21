<?php
/** Finite setup/readback in the disposable native browser database only. */
if (PHP_SAPI!=='cli'||!in_array(getenv('ISSUERS_BROWSER_DB_NAME'),['argws_purchase_ci','argws_upgrade_ci','argws_fresh_ci','argws_crm_ci'],true)) { exit(2); }
define('FCPATH',dirname(__DIR__,2).'/'); define('BASEPATH',FCPATH.'system/'); define('APPPATH',FCPATH.'application/'); require APPPATH.'config/app-config.php';
if (APP_DB_NAME!==getenv('ISSUERS_BROWSER_DB_NAME')||!preg_match('/^(127\\.0\\.0\\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME)) { exit(2); }
require dirname(__DIR__).'/support/BbPdoDatabase.php'; require APPPATH.'services/CompanyPurchasesApi.php'; require APPPATH.'services/issuers/IssuerRegistry.php';
function db_prefix() { return 'tbl'; }
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $db=new BbPdoDatabase($pdo);
$identity=$db->query('SELECT * FROM tblissuers_installation_identity WHERE id=1')->row_array();
if ((int)$identity['master_staff_id']!==17||!in_array($identity['source'],['installer','legacy_designation'],true)||(int)$db->query('SELECT version FROM tblmigrations')->row()->version<454) { throw new RuntimeException('Actual installed454+ with preserved master17 required'); }
CompanyPurchasesSchema::assertValid($db,'tbl'); $s=new CompanyPurchases($db,'tbl'); $mode=$argv[1]??''; $value=null;
if ($mode==='prepare') {
    $registry=new IssuerRegistry($db,'tbl'); $owners=[]; $reused=[]; $categories=[];
    foreach (['a'=>['HTTP Purchase A','11222333000181'],'b'=>['HTTP Purchase B','52998224725']] as $label=>[$name,$tax]) {
        $rows=$db->query('SELECT * FROM tblissuers_registry WHERE tax_id=?',[$tax])->result_array(); if (count($rows)>1) { throw new RuntimeException('Ambiguous synthetic issuer'); }
        if ($rows) { $row=$rows[0]; $owners[$label]=(int)$row['id']; $reused[]=$row; }
        else { $owners[$label]=$registry->saveIssuer(17,['legal_name'=>$name,'tax_id'=>$tax]); $row=$db->query('SELECT * FROM tblissuers_registry WHERE id=?',[$owners[$label]])->row_array(); }
        if ((int)$row['active']!==1||$row['identity_status']!=='complete'||$owners[$label]===(int)$identity['primary_issuer_id']) { throw new RuntimeException('Invalid synthetic secondary company'); }
    }
    $owners['principal']=(int)$identity['primary_issuer_id']; if (count(array_unique($owners))!==3) { throw new RuntimeException('Three distinct installed companies required'); }
    $expenses=new CompanyExpenses($db,'tbl'); foreach ($owners as $i) { $found=$db->query('SELECT id FROM tblcompany_expense_categories WHERE issuer_id=? AND name=?',[$i,'Synthetic Purchase HTTP'])->result_array(); $categories[$i]=$found?(int)$found[0]['id']:$expenses->saveCategory($i,17,'Synthetic Purchase HTTP',hash('sha256','purchase-http-category|'.$i)); }
    $email='purchase-member-455@example.invalid'; $password='synthetic-purchase-member-455'; $member=$db->query('SELECT staffid,admin,active,password FROM tblstaff WHERE email=?',[$email])->result_array();
    if (count($member)>1) { throw new RuntimeException('Ambiguous synthetic member'); }
    if (!$member) { $db->query('INSERT INTO tblstaff(email,firstname,lastname,password,datecreated,active,admin) VALUES(?,?,?,?,?,1,0)',[$email,'Purchase','Member',password_hash($password,PASSWORD_BCRYPT),gmdate('Y-m-d H:i:s')]); $memberId=(int)$db->insert_id(); }
    else { $memberId=(int)$member[0]['staffid']; if ((int)$member[0]['admin']!==0||(int)$member[0]['active']!==1||!password_verify($password,$member[0]['password'])) { throw new RuntimeException('Synthetic member identity changed'); } }
    $db->query('INSERT IGNORE INTO tblissuers_staff(issuer_id,staff_id,can_view) VALUES(?,?,1)',[$owners['a'],$memberId]); $db->query('INSERT IGNORE INTO tblissuers_staff_defaults(staff_id,issuer_id) VALUES(?,?)',[$memberId,$owners['a']]);
    foreach (['view','create','edit'] as $cap) { $db->query("INSERT IGNORE INTO tblissuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,'purchases',?)",[$owners['a'],$memberId,$cap]); }
    foreach ($reused as $row) { if ($db->query('SELECT * FROM tblissuers_registry WHERE id=?',[$row['id']])->row_array()!==$row) { throw new RuntimeException('Preexisting company modified by setup'); } }
    if ($db->query('SELECT * FROM tblissuers_installation_identity WHERE id=1')->row_array()!==$identity) { throw new RuntimeException('Installation identity modified by setup'); }
    $value=['owners'=>$owners,'categories'=>$categories,'master'=>17,'member_id'=>$memberId,'member_email'=>$email,'identity'=>$identity];
} elseif ($mode==='workspace') { $value=$s->workspace(IssuerPolicy::id($argv[2]??null),17,IssuerPolicy::id($argv[3]??null)); }
elseif ($mode==='payable') { $value=$s->get(IssuerPolicy::id($argv[2]??null),17,'payable',IssuerPolicy::id($argv[3]??null)); }
elseif ($mode==='configuration') { $value=$s->configuration(IssuerPolicy::id($argv[2]??null),17); }
elseif ($mode==='restrict') {
    $id=IssuerPolicy::id($argv[2]??null); $i=IssuerPolicy::id($argv[3]??null);
    if (!$db->query("SELECT staffid FROM tblstaff WHERE staffid=? AND email='purchase-member-455@example.invalid' AND admin=0",[$id])->row_array()) { throw new RuntimeException('Only synthetic ordinary member may be restricted'); }
    $db->query("DELETE FROM tblissuers_resource_permissions WHERE issuer_id=? AND staff_id=? AND feature='purchases' AND capability<>'view'",[$i,$id]); $value=['restricted'=>true];
} elseif ($mode==='issue') { $i=IssuerPolicy::id($argv[2]??null); $scope=$argv[3]??'all'; $value=(new CompanyApiTokens($db,'tbl'))->issue($i,17,'Synthetic Purchase HTTP',$scope==='all'?array_keys(CompanyPurchasesApi::SCOPES):[$scope],1,bin2hex(random_bytes(32))); }
elseif ($mode==='revoke') { (new CompanyApiTokens($db,'tbl'))->revoke(IssuerPolicy::id($argv[2]??null),17,$argv[3]??''); $value=['revoked'=>true]; }
elseif ($mode==='preservation') {
    $value=['identity'=>$identity]; foreach (['issuers_registry','invoices','invoicepaymentrecords','creditnotes','expenses','pur_orders','pur_vendor','leads'] as $table) {
        if (!$db->table_exists('tbl'.$table)) { $value[$table]=['exists'=>false]; continue; }
        $rows=$db->query('SELECT * FROM tbl'.$table)->result_array(); foreach ($rows as &$row) { ksort($row); } unset($row); usort($rows,static fn($a,$b)=>json_encode($a)<=>json_encode($b));
        $value[$table]=['exists'=>true,'count'=>count($rows),'sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];
    }
} elseif ($mode==='transport') {
    $rows=$db->query('SELECT * FROM tblsessions ORDER BY id')->result_array();
    foreach ($rows as &$row) { ksort($row); foreach ($row as &$field) { if (is_string($field)) { $field=['bytes'=>strlen($field),'base64'=>base64_encode($field)]; } } unset($field); } unset($row);
    $value=['csrf_cookie_name'=>(defined('APP_COOKIE_PREFIX')?APP_COOKIE_PREFIX:'').(defined('APP_CSRF_COOKIE_NAME')?APP_CSRF_COOKIE_NAME:'csrf_cookie_name'),
        'session_cookie_name'=>defined('APP_SESSION_COOKIE_NAME')?APP_SESSION_COOKIE_NAME:'sp_session',
        'session_count'=>count($rows),'session_sha256'=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR))];
} elseif ($mode==='financial') { $value=['expenses'=>$db->query('SELECT id,issuer_id,total_cents,state FROM tblcompany_expenses ORDER BY id')->result_array(),'events'=>$db->query('SELECT issuer_id,kind,source_type,source_id,amount FROM tblcompany_financial_events ORDER BY issuer_id,issuer_sequence')->result_array(),'payables'=>$db->query('SELECT id,issuer_id,document_id,expense_id,amount_cents FROM tblcompany_purchase_payables ORDER BY id')->result_array()]; }
else { throw new RuntimeException('Unknown finite Purchase HTTP fixture action'); }
echo json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE).PHP_EOL;
