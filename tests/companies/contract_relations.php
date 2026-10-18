<?php
require __DIR__.'/../support/client_issuer_test_support.php';
require __DIR__.'/../../application/models/Contracts_model.php';
class ContractBoundaryProbe extends Contracts_model {public function __construct(){} }
$contracts=new ContractBoundaryProbe;$contracts->db=$model->db;
$pdo->exec("CREATE TABLE q_contracts(id INT,client INT,contract_type INT,addedfrom INT,subject TEXT);
INSERT INTO q_contracts VALUES(1,1,1,7,'Legacy'),(2,2,1,7,'Private');
CREATE TABLE q_contracts_types(id INT,name TEXT); INSERT INTO q_contracts_types VALUES(1,'Service');
CREATE TABLE q_contract_comments(id INT,contract_id INT,content TEXT,dateadded TEXT);
INSERT INTO q_contract_comments VALUES(1,1,'Legacy',''),(2,2,'Private','');
CREATE TABLE q_contract_renewals(id INT,contractid INT,date_renewed TEXT);
INSERT INTO q_contract_renewals VALUES(1,1,''),(2,2,'');
INSERT INTO q_files VALUES(11,1,'contract','legacy.txt','https://example.test',1),(12,2,'contract','private.txt','https://example.test',1),(13,1,'task','task.txt','https://example.test',1);");
check(array_column($contracts->get(),'id')===[1],'contract list keeps legacy and excludes private customer before hydration');
check($contracts->get_merge_fields((object)['id'=>2,'client'=>2])===[],'private merge fields denied before loading providers');
check($contracts->get(2)===null,'private contract direct read denied before merge fields');
check(array_column($contracts->get('', 'q_contracts.id=2 OR q_contracts.id=1'),'id')===[1],'contract OR cannot bypass ownership');
check($contracts->get_contract_attachments(12)===null,'private contract attachment denied');
check($contracts->get_contract_attachments(13)===null,'task file cannot be read as contract attachment');
check($contracts->get_contract_attachments(11)->file_name==='legacy.txt','legacy contract attachment retained');
check($contracts->get_comments(2)===[] && $contracts->get_comment(2)===null,'private contract comments unreadable');
check(count($contracts->get_comments(1))===1,'legacy contract comments retained');
check($contracts->edit_comment(['content'=>'blocked'],2)===false,'private comment edit denied');
check($contracts->remove_comment(2)===false,'private comment delete denied');
check($contracts->add_comment(['contract_id'=>2,'content'=>'blocked'])===false,'private comment add denied before notification');
check($contracts->delete_renewal(2,1)===false,'legacy parent cannot authorize foreign renewal');
check($contracts->get_contract_renewal_history(2)===[],'private renewal history denied');
check(count($contracts->get_contract_renewal_history(1))===1,'legacy renewal history retained');
foreach(['clear_signature','add_signature','mark_as_signed','unmark_as_signed','delete']as$method)check($contracts->$method(2)===false,'private contract '.$method.' denied before effects');
check($model->db->writes===0,'all rejected contract mutations leave synthetic database unchanged');
check($contracts->edit_comment(['content'=>'updated'],1)===true,'legacy comment update still works');
check($contracts->get_comment(1)->content==='updated','legacy comment update persisted only in permitted parent');
check($pdo->query('SELECT content FROM q_contract_comments WHERE id=2')->fetchColumn()==='Private','private sibling untouched');
echo "Contract ownership: $n checks passed (actual model / synthetic SQLite)\n";
function get_acceptance_info_array($reset=false){return [];}
function restore_merge_fields($content){return $content;}
function delete_tracked_emails(...$args){}
class ContractCleanupProbe extends ContractBoundaryProbe { public function get_merge_fields(object $contract):array{return [];} }
$cleanup=new ContractCleanupProbe;$cleanup->db=$model->db;
$pdo->exec("ALTER TABLE q_contracts ADD content TEXT DEFAULT ''; ALTER TABLE q_contracts ADD signature TEXT DEFAULT ''; ALTER TABLE q_contracts ADD signed INT DEFAULT 0;
CREATE TABLE q_customfieldsvalues(relid INT,fieldto TEXT);
CREATE TABLE q_notes(rel_id INT,rel_type TEXT);
CREATE TABLE q_tasks(id INT,rel_type TEXT,rel_id INT);");
check($cleanup->delete(1)===true,'native legacy contract deletion preserves attachment cleanup after parent removal');
check($pdo->query('SELECT COUNT(*) FROM q_files WHERE id=11')->fetchColumn()==0,'legacy contract attachment metadata actually deleted');
check($pdo->query('SELECT COUNT(*) FROM q_files WHERE id IN(12,13)')->fetchColumn()==2,'foreign private and task attachment metadata preserved');
check($pdo->query('SELECT COUNT(*) FROM q_contracts WHERE id=2')->fetchColumn()==1,'private contract sibling preserved');
check((new ReflectionProperty(Contracts_model::class,'authorizedContractDeletion'))->getValue($cleanup)===[],'contract deletion proof cleared');
echo "Contract cascade: $n checks passed\n";

set_error_handler(static function($severity,$message){throw new RuntimeException($message);});
try { foreach([[],new stdClass(),true,'2e0',0]as$invalid)check($contracts->get_contract_attachments('', $invalid)===[],'malformed contract parent rejected without warnings'); } finally {restore_error_handler();}
echo "Contract malformed IDs: $n checks passed\n";
