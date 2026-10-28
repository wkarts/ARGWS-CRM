<?php
require __DIR__.'/client_profiles.php';$start=$GLOBALS['essentialChecks'];
class CompanyRequestScope{public static int $issuer=41;public static int $actor=12;public static int $primary=0;public static bool $valid=true;static function nativeFor($c){return true;}static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return CompanyRequestScope::$actor;}function isPrimary(){return CompanyRequestScope::$issuer===CompanyRequestScope::$primary;}};}static function assertNativeMutation($c){if($c->input->method(true)!=='POST'||!self::$valid||$c->input->post('_native_company_csrf',false)!=='synthetic-csrf')throw new DomainException('native_company_csrf_denied');}}
class AdminController{function __get($key){return $GLOBALS['CI']->$key;}}
$GLOBALS['CI']->input=new class{public array $post=[];public string $verb='POST';function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}function post($key,$filter=false){return $this->post[$key]??null;}};
$GLOBALS['CI']->output=new class{public int $status=200;public string $body='';function set_header($v){return $this;}function set_status_header($v){$this->status=$v;return $this;}function set_content_type($v){return $this;}function set_output($v){$this->body=$v;return $this;}};
$GLOBALS['CI']->security=new class{function get_csrf_token_name(){return 'native_csrf';}function get_csrf_hash(){return 'renewed-token';}};
$GLOBALS['CI']->session=new class{public array $data=[];function userdata($k){return $this->data[$k]??null;}function set_userdata($k,$v){$this->data[$k]=$v;}};
require APPPATH.'controllers/admin/Clients.php';
class FixtureClientLookup extends Clients{public int $calls=0;protected function queryRegistry(string $kind,string $value):array{$this->calls++;return ['fields'=>['legal_name'=>'Synthetic preview','company_trade_name'=>'Vitrine','company_address_number'=>'20'],'provider'=>'ReceitaWS'];}}
$controller=new FixtureClientLookup();CompanyRequestScope::$primary=$primary;
$valid=['_native_company_csrf'=>'synthetic-csrf','client_id'=>'','kind'=>'cnpj','value'=>'11222333000181'];
$run=function($post,$verb='POST',$scopeValid=true)use($controller){$GLOBALS['CI']->input->post=$post;$GLOBALS['CI']->input->verb=$verb;CompanyRequestScope::$valid=$scopeValid;$GLOBALS['CI']->output->status=200;$GLOBALS['CI']->output->body='';$controller->lookup();return $GLOBALS['CI']->output->status;};
essentialCheck($run($valid,'GET')===405&&$controller->calls===0,'GET cannot query');
essentialCheck($run(array_replace($valid,['_native_company_csrf'=>'bad']))===403&&$controller->calls===0,'native CSRF before lookup');
essentialCheck($run(array_replace($valid,['client_id'=>(string)$pjId]))===403&&$controller->calls===0,'foreign owner client cannot query');
essentialCheck($run(array_replace($valid,['client_id'=>'9']))===403&&$controller->calls===0,'secondary cannot query legacy customer');
CompanyRequestScope::$issuer=82;
essentialCheck($run(array_replace($valid,['client_id'=>(string)$pfId]))===403&&$controller->calls===0,'shared customer remains readonly for lookup');
CompanyRequestScope::$issuer=41;
essentialCheck($run($valid)===200&&$controller->calls===1,'own create lookup allowed');$result=json_decode($GLOBALS['CI']->output->body,true);
essentialCheck($result['fields']===['company'=>'Synthetic preview','trade_name'=>'Vitrine','address_number'=>'20'],'lookup explicit client field mapping');
essentialCheck($run($valid)===429&&$controller->calls===1,'shared rate limiter');
$GLOBALS['CI']->session->data['company_registry_lookup_at']=0;CompanyRequestScope::$issuer=$primary;CompanyRequestScope::$actor=7;
essentialCheck($run(array_replace($valid,['client_id'=>'9']))===200&&$controller->calls===2,'primary legacy authorized lookup');
$GLOBALS['CI']->session->data['company_registry_lookup_at']=0;
essentialCheck($run(array_replace($valid,['value'=>'52998224725']))===422&&$controller->calls===2,'CPF not sent to CNPJ provider');
echo 'Client lookup real controller: '.($GLOBALS['essentialChecks']-$start)." added checks, no network.\n";
