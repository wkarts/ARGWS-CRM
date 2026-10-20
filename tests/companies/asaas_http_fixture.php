<?php
set_exception_handler(static function(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().PHP_EOL);exit(1);});
/** Controlled origin API simulation inside the named disposable HTTP database. No external network. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');define('APP_MODULES_PATH',FCPATH.'modules/');
require APPPATH.'config/app-config.php';if(APP_DB_NAME!=='argws_upgrade_ci')exit(2);
require dirname(__DIR__).'/support/BbPdoDatabase.php';require APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyService.php';require APPPATH.'services/CompanyClients.php';require BASEPATH.'libraries/Encryption.php';
function db_prefix(){return 'tbl';}function log_message($level,$message){}
$pdo=new PDO('mysql:host='.APP_DB_HOSTNAME.';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);
$case=$argv[1]??'';$mode=$argv[2]??'prepare';if(!in_array($case,['a','b'],true)||!in_array($mode,['prepare','refresh','worker','prepare_unpaid','remote_remove'],true))exit(2);
$tax=$case==='a'?'11222333000181':'52998224725';$issuer=(int)$db->query('SELECT id FROM tblissuers_registry WHERE tax_id=?',[$tax])->row()->id;$actor=(int)$db->query('SELECT master_staff_id FROM tblissuers_installation_identity WHERE id=1')->row()->master_staff_id;
$service=new \Asaas\CompanyService($db,'tbl');$account=$db->query("SELECT * FROM tblasaas_company_accounts WHERE issuer_id=? AND environment='sandbox'",[$issuer])->row_array();if(!$account||$account['account_id']!=='wallet-browser-'.$case)throw new RuntimeException('synthetic account unavailable');
$payer=$case==='a'?'39053344705':'93541134780';$client=(int)($db->query('SELECT c.userid FROM tblclients c JOIN tblissuers_clients o ON o.client_id=c.userid WHERE o.owner_issuer_id=? AND c.vat=?',[$issuer,$payer])->row_array()['userid']??0);
if(!$client)$client=(new CompanyClients($db,'tbl'))->create($issuer,$actor,['company'=>'Synthetic Asaas '.$case.' payer','vat'=>$payer]);
$cipher=new CI_Encryption(['driver'=>'openssl','key'=>APP_ENC_KEY]);$today=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');$due=(new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');
$remote='';$charge=[];$removed=false;$unpaid=in_array($mode,['prepare_unpaid','remote_remove'],true);$transport=static function($environment,$method,$path,$payload,$secret)use($db,$case,$payer,$today,&$remote,&$charge,&$removed,$unpaid):array{
    if($db->conn_id->inTransaction())throw new LogicException('synthetic HTTP inside transaction');
    if($environment!=='sandbox'||!preg_match('/^synthetic-browser-key-([ab])$/D',$secret,$keyMatch))throw new RuntimeException('synthetic credentials mismatch');$owner=$keyMatch[1];
    if($path==='wallets/')return ['data'=>[['id'=>'wallet-browser-'.$owner]],'hasMore'=>false];
    if($path==='customers/cus_browser_'.$owner)return ['id'=>'cus_browser_'.$owner,'cpfCnpj'=>$owner==='a'?'39053344705':'93541134780','deleted'=>false];
    if($method==='POST'&&$path==='payments'){$charge=$payload;return $charge+['id'=>$remote,'status'=>'PENDING','netValue'=>'99.00','paymentDate'=>'','creditDate'=>'','deleted'=>false];}
    if($method==='DELETE'&&$unpaid&&preg_match('~^payments/pay_browser_[ab]_[0-9]+$~D',$path)){$removed=true;return ['id'=>substr($path,9),'deleted'=>true];}
    if($method==='GET'&&preg_match('~^payments/(pay_browser_[ab]_[0-9]+)$~D',$path,$match)){$row=$db->query('SELECT * FROM tblasaas_payment_origins WHERE remote_id=? AND account_id=? AND environment=?',[$match[1],'wallet-browser-'.$owner,'sandbox'])->row_array();$meta=$row?$db->query('SELECT customer_id,billing_type FROM tblasaas_company_origin_meta WHERE origin_id=?',[$row['id']])->row_array():null;if(!$row||!$meta)throw new RuntimeException('synthetic origin unavailable');return ['id'=>$match[1],'customer'=>$meta['customer_id'],'billingType'=>$meta['billing_type'],'value'=>$row['invoice_total'],'dueDate'=>$row['invoice_due_date'],'externalReference'=>$row['invoice_hash'],'status'=>$unpaid?'PENDING':'RECEIVED','netValue'=>'99.00','paymentDate'=>$unpaid?'':$today,'creditDate'=>$unpaid?'':$today,'deleted'=>$removed];}
    throw new LogicException('unexpected synthetic protocol');
};
$decrypt=fn($value)=>$cipher->decrypt($value);
if($mode==='worker'){require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyReconciliation.php';echo json_encode((new \Asaas\CompanyReconciliation($db,'tbl'))->runOnce($decrypt,$transport),JSON_THROW_ON_ERROR);exit;}
require_once APPPATH.'services/CompanyExpenses.php';$category=(new CompanyExpenses($db,'tbl'))->saveCategory($issuer,$actor,'Tarifas Asaas browser',hash('sha256','asaas-browser-category-'.$issuer));
if(in_array($mode,['prepare','prepare_unpaid'],true)){
    $service->verifyAccount($issuer,$actor,(int)$account['id'],$account['revision'],$decrypt,$transport);
    $currency=(int)$db->query("SELECT id FROM tblcurrencies WHERE name='BRL'")->row()->id;$invoices=new CompanyInvoices($db,'tbl');
    $invoice=$invoices->create($issuer,$actor,['client_id'=>$client,'currency'=>$currency,'date'=>$today,'due_date'=>$due,'items'=>[['description'=>'Synthetic Asaas browser '.$case,'qty'=>'1.00','rate'=>'100.00']]],bin2hex(random_bytes(32)));
    $remote='pay_browser_'.$case.'_'.$invoice;$origin=$service->issue($issuer,$actor,$invoice,(int)$account['id'],'cus_browser_'.$case,'PIX',$invoices->revision($issuer,$actor,$invoice),bin2hex(random_bytes(32)),$decrypt,$transport);
}else{
    $origin=IssuerPolicy::id($argv[3]??null);$row=$db->query('SELECT * FROM tblasaas_payment_origins WHERE id=? AND issuer_id=?',[$origin,$issuer])->row_array();$meta=$db->query('SELECT * FROM tblasaas_company_origin_meta WHERE origin_id=?',[$origin])->row_array();if(!$row||!$meta)throw new RuntimeException('synthetic origin unavailable');
    $invoice=(int)$row['invoice_id'];$remote=$row['remote_id'];$charge=['customer'=>$meta['customer_id'],'billingType'=>$meta['billing_type'],'value'=>$row['invoice_total'],'dueDate'=>$row['invoice_due_date'],'externalReference'=>$row['invoice_hash']];
}
if($mode==='remote_remove'){require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRemoteOperations.php';echo json_encode((new \Asaas\CompanyRemoteOperations($db,'tbl'))->remove($issuer,$actor,$origin,hash('sha256','company-asaas-delete:'.$issuer.':'.$origin),$decrypt,$transport),JSON_THROW_ON_ERROR);exit;}
$proof=$service->query($issuer,$actor,$origin,$decrypt,$transport);echo json_encode(compact('issuer','invoice','origin','proof','remote','category'),JSON_THROW_ON_ERROR);
