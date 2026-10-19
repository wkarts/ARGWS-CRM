<?php
require __DIR__.'/task_relations.php';
$before=$n;
$pdo->exec('CREATE TABLE q_issuers_clients(client_id INT,owner_issuer_id INT); INSERT INTO q_issuers_clients VALUES(2,82); ALTER TABLE q_tasks ADD COLUMN name TEXT; ALTER TABLE q_tasks ADD COLUMN visible_to_client INT; CREATE TABLE q_task_comments(id INT,taskid INT,staffid INT,contact_id INT,dateadded TEXT,file_id INT); INSERT INTO q_task_comments VALUES(1,1,1,0,\'2026-10-18\',0),(2,2,1,0,\'2026-10-18\',0); CREATE TABLE q_task_checklist_items(id INT,taskid INT); INSERT INTO q_task_checklist_items VALUES(1,1),(2,2); CREATE TABLE q_taskstimers(id INT,task_id INT); INSERT INTO q_taskstimers VALUES(1,1),(2,2),(3,0); CREATE TABLE q_files(id INT,rel_id INT,rel_type TEXT,task_comment_id INT); INSERT INTO q_files VALUES(2,2,\'task\',2),(3,1,\'invoice\',NULL)');
class TaskCleanupDb extends TaskBoundaryDb {
    protected array $conditions=[],$args=[],$joins=[];private string $fields='*';private int $affected=0;
    function join($table,$on,$type='INNER'){$this->joins[]=strtoupper($type).' JOIN '.$table.' ON '.$on;return$this;}
    function select($fields){$this->fields=$fields;return$this;}
    function where($field,$value=null){if(is_array($field)){foreach($field as$k=>$v)$this->where($k,$v);return$this;}$this->conditions[]=$field.($value===null?'':'=?');if($value!==null)$this->args[]=$value;return$this;}
    protected function suffix(){return$this->conditions?' WHERE '.implode(' AND ',$this->conditions):'';}
    protected function clear(){$this->conditions=$this->args=$this->joins=[];$this->fields='*';}
    function get($table){$sql='SELECT '.$this->fields.' FROM '.$table.' '.implode(' ',$this->joins).$this->suffix();$args=$this->args;$this->clear();return$this->query($sql,$args);}
    function delete($table){$stmt=$this->pdo->prepare('DELETE FROM '.$table.$this->suffix());$stmt->execute($this->args);$this->affected=$stmt->rowCount();$this->clear();return true;}
    function affected_rows(){return$this->affected;}
    function order_by(...$args){return$this;}
}
$db=new TaskCleanupDb($pdo);$model=(new ReflectionClass(Tasks_model::class))->newInstanceWithoutConstructor();$model->db=$db;
foreach(['get_checklist_item','delete_checklist_item','remove_task_attachment','remove_comment','delete_timesheet']as$method)verify($model->$method(2)===false,"private child $method denied");
verify($model->update_checklist_item(2,'synthetic')===false,'private checklist edit denied');
verify($model->edit_comment(['id'=>2])===false,'private comment edit denied');
verify($model->update_checklist_order(['order'=>[[1,1],[2,2]]])===false,'mixed checklist reorder rejected before partial write');
verify($model->remove_task_attachment(3)===false,'non-task file cannot be removed through task endpoint');
verify($model->timesheet(['timesheet_task_id'=>1,'timer_id'=>2])===false,'private timer cannot be reassigned to legacy task');
verify($model->timer_tracking(0,2,'',true)===false,'adminStop and zero-task sentinel cannot bypass private timer origin');
verify($model->add_attachment_to_database(2,[])===false,'private task attachment denied before file effects');
verify(\app\services\TaskClientBoundary::childTaskId($db,'q_','timer',3)===0,'unassigned legacy timer identity retained');
function prefixed_table_fields_array($table){return[$table.'.*'];}
function get_option($key){return 0;}
function get_upload_path_by_type($type){return '/tmp/crm-fixture-no-real-uploads/';}
function total_rows($table,$where){$ci=$GLOBALS['cleanupDb'];$result=$ci->where($where)->get($table)->result_array();return count($result);}
function hooks(){return new class {function do_action(...$args){} function apply_filters($name,$value,...$args){return$value;}};}
$GLOBALS['cleanupDb']=$db;
foreach(['task_followers'=>'taskid INT','task_assigned'=>'taskid INT','customfieldsvalues'=>'relid INT,fieldto TEXT','taggables'=>'rel_id INT,rel_type TEXT','reminders'=>'rel_id INT,rel_type TEXT','related_items'=>'rel_id INT,rel_type TEXT','user_meta'=>'meta_key TEXT']as$table=>$columns)$pdo->exec('CREATE TABLE q_'.$table.'('.$columns.')');
verify($model->get_task_timer(['id'=>2])===null,'timer reader excludes private task origin');
verify($model->get_task_timer(['id'=>3])->task_id===0,'standalone legacy timer remains readable');
verify($model->remove_comment(2,true)===false,'force cleanup parameter does not grant a private task');
verify($model->delete_task(1)===true,'actual legacy cascade completes after parent removal');
verify((int)$pdo->query('SELECT COUNT(*) FROM q_task_comments WHERE taskid=1')->fetchColumn()===0,'legacy comments deleted with private cleanup proof');
verify((int)$pdo->query('SELECT COUNT(*) FROM q_task_comments WHERE taskid=2')->fetchColumn()===1,'private sibling comment retained');
verify($model->delete_task(2)===false,'private task deletion denied');
verify((new ReflectionProperty(Tasks_model::class,'authorizedTaskDeletion'))->getValue($model)===[],'cleanup capability released after success');
$pdo->exec("INSERT INTO q_tasks(id,rel_type,rel_id) VALUES(200,'customer',1); CREATE TRIGGER fail_task_delete BEFORE DELETE ON q_tasks WHEN OLD.id=200 BEGIN SELECT RAISE(ABORT,'synthetic deletion failure'); END");
try{$model->delete_task(200);verify(false,'expected synthetic deletion failure');}catch(PDOException $expected){}
verify((new ReflectionProperty(Tasks_model::class,'authorizedTaskDeletion'))->getValue($model)===[],'cleanup capability released after storage failure');
verify((int)$pdo->query('SELECT COUNT(*) FROM q_tasks WHERE id=200')->fetchColumn()===1,'failed delete leaves synthetic source intact');
echo 'Task child records and legacy cleanup: '.($n-$before)." checks passed.\n";
