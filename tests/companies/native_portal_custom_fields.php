<?php
require __DIR__.'/native_portal_registration.php';
require APPPATH.'services/PortalRequestScope.php';
require APPPATH.'helpers/custom_fields_helper.php';
require APPPATH.'helpers/fields_helper.php';
if(!function_exists('_l')){function _l($s,$args='',$log=true){return$s;}}
function is_admin(){throw new LogicException('registration cannot query staff');}
function is_client_logged_in(){return false;}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function form_error($key){return'';}
function set_value($key,$default=''){return$default;}
function html_escape($v){return e($v);}
$store->mutate(41,7,'update','allow_registration','1',null);
$cf=new NativeCompanyCustomFields($db,$p);$def=$cf->saveDefinition(41,7,['fieldto'=>'customers','name'=>'Public custom identity','type'=>'input','required'=>1,'show_on_client_portal'=>1],null,null,str_repeat('1',32));
$hidden=$cf->saveDefinition(41,7,['fieldto'=>'customers','name'=>'Private staff note','type'=>'input','show_on_client_portal'=>0],null,null,str_repeat('2',32));
$ci=$f->ci;$ci->session=new class{public array$data=[];function userdata($k){return$this->data[$k]??null;}function set_userdata($d){$this->data=array_replace($this->data,$d);}};
$ci->router=new class{public string$method='register';function fetch_class(){return'authentication';}function fetch_method(){return$this->method;}function fetch_directory(){return'';}function fetch_module(){return null;}};
$ci->input=new class{function get($k,$filter=false){return$k==='issuer_id'?'41':null;}function post($k=null,$filter=false){return null;}function is_cli_request(){return false;}};
$ci->config=new class{function item($k){return$k==='encryption_key'?str_repeat('synthetic-',4):null;}};
PortalRequestScope::prepare($ci);PortalRequestScope::resolve($ci);
$start=$GLOBALS['essentialChecks'];$html=render_custom_fields('customers','',['show_on_client_portal'=>1]);
essentialCheck(str_contains($html,'Public custom identity')&&!str_contains($html,'Private staff note')&&str_contains($html,'custom_fields[customers]'),'original renderer consumes only issuer-published registration definitions');
essentialThrows(fn()=>render_custom_fields('customers',9,['show_on_client_portal'=>1]),'native_custom_field_denied','registration renderer cannot read existing resource');
essentialThrows(fn()=>render_custom_fields('staff','',['show_on_client_portal'=>1]),'native_custom_field_denied','registration cannot render staff definitions');
$ci->router->method='login';PortalRequestScope::prepare($ci);PortalRequestScope::resolve($ci);essentialThrows(fn()=>render_custom_fields('customers','',['show_on_client_portal'=>1]),'native_custom_field_denied','another public action cannot use registration rendering authority');
$publicInput=array_replace($input,['email'=>'with-field@example.invalid','custom_fields'=>['customers'=>[(string)$def['id']=>'Published value']]]);
$result=$writer->register(41,hash('sha256','with-field'),$publicInput,$hash,$verify);
$values=$db->query("SELECT value FROM {$p}company_custom_field_values WHERE issuer_id=41 AND source_kind='client' AND source_id=?",[$result['client_id']])->result_array();essentialCheck(array_column($values,'value')===['Published value'],'public creation writes required custom value only to newly attested client');
essentialThrows(fn()=>$writer->register(41,hash('sha256','missing-field'),array_replace($input,['email'=>'missing-field@example.invalid']),$hash,$verify),'native_custom_field_required','missing required published field prevents complete registration');
essentialThrows(fn()=>$cf->saveRegisteredInOwnedTransaction(41,'customers',$result['client_id'],[(string)$def['id']=>'Changed'],$writer),'issuer_owned_transaction_required','post-creation invocation cannot reuse public writer to edit existing custom value');
echo 'Native portal original custom fields renderer: '.($GLOBALS['essentialChecks']-$start).' real service/helper checks passed.'.PHP_EOL;

