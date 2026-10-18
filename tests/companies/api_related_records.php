<?php
require __DIR__.'/../support/client_issuer_test_support.php';
require __DIR__.'/../../application/services/InvoiceIssuerScope.php';
require __DIR__.'/../../application/services/CustomerRelationBoundary.php';
require __DIR__.'/../../application/services/TaskClientBoundary.php';
require __DIR__.'/../../modules/api/models/Api_model.php';
function has_permission(...$args){return true;}
class RelatedApiProbe extends Api_model { public function __construct() {} }
$api=new RelatedApiProbe;$api->db=$model->db;
$pdo->exec("CREATE TABLE q_projects(id INT,clientid INT);
INSERT INTO q_projects VALUES(1,1),(2,2);
CREATE TABLE q_tasks(id INT,name TEXT,rel_type TEXT,rel_id INT,milestone INT,status INT);
INSERT INTO q_tasks VALUES(1,'Legacy','project',1,1,5),(2,'Private','project',2,2,5),(3,'Private mislinked','project',2,1,5),(4,'Unrelated','',0,0,1);
CREATE TABLE q_milestones(id INT,project_id INT,milestone_order INT);
INSERT INTO q_milestones VALUES(1,1,1),(2,2,1);");
check(array_column($api->_search_tasks('',0,true)['result'],'id')===[1,4],'API task search retains legacy and unrelated tasks');
check($api->_search_tasks('2',0,true)['result']===[],'API task ID OR cannot bypass ownership');
check($api->_search_tasks('Private',0,true)['result']===[],'API task text search cannot disclose private project');
check($api->_search_tasks("' OR 1=1 --",0,true)['result']===[],'API task search treats injection text as literal');
$rows=$api->get_milestones_api();
check(count($rows)===1 && $rows[0]['id']==1,'API milestone list scopes parent project');
check($rows[0]['total_tasks']==1 && $rows[0]['total_finished_tasks']==1,'API milestone counts exclude private task mislinked to legacy milestone');
check($api->get_milestones_api(2)===[],'API milestone direct ID denies private parent');
check(count($api->get_milestones_api('', 'q_milestones.id=2 OR q_milestones.id=1'))===1,'API milestone custom OR remains within parent boundary');
echo "Related API records: $n checks passed (actual API model / synthetic SQLite)\n";
function is_staff_member(){return true;}
function _startsWith($value,$prefix){return str_starts_with($value,$prefix);}
function strafter($value,$prefix){return substr($value,strlen($prefix));}
$pdo->exec("ALTER TABLE q_projects ADD name TEXT DEFAULT 'Project'; ALTER TABLE q_projects ADD description TEXT DEFAULT '';
CREATE TABLE q_leads(id INT,company TEXT,phonenumber TEXT,city TEXT,zip TEXT,state TEXT,address TEXT);
INSERT INTO q_leads VALUES(1,'Legacy','','','','',''),(2,'Private','','','','','');
CREATE TABLE q_tickets(ticketid INT,userid INT,contactid INT,department INT,subject TEXT,message TEXT);
INSERT INTO q_tickets VALUES(1,1,10,1,'Legacy',''),(2,2,20,1,'Private',''),(3,0,20,1,'Contact only','');
CREATE TABLE q_departments(departmentid INT,name TEXT);INSERT INTO q_departments VALUES(1,'Support');
ALTER TABLE q_contacts ADD phonenumber TEXT; ALTER TABLE q_clients ADD phonenumber TEXT; ALTER TABLE q_clients ADD city TEXT; ALTER TABLE q_clients ADD state TEXT;
CREATE TABLE q_tags(id INT,name TEXT); INSERT INTO q_tags VALUES(1,'tag');
CREATE TABLE q_taggables(tag_id INT,rel_id INT,rel_type TEXT);
INSERT INTO q_taggables VALUES(1,1,'ticket'),(1,2,'ticket'),(1,1,'project'),(1,2,'project');");
foreach(['',"' OR 1=1 --",'#tag'] as $query){
 $tickets=$api->_search_tickets($query,0,true)['result'];
 $projects=$api->_search_projects($query,0,false,null,true)['result'];
 $expected=$query==="' OR 1=1 --"?[]:[1];
 check(array_column($tickets,'ticketid')===$expected,'ticket API text/tag search boundary '.$query);
 check(array_column($projects,'id')===$expected,'project API text/tag search boundary '.$query);
 foreach($tickets as $ticket) check(!isset($ticket['password']) && !isset($ticket['email_verification_key']),'ticket DTO never embeds joined contact credentials');
}
check(count($api->_search_projects('',0,'q_projects.id=2 OR q_projects.id=1','lead',true)['result'])===1,'API caller cannot relabel private customer project as lead to bypass scope');
echo "Related API records expanded: $n checks passed\n";
$pdo->exec("ALTER TABLE q_clients ADD zip TEXT;
CREATE TABLE q_invoices(id INT,issuer_id INT); INSERT INTO q_invoices VALUES(1,NULL),(2,82);
CREATE TABLE q_expenses(id INT,clientid INT,invoiceid INT,paymentmode INT,tax INT,tax2 INT,category INT,currency INT,project_id INT,amount NUMERIC,note TEXT,expense_name TEXT,addedfrom INT,recurring_from INT);
INSERT INTO q_expenses VALUES(1,1,1,1,1,1,1,1,0,12,'Legacy','Legacy',7,0),(2,2,1,1,1,1,1,1,0,12,'Private','Private',7,0),(3,1,2,1,1,1,1,1,0,12,'Named origin','Named origin',7,0);
CREATE TABLE q_payment_modes(id INT,name TEXT); INSERT INTO q_payment_modes VALUES(1,'Manual');
CREATE TABLE q_taxes(id INT,name TEXT,taxrate NUMERIC); INSERT INTO q_taxes VALUES(1,'Tax',0);
CREATE TABLE q_expenses_categories(id INT,name TEXT); INSERT INTO q_expenses_categories VALUES(1,'Category');
CREATE TABLE q_currencies(id INT,name TEXT); INSERT INTO q_currencies VALUES(1,'BRL');");
check(array_column($api->_search_expenses('',0,true)['result'],'expenseid')===[1],'API expense search scopes private customer and immutable invoice origin');
check($api->_search_expenses("' OR 1=1 --",0,true)['result']===[],'API expense search binds literal text');
check(array_column($api->get_expenses_api(''),'expenseid')===[1],'API expense list excludes private and named invoice rows');
check(count($api->get_expenses_api('', 'q_expenses.id=2 OR q_expenses.id=1'))===1,'API expense list custom OR cannot bypass');
check($api->get_expenses_api(2)===null && $api->get_expenses_api(3)===null,'API direct expense IDs denied before attachment or gateway hydration');
check(!\app\services\TaskClientBoundary::permitsRelation($api->db,'q_',['rel_type'=>'expense','rel_id'=>3]),'task write cannot attach expense from named invoice origin');
echo "Related API records complete: $n checks passed\n";
$api->clients_model=$model;
$api->load=new class {function model($name){}};
$api->input=new class {public array $values=[];function post($key){return $this->values[$key]??false;}};
check(array_column($api->get_relation_data_api('customer','Legacy'),'userid')===[1],'relation API customer positive search preserved');
check($api->get_relation_data_api('contacts',"' OR 1=1 --")===[],'relation API contact search binds literal text');
check($api->get_relation_data_api('customer','Private')===[],'relation API customer private search denied');
$api->input->values=['customer_id'=>'2 OR 1=1','rel_type'=>'lead'];
check($api->get_relation_data_api('project','')===[],'relation API rejects nonnumeric project customer filter');
$api->input->values=['customer_id'=>'2','rel_type'=>'lead'];
check($api->get_relation_data_api('project','')===[],'relation API cannot reinterpret private customer as lead');
$api->input->values=['customer_id'=>'1'];
check(array_column($api->get_relation_data_api('project',''),'id')===[1],'relation API legacy project filter preserved');
echo "Related API dispatcher: $n checks passed\n";
