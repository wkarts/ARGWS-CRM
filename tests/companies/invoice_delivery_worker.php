<?php
/** Real queue and production worker; only the last SMTP send call is captured. */
require __DIR__.'/invoice_delivery.php';
if(!class_exists('CI_Email')){class CI_Email {}}
require_once APPPATH.'libraries/App_Email.php';
if(!function_exists('config_item')){function config_item($name){return "\r\n";}}
final class InvoiceWorkerDb
{
    public PDO $pdo;private array $predicates=[];private array $fields=[];private int $affected=0;
    public function __construct(private EssentialTestDb $inner){$this->pdo=$inner->pdo;}
    public function __call($method,$args){return $this->inner->$method(...$args);}
    public function where($key,$value=null){if(!in_array($key,['id','status'],true))throw new LogicException('unexpected worker predicate');$this->predicates[$key]=$value;return $this;}
    public function set($key,$value){$this->fields[$key]=$value;return $this;}
    private function clause():array{$parts=[];$args=[];foreach($this->predicates as $key=>$value){$parts[]=$key.'=?';$args[]=$value;}$this->predicates=[];return [$parts?' WHERE '.implode(' AND ',$parts):'',$args];}
    public function get($table){[$where,$args]=$this->clause();return $this->inner->query('SELECT * FROM '.$table.$where,$args);}
    public function update($table,$fields=null){$fields=$fields??$this->fields;$this->fields=[];[$where,$args]=$this->clause();foreach(array_keys($fields) as $key)if(!in_array($key,['status','date'],true))throw new LogicException('unexpected queue write');$stmt=$this->pdo->prepare('UPDATE '.$table.' SET '.implode(',',array_map(fn($key)=>$key.'=?',array_keys($fields))).$where);$stmt->execute(array_merge(array_values($fields),$args));$this->affected=$stmt->rowCount();return true;}
    public function affected_rows(){return $this->affected;}
}
final class InvoiceWorkerMailer extends App_Email
{
    public array $sent=[];public array $transport=[];private array $recipients=[];
    public function __construct($ci){$this->CI=$ci;(new ReflectionProperty(App_Email::class,'email_queue_table'))->setValue($this,db_prefix().'mail_queue');}
    public function initialize(array $config=[]){$this->transport=$config;return $this;}
    public function set_mailer_engine($engine){$this->mailer_engine=$engine;return $this;}
    public function clean_up_old_queue(){}
    public function clear($attachments=false){return $this;}
    public function send($skip=false){$this->sent[]=['transport'=>$this->transport,'headers'=>$this->_headers,'recipients'=>$this->recipients];return true;}
    public function set_newline($v="\n"){return $this;}
    public function set_crlf($v="\n"){return $this;}
    public function to($v,$name=''){$this->recipients=$v;return $this;}
    public function cc($v,$name=''){return $this;}
    public function bcc($v,$limit=''){return $this;}
    public function message($v){return $this;}
    public function set_alt_message($v){return $this;}
}
$validA=$delivery->queue($a,7,$ia,101,$s->revision($a,7,$ia),hash('sha256','worker-a'),$base,$decrypt);
$validB=$delivery->queue($b,7,$ib,202,$revisionB,hash('sha256','worker-b'),$base,$decrypt);
$workerCi=(object)['db'=>new InvoiceWorkerDb($db),'config'=>new class($base){public array$config=[];public function __construct(private string$base){}public function item($name){if($name==='base_url')return$this->base;throw new LogicException('unexpected worker config');}},'encryption'=>new class{function decrypt($value){return 'synthetic:'.$value;}},'load'=>new class{function config($name){}}];
$mailer=new InvoiceWorkerMailer($workerCi);$mailer->send_queue();
essentialCheck(count($mailer->sent)===2,'native worker sends two valid owner-bound synthetic notifications and rejects stale earlier invoice');
essentialCheck(array_column(array_column($mailer->sent,'transport'),'smtp_host')===['smtp-a.invalid','smtp-b.invalid'],'worker constructs independent origin SMTP configuration');
foreach($mailer->sent as $sent)essentialCheck(!array_key_exists(CompanyInvoiceDelivery::HEADER,$sent['headers']),'internal receipt key never leaves in SMTP headers');
essentialCheck($db->query("SELECT status FROM {$p}mail_queue WHERE id=?",[$qid])->row()->status==='failed','worker marks stale notification failed without dispatch');
essentialCheck($db->query("SELECT status FROM {$p}mail_queue WHERE id=?",[$validA])->row()->status==='sent'&&$db->query("SELECT status FROM {$p}mail_queue WHERE id=?",[$validB])->row()->status==='sent','transport acceptance updates each claimed queue row');
$mailer->send_queue();essentialCheck(count($mailer->sent)===2,'worker replay cannot claim completed notifications');
$revoked=$delivery->queue($b,7,$ib,202,$revisionB,hash('sha256','worker-revoked'),$base,$decrypt);$db->query("DELETE FROM {$p}contact_permissions WHERE userid=202");$mailer->send_queue();
essentialCheck(count($mailer->sent)===2&&$db->query("SELECT status FROM {$p}mail_queue WHERE id=?",[$revoked])->row()->status==='failed','production worker integration denies revoked recipient before mocked SMTP');
$db->query("INSERT INTO {$p}contact_permissions(userid,permission_id) VALUES(202,1)");
echo 'Company invoice production queue worker: '.$GLOBALS['essentialChecks'].' cumulative checks passed; SMTP simulated only.'.PHP_EOL;
