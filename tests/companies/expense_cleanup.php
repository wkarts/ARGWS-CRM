<?php
require __DIR__.'/project_cleanup.php';
require __DIR__.'/../../application/models/Expenses_model.php';
$before=$n;
// Only the unrelated expense display JOINs are adapted; deletion and its child cleanup are native.
class CleanupExpenseModel extends Expenses_model {
    public function get($id='',$where=[]){return $this->db->query('SELECT * FROM q_expenses WHERE id=?',[$id])->row();}
}
function delete_dir($path){
    if(!str_starts_with($path,'/tmp/crm-fixture-no-real-uploads/'))throw new RuntimeException('unexpected fixture path');
    foreach(glob($path.'/*')as$file)unlink($file);return rmdir($path);
}
$pdo->exec('ALTER TABLE q_expenses ADD COLUMN invoiceid INT; ALTER TABLE q_expenses ADD COLUMN recurring_from INT; UPDATE q_expenses SET recurring_from=1 WHERE id=2; INSERT INTO q_files VALUES(201,1,\'expense\',NULL); INSERT INTO q_expenses(id,clientid,invoiceid,recurring_from) VALUES(4,1,1,1)');
$expense=(new ReflectionClass(CleanupExpenseModel::class))->newInstanceWithoutConstructor();$expense->db=$db;$expense->tasks_model=$task;
$root='/tmp/crm-fixture-no-real-uploads/';
foreach([1,2]as$id){mkdir($root.$id,0700,true);file_put_contents($root.$id.'/synthetic.txt','synthetic-only');}
try{
    verify($expense->delete_expense_attachment(2)===false,'private expense receipt denied before filesystem changes');
    verify(is_file($root.'2/synthetic.txt'),'private fixture receipt preserved');
    verify($expense->delete(1)===true,'native legacy expense cascade completes');
    verify(!is_dir($root.'1'),'legacy fixture receipt directory removed after parent deletion');
    verify((int)$pdo->query("SELECT COUNT(*) FROM q_files WHERE rel_type='expense' AND rel_id=1")->fetchColumn()===0,'legacy receipt metadata removed');
    verify((int)$pdo->query('SELECT COUNT(*) FROM q_tasks WHERE id=11')->fetchColumn()===0,'legacy related task removed');
    verify((int)$pdo->query('SELECT recurring_from FROM q_expenses WHERE id=2')->fetchColumn()===1,'private recurrence reference not changed');
    verify((int)$pdo->query('SELECT recurring_from FROM q_expenses WHERE id=4')->fetchColumn()===1,'named invoice origin recurrence reference remains unchanged');
    verify((new ReflectionProperty(Expenses_model::class,'authorizedExpenseDeletion'))->getValue($expense)===[],'expense cleanup proof released');
}finally{foreach([1,2]as$id)if(is_dir($root.$id))delete_dir($root.$id);if(is_dir($root))rmdir($root);}
echo 'Expense receipts and legacy cascade: '.($n-$before)." additional checks passed.\n";
