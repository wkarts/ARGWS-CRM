<?php
/** Actual App_Email/App_mailer and PHPMailer provider; only final SMTP transmission is captured. */
require_once dirname(__DIR__).'/issuers_core_support.php';require BASEPATH.'libraries/Email.php';
require __DIR__.'/google_mail_queue.php';require_once APPPATH.'libraries/App_Email.php';
$workerStart=$GLOBALS['essentialChecks'];
if(!function_exists('config_item')){function config_item($key){return "\r\n";}}
if(!function_exists('valid_email')){function valid_email($value){return(bool)filter_var($value,FILTER_VALIDATE_EMAIL);}}
final class GoogleWorkerDb
{
 public PDO$pdo;private array$where=[];private array$fields=[];private int$affected=0;public function __construct(private EssentialTestDb$inner){$this->pdo=$inner->pdo;}public function __call($m,$args){return$this->inner->$m(...$args);}
 public function where($k,$v=null){if(!in_array($k,['id','status'],true))throw new LogicException('unexpected predicate');$this->where[$k]=$v;return$this;}
 private function clause():array{$fields=[];$args=[];foreach($this->where as$k=>$v){$fields[]=$k.'=?';$args[]=$v;}$this->where=[];return[$fields?' WHERE '.implode(' AND ',$fields):'',$args];}
 public function get($table){[$where,$args]=$this->clause();return$this->inner->query('SELECT * FROM '.$table.$where,$args);}
 public function set($key,$value){$this->fields[$key]=$value;return$this;}
 public function update($table,$fields=null){$fields=$fields??$this->fields;$this->fields=[];[$where,$args]=$this->clause();$q=$this->pdo->prepare('UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($fields))).$where);$q->execute(array_merge(array_values($fields),$args));$this->affected=$q->rowCount();return true;}
 public function affected_rows(){return$this->affected;}
}
#[AllowDynamicProperties]
final class GoogleQueueMailer extends App_Email
{
 public array$sent=[];public function __construct($ci){$this->CI=$ci;$this->properties=self::$default_properties;foreach(array_keys(self::$default_properties)as$name)if(property_exists($this,$name))unset($this->{$name});(new ReflectionProperty(App_Email::class,'email_queue_table'))->setValue($this,db_prefix().'mail_queue');}
 public function clear($attachments=false){if($this->phpmailer){$this->phpmailer->clearAllRecipients();$this->phpmailer->clearCustomHeaders();}return$this;}
 public function clean_up_old_queue(){}
 public function send($skip=false){$provider=$this->phpmailer?->getOAuth();if(!$provider instanceof CompanyGoogleMailOAuthTokenProvider)throw new LogicException('unexpected transport');$this->sent[]=base64_decode($provider->getOauth64());return true;}
}
$workerCi=(object)['db'=>new GoogleWorkerDb($db),'config'=>(object)['config'=>[]],'encryption'=>new class{function decrypt($v){return str_starts_with($v,'sealed:')?base64_decode(substr($v,7),true):'native:'.$v;}function encrypt($v){return'sealed:'.base64_encode($v);}},'load'=>new class{function config($v){}}];
$worker=new GoogleQueueMailer($workerCi);$worker->send_queue();essentialCheck(count($worker->sent)===1&&$worker->sent[0]==="user=a@example.invalid\x01auth=Bearer synthetic-access\x01\x01",'actual worker initializes canonical own XOAUTH2 and captures one allowed SMTP submission');$worker->send_queue();essentialCheck(count($worker->sent)===1,'completed queue is not resubmitted by next worker invocation');
$revokeKey=hash('sha256','google-worker-revoke');$q=$delivery->queue($a,7,$invoice,101,$revision,$revokeKey,$base,$decrypt);$oauth->revoke($a,7,1);$worker->send_queue();essentialCheck(count($worker->sent)===1&&$db->query('SELECT status FROM '.$p.'mail_queue WHERE id=?',[$q])->row()->status==='failed','revocation blocks actual native worker before SMTP submission');
$u=$oauth->begin($a,7,1,'https://crm.example.invalid/admin/company_google_mail_oauth',$encrypt);parse_str(parse_url($u,PHP_URL_QUERY),$params);$googleEmail='a@example.invalid';$oauth->callback($a,7,$params['state'],'worker-connect',$encrypt,$decrypt);$q=$delivery->queue($a,7,$invoice,101,$revision,hash('sha256','google-worker-inactive'),$base,$decrypt);$db->query('UPDATE '.$p.'issuers_registry SET active=0 WHERE id=?',[$a]);$worker->send_queue();essentialCheck(count($worker->sent)===1&&$db->query('SELECT status FROM '.$p.'mail_queue WHERE id=?',[$q])->row()->status==='failed','disabled origin cannot initialize worker account or submit SMTP');$db->query('UPDATE '.$p.'issuers_registry SET active=1 WHERE id=?',[$a]);
echo 'Google actual native worker: '.($GLOBALS['essentialChecks']-$workerStart).' added / '.$GLOBALS['essentialChecks'].' cumulative checks ('.$db->driver().'); SMTP captured, no external network.'.PHP_EOL;
