<?php
require __DIR__.'/sales_document_readers.php';
require __DIR__.'/../../application/models/Misc_model.php';
function format_credit_note_number($id){return (string)$id;}
function delete_tracked_emails(...$args){}
$before=$n;
$models=[];
foreach(['Estimates'=>['estimate','estimates'],'Proposals'=>['proposal','proposals'],'Credit_notes'=>['credit_note','creditnotes']]as$class=>[$kind,$table]){
 $m=(new ReflectionClass($class.'_model'))->newInstanceWithoutConstructor();$m->db=$model->db;$models[$kind]=$m;
 $base=count($models)*100;
 $pdo->exec("INSERT INTO q_files VALUES($base,1,'$kind','legacy.txt','https://example.test',1),(".($base+1).",2,'$kind','private.txt','https://example.test',1)");
 check($m->get_attachments(2)===[],'private '.$kind.' attachments denied');
 check(count($m->get_attachments(1))===1,'legacy '.$kind.' attachments retained');
 if($kind!=='credit_note'){
  check($m->get_attachments('', $base+1)===null,'private '.$kind.' attachment by ID denied');
  check($m->get_attachments(2, $base)===null,'wrong '.$kind.' parent cannot authorize legacy attachment');
 }
 check($m->delete_attachment($base+1)===false,'private '.$kind.' attachment delete denied before filesystem');
 check($m->delete(2)===false,'private '.$kind.' document delete denied');
}
$misc=(new ReflectionClass(Misc_model::class))->newInstanceWithoutConstructor();$misc->db=$model->db;
$models['credit_note']->misc_model=$misc;
$pdo->exec("CREATE TABLE q_itemable(id INT,rel_id INT,rel_type TEXT); CREATE TABLE q_customfieldsvalues(relid INT,fieldto TEXT); CREATE TABLE q_credits(id INT,credit_id INT,invoice_id INT); CREATE TABLE q_item_tax(rel_id INT,rel_type TEXT); CREATE TABLE q_reminders(rel_id INT,rel_type TEXT)");
check($models['credit_note']->delete(1,true)===true,'native legacy credit-note cascade completes after parent deletion');
check($pdo->query('SELECT COUNT(*) FROM q_files WHERE id=300')->fetchColumn()==0,'legacy credit-note file metadata actually removed');
check($pdo->query('SELECT COUNT(*) FROM q_files WHERE id=301')->fetchColumn()==1,'private credit-note file preserved');
check((new ReflectionProperty(Credit_notes_model::class,'authorizedDocumentDeletion'))->getValue($models['credit_note'])===[],'credit-note cleanup proof released');
echo 'Sales document files: '.($n-$before)." additional checks passed\n";
$pdo->exec("CREATE TABLE q_proposal_comments(id INT,proposalid INT,content TEXT,dateadded TEXT); INSERT INTO q_proposal_comments VALUES(1,1,'Legacy',''),(2,2,'Private',''); CREATE TABLE q_sales_activity(id INT,rel_id INT,rel_type TEXT,date TEXT)");
$proposal=$models['proposal'];$estimate=$models['estimate'];
check($proposal->get_comment(1)->content==='Legacy','legacy proposal comment read preserved');
check($proposal->get_comment(2)===null && $proposal->get_comments(2)===[],'private proposal comments denied');
check($proposal->edit_comment(['content'=>'blocked'],2)===false && $proposal->remove_comment(2)===false,'private proposal comment mutations denied');
check($proposal->add_comment(['proposalid'=>2])===false,'private proposal comment creation denied before notifications');
check($proposal->mark_action_status(1,2)===false && $proposal->clear_signature(2)===false,'private proposal status/signature denied');
check($estimate->mark_action_status(1,2)===false && $estimate->clear_signature(2)===false,'private estimate status/signature denied');
check($estimate->get_estimate_activity(2)===[] && $estimate->log_estimate_activity(2)===false,'private estimate activity read/write denied');
check($estimate->set_estimate_sent(2)===false && $estimate->save_formatted_number(2)===false,'private estimate sent/number mutation denied');
check($proposal->edit_comment(['content'=>'Updated'],1)===true,'legacy proposal comment edit preserved');
check($proposal->get_comment(1)->content==='Updated','legacy proposal comment persisted');
echo 'Sales files and secondary actions: '.($n-$before)." additional checks passed\n";

set_error_handler(static function($severity,$message){throw new RuntimeException($message);});
try {
 foreach([[],new stdClass(),true,'2e0',0]as$invalid)foreach($models as$kind=>$modelProbe)check($modelProbe->get_attachments($invalid)===[],'invalid '.$kind.' parent rejects without warning or data');
} finally {restore_error_handler();}
echo 'Sales malformed parent IDs: '.($n-$before)." additional checks passed\n";
