<?php
require dirname(__DIR__).'/support/client_issuer_test_support.php';
require __DIR__.'/../../modules/custom_links/models/Custom_links_model.php';
require __DIR__.'/../../modules/connect_api_chat/models/Connect_api_chat_model.php';
class ModuleReaderDb extends ClientModelDb
{
    public bool $db_debug=true,$save_queries=true;
    public array $passwordQueryFlags=[];
    public $onPasswordUpdate=null;
    public function group_by(...$args){return $this;}
    public function select($sql){return parent::select(is_array($sql)?implode(',',$sql):$sql);}
    public function limit(...$args){return $this;}
    public function count_all_results($table){return count($this->get($table)->result_array());}
    public function escape($value){return $this->pdo->quote($value);}
    public function escape_like_str($value){return str_replace(['!','%','_'],['!!','!%','!_'],$value);}
    public function query($sql,$args=[]){
        if(str_starts_with($sql,'UPDATE q_contacts SET password=') && $this->onPasswordUpdate!==null){$hook=$this->onPasswordUpdate;$this->onPasswordUpdate=null;$hook();}
        if(str_contains($sql,'password'))$this->passwordQueryFlags[]=[$this->db_debug,$this->save_queries];
        $rows=parent::query($sql,$args)->result_array();
        return new class($rows) extends ClientModelResult {public function row_array(){return $this->result_array()[0]??null;} public function result(){return array_map(fn($row)=>(object)$row,$this->result_array());}};
    }
}
function admin_url($url=''){return 'https://crm.example.invalid/admin/'.$url;}
$db=new ModuleReaderDb($pdo);
$links=(new ReflectionClass(Custom_links_model::class))->newInstanceWithoutConstructor();$links->db=$db;
$rows=$links->get_clients_to_filter(null);
check(!$rows['client_ajax'] && count($rows['clients'])===1 && (int)$rows['clients'][0]['userid']===1,'small-directory dropdown hides private clients');
for($i=100;$i<115;$i++)$pdo->exec("INSERT INTO q_clients(userid,company,active) VALUES($i,'Legacy synthetic $i',1)");
$rows=$links->get_clients_to_filter(['clients'=>'1,2,3']);
check($rows['client_ajax'] && count($rows['clients'])===1 && (int)$rows['clients'][0]['userid']===1,'large-directory selected IDs cannot expose private identities');
$pdo->exec("ALTER TABLE q_contacts ADD COLUMN phonenumber TEXT");
$pdo->exec("UPDATE q_contacts SET phonenumber='5511999999999' WHERE id=20; UPDATE q_contacts SET phonenumber='5511888888888' WHERE id=10");
$chat=(new ReflectionClass(Connect_api_chat_model::class))->newInstanceWithoutConstructor();$chat->db=$db;
check($chat->crmContext('5511999999999')===[],'phone search cannot return another company private identity');
$context=$chat->crmContext('5511888888888');
check(isset($context['contact']) && (int)$context['contact']['id']===10,'legacy phone search remains functional');
require __DIR__.'/../../modules/accounting/models/Accounting_model.php';
require __DIR__.'/../../modules/goals/models/Goals_model.php';
$accounting=(new ReflectionClass(Accounting_model::class))->newInstanceWithoutConstructor();$accounting->db=$db;
$rows=$accounting->get_client_for_hansometable();
check(count($rows)===16 && !array_intersect([2,3],array_column($rows,'id')),'accounting client dropdown excludes private identities');
$pdo->exec("CREATE TABLE q_pur_vendor(userid INT,company TEXT,active INT); INSERT INTO q_pur_vendor VALUES(1,'Synthetic vendor',1)");
$payees=$accounting->get_payee_for_hansometable();
check(count($payees)===17 && !array_intersect(['customer_2','customer_3'],array_column($payees,'id')),'accounting mixed payee list preserves vendors without private clients');
$pdo->exec("ALTER TABLE q_clients ADD COLUMN datecreated TEXT; ALTER TABLE q_clients ADD COLUMN leadid INT; UPDATE q_clients SET datecreated='2026-10-18 00:00:00'");
$pdo->exec("CREATE TABLE q_leads(id INT,date_converted TEXT,status INT,assigned INT,addedfrom INT,name TEXT,phonenumber TEXT); INSERT INTO q_leads(id,date_converted,status,assigned,addedfrom) VALUES(101,'2026-10-18',1,0,7),(102,'2026-10-18',1,0,7); UPDATE q_clients SET leadid=101 WHERE userid=1; UPDATE q_clients SET leadid=102 WHERE userid=2");
class PrivateGoalProbe extends Goals_model {
    public int $testType=4;
    public function __construct(){}
    public function get($id='',$exclude_notified=false){return (object)['start_date'=>'2026-10-01','end_date'=>'2026-10-31','goal_type'=>$this->testType,'staff_id'=>0,'achievement'=>100];}
}
$goals=new PrivateGoalProbe();$goals->db=$db;
check($goals->calculate_goal_achievement(1)['total']===16.0,'goal customer count excludes private identities');
$goals->testType=3;check($goals->calculate_goal_achievement(1)['total']===15.0,'unconverted-customer goal excludes private identities');
$goals->testType=2;check($goals->calculate_goal_achievement(1)['total']===1.0,'lead conversion goal excludes private customer relationship');
require __DIR__.'/../../modules/ma/models/Ma_model.php';
$pdo->exec("ALTER TABLE q_clients ADD COLUMN ma_unsubscribed INT DEFAULT 0; CREATE TABLE q_ma_lead_segments(client_id INT,deleted INT,segment_id INT); INSERT INTO q_ma_lead_segments VALUES(1,0,5),(2,0,5),(3,0,5); CREATE TABLE q_ma_campaign_client_exceptions(campaign_id INT,client_id INT); INSERT INTO q_customer_groups VALUES(3,1,10),(4,2,10),(5,3,10)");
class PrivateMarketingProbe extends Ma_model {
    public function __construct(){}
    public function get_segment($id='',$where=[],$count=false,$is_kanban=false){return (object)['filter_type'=>'customer','filters'=>[]];}
    public function get_campaign($id='',$where=[],$count=false,$is_kanban=false){return (object)['workflow'=>json_encode(json_encode(['drawflow'=>['Home'=>['data'=>[['class'=>'flow_start','data'=>['data_type'=>'customer','client_data_from'=>'segment','customer_segment'=>5]]]]]]))];}
}
$marketing=new PrivateMarketingProbe();$marketing->db=$db;
foreach(['get_client_by_group'=>10,'get_client_by_segment'=>5,'get_client_by_campaign'=>8] as $method=>$id){
    $audience=$marketing->$method($id);
    check(count($audience)===1 && (int)$audience[0]['userid']===1,'marketing '.$method.' excludes private recipients');
    $predicate=$marketing->$method($id,'where');
    $audience=$db->query('SELECT userid FROM q_clients WHERE '.$predicate)->result_array();
    check(count($audience)===1 && (int)$audience[0]['userid']===1,'marketing returned predicate stays private');
}
define('APPPATH',dirname(__DIR__,2).'/application/');
require __DIR__.'/../../modules/multiple_companies/services/LegacyContactIdentity.php';
class CI_Model extends App_Model {}
require __DIR__.'/../../modules/multiple_companies/models/Multiple_companies_model.php';
$pdo->exec("UPDATE q_contacts SET email='same@example.invalid',password='synthetic-before'; INSERT INTO q_contacts(id,userid,email,password) VALUES(40,1,'same@example.invalid','synthetic-other'); UPDATE q_contacts SET password='synthetic-source' WHERE id=10");
check(MultipleCompaniesLegacyContactIdentity::syncPassword($db,'q_',10),'legacy same-email synchronization remains supported');
$passwords=$db->query('SELECT id,password FROM q_contacts ORDER BY id')->result_array();
check($passwords[0]['password']==='synthetic-source' && $passwords[3]['password']==='synthetic-source','legacy recipients synchronize');
check($passwords[1]['password']==='synthetic-before' && $passwords[2]['password']==='synthetic-before','private contact credentials never inherit legacy change');
check(MultipleCompaniesLegacyContactIdentity::syncPassword($db,'q_',20)===false,'private contact cannot become global credential source');
check(in_array([false,false],$db->passwordQueryFlags,true) && $db->db_debug && $db->save_queries,'sensitive statement logging disabled temporarily and restored');
$multiple=(new ReflectionClass(Multiple_companies_model::class))->newInstanceWithoutConstructor();$multiple->db=$db;
$companies=$multiple->get_contact_companies('same@example.invalid');
check(count($companies)===2 && !array_intersect([2,3],array_map(fn($row)=>(int)$row->userid,$companies)),'portal company lookup excludes private relationships');
$beforeWrites=$db->writes;
check($multiple->contact_copy(2,10)===false && $multiple->contact_copy(1,20)===false,'contact copy rejects both private source and target');
check($db->writes===$beforeWrites,'denied copy creates no contact or hook side effect');
$pdo->exec("UPDATE q_contacts SET password='synthetic-race-old' WHERE id=40; UPDATE q_contacts SET password='synthetic-race-new' WHERE id=10");
$db->onPasswordUpdate=fn()=>$pdo->exec('INSERT INTO q_issuers_clients VALUES(1,41)');
MultipleCompaniesLegacyContactIdentity::syncPassword($db,'q_',10);
check($db->query('SELECT password FROM q_contacts WHERE id=40')->row()->password==='synthetic-race-old','source adoption between lookup and write cannot broadcast private credentials');
$pdo->exec('DELETE FROM q_issuers_clients WHERE client_id=1');
$pdo->exec('DROP TABLE q_issuers_clients');
check($chat->crmContext('5511888888888')===[],'partial ownership schema does not reopen private lookup');
echo "Legacy module customer readers: $n checks passed.\n";
