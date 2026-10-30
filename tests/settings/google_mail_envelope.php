<?php
/** Header stripping must not turn a bound Google job into native SMTP delivery. */
require __DIR__.'/google_mail_worker.php';$envelopeStart=$GLOBALS['essentialChecks'];
#[AllowDynamicProperties]
class GoogleEnvelopeMailer extends App_Email
{
 public array$submitted=[];public function __construct($ci){$this->CI=$ci;$this->properties=self::$default_properties;foreach(array_keys(self::$default_properties)as$name)if(property_exists($this,$name))unset($this->{$name});(new ReflectionProperty(App_Email::class,'email_queue_table'))->setValue($this,db_prefix().'mail_queue');}
 public function clear($attachments=false){if($this->phpmailer){$this->phpmailer->clearAllRecipients();$this->phpmailer->clearCustomHeaders();}return$this;}public function clean_up_old_queue(){}
 public function send($skip=false){$this->submitted[]=$this->phpmailer?->getOAuth()instanceof CompanyGoogleMailOAuthTokenProvider?'google':'native';return true;}
}
$settings->mutate($a,7,'update','mail_engine','phpmailer',null);$emailWorker=new GoogleEnvelopeMailer($workerCi);
foreach(['removed','modified','issuer-null']as$mutation){$key=hash('sha256','google-envelope-'.$mutation);$qid=$delivery->queue($a,7,$invoice,101,$revision,$key,$base,$decrypt);$row=$db->query('SELECT * FROM '.$p.'mail_queue WHERE id=?',[$qid])->row_array();$headers=unserialize($row['headers'],['allowed_classes'=>false]);if($mutation==='removed')unset($headers[CompanyInvoiceDelivery::HEADER]);elseif($mutation==='modified')$headers[CompanyInvoiceDelivery::HEADER]=str_repeat('0',64);if($mutation==='issuer-null')$db->query('UPDATE '.$p.'mail_queue SET issuer_id=NULL WHERE id=?',[$qid]);else$db->query('UPDATE '.$p.'mail_queue SET headers=? WHERE id=?',[serialize($headers),$qid]);$emailWorker->send_queue();essentialCheck($emailWorker->submitted===[]&&$db->query('SELECT status FROM '.$p.'mail_queue WHERE id=?',[$qid])->row()->status==='failed','durable Google binding rejects '.$mutation.' before any native/Google SMTP attempt');}
echo 'Google bound queue envelope: '.($GLOBALS['essentialChecks']-$envelopeStart).' added / '.$GLOBALS['essentialChecks'].' cumulative checks ('.$db->driver().'); final SMTP captured.'.PHP_EOL;
