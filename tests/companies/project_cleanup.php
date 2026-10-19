<?php
require __DIR__.'/task_children.php';
require __DIR__.'/../../application/models/Projects_model.php';
$before=$n;
class ProjectCleanupDb extends TaskCleanupDb {
    function update($table,$values){$set=implode(',',array_map(fn($key)=>$key.'=?',array_keys($values)));$stmt=$this->pdo->prepare('UPDATE '.$table.' SET '.$set.$this->suffix());$stmt->execute(array_merge(array_values($values),$this->args));$this->clear();return true;}
}
function is_client_logged_in(){return false;}
function is_staff_logged_in(){return true;}
function staff_can(...$args){return true;}
function get_sql_select_task_assignees_ids(){return 'NULL';}
function get_project_name_by_id($id){return 'Synthetic project';}
function log_activity($message){}
$db=new ProjectCleanupDb($pdo);$task=(new ReflectionClass(Tasks_model::class))->newInstanceWithoutConstructor();$task->db=$db;
$project=(new ReflectionClass(Projects_model::class))->newInstanceWithoutConstructor();$project->db=$db;$project->tasks_model=$task;$project->uri=new class{function segment($n){return '';}};
foreach(['project_members'=>'project_id INT','project_notes'=>'id INT,project_id INT','milestones'=>'id INT,project_id INT,name TEXT','projectdiscussions'=>'id INT,project_id INT','projectdiscussioncomments'=>'discussion_id INT','project_files'=>'id INT,project_id INT','project_settings'=>'project_id INT','project_activity'=>'id INT,project_id INT','pinned_projects'=>'project_id INT','creditnotes'=>'id INT,clientid INT,project_id INT']as$table=>$columns)$pdo->exec('CREATE TABLE q_'.$table.'('.$columns.')');
$pdo->exec('ALTER TABLE q_tasks ADD COLUMN milestone INT DEFAULT 0; ALTER TABLE q_taskstimers ADD COLUMN start_time INT; ALTER TABLE q_taskstimers ADD COLUMN end_time INT; ALTER TABLE q_task_assigned ADD COLUMN staffid INT');
foreach(['expenses','contracts','invoices','estimates','tickets','proposals']as$table)$pdo->exec('ALTER TABLE q_'.$table.' ADD COLUMN project_id INT');
$pdo->exec('INSERT INTO q_project_files VALUES(2,2); UPDATE q_expenses SET project_id=1 WHERE id=2; UPDATE q_proposals SET project_id=1 WHERE id=2; INSERT INTO q_tickets(ticketid,userid,contactid,project_id) VALUES(99,0,20,1)');
verify($project->get_file(2)===false,'private project file reader denied before metadata');
verify($project->update_file_data(['id'=>2])===false,'private project file edit denied');
verify($project->remove_file(2)===false,'private project file removal denied before hooks/files');
verify($project->add_external_file(['project_id'=>2])===false,'private external attachment cannot be registered');
verify($project->delete(1)===true,'real legacy project cascade completes after parent removal');
verify((int)$pdo->query('SELECT COUNT(*) FROM q_tasks WHERE id=3')->fetchColumn()===0,'legacy project task actually removed after parent deletion');
verify((int)$pdo->query('SELECT COUNT(*) FROM q_tasks WHERE id=4')->fetchColumn()===1,'private project sibling task retained');
verify((int)$pdo->query('SELECT project_id FROM q_expenses WHERE id=2')->fetchColumn()===1,'foreign private expense reference is not mutated by legacy cleanup');
verify((int)$pdo->query('SELECT project_id FROM q_proposals WHERE id=2')->fetchColumn()===1,'private proposal reference preserved during project cleanup');
verify((int)$pdo->query('SELECT project_id FROM q_tickets WHERE ticketid=99')->fetchColumn()===1,'private contact-only ticket reference preserved during project cleanup');
verify((new ReflectionProperty(Projects_model::class,'authorizedProjectDeletion'))->getValue($project)===[],'project cleanup proof released');
verify($project->delete(2)===false,'private project deletion denied');
echo 'Project files and legacy cascade: '.($n-$before)." additional checks passed.\n";
