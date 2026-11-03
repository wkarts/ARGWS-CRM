<?php
require __DIR__.'/native_contact_message.php';
#[AllowDynamicProperties]
final class NativeContactMessageQueueMailer extends App_Email
{
    public array $attempts=[];public $duringSend=null;public bool $throwAfterAccept=false;
    public function __construct($ci){$this->CI=$ci;$this->properties=self::$default_properties;foreach(array_keys(self::$default_properties)as$name)if(property_exists($this,$name))unset($this->{$name});(new ReflectionProperty(App_Email::class,'email_queue_table'))->setValue($this,db_prefix().'mail_queue');}
    public function clean_up_old_queue(){}
    public function clear($attachments=false){if($this->phpmailer){$this->phpmailer->clearAllRecipients();$this->phpmailer->clearCustomHeaders();}return$this;}
    public function send($skip=false){
        essentialCheck($skip===true,'real worker invokes transport with queue bypass only after permit');
        $serialized=serialize($this->phpmailer?$this->phpmailer->getCustomHeaders():$this->_headers);
        essentialCheck(!str_contains($serialized,CompanyNativeContactMail::HEADER)&&!str_contains($serialized,CompanyPortalRecoveryMail::HEADER),'internal ownership metadata never becomes SMTP header');
        $this->attempts[]=['engine'=>$this->phpmailer?'phpmailer':'codeigniter'];
        if($this->duringSend)($this->duringSend)();
        if($this->throwAfterAccept)throw new RuntimeException('synthetic transport accepted then connection lost');return true;
    }
}

$workerCi->config->config['encryption_key']=$portalSecret;$worker=new NativeContactMessageQueueMailer($workerCi);$start=$GLOBALS['essentialChecks'];$db->query("UPDATE {$p}mail_queue SET status='failed'");
$id=$messageQueue();$worker->send_queue();essentialCheck(count($worker->attempts)===1,'actual queue worker dispatches registration message without recovery marker');
$state=$db->query("SELECT state FROM {$p}company_native_mail_dispatch_fences WHERE queue_id=?",[$id])->row()->state;essentialCheck($state==='sent','actual SMTP success persists frozen technical receipt');
$worker->send_queue();essentialCheck(count($worker->attempts)===1,'worker second pass cannot duplicate message');
$cfg->mutate($a,7,'update','mail_engine','phpmailer');$id=$messageQueue();$worker->send_queue();essentialCheck(count($worker->attempts)===2&&$worker->attempts[1]['engine']==='phpmailer','PHPMailer also consumes exact message envelope without internal headers');
$id=$messageQueue();$worker->throwAfterAccept=true;$worker->send_queue();$worker->throwAfterAccept=false;$db->query("UPDATE {$p}mail_queue SET status='pending' WHERE id=?",[$id]);$worker->send_queue();essentialCheck(count($worker->attempts)===3,'unknown outcome cannot be resent after retry_queue resets status');
$id=$messageQueue();$cfg->mutate($a,7,'update','smtp_host','rotated-for-worker.example.invalid');$worker->send_queue();essentialCheck(count($worker->attempts)===3,'configuration rotation blocks actual transport before send');
$cfg->mutate($a,7,'update','smtp_host',$before);$id=$messageQueue();$mail=$load($id);$headers=unserialize($mail->headers,['allowed_classes'=>false]);unset($headers[CompanyNativeContactMail::HEADER]);$db->query("UPDATE {$p}mail_queue SET headers=? WHERE id=?",[serialize($headers),$id]);$worker->send_queue();essentialCheck(count($worker->attempts)===3,'removed ownership header cannot downgrade actual queue routing');
echo 'Native contact message actual worker: '.($GLOBALS['essentialChecks']-$start).' checks passed; SMTP capture only.'.PHP_EOL;
