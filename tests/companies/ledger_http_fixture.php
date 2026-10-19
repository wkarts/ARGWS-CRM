<?php
/** Canonical synthetic writes for a >100-event page, exclusively in the named disposable HTTP fixture. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');define('APP_MODULES_PATH',FCPATH.'modules/');
require APPPATH.'config/app-config.php';if(APP_DB_NAME!=='argws_upgrade_ci'||($argv[1]??'')!=='a')exit(2);
require dirname(__DIR__).'/support/BbPdoDatabase.php';require APPPATH.'services/CompanyInvoices.php';
function db_prefix(){return 'tbl';}function log_message($level,$message){}
$pdo=new PDO('mysql:host='.APP_DB_HOSTNAME.';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);CompanyFinancialJournalSchema::assertValid($db,'tbl');
$issuer=(int)$db->query("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'")->row()->id;$actor=(int)$db->query('SELECT master_staff_id FROM tblissuers_installation_identity WHERE id=1')->row()->master_staff_id;
$client=(int)$db->query("SELECT c.userid FROM tblclients c JOIN tblissuers_clients o ON o.client_id=c.userid WHERE o.owner_issuer_id=? AND c.company='Synthetic portal client a'",[$issuer])->row()->userid;$currency=(int)$db->query("SELECT id FROM tblcurrencies WHERE name='BRL'")->row()->id;if($issuer<1||$actor<1||$client<1||$currency<1)throw new RuntimeException('Synthetic journal fixture unavailable');
$today=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');$due=(new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');$writer=new CompanyInvoices($db,'tbl');$ids=[];
for($i=0;$i<101;$i++)$ids[]=$writer->create($issuer,$actor,['client_id'=>$client,'currency'=>$currency,'date'=>$today,'due_date'=>$due,'items'=>[['description'=>'Synthetic journal page '.$i,'qty'=>'1.00','rate'=>'1.00']]],bin2hex(random_bytes(32)));
$events=(int)$db->query("SELECT COUNT(*) n FROM tblcompany_financial_events WHERE issuer_id=? AND kind='invoice_issued' AND source_type='invoice' AND source_id IN (".implode(',',array_fill(0,count($ids),'?')).')',array_merge([$issuer],array_map('strval',$ids)))->row()->n;
if($events!==101)throw new RuntimeException('Canonical journal capture unconfirmed');echo json_encode(['issuer'=>$issuer,'book_date'=>$today,'created_original_events'=>$events],JSON_THROW_ON_ERROR);
