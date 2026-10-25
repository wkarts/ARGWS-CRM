<?php
/** Actual App_Email retry guard; original send command fixture provides durable technical receipts. */
require getenv('CRM_MAIL_TEST_FIXTURE')?:__DIR__.'/mail_test.php';
require_once BASEPATH.'libraries/Email.php';require_once getenv('CRM_APP_EMAIL_FILE')?:APPPATH.'libraries/App_Email.php';
final class NativeMailTestWorkerDb{
    public PDO $pdo;private array $where=[];private int $affected=0;public function __construct(private $inner){$this->pdo=$inner->pdo;}public function __call($m,$args){return $this->inner->$m(...$args);}
    public function where($k,$v){$this->where[$k]=$v;return $this;}private function clause(){ $parts=[];$args=[];foreach($this->where as$k=>$v){if(!in_array($k,['id','status'],true))throw new LogicException('unexpected predicate');$parts[]=$k.'=?';$args[]=$v;}$this->where=[];return[$parts?' WHERE '.implode(' AND ',$parts):'',$args];}
    public function get($table){[$where,$args]=$this->clause();return $this->inner->query('SELECT * FROM '.$table.$where,$args);}
    public function update($table,$values){[$where,$args]=$this->clause();$stmt=$this->pdo->prepare('UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($values))).$where);$stmt->execute(array_merge(array_values($values),$args));$this->affected=$stmt->rowCount();return true;}public function affected_rows(){return$this->affected;}
}
#[AllowDynamicProperties]
final class NativeMailTestQueueCapture extends App_Email{
    public int $sentCount=0;public int $transportCount=0;
    public function __construct($ci){$this->CI=$ci;(new ReflectionProperty(App_Email::class,'email_queue_table'))->setValue($this,db_prefix().'mail_queue');}
    public function clean_up_old_queue(){}public function send($skip=false){$this->sentCount++;return true;}
    public function initialize($config=[]){$this->transportCount++;return$this;}
}
if(!function_exists('log_message')){function log_message($level,$text){}}
$ci=(object)['db'=>new NativeMailTestWorkerDb($db),'config'=>(object)['config'=>[]],'load'=>new class{function config($key){}}];$worker=new NativeMailTestQueueCapture($ci);
$queue=$db->query('SELECT id FROM '.$p.'mail_queue ORDER BY id')->result_array();
foreach($queue as$row)$db->query('UPDATE '.$p.'mail_queue SET status=?,headers=? WHERE id=?',['pending',serialize([]),$row['id']]);
$worker->send_queue();essentialCheck($worker->sentCount===0&&$worker->transportCount===0,'cron refuses all smtp_test receipts before credentials/transport despite pending reset and absent header');
foreach($queue as$row)essentialCheck($db->query('SELECT status FROM '.$p.'mail_queue WHERE id=?',[$row['id']])->row()->status==='failed','replayed smtp_test queue marked failed, never sent');
$before=$db->query('SELECT * FROM '.$p.'company_native_mail_dispatch_fences ORDER BY queue_id')->result_array();
foreach($queue as$row)$db->query('UPDATE '.$p.'mail_queue SET status=? WHERE id=?',['pending',$row['id']]);$worker->send_queue();
essentialCheck($worker->sentCount===0&&$before===$db->query('SELECT * FROM '.$p.'company_native_mail_dispatch_fences ORDER BY queue_id')->result_array(),'repeated retry cannot mutate or supersede sent/unknown technical receipts');
echo "Actual App_Email SMTP test replay: zero transport initialization and zero SMTP after pending/header tampering, receipts preserved.\n";
