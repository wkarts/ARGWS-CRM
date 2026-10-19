<?php
require __DIR__.'/../support/client_issuer_test_support.php';
require __DIR__.'/../../modules/multiple_companies/services/LegacyContactIdentity.php';
#[AllowDynamicProperties]
class ClientsController {}
require __DIR__.'/../../modules/multiple_companies/controllers/Clients.php';
function is_client_logged_in(){return true;}
function site_url(){return '/';}
class SwitchDenied extends RuntimeException {}
class SwitchRedirect extends RuntimeException {}
function show_error($message,$status){throw new SwitchDenied((string)$status);}
function show_404(){throw new SwitchDenied('404');}
function redirect($url){throw new SwitchRedirect($url);}
$pdo->exec("INSERT INTO q_clients(userid,company,vat,country) VALUES(4,'Legacy Other','other',1); INSERT INTO q_contacts(id,userid,is_primary,active,email) VALUES(40,4,1,1,'l@example.test'); UPDATE q_contacts SET email='l@example.test' WHERE id=20");
$targets=MultipleCompaniesLegacyContactIdentity::switchTargets($model->db,'q_',10,1);
check(array_column($targets,'id')===[10,40],'same-email switch offers only active legacy contacts');
check(MultipleCompaniesLegacyContactIdentity::switchTargets($model->db,'q_',20,2)===[],'private source cannot enumerate legacy identities by matching email');
check(MultipleCompaniesLegacyContactIdentity::switchTargets($model->db,'q_',10,2)===[],'stale source client/contact pair denied');
$controller=(new ReflectionClass(Clients::class))->newInstanceWithoutConstructor();$controller->db=$model->db;
$controller->input=new class {public $verb='GET';function method(){return $this->verb;}};
$controller->session=new class {public $data=['all_clients'=>[['id'=>20,'userid'=>2]]];function set_userdata($data){$this->data=array_replace($this->data,$data);}function unset_userdata($key){unset($this->data[$key]);}};
try{$controller->change_auth(4);throw new RuntimeException('GET accepted');}catch(SwitchDenied $e){check($e->getMessage()==='405','GET no longer switches authentication context');}
$controller->input->verb='POST';
try{$controller->change_auth(2);throw new RuntimeException('cached private target accepted');}catch(SwitchDenied $e){check($e->getMessage()==='404','forged/stale display cache never grants target');}
$GLOBALS['contact']=(object)['id'=>10];$GLOBALS['client']=(object)['userid'=>1];
try{$controller->change_auth(4);throw new RuntimeException('no redirect');}catch(SwitchRedirect $e){check(true,'native controller legacy switch accepted');}
check($controller->session->data['client_user_id']===4 && $controller->session->data['contact_user_id']===40,'fresh server lookup sets matching target pair');
check(!isset($controller->session->data['all_clients'],$GLOBALS['contact'],$GLOBALS['client']),'switch invalidates identity display caches');
$pdo->exec("INSERT INTO q_contacts(id,userid,is_primary,active,email) VALUES(41,4,0,1,'l@example.test')");
try{$controller->change_auth(4);throw new RuntimeException('ambiguous contact accepted');}catch(SwitchDenied $e){check(true,'duplicate matching target contacts require reconciliation');}
$pdo->exec('UPDATE q_contacts SET active=0 WHERE id IN(40,41)');
check(array_column(MultipleCompaniesLegacyContactIdentity::switchTargets($model->db,'q_',10,1),'id')===[10],'revoked target is absent despite prior display cache');
$pdo->exec('UPDATE q_contacts SET userid=2 WHERE id=10');
check(MultipleCompaniesLegacyContactIdentity::switchTargets($model->db,'q_',10,1)===[],'reparented source invalidates previous identity pair');
check($model->db->writes===0,'portal switch never copies credentials or writes customer data');
echo "Legacy portal switch: $n checks passed (actual controller/service, synthetic session/SQLite; no HTTP)\n";
