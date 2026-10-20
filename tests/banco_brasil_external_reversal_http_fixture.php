<?php
/** Read-only observation of the component browser's receipts in the disposable full CRM database. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');
require APPPATH.'config/app-config.php';if(APP_DB_NAME!=='argws_upgrade_ci')exit(2);
$case=$argv[1]??'';if(!in_array($case,['a','b'],true))exit(2);
$pdo=new PDO('mysql:host='.APP_DB_HOSTNAME.';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function fixtureRows(string $sql,array $args=[]):array{global $pdo;$query=$pdo->prepare($sql);$query->execute($args);return $query->fetchAll(PDO::FETCH_ASSOC);}
$module=fixtureRows("SELECT installed_version,active FROM tblmodules WHERE module_name='banco_brasil'")[0]??[];if(($module['installed_version']??null)!=='1.1.9'||(int)($module['active']??0)!==1)throw new RuntimeException('fixture requires native BB119');
$issuer=(int)(fixtureRows('SELECT id FROM tblissuers_registry WHERE tax_id=?',[$case==='a'?'11222333000181':'52998224725'])[0]['id']??0);
$row=fixtureRows('SELECT c.payment_id,c.invoice_id,r.event_id FROM tblcompany_bank_receipt_components c JOIN tblbb_event_receipts r ON r.payment_id=c.payment_id AND r.issuer_id=c.issuer_id JOIN tblinvoices i ON i.id=c.invoice_id AND i.issuer_id=c.issuer_id WHERE c.issuer_id=? AND EXISTS(SELECT 1 FROM tblitemable item WHERE item.rel_id=i.id AND item.rel_type=? AND item.description=?) ORDER BY c.payment_id DESC LIMIT 1',[$issuer,'invoice','Synthetic BB components browser '.$case])[0]??null;if(!$row)throw new RuntimeException('run BB components browser first');
$payment=(int)$row['payment_id'];$invoice=(int)$row['invoice_id'];$event=(int)$row['event_id'];
$native=fixtureRows('SELECT * FROM tblinvoicepaymentrecords WHERE id=? AND invoiceid=?',[$payment,$invoice])[0]??null;
$component=fixtureRows('SELECT * FROM tblcompany_bank_receipt_components WHERE payment_id=? AND issuer_id=? AND invoice_id=?',[$payment,$issuer,$invoice])[0]??null;
$receipt=fixtureRows('SELECT * FROM tblcompany_payment_receipts WHERE payment_id=? AND issuer_id=? AND invoice_id=?',[$payment,$issuer,$invoice])[0]??null;
if(!$native||!$component||!$receipt)throw new RuntimeException('component receipt proof unavailable');
$original=json_decode($component['payload_json'],true,32,JSON_THROW_ON_ERROR);$reversals=fixtureRows('SELECT * FROM tblcompany_bank_receipt_reversals WHERE payment_id=?',[$payment]);$status=(int)fixtureRows('SELECT status FROM tblinvoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]['status'];
$hash=fn(array $data)=>hash('sha256',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$today=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
echo json_encode(['case'=>$case,'issuer'=>$issuer,'invoice'=>$invoice,'payment'=>$payment,'event'=>$event,'bookDate'=>$native['date'],'today'=>$today,'original'=>$original,'native_amount'=>$native['amount'],'native_hash'=>$hash($native),'component_hash'=>$hash($component),'receipt_hash'=>$hash($receipt),'status'=>$status,'reversals'=>count($reversals),'reversal_hash'=>$reversals?$hash($reversals[0]):null],JSON_THROW_ON_ERROR);
