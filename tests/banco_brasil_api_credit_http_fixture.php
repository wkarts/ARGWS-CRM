<?php
/** Service-generated synthetic API proof, only for the named disposable browser database. No real HTTP. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
require APPPATH.'config/app-config.php';if(APP_DB_NAME!=='argws_upgrade_ci')exit(2);
require __DIR__.'/support/BbPdoDatabase.php';require APPPATH.'services/CompanyInvoices.php';require APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiService.php';require BASEPATH.'libraries/Encryption.php';
function db_prefix(){return 'tbl';}function log_message($level,$message){}
$pdo=new PDO('mysql:host='.APP_DB_HOSTNAME.';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);
$issuer=(int)$db->query("SELECT id FROM tblissuers_registry WHERE tax_id='11222333000181'")->row()->id;
$actor=(int)$db->query('SELECT master_staff_id FROM tblissuers_installation_identity WHERE id=1')->row()->master_staff_id;
$client=(int)$db->query("SELECT c.userid FROM tblclients c JOIN tblissuers_clients o ON o.client_id=c.userid WHERE o.owner_issuer_id=? AND c.vat='11144477735'",[$issuer])->row()->userid;
$profile=$db->query('SELECT * FROM tblbb_api_profiles WHERE issuer_id=? ORDER BY id DESC LIMIT 1',[$issuer])->row_array();
if(!$profile||$profile['api_stage']!=='homologation'||$issuer<1||$actor<1||$client<1)throw new RuntimeException('synthetic fixture unavailable');
$connection=$db->query('SELECT * FROM tblbb_connections WHERE id=? AND issuer_id=?',[$profile['connection_id'],$issuer])->row_array();
$a=new \BancoBrasil\Agreement(new \BancoBrasil\Scope($issuer,(int)$connection['id'],$connection['account_id'],$connection['environment'],$connection['agreement_number']),$connection['issuer_tax_id'],$connection['issuer_name'],$connection['variation'],$connection['wallet']);
$currency=(int)$db->query("SELECT id FROM tblcurrencies WHERE name='BRL'")->row()->id;$today=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');$due=(new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');
$invoice=(new CompanyInvoices($db,'tbl'))->create($issuer,$actor,['client_id'=>$client,'currency'=>$currency,'date'=>$today,'due_date'=>$due,'items'=>[['description'=>'Synthetic API browser credit','qty'=>'1.00','rate'=>'100.00']]],bin2hex(random_bytes(32)));
$cipher=new CI_Encryption(['driver'=>'openssl','key'=>APP_ENC_KEY]);$title=null;$remote=null;
$transport=static function($method,$url,$headers,$body)use(&$title,&$remote,$db,$a,$due):array{
 if($db->conn_id->inTransaction())throw new LogicException('synthetic HTTP inside transaction');
 if(str_contains($url,'oauth/token'))return ['status'=>200,'body'=>'{"access_token":"synthetic-browser-token","token_type":"Bearer","expires_in":600}'];
 parse_str(parse_url($url,PHP_URL_QUERY),$query);
 if($method==='POST'&&str_contains($url,'/boletos?')){
  $title=\BancoBrasil\ApiClient::decodeExact($body);$remote=$title['numeroTituloCliente'];
  $factor=1000+(int)(new DateTimeImmutable('2025-02-22'))->diff(new DateTimeImmutable($due))->format('%r%a');$barcode='00190'.str_pad((string)$factor,4,'0',STR_PAD_LEFT).'0000010000'.'000000'.substr($remote,3).'17';
  $sum=0;$weight=2;foreach(array_reverse(str_split(substr($barcode,0,4).substr($barcode,5))) as $digit){$sum+=(int)$digit*$weight;$weight=$weight===9?2:$weight+1;}$dv=11-$sum%11;$barcode[4]=(string)(in_array($dv,[0,10,11],true)?1:$dv);
  $response=['numero'=>$remote,'numeroCarteira'=>17,'numeroVariacaoCarteira'=>(int)$a->variation,'numeroContratoCobranca'=>1234,'codigoBarraNumerico'=>$barcode,'linhaDigitavel'=>\BancoBrasil\ApiPayload::line($barcode),'beneficiario'=>['agencia'=>(int)$a->branch,'contaCorrente'=>(int)$a->account]];
  return ['status'=>201,'body'=>json_encode($response,JSON_THROW_ON_ERROR)];
 }
 if($method==='GET'&&str_contains($url,'/boletos?')){
  if(($query['contaBeneficiario']??'')!==(string)(int)$a->account||($query['agenciaBeneficiario']??'')!==(string)(int)$a->branch)throw new RuntimeException('synthetic account mismatch');
  $response=['indicadorContinuidade'=>'N','proximoIndice'=>0,'quantidadeRegistros'=>1,'boletos'=>[['numeroBoletoBB'=>$remote,'dataVencimento'=>$title['dataVencimento'],'valorOriginal'=>100,'carteiraConvenio'=>17,'variacaoCarteiraConvenio'=>(int)$a->variation,'contrato'=>1234,'codigoEstadoTituloCobranca'=>16]]];
 }elseif($method==='GET'&&str_contains($url,'/boletos/'.$remote.'?')){
  $response=['numeroContratoCobranca'=>1234,'numeroTituloCedenteCobranca'=>$title['numeroTituloBeneficiario'],'textoCampoUtilizacaoCedente'=>$title['campoUtilizacaoBeneficiario'],'numeroInscricaoSacadoCobranca'=>$title['pagador']['numeroInscricao'],'codigoTipoInscricaoSacado'=>(int)$title['pagador']['tipoInscricao'],'numeroCarteiraCobranca'=>17,'numeroVariacaoCarteiraCobranca'=>(int)$a->variation,'codigoModalidadeTitulo'=>1,'codigoIndicadorEconomico'=>9,'valorOriginalTituloCobranca'=>100,'codigoEstadoTituloCobranca'=>16,'dataEmissaoTituloCobranca'=>$title['dataEmissao'],'dataVencimentoTituloCobranca'=>$title['dataVencimento'],'valorPagoSacado'=>100,'valorCreditoCedente'=>100,'valorPagamentoParcialTitulo'=>0,'valorImpostoSobreOprFinanceirasRecebidoTitulo'=>0,'valorAbatimentoTotal'=>0,'valorJuroMoraRecebido'=>0,'valorDescontoUtilizado'=>0,'valorMultaRecebido'=>0,'valorReajuste'=>0,'valorOutroRecebido'=>0,'dataCreditoLiquidacao'=>(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('d.m.Y'),'codigoNaturezaRecebimento'=>1,'codigoTipoLiquidacao'=>2];
 }else throw new LogicException('unexpected synthetic protocol request');
 return ['status'=>200,'body'=>json_encode($response,JSON_THROW_ON_ERROR)];
};
$api=new \BancoBrasil\ApiService($db,'tbl',fn($v)=>$cipher->encrypt($v),fn($v)=>$cipher->decrypt($v),$transport);
$origin=$api->issue($issuer,$actor,(int)$profile['id'],$invoice,['payer_address'=>'Synthetic street','payer_district'=>'Centro','payer_zip'=>'04000000','payer_city'=>'Salvador','payer_state'=>'BA','species'=>'02','acceptance'=>'N'],bin2hex(random_bytes(32)));
$operation=$api->query($issuer,$actor,$origin,bin2hex(random_bytes(32)));
echo json_encode(compact('invoice','origin','operation'),JSON_THROW_ON_ERROR);
