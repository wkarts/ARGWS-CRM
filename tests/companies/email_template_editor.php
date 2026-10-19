<?php
require __DIR__.'/email_template_controller.php';
class CompanyRequestScope {
 static $sealed=false;
 static function sealedFor($owner){return self::$sealed;}
 static function token(){return 'synthetic-context-token';}
 static function adminUrl($owner,$path=''){return 'https://synthetic.invalid/admin/'.$path;}
}
function show_error($message,$status=500){throw new RuntimeException('http_'.$status);}
$pdo->exec("ALTER TABLE q_issuers_registry ADD legal_name TEXT DEFAULT 'Synthetic'; ALTER TABLE q_issuers_registry ADD tax_id TEXT; ALTER TABLE q_issuers_registry ADD settings_json TEXT DEFAULT '{}'; CREATE TABLE q_issuers_audit(actor_id INT,event TEXT,issuer_id INT,subject_id INT,created_at TEXT);
INSERT INTO q_emailtemplates VALUES(3,82,'reset','portuguese_br','client','B pt','B subject','B body','','',1,0);");
class EditorInput {public $payload=[];function post($key=null,$filter=true){return $this->payload;}}
class EditorLoader {public $data;function view($name,$data){$this->data=$data;}}
$controller->db=$m->db;$controller->input=new EditorInput();$controller->load=new EditorLoader();
$controller->output=new class {public $status=200;function set_status_header($status){$this->status=$status;}};
$GLOBALS['ci']=$controller;CompanyRequestScope::$sealed=true;
CompanySettingsRuntime::$issuer=41;
$controller->index();
check(array_column($controller->load->data['templates'],'emailtemplateid')===[4],'actual isolated editor lists A PT-BR templates only');
$controller->email_template(4);
check($controller->load->data['selected']->subject==='A pt edited','actual isolated editor opens own template');
$controller->input->payload=['subject'=>'A visual edit','message'=>'A body','fromname'=>'A sender','fromemail'=>'','active'=>'1'];
$m->db->beforeBegin=fn()=>$pdo->exec("UPDATE q_emailtemplates SET subject='Fresh fallback edit' WHERE emailtemplateid=2");
$controller->email_template(4);
check($pdo->query('SELECT subject FROM q_emailtemplates WHERE emailtemplateid=2')->fetchColumn()==='Fresh fallback edit','editor reads fallback inside transaction rather than overwriting an intervening edit');
check($controller->load->data['saved'] && $m->get_email_template_by_id(4)->subject==='A visual edit','actual isolated editor saves A template');
check($pdo->query('SELECT issuer_id FROM q_issuers_audit')->fetchColumn()==41,'editor audit records company origin without template body');
unset($controller->input->payload['active']);$controller->email_template(4);
check((int)$pdo->query('SELECT SUM(active) FROM q_emailtemplates WHERE issuer_id=41')->fetchColumn()===0,'disabling template also disables its English fallback without touching B');
check((int)$pdo->query('SELECT active FROM q_emailtemplates WHERE emailtemplateid=3')->fetchColumn()===1,'B activation unchanged');
try{$controller->email_template(3);check(false,'foreign ID expected');}catch(RuntimeException $e){check($e->getMessage()==='not_found','A editor cannot address B template');}
$controller->input->payload['issuer_id']='82';$controller->email_template(4);
check($controller->output->status===422 && !$controller->load->data['saved'],'editor rejects submitted company override');
unset($controller->input->payload['issuer_id']);$controller->input->payload['subject']='Must rollback';
$beforeAuditFailure=$pdo->query('SELECT * FROM q_emailtemplates')->fetchAll(PDO::FETCH_ASSOC);
$m->db->failAudit=true;$controller->email_template(4);$m->db->failAudit=false;
check($controller->output->status===503 && $pdo->query('SELECT * FROM q_emailtemplates')->fetchAll(PDO::FETCH_ASSOC)===$beforeAuditFailure,'audit failure rolls back the actual editor write');
CompanySettingsRuntime::$can=false;
try{$controller->email_template(4);check(false,'permission expected');}catch(RuntimeException $e){check($e->getMessage()==='http_403','editor denies missing resource permission');}
CompanySettingsRuntime::$can=true;CompanyRequestScope::$sealed=false;
try{$controller->index();check(false,'seal expected');}catch(RuntimeException $e){check($e->getMessage()==='http_403','unsealed controller cannot open isolated editor');}
CompanyRequestScope::$sealed=true;CompanySettingsRuntime::$issuer=82;$controller->input->payload=[];
$controller->index();
check(array_column($controller->load->data['templates'],'emailtemplateid')===[3],'B editor sees independent list after A edits');
echo "Isolated template editor: component A/B, writes, audit and denials passed; HTTP bootstrap is separate.\n";
