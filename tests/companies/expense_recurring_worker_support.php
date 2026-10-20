<?php
if(!class_exists('CI_Email')){class CI_Email {}}
require_once APPPATH.'libraries/App_Email.php';
if(!function_exists('config_item')){function config_item($name){return "\r\n";}}
final class ExpenseRecurringWorkerDb
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
final class ExpenseRecurringWorkerMailer extends App_Email
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
