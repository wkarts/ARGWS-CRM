<?php
/** Dependency guard in the same transaction as a prospective customer delete. */
require __DIR__.'/native_clients.php';
require_once APPPATH.'services/CompanyCustomerDeletionReferences.php';
$directFixtures=['invoices'=>'clientid','leads'=>'converted_client_id','company_invoice_drafts'=>'client_id','company_invoice_copy_requests'=>'source_client_id','company_invoice_native_relations'=>'client_id','company_portal_tokens'=>'client_id','company_ticket_merge_replies'=>'client_id','order_master'=>'clientid','acc_account_history'=>'customer','sm_contracts'=>'client','sm_orders'=>'client_id','asaas_customers_map'=>'client_id','asaas_company_payers'=>'client_id','ma_email_logs'=>'client_id'];
foreach($directFixtures as$table=>$column)
    if(!$db->table_exists($p.$table))$db->query('CREATE TABLE '.$p.$table.' ('.$column.' int)');
// The fixture may inherit the historical Asaas column name from the migration baseline;
// normalize it to the current guarded key before exercising the legacy-key failure below.
if($db->table_exists($p.'asaas_customers_map')&&!$db->field_exists('client_id',$p.'asaas_customers_map')&&$db->field_exists('perfex_client_id',$p.'asaas_customers_map')){
    if($db->driver()==='sqlite')$db->query('ALTER TABLE '.$p.'asaas_customers_map RENAME COLUMN perfex_client_id TO client_id');
    else $db->query('ALTER TABLE '.$p.'asaas_customers_map CHANGE perfex_client_id client_id int');
}
if(!$db->table_exists($p.'notes'))$db->query('CREATE TABLE '.$p.'notes (rel_id int,rel_type varchar(50))');
if(!$db->table_exists($p.'taggables'))$db->query('CREATE TABLE '.$p.'taggables (rel_id int,rel_type varchar(50))');
if(!$db->table_exists($p.'company_client_import_batches'))$db->query('CREATE TABLE '.$p.'company_client_import_batches (state varchar(20),result_json text)');
unset($db->data_cache['table_names']);
$target=$clients->create(41,12,['company'=>'Deletion reference fixture','vat'=>''],hash('sha256','delete-reference-fixture'));
$foreign=$clients->create(82,12,['company'=>'Other owner fixture','vat'=>''],hash('sha256','delete-reference-other'));
$guard=new CompanyCustomerDeletionReferences($db,$p);$tables=$guard->tables();
$check=fn()=>IssuerTransaction::run($db,$p,array_values(array_unique(array_merge(['issuers_lock'],$tables))),fn()=>$guard->assertClearInOwned($target,$tables));
$check();essentialCheck(true,'unreferenced fixture passes the explicitly enumerated guard');
foreach($directFixtures as$table=>$field){
    if($table==='invoices')$db->query('INSERT INTO '.$p.'invoices (id,clientid) VALUES (?,?)',[-910001,$foreign]);
    else $db->query('INSERT INTO '.$p.$table.' ('.$field.') VALUES (?)',[$foreign]);$check();
    if($table==='invoices')$db->query('INSERT INTO '.$p.'invoices (id,clientid) VALUES (?,?)',[-910002,$target]);
    else $db->query('INSERT INTO '.$p.$table.' ('.$field.') VALUES (?)',[$target]);
    essentialThrows($check,'company_client_referenced',$table.' blocks referenced customer');
    $db->query('DELETE FROM '.$p.$table.' WHERE '.$field.'=?',[$target]);
}
$db->query('INSERT INTO '.$p.'notes (rel_id,rel_type) VALUES (?,?)',[$target,'lead']);$check();
$db->query('INSERT INTO '.$p.'notes (rel_id,rel_type) VALUES (?,?)',[$target,'customer']);
essentialThrows($check,'company_client_referenced','polymorphic customer note blocks customer deletion');
$db->query('DELETE FROM '.$p.'notes WHERE rel_type=?',['customer']);
$db->query('INSERT INTO '.$p.'taggables (rel_id,rel_type) VALUES (?,?)',[$target,'client']);
essentialThrows($check,'company_client_referenced','alternative polymorphic client tag blocks customer deletion');
$db->query('DELETE FROM '.$p.'taggables WHERE rel_id=? AND rel_type=?',[$target,'client']);
$db->query('INSERT INTO '.$p.'company_client_import_batches(state,result_json) VALUES (?,?)',['committed',json_encode(['batch_id'=>'test','client_ids'=>[$foreign]])]);$check();
$db->query('INSERT INTO '.$p.'company_client_import_batches(state,result_json) VALUES (?,?)',['committed',json_encode(['batch_id'=>'test2','client_ids'=>[$target]])]);
essentialThrows($check,'company_client_referenced','committed import proof blocks customer deletion');
essentialThrows(fn()=>$guard->assertClearInOwned($target,$tables),'issuer_owned_transaction_required','guard requires the owned transaction');
// Older Asaas uses a different local key; until a migration proves it, fail closed.
$db->query('DELETE FROM '.$p.'company_client_import_batches WHERE result_json LIKE ?',['%test2%']);
if($db->driver()==='sqlite')$db->query('ALTER TABLE '.$p.'asaas_customers_map RENAME COLUMN client_id TO perfex_client_id');
else $db->query('ALTER TABLE '.$p.'asaas_customers_map CHANGE client_id perfex_client_id int');
essentialThrows($check,'issuer_customer_dependency_schema_changed','legacy Asaas key cannot be silently ignored');
if($db->driver()==='sqlite')$db->query('ALTER TABLE '.$p.'asaas_customers_map RENAME COLUMN perfex_client_id TO client_id');
else $db->query('ALTER TABLE '.$p.'asaas_customers_map CHANGE perfex_client_id client_id int');
echo "Native customer dependency inventory: financial, lead, polymorphic and import checks passed.\n";
