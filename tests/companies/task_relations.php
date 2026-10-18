<?php
// Synthetic SQLite tests execute the actual policy and model rejection paths.
define('BASEPATH', __DIR__);
require __DIR__.'/../../application/services/ClientIssuerScope.php';
require __DIR__.'/../../application/services/InvoiceIssuerScope.php';
require __DIR__.'/../../application/services/TaskClientBoundary.php';
require_once __DIR__.'/../../application/services/CustomerRelationBoundary.php';
#[AllowDynamicProperties]
class App_Model { public $db; }
require __DIR__.'/../../application/models/Tasks_model.php';
function db_prefix(){return 'q_';}
class TaskBoundaryDb {
    public function __construct(public PDO $pdo){}
    public function table_exists($name){$s=$this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$name]);return(bool)$s->fetch();}
    public function field_exists($field,$table){foreach($this->pdo->query('PRAGMA table_info('.$table.')')as$row)if($row['name']===$field)return true;return false;}
    public function query($sql,$args=[]){$s=$this->pdo->prepare($sql);$s->execute($args);return new class($s->fetchAll(PDO::FETCH_ASSOC)){function __construct(private array $rows){} function row(){return $this->rows?(object)$this->rows[0]:null;} function result_array(){return$this->rows;}};}
}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db=new TaskBoundaryDb($pdo);
$pdo->exec('CREATE TABLE q_clients(userid INT); INSERT INTO q_clients VALUES(1),(2); CREATE TABLE q_issuers_clients(client_id INT,owner_issuer_id INT); INSERT INTO q_issuers_clients VALUES(2,82); CREATE TABLE q_tasks(id INT,rel_type TEXT,rel_id INT);');
$map=['customer'=>['clients','userid','userid'],'project'=>['projects','id','clientid'],'invoice'=>['invoices','id','clientid'],'estimate'=>['estimates','id','clientid'],'contract'=>['contracts','id','client'],'expense'=>['expenses','id','clientid'],'ticket'=>['tickets','ticketid','userid'],'proposal'=>['proposals','id','rel_id']];
foreach($map as$type=>[$table,$id,$client]){if($type==='customer')continue;$extra=$type==='invoice'?',issuer_id INT':($type==='proposal'?',rel_type TEXT':'');$pdo->exec("CREATE TABLE q_$table($id INT,$client INT$extra)");$suffix=$type==='invoice'?',NULL':($type==='proposal'?",'customer'":'');$pdo->exec("INSERT INTO q_$table VALUES(1,1$suffix),(2,2$suffix)");}
$pdo->exec('CREATE TABLE q_contacts(id INT,userid INT); INSERT INTO q_contacts VALUES(10,1),(20,2); ALTER TABLE q_tickets ADD COLUMN contactid INT');
$n=0;function verify($yes,$label){global$n;$n++;if(!$yes)throw new RuntimeException($label);}
use app\services\TaskClientBoundary as Boundary;
$taskId=0;
foreach($map as$type=>$columns){
    verify(Boundary::permitsRelation($db,'q_',['rel_type'=>$type,'rel_id'=>1]),"legacy $type remains writable");
    verify(!Boundary::permitsRelation($db,'q_',['rel_type'=>$type,'rel_id'=>2]),"private $type cannot be attached");
    verify(!Boundary::permitsRelation($db,'q_',['rel_type'=>strtoupper($type),'rel_id'=>2]),"noncanonical $type rejected");
    foreach([1,2]as$ref){$taskId++;$pdo->exec("INSERT INTO q_tasks VALUES($taskId,'$type',$ref)");verify(Boundary::permitsTask($db,'q_',$taskId)===($ref===1),"task $type reader honors indirect customer");}
}
$predicate=Boundary::legacySql($db,'q_','t');
verify(count($db->query('SELECT id FROM q_tasks t WHERE '.$predicate)->result_array())===8,'list predicate excludes every private relation');
verify(!Boundary::permitsTask($db,'q_',1,['rel_id'=>2]),'update omitting relation type validates effective stored relation');
verify(!Boundary::permitsTask($db,'q_',2,['rel_id'=>1]),'reassigning a private task cannot bypass source authorization');
$pdo->exec('UPDATE q_invoices SET issuer_id=82 WHERE id=1');
verify(!Boundary::permitsRelation($db,'q_',['rel_type'=>'invoice','rel_id'=>1]),'named invoice origin denies even legacy customer');
verify(!Boundary::permitsTask($db,'q_',5),'named invoice task read denied');
$pdo->exec("INSERT INTO q_proposals VALUES(3,2,'lead'); INSERT INTO q_expenses VALUES(3,0); INSERT INTO q_tickets(ticketid,userid) VALUES(3,0)");
foreach(['proposal','expense','ticket']as$type)verify(Boundary::permitsRelation($db,'q_',['rel_type'=>$type,'rel_id'=>3]),"non-customer $type retained");
$model=(new ReflectionClass(Tasks_model::class))->newInstanceWithoutConstructor();$model->db=$db;
verify($model->get(2)===false,'real model rejects before comments, attachments and hooks');
verify($model->add(['rel_type'=>'customer','rel_id'=>2])===false,'real add rejects before dates, hooks or writes');
verify($model->update(['rel_id'=>2],1)===false,'real update rejects before mutation');
verify($model->copy(['copy_from'=>2])===false,'real copy rejects before hydration or inserts');
verify($model->get_billable_tasks(2)===[],'billing selector rejects private directory reference');
foreach(['get_checklist_items','get_task_attachments','get_task_followers','get_task_assignees','get_task_comments','get_timers','get_timesheeets','get_reminders']as$method)verify($model->$method(2)===[],"private task $method denied before hydration");
foreach(['make_public','get_task_creator_id','unmark_complete','delete_task']as$method)verify($model->$method(2)===false,"private task $method denied before effects");
foreach(['add_task_comment','add_task_followers','add_task_assignees','add_checklist_item']as$method)verify($model->$method(['taskid'=>2])===false,"private task $method denied before insert");
$pdo->exec("INSERT INTO q_tasks VALUES(100,'CUSTOMER',2)");
verify(!Boundary::permitsTask($db,'q_',100),'noncanonical stored relation cannot bypass SQL ownership');
require __DIR__.'/../../application/services/CustomerProfileBadges.php';
function get_staff_user_id(){return 1;}
function &get_instance(){return $GLOBALS['ci'];}
$ci=(object)['db'=>$db];
$badges=new \app\services\CustomerProfileBadges(2);
foreach(['contacts','notes','invoices','proposals','subscriptions','estimates','credit_notes','contracts','projects','tickets','attachments','vault','expenses','reminders','tasks']as$feature)verify($badges->$feature()===0,"private $feature badge denied before reading related tables");
verify($badges->getBadge('notes')===[],'private badge denied before extension hook');
verify(str_contains(\app\services\ClientIssuerScope::legacyTableSql($db,'q_','q_tasks AS t'),'task_customer_boundary'),'shared generic helpers receive indirect task predicate');
$pdo->exec('DROP TABLE q_issuers_clients; CREATE TABLE q_issuers_client_links(client_id INT,issuer_id INT)');
verify(!Boundary::permitsTask($db,'q_',1),'partial ownership schema fails closed for existing customer');
echo "Task customer relations: $n checks passed.\n";
