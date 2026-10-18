<?php
require __DIR__.'/portal_client_identity.php';
require __DIR__.'/../application/libraries/App_clients_area_constructor.php';
function &get_instance(){return $GLOBALS['portalCi'];}
function db_prefix(){return 'x_';}
function is_client_logged_in(){return $GLOBALS['portalCi']->session->userdata('client_logged_in')===true;}
function _l($key){return $key;}
function site_url(){return '/';}
function redirect($url){throw new DomainException('redirected');}
function init_customers_area_assets(){}
function get_option($key){return $key==='company_requires_vat_number_field'?1:0;}
function is_rtl($x){return false;}
function hooks(){return new class{function do_action(...$args){}function apply_filters($name,$value){return $value;}};}
class PortalNoop {function __call($name,$args){return [];}}
class PortalLoad {public array $values=[];function model(...$args){}function library(...$args){}function vars($vars){$this->values=$vars;}}
class PortalLegacyClient {public int $reads=0;function get($id){$this->reads++;if((int)$id!==1)throw new RuntimeException('private directory read');return (object)['userid'=>1,'company'=>'Legacy Display','vat'=>null];}}
$legacy=new PortalLegacyClient();$auth=new class{public int $logouts=0;function logout($client){$this->logouts++;}};
$GLOBALS['portalCi']=(object)['db'=>new PortalDb($pdo),'session'=>sessionFor(),'load'=>new PortalLoad(),'form_validation'=>new PortalNoop(),'clients_model'=>$legacy,'authentication_model'=>$auth,'announcements_model'=>new PortalNoop(),'tickets_model'=>new PortalNoop(),'departments_model'=>new PortalNoop(),'currencies_model'=>new PortalNoop(),'app_menu'=>new PortalNoop()];
$ci=$GLOBALS['portalCi'];
new App_clients_area_constructor();
check($auth->logouts===0,'private contact remains logged in');check($legacy->reads===0,'private identity never uses directory model');check($ci->load->values['client']->company==='PrivateX','own private client rendered');check((int)$ci->load->values['contact']->id===20,'own contact rendered');check(!property_exists($GLOBALS['contact'],'password'),'global contact excludes auth hash');
$ci->session=sessionFor(10,1);new App_clients_area_constructor();check($legacy->reads===1,'legacy display behavior preserved');check($ci->load->values['client']->company==='Legacy Display','legacy display value');
$ci->session=sessionFor(20,3);try{new App_clients_area_constructor();throw new RuntimeException('invalid identity admitted');}catch(DomainException $e){check($e->getMessage()==='redirected','mismatched session redirected');}check($auth->logouts===1,'mismatched identity logged out');
check(!\app\services\ClientIssuerScope::isLegacyClient($ci->db,'x_',2),'portal constructor never opens staff directory');
echo "OK: $n combined identity/constructor checks.\n";
