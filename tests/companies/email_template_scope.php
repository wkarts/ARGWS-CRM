<?php
require __DIR__.'/../support/client_issuer_test_support.php';
define('APPPATH',realpath(__DIR__.'/../../application').'/');
require APPPATH.'models/Emails_model.php';
class CompanySettingsRuntime {static $issuer=null;static $can=true;static function canResource($feature,$cap){return self::$can;}static function staffId(){return 12;}static function active(){return self::$issuer!==null;}static function isPrimary(){return self::$issuer===7;}static function issuerId(){return self::$issuer;}}
class TemplateDb extends ClientModelDb {
 public ?string $failSubject=null;public $beforeBegin=null;public bool $failAudit=false;
 function trans_begin(){if($this->beforeBegin){$call=$this->beforeBegin;$this->beforeBegin=null;$call();}return $this->pdo->beginTransaction();}function trans_commit(){return $this->pdo->commit();}function trans_rollback(){return $this->pdo->rollBack();}function trans_status(){return true;}
 function query($sql,$args=[]){if($this->failAudit && str_contains($sql,'INSERT INTO q_issuers_audit'))return false;return parent::query(str_replace(' FOR UPDATE','',$sql),$args);}
 function update($table,$data){if(($data['subject']??null)===$this->failSubject && $this->failSubject!==null){$this->get($table);return false;}return parent::update($table,$data);}
}
$pdo->exec("CREATE TABLE q_issuers_installation_identity(id INT,primary_issuer_id INT); INSERT INTO q_issuers_installation_identity VALUES(1,7);
CREATE TABLE q_issuers_registry(id INT,active INT); INSERT INTO q_issuers_registry VALUES(7,1),(41,1),(82,1);
CREATE TABLE q_issuers_lock(id INT PRIMARY KEY); INSERT INTO q_issuers_lock VALUES(1);
CREATE TABLE q_emailtemplates(emailtemplateid INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INT,slug TEXT,language TEXT,type TEXT,name TEXT,subject TEXT,message TEXT,fromname TEXT,fromemail TEXT,active INT,plaintext INT);
INSERT INTO q_emailtemplates VALUES(1,NULL,'reset','english','client','Legacy','Legacy subject','Legacy body','','',1,0),(2,41,'reset','english','client','A','A subject','A body','','',1,0),(3,82,'reset','english','client','B','B subject','B body','','',1,0),(4,41,'reset','portuguese_br','client','A pt','A pt subject','A pt body','','',1,0);");
$m=(new ReflectionClass(Emails_model::class))->newInstanceWithoutConstructor();$m->db=new TemplateDb($pdo);
check(array_column($m->get(),'emailtemplateid')===[1],'unscoped legacy template listing excludes all private templates');
CompanySettingsRuntime::$issuer=41;
check(array_column($m->get(),'emailtemplateid')===[2,4],'A reads only its native templates');
check($m->get_email_template_by_id(3)===null,'A cannot read B template by direct id');
check(array_column($m->get('emailtemplateid=3 OR emailtemplateid=2'),'emailtemplateid')===[2],'template OR predicate cannot escape company');
check($m->mark_as('reset',0)===true && $pdo->query('SELECT active FROM q_emailtemplates WHERE emailtemplateid=3')->fetchColumn()==1,'bulk slug update affects A only');
$payload=['subject'=>[2=>'A edited',4=>'A pt edited'],'message'=>[2=>'A body edited',4=>'A pt body edited'],'fromname'=>'A sender'];
check($m->update($payload)===true,'A edits its own language variants');
$before=$pdo->query('SELECT * FROM q_emailtemplates')->fetchAll(PDO::FETCH_ASSOC);
$payload['subject'][3]='foreign';$payload['message'][3]='foreign';
check($m->update($payload)===false && $pdo->query('SELECT * FROM q_emailtemplates')->fetchAll(PDO::FETCH_ASSOC)===$before,'mixed company update denied before any row changes');
unset($payload['subject'][3],$payload['message'][3]);$payload['subject'][2]='would change';$payload['subject'][4]='fail';$m->db->failSubject='fail';
try{$m->update($payload);check(false,'expected DB failure');}catch(RuntimeException $e){check(true,'DB failure propagated');}
check($pdo->query('SELECT * FROM q_emailtemplates')->fetchAll(PDO::FETCH_ASSOC)===$before,'second language failure rolls back first language');
$m->db->failSubject=null;
CompanySettingsRuntime::$issuer=82;
check(array_column($m->get(),'emailtemplateid')===[3] && $m->get_email_template_by_id(3)->subject==='B subject','B remains independent after A edits and failures');
// Exercise the native template preparation fallback with its actual scoped model.
require APPPATH.'libraries/mails/App_mail_template.php';
class ScopedTemplateFixture extends App_mail_template {
 public function __construct($model){$this->ci=(object)['emails_model'=>$model];$this->slug='reset';$this->for='fixture';$this->rel_type='fixture';}
}
$GLOBALS['hooks']=new class {public $language='portuguese_br';function apply_filters($name,$value,...$args){return $name==='email_template_language'?$this->language:$value;}};
$mail=new ScopedTemplateFixture($m);
CompanySettingsRuntime::$issuer=41;
check($mail->prepare('synthetic@example.invalid')->subject==='A pt edited','actual native preparation selects A translation');
CompanySettingsRuntime::$issuer=82;
check($mail->prepare('synthetic@example.invalid')->subject==='B subject','actual native preparation falls back to B English, never A translation');
$pdo->exec("DELETE FROM q_emailtemplates WHERE issuer_id=82");
check($mail->prepare('synthetic@example.invalid')===null,'actual native preparation has no global fallback when B template missing');
CompanySettingsRuntime::$issuer=null;
check($mail->prepare('synthetic@example.invalid')->subject==='Legacy subject','actual legacy preparation preserves primary fallback');
CompanySettingsRuntime::$issuer=41;
$GLOBALS['hooks']->language='english';
check($mail->prepare('synthetic@example.invalid')->language==='english','explicit English preference is retained');
$GLOBALS['hooks']->language='portuguese_br';
$pdo->exec("UPDATE q_emailtemplates SET message='',active=0 WHERE emailtemplateid=4");
check($mail->prepare('synthetic@example.invalid')->emailtemplateid==4 && $mail->prepare('synthetic@example.invalid')->active==0,'native disabled blank translation stops fallback');
$pdo->exec("UPDATE q_emailtemplates SET active=1 WHERE emailtemplateid=4");
check($mail->prepare('synthetic@example.invalid')->emailtemplateid==2,'native enabled blank translation falls back only in A');
$pdo->exec("INSERT INTO q_emailtemplates VALUES(5,41,'foreign-only','french','client','French custom','Objet','Corps','','',1,0)");
check(count($m->get_catalogue())===2 && $m->get_catalogue()[1]['language']==='french','listing retains a custom foreign-language-only template');
CompanySettingsRuntime::$issuer=82;
check($m->get_catalogue()===[],'catalogue never borrows A templates for B');
echo "Native email template scope: $n synthetic checks passed\n";
