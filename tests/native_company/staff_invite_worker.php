<?php
require __DIR__.'/staff_invite_creation.php';
#[AllowDynamicProperties]
final class StaffInvitationCapturedMailer extends App_Email
{
    public array $attempts=[];public bool $uncertain=false;public $duringSend=null;
    public function __construct($ci){$this->CI=$ci;$this->properties=self::$default_properties;foreach(array_keys(self::$default_properties)as$name)if(property_exists($this,$name))unset($this->{$name});(new ReflectionProperty(App_Email::class,'email_queue_table'))->setValue($this,db_prefix().'mail_queue');}
    public function clean_up_old_queue(){}
    public function clear($attachments=false){if($this->phpmailer){$this->phpmailer->clearAllRecipients();$this->phpmailer->clearCustomHeaders();}return$this;}
    public function send($skip=false){essentialCheck($skip===true,'actual worker reaches SMTP only after committed permit');$headers=serialize($this->phpmailer?$this->phpmailer->getCustomHeaders():$this->_headers);essentialCheck(!str_contains($headers,NativeCompanyStaffInviteMail::HEADER),'internal staff envelope is removed before SMTP');$this->attempts[]=$this->phpmailer?'phpmailer':'codeigniter';if($this->duringSend)($this->duringSend)();if($this->uncertain)throw new RuntimeException('synthetic accepted then disconnected');return true;}
}
$db->query("UPDATE {$p}mail_queue SET status='failed'");$workerCi->config->config['encryption_key']=$secret;$worker=new StaffInvitationCapturedMailer($workerCi);$start=$GLOBALS['essentialChecks'];$store=new CompanySettingsStorage($db,$p);
$store->mutate($a,7,'update','mail_engine','codeigniter');[$id,$mail]=$createInvite('worker-ci');$worker->send_queue();essentialCheck($worker->attempts===['codeigniter'],'actual CI queue accepts creation invitation once');
$store->mutate($a,7,'update','mail_engine','phpmailer');[$id,$mail]=$createInvite('worker-php');$worker->send_queue();essentialCheck($worker->attempts===['codeigniter','phpmailer'],'actual PHPMailer queue uses same origin contract');
[$id,$mail]=$createInvite('worker-unknown');$worker->uncertain=true;$worker->send_queue();$worker->uncertain=false;$db->query('UPDATE '.$p.'mail_queue SET status=? WHERE id=?',['pending',$mail->id]);$worker->send_queue();essentialCheck(count($worker->attempts)===3,'uncertain invitation cannot be resent by retry_queue');
[$id,$mail]=$createInvite('worker-rotation');$host=$store->get($a,'smtp_host');$store->mutate($a,7,'update','smtp_host','rotated.staff.invalid');$worker->send_queue();essentialCheck(count($worker->attempts)===3,'changed sender configuration denies invitation before SMTP');$store->mutate($a,7,'update','smtp_host',$host);
[$id,$mail,$headers]=$createInvite('worker-missing');unset($headers[NativeCompanyStaffInviteMail::HEADER]);$db->query('UPDATE '.$p.'mail_queue SET headers=? WHERE id=?',[serialize($headers),$mail->id]);$worker->send_queue();essentialCheck(count($worker->attempts)===3,'removed staff envelope cannot downgrade transport');
[$id,$mail]=$createInvite('worker-inflight');$worker->duringSend=fn()=>$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=?',[$a,$id]);$worker->send_queue();essentialCheck(count($worker->attempts)===4&&$db->query('SELECT state FROM '.$p.'company_native_mail_dispatch_fences WHERE queue_id=?',[$mail->id])->row()->state==='sent','revocation during SMTP preserves technical receipt without authorizing token');
echo 'Staff invitation actual App_Email: '.($GLOBALS['essentialChecks']-$start).' real checks, both engines; final SMTP captured, no network.'.PHP_EOL;
