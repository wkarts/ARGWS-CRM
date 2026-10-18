<?php
require __DIR__ . '/support/client_issuer_test_support.php';
require __DIR__ . '/../modules/api/models/Api_model.php';
require __DIR__ . '/../application/models/Misc_model.php';
class ClientApiProbe extends Api_model { public function __construct() {} }
class ClientMiscProbe extends Misc_model { public function __construct() {} }
$api = new ClientApiProbe;
$api->db = $model->db;
$api->Clients_model = $api->clients_model = $model;
$api->load = new class { public function model($name) {} };
check(array_column($api->get_table('clients',''),'userid') === [1], 'API directory legacy only');
check($api->get_table('clients',2) === null, 'API private customer ID denied');
check($api->get_table('contacts',20) === null, 'API private contact ID denied');
check($api->get_table('all_contacts',2) === [], 'API private contact list denied');
$public = $api->get_table('contacts',10);
check($public->email === 'l@example.test', 'API legacy contact preserved');
foreach (['password','new_pass_key','new_pass_key_requested','email_verification_key'] as $field) check(!property_exists($public,$field), 'API DTO drops '.$field);
$public = $api->get_table('all_contacts',1);
check(count($public) === 1 && !isset($public[0]['password']), 'API contact list uses safe DTO');
check(array_column($api->search('customers',''),'userid') === [1], 'API empty customer search scoped');
check($api->search('customers','Private') === [], 'API customer text search scoped');
check(array_column($api->search('contacts',''),'id') === [10], 'API empty contact search scoped');
check($api->search('contacts','Private') === [], 'API contact text search scoped');
$public = $api->search('contacts','Legacy');
check(count($public) === 1 && $public[0]['company'] === 'Legacy', 'API custom-prefix contact search positive control');
check(!isset($public[0]['vat']) && !isset($public[0]['address']) && !isset($public[0]['password']), 'contact search does not embed whole customer or credentials');
$pdo->exec("ALTER TABLE q_contacts ADD phonenumber TEXT DEFAULT ''; ALTER TABLE q_contacts ADD title TEXT DEFAULT ''");
$misc = new ClientMiscProbe;
$misc->db = $model->db;
$misc->input = new class { public function post($key) { return false; } };
check(array_column($misc->_search_contacts('')['result'],'id') === [10], 'global contacts search scoped');
check($misc->_search_contacts('Private')['result'] === [], 'global private search denied');
check(array_column($misc->_search_contacts('',0,'q_contacts.id=20 OR q_contacts.id=10')['result'],'id') === [10], 'global contact OR cannot bypass');
check($misc->add_attachment_to_database(2,'customer',[]) === false && $model->db->writes === 0, 'generic external/upload writer rejects private client before work');
echo "Client issuer API/search: $n checks passed (real models / SQLite)\n";
