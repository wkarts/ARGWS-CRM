<?php
/** Synthetic certificates and server-variable boundary; not a real BB TLS handshake or homologation. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');
require_once APPPATH.'services/InvoicePaymentWriter.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/ApiWebhook.php';
$checks=0;function mtlsCheck($value,$label){global $checks;$checks++;if(!$value)throw new RuntimeException($label);}function mtlsDeny($work,$expected){$message=null;try{$work();}catch(Throwable $e){$message=$e->getMessage();}mtlsCheck($message===$expected,'expected '.$expected.', got '.$message);}
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);$csr=openssl_csr_new(['commonName'=>'SYNTHETIC CLIENT ONLY'],$key,['digest_alg'=>'sha256']);$certificate=openssl_csr_sign($csr,null,$key,1,['digest_alg'=>'sha256']);openssl_x509_export($certificate,$pem);
$native=['HTTPS'=>'on','SSL_CLIENT_VERIFY'=>'SUCCESS','SSL_CLIENT_CERT'=>$pem];
$tls=\BancoBrasil\MtlsEvidence::fromNativeTls($native,true);mtlsCheck($tls->fingerprint===openssl_x509_fingerprint($pem,'sha256'),'fingerprint is SHA256 certificate DER, not arbitrary declared header');
mtlsDeny(fn()=>\BancoBrasil\MtlsEvidence::fromNativeTls($native,false),'bb_webhook_transport_denied');
mtlsDeny(fn()=>\BancoBrasil\MtlsEvidence::fromNativeTls(['HTTPS'=>'on','HTTP_SSL_CLIENT_VERIFY'=>'SUCCESS','HTTP_SSL_CLIENT_CERT'=>$pem],true),'bb_webhook_transport_denied');
mtlsDeny(fn()=>\BancoBrasil\MtlsEvidence::fromNativeTls(array_replace($native,['SSL_CLIENT_VERIFY'=>'NONE']),true),'bb_webhook_transport_denied');
mtlsDeny(fn()=>\BancoBrasil\MtlsEvidence::fromNativeTls(array_replace($native,['HTTPS'=>'off']),true),'bb_webhook_transport_denied');
mtlsDeny(fn()=>\BancoBrasil\MtlsEvidence::fromNativeTls(array_replace($native,['SSL_CLIENT_CERT'=>'not a certificate']),true),'bb_webhook_transport_denied');
$wire=['id'=>'00012345670000000100','numeroConvenio'=>1234567,'numeroOperacao'=>1001,'carteiraConvenio'=>17,'variacaoCarteiraConvenio'=>19,'codigoEstadoBaixaOperacional'=>1,'codigoModalidadeBoleto'=>1,'canalLiquidacao'=>3,'formaPagamento'=>2,'dataRegistro'=>'19.10.2026','dataVencimento'=>'19.11.2026','dataCredito'=>'20.10.2026','dataLiquidacao'=>'19/10/2026 12:20:30','valorOriginal'=>100.10,'valorPagoSacado'=>100.10,'nomePortador'=>'SYNTHETIC PRIVATE NAME','identidadePortador'=>'11144477735'];
$projection=\BancoBrasil\ApiWebhook::projection(json_encode($wire,JSON_THROW_ON_ERROR));mtlsCheck($projection['valorPagoSacado']==='100.10'&&$projection['observation']==='operational_down_observed','exact cents and operational observation, not financial receipt');
mtlsCheck(!str_contains(json_encode($projection),'PRIVATE NAME')&&!isset($projection['identidadePortador']),'payer identity omitted from persisted/GUI projection');
mtlsCheck(\BancoBrasil\ApiWebhook::projection(json_encode(array_reverse($wire,true)))===$projection,'canonical field order tolerates equivalent payload key ordering');
mtlsDeny(fn()=>\BancoBrasil\ApiWebhook::projection(json_encode(array_replace($wire,['numeroConvenio'=>7654321]))),'bb_webhook_payload_invalid');
mtlsDeny(fn()=>\BancoBrasil\ApiWebhook::projection(json_encode(array_replace($wire,['dataCredito'=>'31.02.2026']))),'bb_webhook_payload_invalid');
mtlsDeny(fn()=>\BancoBrasil\ApiWebhook::projection(json_encode(array_replace($wire,['codigoEstadoBaixaOperacional'=>16]))),'bb_webhook_payload_invalid');
mtlsDeny(fn()=>\BancoBrasil\ApiWebhook::projection('{"id":1,"id":2}'),'bb_api_duplicate_key');
$cancel=\BancoBrasil\ApiWebhook::projection(json_encode(array_replace($wire,['codigoEstadoBaixaOperacional'=>10])));mtlsCheck($cancel['observation']==='operational_down_cancelled','cancellation is adverse observation requiring review');
mtlsDeny(fn()=>\BancoBrasil\ApiWebhook::projection(str_repeat('x',65537)),'bb_webhook_payload_invalid');
$reflection=new ReflectionClass(\BancoBrasil\MtlsEvidence::class);mtlsCheck($reflection->getConstructor()->isPrivate()&&$reflection->getProperty('fingerprint')->isReadOnly(),'transport evidence cannot be constructed or rebound by ordinary caller');
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/ApiService.php';
$amend=\BancoBrasil\ApiClient::decodeExact(\BancoBrasil\ApiService::amendmentPayload('1234567','2026-11-21'));
mtlsCheck(count(array_filter($amend,fn($v)=>$v==='N'))===17&&$amend['indicadorNovaDataVencimento']==='S'&&$amend['alteracaoData']['novaDataVencimento']==='21.11.2026','date PATCH explicitly sets all 18 mandatory indicators');
require_once APPPATH.'services/CompanyBbCallbackScope.php';
function bbCallbackCi($parts=['banco_brasil','notifications'],$directory='../../modules/banco_brasil/controllers',$class='notifications',$method='index',$module='banco_brasil',$cli=false){return (object)['input'=>new class($cli){function __construct(private $cli){}function is_cli_request(){return $this->cli;}},'uri'=>new class($parts){function __construct(private $parts){}function segment($i){return $this->parts[$i-1]??null;}},'router'=>new class($directory,$class,$method,$module){function __construct(private $directory,private $class,private $method,private $module){}function fetch_directory(){return $this->directory;}function fetch_class(){return $this->class;}function fetch_method(){return $this->method;}function fetch_module(){return $this->module;}}];}
mtlsCheck(CompanyBbCallbackScope::prepare(bbCallbackCi())&&CompanyBbCallbackScope::active(),'only exact public module callback enters minimal autoload boundary');
foreach([bbCallbackCi(['admin','banco_brasil','notifications']),bbCallbackCi(['banco_brasil','notifications','index','extra']),bbCallbackCi(['banco_brasil','notifications'],'admin'),bbCallbackCi(['banco_brasil','notifications'],'../../modules/banco_brasil/controllers','notifications','helper'),bbCallbackCi(['banco_brasil','notifications'],'../../modules/other/controllers'),bbCallbackCi(['banco_brasil','notifications'],'../../modules/banco_brasil/controllers','notifications','index','other'),bbCallbackCi(['banco_brasil','notifications'],'../../modules/banco_brasil/controllers','notifications','index','banco_brasil',true)] as $invalid)mtlsCheck(!CompanyBbCallbackScope::prepare($invalid)&&!CompanyBbCallbackScope::active(),'admin/extra/helper/foreign/CLI route cannot inherit callback boundary');
mtlsCheck(CompanyBbCallbackScope::prepare(bbCallbackCi(['banco_brasil','notifications','index'])),'explicit index alias remains exact callback');
if(!class_exists('CI_Controller')){#[AllowDynamicProperties] class CI_Controller {}}
function show_404(){throw new DomainException('synthetic_404');}
require_once __DIR__.'/../modules/banco_brasil/controllers/Notifications.php';
$controller=new Notifications();$controller->input=new class{function method($upper){return 'POST';}};
$controller->output=new class{public $status=200;public $body='';function set_header($v){return $this;}function set_content_type($v){return $this;}function set_status_header($v){$this->status=$v;return $this;}function set_output($v){$this->body=$v;return $this;}};
$controller->load=new class{function database(){throw new LogicException('unauthenticated DB hydration');}};
$serverBefore=$_SERVER;$_SERVER=['HTTPS'=>'on','HTTP_SSL_CLIENT_VERIFY'=>'SUCCESS','HTTP_SSL_CLIENT_CERT'=>$pem];$controller->index();$_SERVER=$serverBefore;
mtlsCheck($controller->output->status===401&&$controller->output->body==='{"accepted":false}','public controller rejects forged TLS headers before database or session load');
CompanyBbCallbackScope::prepare(bbCallbackCi(['admin','banco_brasil','notifications']));mtlsDeny(fn()=>$controller->index(),'synthetic_404');
echo 'BB mTLS boundary/projection: '.$checks.' checks; synthetic certificates, no external bank handshake.'.PHP_EOL;
