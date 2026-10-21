<?php
require __DIR__ . '/company_settings.php';
require APPPATH . 'services/CompanyMailConfig.php';
class CI_Email {}
require APPPATH . 'libraries/App_Email.php';
if (!function_exists('log_message')) { function log_message($level,$message) {} }
final class QueueFixtureDb {
    public array $rows=[]; public bool $loseClaim=false; private array $where=[]; private int $affected=0; private array $set=[];
    public function __construct(public $inner) {}
    public function query($sql,$args=[]) { return $this->inner->query($sql,$args); }
    public array $tablesChecked=[];
    public function table_exists($table) { $this->tablesChecked[]=$table;return $this->inner->table_exists($table); }
    public function field_exists($field,$table) { return $field==='issuer_id' ? true : $this->inner->field_exists($field,$table); }
    public function where($name,$value=null) { $this->where[$name]=$value; return $this; }
    private function matches($row) { foreach($this->where as $key=>$value) { if(($row[preg_replace('/^q\./','',$key)]??null)!==$value)return false; } return true; }
    public function get($table,$limit=null,$offset=null) { $rows=array_values(array_filter($this->rows,fn($row)=>$this->matches($row)));$this->where=[];return new class($rows) {function __construct(private $rows){}function result(){return array_map(fn($r)=>(object)$r,$this->rows);}}; }
    public function set($key,$value){$this->set[$key]=$value;return $this;}
    public function update($table,$data=null) { $data=$data??$this->set;$this->set=[]; $this->affected=0;if($this->loseClaim && ($data['status']??null)==='sending'){$this->where=[];return true;}foreach($this->rows as &$row){if($this->matches($row)){$row=array_replace($row,$data);$this->affected++;}}$this->where=[];return true; }
    public function affected_rows(){return $this->affected;}
}
final class QueueFixtureMailer extends App_Email {
    public array $sent=[]; public array $transport=[];
    public function __construct($ci){$this->CI=$ci;$p=new ReflectionProperty(App_Email::class,'email_queue_table');$p->setValue($this,db_prefix().'mail_queue');}
    public function initialize(array $config=[]){$this->transport=$config;return $this;}
    public function set_mailer_engine($engine){$this->mailer_engine=$engine;return $this;}
    public function clear($attachments=false){return $this;}
    public function clean_up_old_queue(){}
    public function send($skip=false){$this->sent[]=$this->transport;return true;}
    public function set_newline($v="\n"){return $this;}
    public function set_crlf($v="\n"){return $this;}
    public function to($v,$name=''){return $this;}
    public function cc($v,$name=''){return $this;}
    public function bcc($v,$limit=''){return $this;}
    public function message($v){return $this;}
    public function set_alt_message($v){return $this;}
}
if(!function_exists('config_item')){function config_item($name){return "\r\n";}}
$start=$GLOBALS['essentialChecks'];
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
foreach([41=>'a',82=>'b'] as $issuer=>$label){foreach(['mail_engine'=>'codeigniter','email_protocol'=>'smtp','smtp_host'=>"{$label}.invalid",'smtp_port'=>'587','smtp_password'=>"synthetic-{$label}"] as $name=>$value){$store->mutate($issuer,7,'update',$name,$value,null);}}
$q=new QueueFixtureDb($db);
foreach([1=>41,2=>82,3=>null] as $id=>$issuer){$q->rows[$id]=['id'=>$id,'issuer_id'=>$issuer,'status'=>'pending','engine'=>'codeigniter','email'=>'synthetic@example.invalid','cc'=>'','bcc'=>'','headers'=>serialize([]),'attachments'=>'','message'=>'synthetic','alt_message'=>''];}
$ci=(object)['db'=>$q,'config'=>(object)['config'=>['smtp_host'=>'legacy.invalid','smtp_pass'=>'synthetic-legacy']],'encryption'=>new class{function decrypt($v){return 'decoded:'.$v;}},'load'=>new class{function config($name){}}];
$mail=new QueueFixtureMailer($ci);
$mail->send_queue();
essentialCheck(in_array($p.'company_portal_mail_deliveries',$q->tablesChecked,true)&&in_array($p.'company_google_mail_accounts',$q->tablesChecked,true)&&in_array($p.'company_expense_recurring',$q->tablesChecked,true),'actual queue routing guards inspect real optional schema presence through the fixture adapter');
essentialCheck(count($mail->sent)===3,'all explicit synthetic queue origins processed');
essentialCheck(array_column($mail->sent,'smtp_host')===['a.invalid','b.invalid','legacy.invalid'],'worker chooses each durable origin and restores legacy configuration');
essentialCheck(array_column($mail->sent,'smtp_pass')===['decoded:synthetic-a','decoded:synthetic-b','synthetic-legacy'],'worker cannot retain prior company credential');
essentialCheck(array_column($q->rows,'status')===['sent','sent','sent'],'claimed rows complete individually');
$mail->send_queue();
essentialCheck(count($mail->sent)===3,'completed rows cannot be sent a second time');
$q->rows[2]['status']='pending';$db->query("UPDATE {$p}issuers_registry SET active=0 WHERE id=82");
$mail->send_queue();
essentialCheck(count($mail->sent)===3 && $q->rows[2]['status']==='failed','inactive company is deferred without fallback or delivery');
$mail->set_status('sent');
essentialCheck(count($mail->get_queue_emails())===1,'legacy queue UI excludes positive company origins');
$q->rows[1]['status']='pending';$q->loseClaim=true;
$mail->send_queue();
essentialCheck(count($mail->sent)===3,'lost atomic claim cannot perform SMTP delivery');
$beforeQueue=$q->rows;
CompanySettingsRuntime::run($db,$p,41,12,function()use($mail){
    settingThrows(fn()=>$mail->send_queue(),'company_mail_worker_context_denied','interactive company cannot run global queue worker');
});
essentialCheck($q->rows===$beforeQueue,'rejected interactive worker does not mutate queue');
$q->loseClaim=false;$q->rows[1]['headers']='malformed serialization';
$mail->send_queue();
essentialCheck(count($mail->sent)===3 && $q->rows[1]['status']==='failed','malformed queued payload never reaches transport');
$native=(new ReflectionClass(App_Email::class))->newInstanceWithoutConstructor();
CompanySettingsRuntime::run($db,$p,41,12,function()use($native){
    settingThrows(fn()=>$native->send(),'company_mail_context_changed','transport created without company cannot be reused by a company request');
});
(new ReflectionProperty(App_Email::class,'companyOrigin'))->setValue($native,41);
settingThrows(fn()=>$native->send(),'company_mail_context_changed','company transport cannot escape into unscoped legacy request');
$closed=(object)['count'=>0];
$native->phpmailer=new class($closed){function __construct(private $closed){}function smtpClose(){$this->closed->count++;}};
(new ReflectionMethod(App_mailer::class,'reset_account_transport'))->invoke($native);
essentialCheck($closed->count===1 && $native->phpmailer===null,'actual account reset closes and discards previous OAuth/SMTP transport');
echo 'Company queue runtime: '.($GLOBALS['essentialChecks']-$start).' additional checks passed.'.PHP_EOL;
