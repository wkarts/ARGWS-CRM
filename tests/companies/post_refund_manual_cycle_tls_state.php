<?php
/** Read-only synthetic protocol exporter for the local verified-TLS browser sink. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');
require APPPATH.'config/app-config.php';
if(APP_DB_NAME!=='argws_upgrade_ci'||!preg_match('/^(127\.0\.0\.1|argws-crm-provision-db-[0-9]+)(:[0-9]+)?$/D',APP_DB_HOSTNAME))throw new RuntimeException('Disposable HTTP database required');
require BASEPATH.'libraries/Encryption.php';require FCPATH.'modules/asaas/libraries/Asaas/CompanyRefundContract.php';
require __DIR__.'/post_refund_manual_cycle_synthetic_protocol.php';
function log_message($level,$message){}
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if((int)$pdo->query('SELECT version FROM tblmigrations')->fetchColumn()<440)throw new RuntimeException('Actual native440 required before synthetic TLS export');
$cipher=new CI_Encryption(['driver'=>'openssl','key'=>APP_ENC_KEY]);$rows=$pdo->query("SELECT r.source_json,r.source_hash,o.result_json,o.evidence_hash,a.secret_cipher FROM tblasaas_company_refund_requests r JOIN tblasaas_company_refund_observations o ON o.id=r.latest_observation AND o.request_key=r.request_key AND o.issuer_id=r.issuer_id JOIN tblasaas_company_accounts a ON a.id=r.account_row AND a.issuer_id=r.issuer_id AND a.revision=r.account_revision WHERE a.environment='sandbox' AND r.state='full_observed' ORDER BY r.created_at DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);$out=['accounts'=>[]];
foreach($rows as$row){$source=json_decode($row['source_json'],true,32,JSON_THROW_ON_ERROR);if(!preg_match('/^pay_refund_browser_([ab])_[1-9][0-9]*$/D',$source['remote_id']??'',$match))continue;$secret='synthetic-refund-browser-'.$match[1];if($cipher->decrypt($row['secret_cipher'])!==$secret)continue;$safe=json_decode($row['result_json'],true,32,JSON_THROW_ON_ERROR);if(!hash_equals($row['source_hash'],\Asaas\CompanyRefundContract::hash($source))||!hash_equals($row['evidence_hash'],\Asaas\CompanyRefundContract::hash($safe)))throw new RuntimeException('Synthetic source/observation corrupt');$raw=financeCycleSyntheticResponse($source,$safe);if(isset($out['accounts'][$secret])&&$out['accounts'][$secret]['wallet']!==$source['account_id'])throw new RuntimeException('Synthetic wallet conflict');$out['accounts'][$secret]['wallet']=$source['account_id'];$out['accounts'][$secret]['payments'][$source['remote_id']]=$raw;}
if(!$out['accounts'])throw new RuntimeException('Synthetic browser refund fixture required');echo json_encode($out,JSON_THROW_ON_ERROR).PHP_EOL;
