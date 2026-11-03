<?php
require __DIR__.'/native_portal_verification.php';
#[AllowDynamicProperties]
final class NativeVerificationQueueMailer extends App_Email
{
 public array$attempts=[];public bool$uncertain=false;public $duringSend=null;
 public function __construct($ci){$this->CI=$ci;$this->properties=self::$default_properties;foreach(array_keys(self::$default_properties)as$name)if(property_exists($this,$name))unset($this->{$name});(new ReflectionProperty(App_Email::class,'email_queue_table'))->setValue($this,db_prefix().'mail_queue');}
 public function clean_up_old_queue(){}
 public function clear($attachments=false){if($this->phpmailer){$this->phpmailer->clearAllRecipients();$this->phpmailer->clearCustomHeaders();}return$this;}
 public function send($skip=false){essentialCheck($skip===true,'SMTP capture is reached after worker dispatch permit');$headers=serialize($this->phpmailer?$this->phpmailer->getCustomHeaders():$this->_headers);essentialCheck(!str_contains($headers,NativePortalVerificationMail::HEADER),'verification ownership envelope is stripped before SMTP');$this->attempts[]=$this->phpmailer?'phpmailer':'codeigniter';if($this->duringSend)($this->duringSend)();if($this->uncertain)throw new RuntimeException('synthetic accepted then connection lost');return true;}
}
$db->query("UPDATE {$p}mail_queue SET status='failed'");$workerCi->config->config['encryption_key']=$portalSecret;$worker=new NativeVerificationQueueMailer($workerCi);$start=$GLOBALS['essentialChecks'];
$store=new CompanySettingsStorage($db,$p);
$newQueue=function()use($db,$p,$verify,$a,$client,$req,$base,$decrypt){$db->query('UPDATE '.$p.'contacts SET email_verified_at=NULL,email_verification_key=NULL,email_verification_sent_at=NULL WHERE id=101');return IssuerTransaction::run($db,$p,$verify->tables(),fn()=>$verify->queueRegistrationInOwned($a,$client,101,$req,$base,$decrypt));};
$store->mutate($a,7,'update','mail_engine','codeigniter');$q=$newQueue();$worker->send_queue();essentialCheck($worker->attempts===['codeigniter'],'actual CodeIgniter queue dispatch sends verification only once');
$store->mutate($a,7,'update','mail_engine','phpmailer');$q=$newQueue();$worker->send_queue();essentialCheck($worker->attempts===['codeigniter','phpmailer'],'actual PHPMailer queue dispatch has equivalent verification contract');
$q=$newQueue();$worker->uncertain=true;$worker->send_queue();$worker->uncertain=false;$db->query('UPDATE '.$p.'mail_queue SET status=? WHERE id=?',['pending',$q]);$worker->send_queue();essentialCheck(count($worker->attempts)===3,'unknown SMTP cannot retry after native queue status reset');
$q=$newQueue();$oldHost=$store->get($a,'smtp_host');$store->mutate($a,7,'update','smtp_host','rotated-verification.invalid');$worker->send_queue();essentialCheck(count($worker->attempts)===3,'config rotation denies verification transport');$store->mutate($a,7,'update','smtp_host',$oldHost);
$q=$newQueue();$mail=$db->query('SELECT * FROM '.$p.'mail_queue WHERE id=?',[$q])->row_array();$headers=unserialize($mail['headers'],['allowed_classes'=>false]);unset($headers[NativePortalVerificationMail::HEADER]);$db->query('UPDATE '.$p.'mail_queue SET headers=? WHERE id=?',[serialize($headers),$q]);$worker->send_queue();essentialCheck(count($worker->attempts)===3,'header removal cannot enter legacy SMTP path');
$q=$newQueue();$worker->duringSend=fn()=>$db->query('UPDATE '.$p.'contacts SET active=0 WHERE id=101');$worker->send_queue();essentialCheck(count($worker->attempts)===4&&$db->query('SELECT state FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=?',[$q])->row()->state==='sent','revocation during accepted SMTP preserves only frozen technical receipt');
echo 'Native portal verification actual App_Email: '.($GLOBALS['essentialChecks']-$start).' checks passed; final SMTP capture, no network.'.PHP_EOL;
