<?php
/** CLI-only disposable HTTP DB fixture. No external Google, SMTP, installation authority changes or version writes. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('FCPATH',dirname(__DIR__,2).'/');define('BASEPATH',FCPATH.'system/');define('APPPATH',FCPATH.'application/');
require APPPATH.'config/app-config.php';if(APP_DB_NAME!=='argws_upgrade_ci')exit(2);
require dirname(__DIR__).'/support/BbPdoDatabase.php';require APPPATH.'services/CompanyGoogleMailOAuth.php';require BASEPATH.'libraries/Encryption.php';
function db_prefix(){return'tbl';}function log_message($l,$m){}
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);CompanyGoogleMailOAuthSchema::ensure($db,'tbl');
$mode=$argv[1]??'prepare';$identity=$db->query('SELECT master_staff_id,primary_issuer_id FROM tblissuers_installation_identity WHERE id=1')->row_array();$actor=(int)$identity['master_staff_id'];$primary=(int)$identity['primary_issuer_id'];$a=(int)$db->query("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'")->row()->id;$b=(int)$db->query("SELECT id FROM tblissuers_registry WHERE tax_id='52998224725'")->row()->id;$cipher=new CI_Encryption(['driver'=>'openssl','key'=>APP_ENC_KEY]);
if($mode==='prepare'){
 $db->query('DELETE FROM tblcompany_google_mail_states WHERE actor_id=? AND issuer_id IN (?,?,?)',[$actor,$primary,$a,$b]);
 foreach([$primary,$a,$b]as$issuer)foreach(['smtp_email'=>'google-'.$issuer.'@example.invalid','mail_engine'=>'phpmailer','email_protocol'=>'smtp','smtp_host'=>'smtp-'.$issuer.'.invalid','smtp_port'=>'587']as$name=>$value)(new CompanySettingsStorage($db,'tbl'))->mutate($issuer,$actor,'update',$name,$value,null);
 echo json_encode(['a'=>$a,'b'=>$b,'primary'=>$primary],JSON_THROW_ON_ERROR);exit;
}
if($mode==='connect'){
 $issuer=IssuerPolicy::id($argv[2]??null);if(!in_array($issuer,[$primary,$a,$b],true))exit(2);$sender=(new CompanySettingsStorage($db,'tbl'))->get($issuer,'smtp_email');$service=new CompanyGoogleMailOAuth($db,'tbl',new CompanyGoogleMailOAuthClient(function($url,$method,$form,$bearer)use($pdo,$sender){if($pdo->inTransaction())throw new RuntimeException('synthetic network in transaction');return$url===CompanyGoogleMailOAuthClient::TOKEN?['token_type'=>'Bearer','expires_in'=>3600,'scope'=>CompanyGoogleMailOAuthClient::MAIL_SCOPE,'access_token'=>'synthetic-browser-access','refresh_token'=>'synthetic-browser-refresh']:['sub'=>'synthetic-sub-'.$sender,'email'=>$sender,'email_verified'=>true];}));$profile=$service->view($issuer,$actor);$url=$service->begin($issuer,$actor,$profile['revision'],COMPANY_GOOGLE_MAIL_REDIRECT_URI,fn($v)=>$cipher->encrypt($v));parse_str(parse_url($url,PHP_URL_QUERY),$params);$service->callback($issuer,$actor,$params['state'],'synthetic-browser-code',fn($v)=>$cipher->encrypt($v),fn($v)=>$cipher->decrypt($v));echo'connected';exit;
}
exit(2);
