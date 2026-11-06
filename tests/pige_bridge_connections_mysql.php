<?php
/** Real native issuer ACL + encrypted connection schema; disposable database only, no provider. */
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"Disposable MySQL DSN required; configuration integration NOT RUN.\n");exit(2);}
require __DIR__.'/companies/invoices.php';
require_once BASEPATH.'libraries/Encryption.php';
require_once __DIR__.'/../modules/pige_bridge/libraries/PigeBridgeConnections.php';
// The essential issuer fixture already loaded global Migration_Version_102. Alias only this test
// class while running original migration up(); native module runner has its separate pure fixture.
$file=__DIR__.'/../modules/pige_bridge/migrations/102_version_102.php';$source=file_get_contents($file);$source=preg_replace('/^<\?php\s*/','',$source,1);$source=str_replace('class Migration_Version_102 extends','class PigeConnectionFixtureMigration102 extends',$source,$changed);if($changed!==1)throw new RuntimeException('migration declaration changed');eval($source);(new PigeConnectionFixtureMigration102())->up();(new PigeConnectionFixtureMigration102())->up();
register_shutdown_function(function()use($db,$p){$db->pdo->exec('DROP TABLE IF EXISTS '.$p.'pige_bridge_connections');});
$cipher=new CI_Encryption(['driver'=>'openssl','cipher'=>'aes-256','mode'=>'cbc','key'=>random_bytes(32)]);$connections=new PigeBridgeConnections($db,$cipher,$p);
$scope=['school_id'=>'11111111-1111-4111-8111-111111111111','issuer_id'=>(string)$a,'account_id'=>'fixture-wallet','environment'=>'sandbox'];$secret=str_repeat('synthetic-only-token-',3);$beforeInvoices=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
$connections->save('school',$scope,$secret,true,0,7);$saved=$db->query('SELECT * FROM '.$p.'pige_bridge_connections WHERE connection_key=?',['school'])->row();
essentialCheck($saved->secret_cipher!==$secret&&!str_contains($saved->secret_cipher,$secret)&&$cipher->decrypt($saved->secret_cipher)===$secret,'native encryption protects per-connection HMAC secret');
essentialCheck($connections->active([])['school']['scope']===$scope,'authentication scope comes from connection, never UI company');
essentialCheck(!str_contains(json_encode($connections->listed(7)),$secret)&&!str_contains(json_encode($connections->listed(7)),$saved->secret_cipher),'admin list never contains secret/cipher');
essentialThrows(fn()=>$connections->save('school',$scope,null,false,0,7),'pige_configuration_changed_reload','stale form denied');
$wrong=$scope;$wrong['issuer_id']=(string)$b;essentialThrows(fn()=>$connections->save('school',$wrong,null,true,1,7),'pige_scope_immutable','existing token cannot migrate issuer');
essentialThrows(fn()=>$connections->save('other',$wrong,$secret,true,0,7),'pige_duplicate_scope_or_secret','same token cannot be reused for another company');
essentialThrows(fn()=>$connections->save('other',$scope,str_repeat('other-synthetic-',3),true,0,7),'pige_duplicate_scope_or_secret','same scope cannot be duplicated with new token');
$mapping=['enabled'=>true,'actor_id'=>7,'currency_id'=>1];$connections->saveMapping('school',1,$mapping,7);
$loaded=IssuerTransaction::run($db,$p,PigeBridgeConnections::tables(),fn()=>$connections->assertMappingInOwned('school',$scope,2));essentialCheck($loaded['mapping']===$mapping,'explicit financial mapping ready under owned issuer lock');
$_SESSION=['issuer_id'=>$b];essentialCheck($connections->mapping('school')['scope']===$scope,'ambient UI company does not change mapping');unset($_SESSION);
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12',[$a]);$delegated=$mapping;$delegated['actor_id']=12;
essentialThrows(fn()=>$connections->saveMapping('school',2,$delegated,7),'pige_invoice_actor_denied','invoice access alone cannot delegate customer access');
foreach([['customers','view'],['settings','edit']] as [$feature,$cap])$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$a,$feature,$cap]);
$connections->saveMapping('school',2,$delegated,7);essentialThrows(fn()=>$connections->saveMapping('school',3,$mapping,12),'pige_delegation_denied','nonmaster cannot delegate a more privileged actor');
$newSecret=str_repeat('rotated-synthetic-token-',3);$connections->save('school',$scope,$newSecret,true,3,7);essentialCheck($connections->mapping('school')['mapping']['enabled']===false,'rotation invalidates invoice consent without changing actor/scope');
essentialCheck($connections->active([])['school']['secret']===$newSecret,'rotation replaces accepted transport secret');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature=?',[$a,'customers']);
essentialThrows(fn()=>$connections->save('school',$scope,str_repeat('third-synthetic-',3),true,4,7),'pige_invoice_actor_denied','retained delegated actor must remain authorized even for token rotation');
$connections->save('school',$scope,null,false,4,7);essentialCheck($connections->active([])===[],'revocation remains possible after delegated permissions expired');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeInvoices,'configuration creates no invoice or financial effect');
echo "PIGE native configuration integration: encryption, scope, ACL/delegation, rotation, revocation; no bank calls.\n";

// Actual disable action with synthetic framework I/O and the real transactional writer.
$uiScope=$scope;$uiScope['account_id']='disable-ui';
$connections->save('disable-ui',$uiScope,str_repeat('ui-synthetic-secret-',3),true,0,7);
$connections->saveMapping('disable-ui',1,$mapping,7);
$otherScope=$uiScope;$otherScope['issuer_id']=(string)$b;
$connections->save('other-ui',$otherScope,str_repeat('other-ui-secret-',3),true,0,7);
$uiAdmin=true;$uiActor=7;$uiErrors=[];$uiAlerts=[];
function is_admin(){return $GLOBALS['uiAdmin'];}
function get_staff_user_id(){return $GLOBALS['uiActor'];}
function access_denied($name){$GLOBALS['uiErrors'][]=403;}
function show_error($message,$status){$GLOBALS['uiErrors'][]=$status;}
function admin_url($path){return '/admin/'.$path;}
function redirect($path){}
function set_alert($level,$message){$GLOBALS['uiAlerts'][]=$level;}
class AdminController {
    public $db,$encryption,$input,$session,$output,$load;
    public function __construct(){
        $this->db=$GLOBALS['db'];$this->encryption=$GLOBALS['cipher'];
        $this->input=new class{public $verb='POST',$values=[];function method($upper=false){return $this->verb;}function post($key,$filter=false){return $this->values[$key]??null;}};
        $this->session=new class{function userdata($key){return str_repeat('a',64);}};
        $this->output=new class{function set_header($header){return $this;}};
        $this->load=new class{function library($name){}};
    }
}
require __DIR__.'/../modules/pige_bridge/controllers/Settings.php';
$ui=new Settings();$ui->input->values=['connection_key'=>'disable-ui','revision'=>'2','pige_csrf'=>str_repeat('a',64),'issuer_id'=>(string)$b];
$row=fn($key)=>$db->query('SELECT * FROM '.$p.'pige_bridge_connections WHERE connection_key=?',[$key])->row_array();
$before=$row('disable-ui');$otherBefore=$row('other-ui');
$ui->input->verb='GET';$ui->disable();essentialCheck(end($uiErrors)===405&&$row('disable-ui')===$before,'disable GET does not write');
$ui->input->verb='POST';$ui->input->values['pige_csrf']='wrong';$ui->disable();essentialCheck(end($uiErrors)===403&&$row('disable-ui')===$before,'disable rejects invalid CSRF');
$ui->input->values['pige_csrf']=str_repeat('a',64);$uiAdmin=false;$ui->disable();essentialCheck(end($uiErrors)===403&&$row('disable-ui')===$before,'disable rejects nonadmin');$uiAdmin=true;
$uiActor=12;$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature=?',[$a,'settings']);
$ui->disable();essentialCheck(end($uiAlerts)==='danger'&&$row('disable-ui')===$before,'revoked company permission rejects disable');$uiActor=7;
$ui->input->values['revision']='1';$ui->disable();essentialCheck(end($uiAlerts)==='danger'&&$row('disable-ui')===$before,'disable rejects obsolete revision');
$ui->input->values['revision']='2';$ui->disable();$after=$row('disable-ui');
essentialCheck(end($uiAlerts)==='success'&&(int)$after['revision']===3&&(int)$after['enabled']===0,'valid disable updates once');
foreach(['scope_json','scope_hash','secret_cipher','secret_hash','created_at'] as $field)essentialCheck($after[$field]===$before[$field],'disable preserves '.$field);
essentialCheck(json_decode($after['mapping_json'],true)['enabled']===false,'disable revokes delegated consent');
essentialCheck($row('other-ui')===$otherBefore,'request issuer cannot select another company or alter its data');
$ui->disable();essentialCheck(end($uiAlerts)==='danger'&&$row('disable-ui')===$after,'replayed disable is stale');
echo "PIGE disable HTTP boundary: actual controller and writer, GET/CSRF/ACL/revision, server-owned scope, secret preservation and other-company isolation passed.\n";
