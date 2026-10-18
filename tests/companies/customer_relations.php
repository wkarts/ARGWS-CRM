<?php
// Reuse the synthetic task fixture after its independent assertions; no external transport.
require __DIR__.'/task_relations.php';
require_once __DIR__.'/../../application/services/CustomerRelationBoundary.php';
require __DIR__.'/../../application/models/Projects_model.php';
require __DIR__.'/../../application/models/Expenses_model.php';
require __DIR__.'/../../application/models/Tickets_model.php';
use app\services\CustomerRelationBoundary as Relations;
$before=$n;
$pdo->exec('CREATE TABLE q_issuers_clients(client_id INT,owner_issuer_id INT); INSERT INTO q_issuers_clients VALUES(2,82); UPDATE q_tickets SET contactid=10 WHERE ticketid=1; UPDATE q_tickets SET contactid=20 WHERE ticketid=2; INSERT INTO q_tickets VALUES(4,0,20),(5,0,NULL)');
foreach(['projects','expenses','tickets']as$table){
    $field=$table==='tickets'?'userid':'clientid';
    verify(Relations::permitsWrite($db,'q_',$table,[$field=>1]),"legacy $table relation permitted");
    verify(!Relations::permitsWrite($db,'q_',$table,[$field=>2]),"private $table relation denied");
    verify(Relations::permitsRecord($db,'q_',$table,1),"legacy $table record readable");
    verify(!Relations::permitsRecord($db,'q_',$table,2),"private $table record denied");
    verify(!Relations::permitsRecord($db,'q_',$table,2,[$field=>1]),"private $table cannot be reassigned to escape ownership");
    verify(!Relations::permitsRecord($db,'q_',$table,1,[$field=>2]),"legacy $table cannot attach private customer");
    $sql=Relations::sql($db,'q_',$table,'r');
    $rows=$db->query('SELECT * FROM q_'.$table.' r WHERE '.$sql)->result_array();
    verify(count($rows)===($table==='projects'?1:($table==='expenses'?2:3)),"$table SQL excludes private direct and contact references");
    verify(str_contains(\app\services\ClientIssuerScope::legacyTableSql($db,'q_','q_'.$table.' AS r'),'client_owner_scope'),"generic $table totals inherit ownership");
}
$pdo->exec("INSERT INTO q_tasks VALUES(101,'ticket',4)");
verify(!\app\services\TaskClientBoundary::permitsTask($db,'q_',101),'task inherits private contact-only ticket boundary');
verify(!\app\services\TaskClientBoundary::permitsRelation($db,'q_',['rel_type'=>'ticket','rel_id'=>4]),'task write cannot attach private contact-only ticket');
verify(!Relations::permitsRecord($db,'q_','tickets',4),'ticket with no userid but private contact denied');
verify(!Relations::permitsWrite($db,'q_','tickets',['userid'=>0,'contactid'=>20]),'private contact cannot be attached to anonymous ticket');
verify(Relations::permitsWrite($db,'q_','tickets',['userid'=>0,'contactid'=>0]),'anonymous support ticket retained');
verify(Relations::permitsWrite($db,'q_','expenses',['clientid'=>0]),'non-customer expense retained');
foreach(['Projects'=>'projects','Expenses'=>'expenses','Tickets'=>'tickets']as$modelName=>$table){
    $class=$modelName.'_model';$model=(new ReflectionClass($class))->newInstanceWithoutConstructor();$model->db=$db;
    $field=$table==='tickets'?'userid':'clientid';
    verify($model->add([$field=>2])===false,"real $modelName add rejects before effects");
    if($table!=='tickets')verify($model->update([$field=>2],1)===false,"real $modelName update rejects before effects");
}
$pdo->exec('CREATE TABLE q_ticket_attachments(id INT,ticketid INT); INSERT INTO q_ticket_attachments VALUES(1,1),(2,2); CREATE TABLE q_ticket_replies(id INT,ticketid INT,userid INT,contactid INT); INSERT INTO q_ticket_replies VALUES(1,1,1,10),(2,2,2,20),(3,1,0,20)');
verify($model->get_ticket_attachment(2)===false,'private ticket attachment metadata denied');
verify($model->delete_ticket_attachment(2)===false,'private ticket attachment deletion denied before unlink');
verify($model->delete_ticket_reply(1,2)===false,'legacy ticket ID cannot authorize another ticket reply');
verify($model->delete_ticket_reply(1,3)===false,'reply with private contact cannot be deleted through legacy ticket');
verify($model->insert_ticket_attachments_to_database([],1,2)===false,'attachments cannot cross ticket/reply ownership');
verify($model->add_reply(['contactid'=>20],1)===false,'legacy ticket cannot receive private contact reply');
verify($model->transfer_email_tickets_to_contact('synthetic@example.test',20)===false,'private contact cannot adopt legacy support messages');
$expenseModel=(new ReflectionClass(Expenses_model::class))->newInstanceWithoutConstructor();$expenseModel->db=$db;
verify($expenseModel->convert_to_invoice(2)===false,'private expense cannot trigger legacy invoice conversion');
verify($expenseModel->get_child_expenses(2)===[],'private recurring expense children denied');
$projectModel=(new ReflectionClass(Projects_model::class))->newInstanceWithoutConstructor();$projectModel->db=$db;
foreach(['get_files','get_milestones','get_project_settings','get_project_members','get_timesheets','get_discussions','get_tasks']as$method)verify($projectModel->$method(2)===[],"private project $method denied before related data");
verify($projectModel->get_tasks(2,[],false,true)===0,'private project task count denied');
foreach(['get_currency','is_member','pin_action','timers_started_for_project']as$method)verify($projectModel->$method(2)===false,"private project $method denied");
verify($projectModel->send_project_customer_email(2,'synthetic')===false,'private project email denied before transport');
verify($projectModel->add_edit_members([],2)===false,'private project members cannot be changed');
verify($model->add_reply([],2)===false,'private ticket reply rejected before notification or insert');
verify($model->update_single_ticket_settings(['ticketid'=>2])===false,'private ticket configuration denied');
verify($model->merge(1,1,[2])===false,'private ticket cannot be merged into legacy destination');
verify($model->delete(2)===false,'private ticket delete denied');
verify($model->get_ticket_replies(2)===[],'private ticket replies excluded');
function hooks(){return new class {function add_action(...$args){}};}
require __DIR__.'/../../application/helpers/projects_helper.php';
require __DIR__.'/../../application/helpers/tasks_helper.php';
$ci->app_object_cache=new class {public function get($key){return(object)['name'=>'Cached synthetic'];}};
verify(get_project_name_by_id(2)==='','private project name cannot come from stale cache');
verify(get_project_name_by_id(1)==='Cached synthetic','legacy cached project name retained');
verify(get_project_milestones(2)===[],'private project milestones denied before query');
verify(get_client_id_by_project_id(2)===false,'private project customer ID denied');
verify(get_task_subject_by_id(2)==='','private task title denied before query');
require __DIR__.'/../../application/services/AbstractKanban.php';
require __DIR__.'/../../application/services/estimates/EstimatesPipeline.php';
require __DIR__.'/../../application/services/leads/LeadsKanban.php';
function staff_can(...$args){return true;}
function get_estimates_where_sql_for_staff($id){return '';}
function staff_cant(...$args){return false;}
class PipelineBoundaryDb {
    public array $predicates=[]; public string $selected='';
    function __construct(private $inner){}
    function table_exists($table){return $this->inner->table_exists($table);}
    function select($sql){$this->selected=$sql;return $this;}
    function from(...$args){return $this;}
    function join(...$args){return $this;}
    function where($sql,$value=null,$escape=null){if($value===null)$this->predicates[]=$sql;return $this;}
}
function get_sql_select_client_company(){return 'q_clients.company';}
$builder=new PipelineBoundaryDb($db);
foreach([\app\services\estimates\EstimatesPipeline::class,\app\services\leads\LeadsKanban::class]as$class){
    $pipeline=(new ReflectionClass($class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($class,'ci'))->setValue($pipeline,(object)['db'=>$builder]);
    (new ReflectionProperty($class,'status'))->setValue($pipeline,1);
    (new ReflectionMethod($class,'initiateQuery'))->invoke($pipeline);
    if(str_contains($class,'Estimates')){
        $rows=$db->query('SELECT id FROM q_estimates WHERE '.implode(' AND ',$builder->predicates))->result_array();
        verify(array_column($rows,'id')===[1],'actual estimate pipeline predicate excludes private customer');
    }else{
        verify(str_contains($builder->selected,'client_owner_scope'),'lead conversion projection includes ownership predicate');
    }
}
$pdo->exec('ALTER TABLE q_expenses ADD COLUMN invoiceid INT; UPDATE q_expenses SET invoiceid=1 WHERE id=1');
verify(!Relations::permitsRecord($db,'q_','expenses',1),'named invoice origin denies even expense with legacy customer');
verify(!Relations::permitsWrite($db,'q_','expenses',['invoiceid'=>1]),'legacy expense cannot attach named invoice origin');
$pdo->exec('DROP TABLE q_issuers_clients');
verify(!Relations::permitsRecord($db,'q_','tickets',1),'partial ownership schema cannot reopen customer relations');
echo 'Additional customer relations: '.($n-$before)." checks passed.\n";
