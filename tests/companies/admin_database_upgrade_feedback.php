<?php
// Native feedback identity/expiry/replay contract; no external integrations.
define('BASEPATH',__DIR__);require dirname(__DIR__,2).'/application/services/AdminDatabaseUpgrade.php';
final class UpgradeFeedbackSession
{
    public array $data=[];
    public function userdata($key){return $this->data[$key]??null;}
    public function set_userdata($key,$value):void{$this->data[$key]=$value;}
    public function unset_userdata($key):void{unset($this->data[$key]);}
}
$checks=0;function verify($condition,$message){global $checks;++$checks;if(!$condition)throw new RuntimeException($message);}
$s=new UpgradeFeedbackSession();verify(!AdminDatabaseUpgrade::consumeSuccess($s,17,'436',1000),'No forged success without a server receipt');
AdminDatabaseUpgrade::recordSuccess($s,17,'436',1000);verify(AdminDatabaseUpgrade::consumeSuccess($s,17,'436',1001),'Exact actor/version receives success');verify(!AdminDatabaseUpgrade::consumeSuccess($s,17,'436',1001),'Receipt consumed once');
AdminDatabaseUpgrade::recordSuccess($s,17,'436',1000);verify(!AdminDatabaseUpgrade::consumeSuccess($s,18,'436',1001),'Different staff cannot receive another actor feedback');verify(!$s->data,'Actor mismatch discarded');
AdminDatabaseUpgrade::recordSuccess($s,17,'436',1000);verify(!AdminDatabaseUpgrade::consumeSuccess($s,17,'435',1001),'Different migration cannot receive feedback');
AdminDatabaseUpgrade::recordSuccess($s,17,'436',1000);verify(AdminDatabaseUpgrade::consumeSuccess($s,17,'436',1299),'Receipt available within TTL');
AdminDatabaseUpgrade::recordSuccess($s,17,'436',1000);verify(!AdminDatabaseUpgrade::consumeSuccess($s,17,'436',1300),'Receipt expires at TTL');verify(!$s->data,'Expired receipt discarded');
foreach([true,'forged',['actor'=>'17','version'=>'436','expires_at'=>1300],['actor'=>17,'version'=>436,'expires_at'=>1300],['actor'=>17,'version'=>'436','expires_at'=>'1300']] as $bad){$s->set_userdata('admin_database_upgrade_success',$bad);verify(!AdminDatabaseUpgrade::consumeSuccess($s,17,'436',1001),'Malformed receipt rejected');}
foreach([[0,'436'],[17,'wrong']] as [$actor,$version]){try{AdminDatabaseUpgrade::recordSuccess($s,$actor,$version,1000);throw new RuntimeException('Invalid identity accepted');}catch(InvalidArgumentException $expected){verify(true,'Invalid identity rejected');}}
// Authoritative company-scope attestation is explicit; production Scope is not modified.
final class CompanyRequestScope
{
    public static $owner;
    public static int $actor=17;
    public static function sealedFor($ci):bool{return self::$owner===$ci;}
    public static function context(){return new class {public function staffId(){return CompanyRequestScope::$actor;}};}
}
$ci=(object)['session'=>$s,'input'=>new class {public string $verb='GET';public bool $ajax=false;public function method($upper){return $this->verb;}public function is_ajax_request(){return $this->ajax;}}];
foreach(['HEAD','POST'] as $verb){AdminDatabaseUpgrade::recordSuccess($s,17,'436',time());CompanyRequestScope::$owner=$ci;$ci->input->verb=$verb;verify(!AdminDatabaseUpgrade::consumeContextSuccess($ci,'436'),'Only GET consumes feedback');verify(isset($s->data['admin_database_upgrade_success']),'Other methods preserve receipt');}
$ci->input->verb='GET';$ci->input->ajax=true;verify(!AdminDatabaseUpgrade::consumeContextSuccess($ci,'436'),'Background GET cannot consume feedback');verify(isset($s->data['admin_database_upgrade_success']),'Background GET preserves receipt');$ci->input->ajax=false;CompanyRequestScope::$owner=null;verify(!AdminDatabaseUpgrade::consumeContextSuccess($ci,'436'),'Unattested context cannot consume');verify(isset($s->data['admin_database_upgrade_success']),'Unattested context preserves receipt');CompanyRequestScope::$owner=$ci;CompanyRequestScope::$actor=18;verify(!AdminDatabaseUpgrade::consumeContextSuccess($ci,'436'),'Different scoped staff cannot consume another actor receipt');
AdminDatabaseUpgrade::recordSuccess($s,17,'436',time());CompanyRequestScope::$actor=17;verify(!AdminDatabaseUpgrade::consumeContextSuccess($ci,'435'),'Scoped destination must match persisted version');AdminDatabaseUpgrade::recordSuccess($s,17,'436',time());verify(AdminDatabaseUpgrade::consumeContextSuccess($ci,'436'),'Attested exact GET consumes');verify(!AdminDatabaseUpgrade::consumeContextSuccess($ci,'436'),'Context feedback is single-use');
echo 'Native upgrade actor/version/TTL/single-use feedback '.$checks.' checks passed.'.PHP_EOL;

AdminDatabaseUpgrade::recordSuccess($s,17,'474',time());
$before=$s->data;verify(AdminDatabaseUpgrade::peekContextSuccess($ci,'474'),'Native dashboard previews exact receipt');verify($s->data===$before,'Preview never consumes before render/ACL confirmation');
foreach(['HEAD','POST']as$verb){$ci->input->verb=$verb;verify(!AdminDatabaseUpgrade::peekContextSuccess($ci,'474'),'Non-document preview denied');verify($s->data===$before,'Non-document preview preserves receipt');}
$ci->input->verb='GET';$ci->input->ajax=true;verify(!AdminDatabaseUpgrade::peekContextSuccess($ci,'474'),'XHR preview denied');$ci->input->ajax=false;
CompanyRequestScope::$actor=18;verify(!AdminDatabaseUpgrade::peekContextSuccess($ci,'474'),'Other actor preview denied');CompanyRequestScope::$actor=17;
verify(!AdminDatabaseUpgrade::peekContextSuccess($ci,'473'),'Wrong version preview denied');verify($s->data===$before,'Rejected preview has no session mutation');
verify(AdminDatabaseUpgrade::consumeContextSuccess($ci,'474'),'Confirmed render consumes once');verify(!AdminDatabaseUpgrade::peekContextSuccess($ci,'474'),'Replay has no banner');
echo "Native dashboard feedback read-only preview and final consumption: PASS.\n";

if(!function_exists('db_prefix')){function db_prefix(){return'tbl';}}
final class UpgradeVersionDatabase
{
    public array $rows=[['version'=>'474']];
    public array $queries=[];
    public bool $db_debug=true,$save_queries=true;
    public function query($sql,$params){
        $this->queries[]=[$sql,$params,$this->db_debug,$this->save_queries];
        return new class($this->rows){public function __construct(private array $rows){} public function result_array(){return$this->rows;}};
    }
}
$ci->db=new UpgradeVersionDatabase();
CompanyRequestScope::$owner=$ci;
verify(AdminDatabaseUpgrade::contextVersion($ci)==='474','Native bootstrap reads the persisted migration, even without a controller version property');
verify($ci->db->queries===[['SELECT version FROM tblmigrations LIMIT 2',[],false,false]],'Version lookup is bounded metadata-only and uses protected query execution');
verify($ci->db->db_debug&&$ci->db->save_queries,'Query flags restored after native version read');
$receiptBefore=$s->data;
foreach([[],[['version'=>'']], [['version'=>'not-a-version']], [['version'=>true]], [['version'=>[]]], [['version'=>'474'],['version'=>'475']]]as$rows){
 $ci->db->rows=$rows;
 try{AdminDatabaseUpgrade::contextVersion($ci);throw new RuntimeException('Unconfirmed version accepted');}
 catch(RuntimeException$e){verify($e->getMessage()==='native_upgrade_version_unconfirmed','Missing/invalid/ambiguous migration version rejected');}
 verify($s->data===$receiptBefore,'Version diagnostics do not consume feedback');
}
$beforeQueries=count($ci->db->queries);CompanyRequestScope::$owner=null;
try{AdminDatabaseUpgrade::contextVersion($ci);throw new RuntimeException('Unattested read accepted');}
catch(DomainException$e){verify($e->getMessage()==='native_upgrade_context_denied','Version read requires exact sealed owner');}
verify(count($ci->db->queries)===$beforeQueries,'Unattested caller cannot query migration metadata');
echo "Native upgrade persisted version under sealed owner: PASS.\n";
