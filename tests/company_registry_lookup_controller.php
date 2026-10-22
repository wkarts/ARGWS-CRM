<?php
require __DIR__.'/settings/company_registry_identity.php';
$start=$GLOBALS['essentialChecks'];
class CompanyRequestScope{public static int $actor=7;static function sealedFor($c){return true;}static function context(){return new class{function staffId(){return CompanyRequestScope::$actor;}};}}
class AdminController{function __get($key){return $GLOBALS['CI']->$key;}}
$GLOBALS['CI']->input=new class{public array $post=[];public string $verb='POST';function method($upper=false){return $upper?$this->verb:strtolower($this->verb);}function post($key,$filter=false){return $this->post[$key]??null;}};
$GLOBALS['CI']->output=new class{public int $status=200;public string $body='';function set_header($v){return $this;}function set_status_header($v){$this->status=$v;return $this;}function set_content_type($v){return $this;}function set_output($v){$this->body=$v;return $this;}};
$GLOBALS['CI']->security=new class{function get_csrf_token_name(){return 'native_csrf';}function get_csrf_hash(){return 'renewed-token';}};
$GLOBALS['CI']->session=new class{public array $data=[];function userdata($k){return $this->data[$k]??null;}function set_userdata($k,$v){$this->data[$k]=$v;}};
require APPPATH.'controllers/admin/Issuers.php';
class FixtureIssuers extends Issuers{public int $calls=0;protected function queryRegistry(string $kind,string $value):array{$this->calls++;return ['fields'=>['legal_name'=>'Synthetic preview'],'provider'=>'ReceitaWS'];}}
$controller=(new ReflectionClass(FixtureIssuers::class))->newInstanceWithoutConstructor();$property=new ReflectionProperty(Issuers::class,'registry');$property->setValue($controller,$registry);
$token=str_repeat('a',64);$GLOBALS['CI']->session->data=['issuers_csrf'=>$token,'company_onboarding_csrf'=>$token];
$valid=['lookup_mode'=>'registry','lookup_csrf'=>$token,'issuer_id'=>(string)$new,'kind'=>'cnpj','value'=>'11222333000181'];
$run=function($post,$verb='POST')use($controller){$GLOBALS['CI']->input->post=$post;$GLOBALS['CI']->input->verb=$verb;$controller->lookup();return $GLOBALS['CI']->output->status;};
$before=$identity->read($new);
essentialCheck($run($valid,'GET')===405&&$controller->calls===0,'GET cannot contact provider');
essentialCheck($run(array_replace($valid,['lookup_csrf'=>'bad']))===403&&$controller->calls===0,'dedicated CSRF rejected before provider');
CompanyRequestScope::$actor=12;
essentialCheck($run($valid)===403&&$controller->calls===0,'foreign issuer lookup rejected before provider');
essentialCheck($run(array_replace($valid,['lookup_mode'=>'onboarding','issuer_id'=>'']))===403&&$controller->calls===0,'only master may look up new company');
CompanyRequestScope::$actor=7;
essentialCheck($run(array_replace($valid,['value'=>'invalid']))===422&&$controller->calls===0,'invalid ID rejected before provider');
essentialCheck($run($valid)===200&&$controller->calls===1,'authorized lookup returns preview');
$body=json_decode($GLOBALS['CI']->output->body,true);essentialCheck($body['csrf']['hash']==='renewed-token'&&$body['fields']['legal_name']==='Synthetic preview','response renews native token with preview');
essentialCheck($run($valid)===429&&$controller->calls===1,'repeated lookup rate limited');
essentialCheck($identity->read($new)===$before,'lookup never mutates registry');
$GLOBALS['CI']->session->data['company_registry_lookup_at']=0;
essentialCheck($run(array_replace($valid,['lookup_mode'=>'onboarding','issuer_id'=>'']))===200&&$controller->calls===2,'master onboarding preview allowed');
echo 'Registry lookup controller: '.($GLOBALS['essentialChecks']-$start)." added checks passed. No network.\n";
