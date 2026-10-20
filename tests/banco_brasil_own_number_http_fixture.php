<?php
/** Own-number GUI transport fixture. Profiles/credentials come from real GUI; invoice and operations use canonical services. Synthetic provider state travels in the CLI input, never in production configuration. No real bank HTTP. */
if(PHP_SAPI!=='cli'||getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_upgrade_ci')exit(2);
set_exception_handler(static function(Throwable $error):void{fwrite(STDERR,get_class($error).': '.$error->getMessage().' at '.$error->getFile().':'.$error->getLine().PHP_EOL);exit(1);});
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');define('APP_MODULES_PATH',dirname(__DIR__).'/modules/');
require APPPATH.'config/app-config.php';if(APP_DB_NAME!=='argws_upgrade_ci')exit(2);
require __DIR__.'/support/BbPdoDatabase.php';require APPPATH.'services/CompanyInvoices.php';require APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ApiService.php';require BASEPATH.'libraries/Encryption.php';
function db_prefix(){return 'tbl';}function log_message($level,$message){}
$pdo=new PDO('mysql:host='.APP_DB_HOSTNAME.';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=new BbPdoDatabase($pdo);
$input=json_decode(base64_decode($argv[2]??'',true)?:'{}',true,32,JSON_THROW_ON_ERROR);$action=$argv[1]??'';if(!in_array($action,['prepare','reference','query'],true))exit(2);$monthly=false;$daily=false;$case=$input['case']??'a';if(!in_array($case,['a','b'],true))exit(2);$issuerTax=$case==='a'?'11222333000181':'52998224725';
$issuer=(int)$db->query('SELECT id FROM tblissuers_registry WHERE tax_id=?',[$issuerTax])->row()->id;
$actor=(int)$db->query('SELECT master_staff_id FROM tblissuers_installation_identity WHERE id=1')->row()->master_staff_id;
$payerTax=$case==='a'?'11144477735':'12345678909';$clientRow=$db->query('SELECT c.userid FROM tblclients c JOIN tblissuers_clients o ON o.client_id=c.userid WHERE o.owner_issuer_id=? AND c.vat=?',[$issuer,$payerTax])->row_array();
$client=(int)($clientRow['userid']??0);if(!$client&&$case==='b'){require_once APPPATH.'services/CompanyClients.php';$client=(new CompanyClients($db,'tbl'))->create($issuer,$actor,['company'=>'Synthetic API B payer','vat'=>$payerTax]);}
$profile=$db->query('SELECT * FROM tblbb_api_profiles WHERE issuer_id=? AND id=?',[$issuer,(int)($input['profile']??0)])->row_array();
if(!$profile||$profile['api_stage']!=='homologation'||$issuer<1||$actor<1||$client<1)throw new RuntimeException('synthetic fixture unavailable');
$definition=json_decode($profile['definition_json'],true,16,JSON_THROW_ON_ERROR);if(($definition['discount_policy']??null)!=='fixed_until')throw new RuntimeException('fixed offer GUI profile required');if(($definition['interest_exempt']??null)!==true||array_key_exists('interest_policy',$definition))throw new RuntimeException('GUI explicit exempt profile required');
$connection=$db->query('SELECT * FROM tblbb_connections WHERE id=? AND issuer_id=?',[$profile['connection_id'],$issuer])->row_array();
$a=new \BancoBrasil\Agreement(new \BancoBrasil\Scope($issuer,(int)$connection['id'],$connection['account_id'],$connection['environment'],$connection['agreement_number']),$connection['issuer_tax_id'],$connection['issuer_name'],$connection['variation'],$connection['wallet']);
$currency=(int)$db->query("SELECT id FROM tblcurrencies WHERE name='BRL'")->row()->id;$today=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');$due=(new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');
$invoices=new CompanyInvoices($db,'tbl');
if($action==='prepare')$invoice=$invoices->create($issuer,$actor,['client_id'=>$client,'currency'=>$currency,'date'=>$today,'due_date'=>$due,'items'=>[['description'=>'Synthetic119 operational browser','qty'=>'1.00','rate'=>'100.00']]],bin2hex(random_bytes(32)));
else{$invoice=(int)($input['invoice']??0);$origin=(int)($input['origin']??0);$owned=$db->query('SELECT o.id FROM tblbb_origins o JOIN tblbb_api_origins x ON x.origin_id=o.id WHERE o.id=? AND o.invoice_id=? AND o.issuer_id=? AND x.profile_id=?',[$origin,$invoice,$issuer,(int)$profile['id']])->row_array();if(!$owned)throw new RuntimeException('synthetic scope mismatch');}
$discountUntil=(new DateTimeImmutable($today))->modify('+10 days')->format('Y-m-d');$cipher=new CI_Encryption(['driver'=>'openssl','key'=>APP_ENC_KEY]);$title=$input['wire']['title']??null;$remote=$input['wire']['remote']??null;$abatement=$input['wire']['abatement']??'0.00';$bankAddress=$input['wire']['address']??null;$patchCount=(int)($input['wire']['patchCount']??0);
$transport=static function($method,$url,$headers,$body)use(&$title,&$remote,&$abatement,&$bankAddress,&$patchCount,$db,$a,$due,$monthly,$daily):array{
 if($db->conn_id->inTransaction())throw new LogicException('synthetic HTTP inside transaction');
 if(str_contains($url,'oauth/token'))return ['status'=>200,'body'=>'{"access_token":"synthetic-browser-token","token_type":"Bearer","expires_in":600}'];
 parse_str(parse_url($url,PHP_URL_QUERY),$query);
 if($method==='PATCH'){
  ++$patchCount;$wire=\BancoBrasil\ApiClient::decodeExact($body);
  if(($wire['indicadorAlterarSeuNumero']??null)!=='S')throw new LogicException('unexpected synthetic own-number PATCH');foreach($wire as$key=>$value)if(str_starts_with($key,'indicador')&&$value!==($key==='indicadorAlterarSeuNumero'?'S':'N'))throw new LogicException('unrelated synthetic mutation');$title['operational_reference']=$wire['alteracaoSeuNumero']['codigoSeuNumero'];
  return ['status'=>200,'body'=>json_encode(['numeroContratoCobranca'=>1234,'dataAtualizacao'=>gmdate('d.m.Y'),'horarioAtualizacao'=>'14:21:05'],JSON_THROW_ON_ERROR)];
 }
 if($method==='POST'&&str_contains($url,'/boletos?')){
  $title=\BancoBrasil\ApiClient::decodeExact($body);$remote=$title['numeroTituloCliente'];
  $factor=1000+(int)(new DateTimeImmutable('2025-02-22'))->diff(new DateTimeImmutable($due))->format('%r%a');$barcode='00190'.str_pad((string)$factor,4,'0',STR_PAD_LEFT).'0000010000'.'000000'.substr($remote,3).'17';
  $sum=0;$weight=2;foreach(array_reverse(str_split(substr($barcode,0,4).substr($barcode,5))) as $digit){$sum+=(int)$digit*$weight;$weight=$weight===9?2:$weight+1;}$dv=11-$sum%11;$barcode[4]=(string)(in_array($dv,[0,10,11],true)?1:$dv);
  $response=['numero'=>$remote,'numeroCarteira'=>17,'numeroVariacaoCarteira'=>(int)$a->variation,'numeroContratoCobranca'=>1234,'codigoBarraNumerico'=>$barcode,'linhaDigitavel'=>\BancoBrasil\ApiPayload::line($barcode),'beneficiario'=>['agencia'=>(int)$a->branch,'contaCorrente'=>(int)$a->account]];
  return ['status'=>201,'body'=>json_encode($response,JSON_THROW_ON_ERROR)];
 }
 if($method==='GET'&&str_contains($url,'/boletos?')){
  if(($query['contaBeneficiario']??'')!==(string)(int)$a->account||($query['agenciaBeneficiario']??'')!==(string)(int)$a->branch)throw new RuntimeException('synthetic account mismatch');
  $response=['indicadorContinuidade'=>'N','proximoIndice'=>0,'quantidadeRegistros'=>1,'boletos'=>[['numeroBoletoBB'=>$remote,'dataVencimento'=>$title['dataVencimento'],'valorOriginal'=>100,'carteiraConvenio'=>17,'variacaoCarteiraConvenio'=>(int)$a->variation,'contrato'=>1234,'codigoEstadoTituloCobranca'=>1]]];
 }elseif($method==='GET'&&str_contains($url,'/boletos/'.$remote.'?')){
  $response=['numeroContratoCobranca'=>1234,'numeroTituloCedenteCobranca'=>$title['operational_reference']??$title['numeroTituloBeneficiario'],'textoCampoUtilizacaoCedente'=>$title['campoUtilizacaoBeneficiario'],'numeroInscricaoSacadoCobranca'=>$title['pagador']['numeroInscricao'],'codigoTipoInscricaoSacado'=>(int)$title['pagador']['tipoInscricao'],'numeroCarteiraCobranca'=>17,'numeroVariacaoCarteiraCobranca'=>(int)$a->variation,'codigoModalidadeTitulo'=>1,'codigoIndicadorEconomico'=>9,'valorOriginalTituloCobranca'=>100,'codigoEstadoTituloCobranca'=>1,'dataEmissaoTituloCobranca'=>$title['dataEmissao'],'dataVencimentoTituloCobranca'=>$title['dataVencimento'],'valorPagoSacado'=>0,'valorCreditoCedente'=>0,'valorPagamentoParcialTitulo'=>0,'valorImpostoSobreOprFinanceirasRecebidoTitulo'=>0,'valorAbatimentoTotal'=>0,'valorJuroMoraRecebido'=>0,'valorDescontoUtilizado'=>0,'valorMultaRecebido'=>0,'valorReajuste'=>0,'valorOutroRecebido'=>0,'dataCreditoLiquidacao'=>(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('d.m.Y'),'codigoNaturezaRecebimento'=>1,'codigoTipoLiquidacao'=>2];
  $response+=['valorAbatimentoTituloCobranca'=>$abatement]+($bankAddress??['textoEnderecoSacadoCobranca'=>$title['pagador']['endereco'],'nomeBairroSacadoCobranca'=>$title['pagador']['bairro'],'nomeMunicipioSacadoCobranca'=>$title['pagador']['cidade'],'siglaUnidadeFederacaoSacadoCobranca'=>$title['pagador']['uf'],'numeroCepSacadoCobranca'=>(string)$title['pagador']['cep']]);
  if($monthly)$response+=['codigoTipoJuroMora'=>2,'valorJuroMoraTitulo'=>0,'percentualJuroMoraTitulo'=>1.25];if($daily)$response+=['codigoTipoJuroMora'=>1,'valorJuroMoraTitulo'=>0.10,'percentualJuroMoraTitulo'=>0];$response+=['codigoDescontoTitulo'=>1,'valorDescontoTitulo'=>$title['desconto']['valor'],'percentualDescontoTitulo'=>0,'dataDescontoTitulo'=>$title['desconto']['dataExpiracao'],'codigoSegundoDescontoTitulo'=>0,'valorSegundoDescontoTitulo'=>0,'percentualSegundoDescontoTitulo'=>0,'codigoTerceiroDescontoTitulo'=>0,'valorTerceiroDescontoTitulo'=>0,'percentualTerceiroDescontoTitulo'=>0];
 }else throw new LogicException('unexpected synthetic protocol request');
 return ['status'=>200,'body'=>json_encode($response,JSON_THROW_ON_ERROR)];
};
$api=new \BancoBrasil\ApiService($db,'tbl',fn($v)=>$cipher->encrypt($v),fn($v)=>$cipher->decrypt($v),$transport);
if($action==='prepare'){
 $origin=$api->issue($issuer,$actor,(int)$profile['id'],$invoice,['payer_address'=>'Synthetic street','payer_district'=>'Centro','payer_zip'=>'04000000','payer_city'=>'Salvador','payer_state'=>'BA','species'=>'02','acceptance'=>'N','fixed_discount'=>'10.00','discount_until'=>$discountUntil],bin2hex(random_bytes(32)));
 $operation=$api->query($issuer,$actor,$origin,bin2hex(random_bytes(32)));
}else{
 $key=$input['request_key']??'';$instruction=$input['instruction']??[];
 $operation=match($action){'reference'=>$api->amendOwnNumber($issuer,$actor,$origin,$instruction,$key),'query'=>$api->query($issuer,$actor,$origin,$key)};
}
$snapshot=hash('sha256',json_encode($invoices->document($issuer,$actor,$invoice)->data(),JSON_THROW_ON_ERROR));
if($action!=='prepare'&&$snapshot!==($input['snapshot']??''))throw new RuntimeException('immutable document changed');
if($invoices->payments($issuer,$actor,$invoice)!==[])throw new RuntimeException('operational instruction posted money');
$status=$db->query('SELECT p.status FROM tblbb_api_operations p JOIN tblbb_origins o ON o.id=p.origin_id WHERE p.id=? AND o.issuer_id=?',[$operation,$issuer])->row()->status;
$wire=['title'=>$title,'remote'=>$remote,'abatement'=>$abatement,'address'=>$bankAddress,'patchCount'=>$patchCount];
echo json_encode(['case'=>$case,'profile'=>(int)$profile['id']]+compact('invoice','origin','operation','status','discountUntil','snapshot','wire'),JSON_THROW_ON_ERROR);
