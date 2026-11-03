<?php
/** Real App_Email queue loop; replace only final SMTP side effect with a capture. */
require __DIR__.'/native_contact_mail.php';
$workerContactStart=$GLOBALS['essentialChecks'];
#[AllowDynamicProperties]
final class NativeContactQueueMailer extends App_Email
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
$workerCi->config->config['encryption_key']=$portalSecret;
$nativeWorker=new NativeContactQueueMailer($workerCi);
// Retire only earlier synthetic test queues; prepare the actual test messages through the service.
$db->query("UPDATE {$p}mail_queue SET status='failed'");$queue();
$nativeWorker->send_queue();essentialCheck(count($nativeWorker->attempts)===2,'actual worker sends both current owner welcome/setup messages');
$states=$db->query("SELECT f.state FROM {$p}company_native_mail_dispatch_fences f JOIN {$p}mail_queue q ON q.id=f.queue_id WHERE q.status='sent'")->result_array();essentialCheck(count($states)===2&&array_column($states,'state')===['sent','sent'],'SMTP success persists technical sent fences');
$nativeWorker->send_queue();essentialCheck(count($nativeWorker->attempts)===2,'next worker pass never duplicates completed native mails');
$queue();$nativeWorker->throwAfterAccept=true;$nativeWorker->send_queue();essentialCheck(count($nativeWorker->attempts)===4,'uncertain transport gets one attempt for each original envelope');
$db->query("UPDATE {$p}mail_queue SET status='pending' WHERE id IN (SELECT queue_id FROM {$p}company_native_mail_dispatch_fences WHERE state='unknown')");$nativeWorker->throwAfterAccept=false;$nativeWorker->send_queue();essentialCheck(count($nativeWorker->attempts)===4,'retry_queue status reset cannot redispatch uncertain native envelopes');
$queue();$nativeWorker->duringSend=fn()=>$db->query("UPDATE {$p}contacts SET active=0 WHERE id=101");$nativeWorker->send_queue();essentialCheck(count($nativeWorker->attempts)===5,'revocation after first send blocks second message source before transport');
$last=$db->query("SELECT f.state,q.status FROM {$p}company_native_mail_dispatch_fences f JOIN {$p}mail_queue q ON q.id=f.queue_id ORDER BY q.id DESC LIMIT 2")->result_array();essentialCheck($last[0]['state']==='prepared'&&$last[0]['status']==='failed'&&$last[1]['state']==='sent','frozen technical receipt retained after revocation; next envelope unspent');
$nativeWorker->duringSend=null;$db->query("UPDATE {$p}contacts SET active=1 WHERE id=101");
(new CompanySettingsStorage($db,$p))->mutate($a,7,'update','mail_engine','phpmailer',null);
$queue();$nativeWorker->send_queue();essentialCheck(count($nativeWorker->attempts)===7&&$nativeWorker->attempts[6]['engine']==='phpmailer','same owned queue path also proves PHPMailer without metadata leakage');
echo 'Native contact actual App_Email worker: '.($GLOBALS['essentialChecks']-$workerContactStart).' checks passed; final SMTP seam captured, no network.'.PHP_EOL;
