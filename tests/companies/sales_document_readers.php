<?php
require __DIR__.'/../support/client_issuer_test_support.php';
require __DIR__.'/../../application/models/Estimates_model.php';
require __DIR__.'/../../application/models/Proposals_model.php';
require __DIR__.'/../../application/models/Credit_notes_model.php';
function is_client_logged_in(){return false;}
$pdo->exec("CREATE TABLE q_currencies(id INT,name TEXT); INSERT INTO q_currencies VALUES(1,'BRL');
CREATE TABLE q_estimates(id INT,clientid INT,currency INT); INSERT INTO q_estimates VALUES(1,1,1),(2,2,1);
CREATE TABLE q_creditnotes(id INT,clientid INT,currency INT,number INT,date TEXT); INSERT INTO q_creditnotes VALUES(1,1,1,1,'2026-01-01'),(2,2,1,2,'2026-01-01');
CREATE TABLE q_proposals(id INT,rel_id INT,rel_type TEXT,currency INT,status INT); INSERT INTO q_proposals VALUES(1,1,'customer',1,1),(2,2,'customer',1,1),(3,2,'lead',1,1);");
foreach(['Estimates'=>'estimates','Proposals'=>'proposals','Credit_notes'=>'creditnotes']as$class=>$table){
 $m=(new ReflectionClass($class.'_model'))->newInstanceWithoutConstructor();$m->db=$model->db;
 check($m->get(2)===null,'private '.$table.' denied before child hydration');
 $expected=$table==='proposals'?[1,3]:[1];
 check(array_column($m->get(),'id')===$expected,'legacy '.$table.' list preserved');
 check(array_column($m->get('', 'q_'.$table.'.id=2 OR q_'.$table.'.id=1'),'id')===[1],$table.' OR filter cannot escape ownership');
 $predicate=\app\services\ClientIssuerScope::legacyTableSql($model->db,'q_','q_'.$table.' AS d');
 check(array_column($model->db->query('SELECT id FROM q_'.$table.' AS d WHERE '.$predicate)->result_array(),'id')===$expected,$table.' generic aggregate predicate preserved');
 if($table==='creditnotes')check($m->get_available_creditable_invoices(2)===[],'private credit note cannot query candidate invoices');
}
echo "Sales document readers: $n checks passed (actual models / synthetic SQLite)\n";
$credit=(new ReflectionClass(Credit_notes_model::class))->newInstanceWithoutConstructor();$credit->db=$model->db;
$pdo->exec("CREATE TABLE q_creditnote_refunds(id INT,credit_note_id INT,amount NUMERIC,note TEXT); INSERT INTO q_creditnote_refunds VALUES(1,1,10,'Legacy'),(2,2,20,'Private')");
check($credit->get_refund(1)->note==='Legacy','legacy refund reader preserved');
check($credit->get_refund(2)===null && $credit->get_refunds(2)===[],'private refund readers denied before gateway hydration');
check($credit->create_refund(2,[])===false,'private refund creation denied before input and writes');
check($credit->edit_refund(2,[])===false,'private refund edit denied');
check($credit->delete_refund(2,1)===false,'legacy note cannot authorize foreign refund removal');
check($credit->delete_refund(1,2)===false,'wrong private parent cannot remove legacy refund');
check($credit->delete_applied_credit(1,2,1)===false,'private applied credit deletion denied before invoice lookup');
check($credit->apply_credits(2,[])===false,'private credit application denied before writer');
check($credit->mark(2,2)===false && $credit->delete(2)===false,'private credit state and delete denied before effects');
check($credit->get_applied_credits(2)===[] && $credit->get_open_credits(2)===[],'private credit queries denied');
check($credit->total_remaining_credits_by_credit_note(2)===0 && $credit->total_remaining_credits_by_customer(2)===0,'private balances not disclosed');
check($model->db->writes===0,'rejected financial mutations have no database side effects');
echo "Sales document/refund boundaries: $n checks passed\n";

check($credit->update_credit_note_status(2)===false,'private credit derived status denied before aggregate and write');
check($credit->save_formatted_number(2)===false,'private formatted number denied before formatter');
echo "Credit derived mutations: $n checks passed\n";
