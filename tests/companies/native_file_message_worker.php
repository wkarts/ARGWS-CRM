<?php
require __DIR__.'/native_file_message.php';
#[AllowDynamicProperties]
final class NativeFileMessageQueueMailer extends App_Email
{
    public array$attempts=[];public bool$uncertain=false;public $duringSend=null;
    public function __construct($ci){$this->CI=$ci;$this->properties=self::$default_properties;foreach(array_keys(self::$default_properties)as$name)if(property_exists($this,$name))unset($this->{$name});(new ReflectionProperty(App_Email::class,'email_queue_table'))->setValue($this,db_prefix().'mail_queue');}
    public function clean_up_old_queue(){}
    public function clear($attachments=false){if($this->phpmailer){$this->phpmailer->clearAllRecipients();$this->phpmailer->clearCustomHeaders();$this->phpmailer->clearAttachments();}$this->_attachments=[];return$this;}
    public function send($skip=false){
        essentialCheck($skip===true,'file worker bypasses queue only at final transport');$headers=serialize($this->phpmailer?$this->phpmailer->getCustomHeaders():$this->_headers);essentialCheck(!str_contains($headers,NativeCompanyFileMessage::HEADER),'internal file provenance header never reaches SMTP');
        $attachments=$this->phpmailer?$this->phpmailer->getAttachments():$this->_attachments;$bytes=$this->phpmailer?$attachments[0][0]:base64_decode($attachments[0]['content']);$this->attempts[]=['engine'=>$this->phpmailer?'phpmailer':'codeigniter','bytes'=>$bytes,'count'=>count($attachments)];if($this->duringSend)($this->duringSend)();if($this->uncertain)throw new RuntimeException('synthetic SMTP accepted then connection lost');return true;
    }
}
$db->query("UPDATE {$p}mail_queue SET status='failed'");$fileId=$fileService->upload($a,7,$client,hash('sha256','file-mail-worker-upload'),'private.pdf',$bytes);$request=0;$newQueue=function()use($service,$a,$fileId,$decrypt,&$request){return$service->queue($a,7,'customer',$fileId,1,hash('sha256','worker-file-'.++$request),'explicit@example.invalid','Private attachment','Native file message',$decrypt);};
$workerCi->config->config['encryption_key']=$portalSecret;$worker=new NativeFileMessageQueueMailer($workerCi);$start=$GLOBALS['essentialChecks'];$store->mutate($a,7,'update','mail_engine','codeigniter');$q=$newQueue();$worker->send_queue();essentialCheck(count($worker->attempts)===1&&$worker->attempts[0]===['engine'=>'codeigniter','bytes'=>$bytes,'count'=>1],'actual codeigniter worker attaches exact in-memory file once');
$store->mutate($a,7,'update','mail_engine','phpmailer');$q=$newQueue();$worker->send_queue();essentialCheck(count($worker->attempts)===2&&$worker->attempts[1]===['engine'=>'phpmailer','bytes'=>$bytes,'count'=>1],'actual PHPMailer worker attaches exact in-memory file once');
$q=$newQueue();$worker->uncertain=true;$worker->send_queue();$worker->uncertain=false;$db->query('UPDATE '.$p.'mail_queue SET status=? WHERE id=?',['pending',$q]);$worker->send_queue();essentialCheck(count($worker->attempts)===3,'ambiguous SMTP result is never sent again after native queue retry');
$q=$newQueue();$beforeHost=$store->get($a,'smtp_host');$store->mutate($a,7,'update','smtp_host','rotated-file.example.invalid');$worker->send_queue();essentialCheck(count($worker->attempts)===3,'configuration rotation blocks actual SMTP seam');$store->mutate($a,7,'update','smtp_host',$beforeHost);
$q=$newQueue();$mail=$load($q);$headers=unserialize($mail->headers,['allowed_classes'=>false]);unset($headers[NativeCompanyFileMessage::HEADER]);$db->query('UPDATE '.$p.'mail_queue SET headers=? WHERE id=?',[serialize($headers),$q]);$worker->send_queue();essentialCheck(count($worker->attempts)===3,'removed file header cannot fall back to legacy worker');
$q=$newQueue();$worker->duringSend=fn()=>$fileService->delete($a,7,$client,$fileId,1);$worker->send_queue();essentialCheck(count($worker->attempts)===4&&$db->query('SELECT state FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=?',[$q])->row()->state==='sent','revocation during accepted send preserves technical receipt without a second authorization');
echo 'Native file message actual worker: '.($GLOBALS['essentialChecks']-$start).' checks passed; SMTP capture only.'.PHP_EOL;
