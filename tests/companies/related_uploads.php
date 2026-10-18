<?php
require __DIR__.'/../client_issuer_helpers.php';
require_once __DIR__.'/../../application/services/InvoiceIssuerScope.php';
require_once __DIR__.'/../../application/services/TaskClientBoundary.php';
require_once __DIR__.'/../../application/services/CustomerRelationBoundary.php';
foreach(['CONTRACTS_UPLOADS_FOLDER','TASKS_ATTACHMENTS_FOLDER','EXPENSE_ATTACHMENTS_FOLDER','PROJECT_ATTACHMENTS_FOLDER']as$constant)if(!defined($constant))define($constant,'/tmp/crm-empty-upload-fixture/');
$before=$n;
$pdo->exec("CREATE TABLE q_contracts(id INT,client INT); INSERT INTO q_contracts VALUES(1,1),(2,2); CREATE TABLE q_projects(id INT,clientid INT); INSERT INTO q_projects VALUES(1,1),(2,2); CREATE TABLE q_expenses(id INT,clientid INT); INSERT INTO q_expenses VALUES(1,1),(2,2); CREATE TABLE q_tickets(ticketid INT,userid INT,contactid INT); INSERT INTO q_tickets VALUES(1,1,10),(2,2,20); CREATE TABLE q_tasks(id INT,rel_type TEXT,rel_id INT); INSERT INTO q_tasks VALUES(1,'customer',1),(2,'customer',2)");
$_FILES=[];$effects=$GLOBALS['effects'];
check(handle_project_file_uploads(2)===false,'private project upload denied before hooks');
check(handle_task_attachments_array(2)===false,'private task upload denied before hooks');
check(handle_expense_attachments(2)===false,'private expense upload denied before hooks');
check(handle_contract_attachment(2)===false,'private contract upload denied before hooks');
check(handle_ticket_attachments(2)===false,'private ticket upload denied before hooks');
check($GLOBALS['effects']===$effects && $model->db->writes===0,'private upload denials have no hook or DB side effects');
handle_contract_attachment(1);handle_project_file_uploads(1);handle_task_attachments_array(1);handle_expense_attachments(1);
check($model->db->writes===0,'legacy empty upload flows remain no-op');
echo 'Related upload boundaries: '.($n-$before)." additional checks passed.\n";
