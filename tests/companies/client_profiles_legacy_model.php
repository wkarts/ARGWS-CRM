<?php
require __DIR__.'/client_profiles.php';
$start=$GLOBALS['essentialChecks'];
require_once APPPATH.'services/utilities/Arr.php';require_once APPPATH.'services/ClientIssuerScope.php';
#[AllowDynamicProperties] class App_Model{}
require APPPATH.'models/Clients_model.php';
class CompanyRequestScope{static function context(){return new class{function isPrimary(){return true;}function issuerId(){return (new IssuerResourceAccess($GLOBALS['db'],$GLOBALS['p']))->primaryId();}function staffId(){return 7;}};}}
function is_staff_logged_in(){return true;}function is_client_logged_in(){return false;}function get_staff_user_id(){return 7;}function handle_custom_fields_post(...$args){return false;}function do_action_deprecated(...$args){}function log_activity(...$args){}
class ProfileLegacyDb{
    public PDO $pdo;public array $data_cache=[];public string $dbdriver='pdo';public bool $rejectInsert=false,$rejectUpdate=false;
    function __construct(public $inner){$this->pdo=$inner->pdo;$this->data_cache=&$inner->data_cache;}
    function insert(...$args){return $this->rejectInsert?false:$this->inner->insert(...$args);}function update(...$args){return $this->rejectUpdate?false:$this->inner->update(...$args);}
    function __call($name,$args){return $this->inner->$name(...$args);}function affected_rows(){return 1;}
}
foreach(['show_primary_contact','default_currency','billing_country','shipping_country'] as $key)$db->query('ALTER TABLE '.$p.'clients ADD '.$key.' int DEFAULT 0');
$model=(new ReflectionClass(Clients_model::class))->newInstanceWithoutConstructor();$model->db=new ProfileLegacyDb($db);(new ReflectionProperty(Clients_model::class,'contact_columns'))->setValue($model,[]);$model->client_groups_model=new class{function sync_customer_groups($id,$groups){return false;}};
$input=['company'=>'Legacy new synthetic','vat'=>'39053344705','brazil_profile'=>['person_type'=>'pf','rg'=>'PRIVATE-MODEL']];
$id=$model->add($input);$primary=CompanyRequestScope::context()->issuerId();essentialCheck($profile->read($primary,7,$id)['rg']==='PRIVATE-MODEL','real legacy model saves private profile');
essentialCheck(!array_key_exists('rg',(array)$db->query('SELECT * FROM '.$p.'clients WHERE userid=?',[$id])->row()),'no RG column exposed by legacy SELECT star');
$count=$db->query('SELECT COUNT(*) n FROM '.$p.'clients')->row()->n;$db->failOn='INSERT INTO '.$p.'company_client_profiles';
essentialThrows(fn()=>$model->add($input),'issuer_storage_failed','legacy create profile storage failure reported');$db->failOn=null;
essentialCheck($db->query('SELECT COUNT(*) n FROM '.$p.'clients')->row()->n===$count,'legacy create row rolled back with failed profile');
$before=$db->query('SELECT * FROM '.$p.'clients WHERE userid=?',[$id])->result_array();$oldProfile=$profile->read($primary,7,$id);
$db->failOn='UPDATE '.$p.'company_client_profiles';$edit=$input;$edit['company']='Rollback name';$edit['brazil_profile']['rg']='ROLLBACK';
essentialThrows(fn()=>$model->update($edit,$id),'issuer_storage_failed','legacy update profile failure reported');$db->failOn=null;
essentialCheck($db->query('SELECT * FROM '.$p.'clients WHERE userid=?',[$id])->result_array()===$before&&$profile->read($primary,7,$id)===$oldProfile,'legacy update native row and private profile roll back together');
$edit['company']='Edited legacy';essentialCheck($model->update($edit,$id)===true&&$profile->read($primary,7,$id)['rg']==='ROLLBACK','legacy update positive');
essentialCheck($model->update($edit,$pfId)===false,'legacy endpoint still cannot mutate company-owned clients');
$model->db->rejectInsert=true;essentialThrows(fn()=>$model->add($input),'issuer_storage_failed','false native insert rejected without reusing prior insert id');$model->db->rejectInsert=false;
$model->db->rejectUpdate=true;essentialThrows(fn()=>$model->update($edit,$id),'issuer_storage_failed','false native update rejected before private write');$model->db->rejectUpdate=false;
$edit['brazil_profile']['person_type']='pj';essentialThrows(fn()=>$model->update($edit,$id),'company_client_invalid_input','legacy explicit type/document mismatch rejected');
echo 'Real legacy client model profile: '.($GLOBALS['essentialChecks']-$start)." added checks (sqlite).\n";
