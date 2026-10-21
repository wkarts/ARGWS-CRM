<?php
require __DIR__.'/client_profiles.php';
$start=$GLOBALS['essentialChecks'];
require_once APPPATH.'services/utilities/Arr.php';require_once APPPATH.'services/ClientIssuerScope.php';
#[AllowDynamicProperties] class App_Model{}
require APPPATH.'models/Clients_model.php';
class CompanyRequestScope{public static bool $available=true;static function context(){if(!self::$available)return null;return new class{function isPrimary(){return true;}function issuerId(){return (new IssuerResourceAccess($GLOBALS['db'],$GLOBALS['p']))->primaryId();}function staffId(){return 7;}};}}
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
$inferredId=$model->add(['company'=>'Legacy inferred PF','vat'=>'93541134780','brazil_profile'=>['rg'=>'NATIVE-INFERRED']]);
// API/import callers without an ambient company context must still preserve private integrity.
CompanyRequestScope::$available=false;
$inferredBefore=$db->query('SELECT * FROM '.$p.'clients WHERE userid=?',[$inferredId])->result_array();
essentialThrows(fn()=>$model->update(['vat'=>'98989898000177'],$inferredId),'company_client_invalid_input','native API also checks persisted RG when person_type was never explicit');
essentialCheck($db->query('SELECT * FROM '.$p.'clients WHERE userid=?',[$inferredId])->result_array()===$inferredBefore,'native inferred profile mismatch rolls back VAT');
$before=$db->query('SELECT * FROM '.$p.'clients WHERE userid=?',[$id])->result_array();
$privateBefore=$db->query('SELECT * FROM '.$p.'company_client_profiles WHERE client_id=?',[$id])->result_array();
essentialThrows(fn()=>$model->update(['company'=>'Rejected API rename','vat'=>'98989898000177'],$id),'company_client_invalid_input','legacy API VAT update without profile cannot bypass persisted PF classification');
essentialCheck($db->query('SELECT * FROM '.$p.'clients WHERE userid=?',[$id])->result_array()===$before&&$db->query('SELECT * FROM '.$p.'company_client_profiles WHERE client_id=?',[$id])->result_array()===$privateBefore,'legacy API mismatch rolls back the whole native row and preserves private data');
$db->failOn='UPDATE '.$p.'company_client_profiles';
essentialCheck($model->update(['vat'=>'390.533.447-05'],$id)===true,'legacy API accepts normalized VAT without context or private writes');
$db->failOn=null;
essentialCheck($db->query('SELECT * FROM '.$p.'company_client_profiles WHERE client_id=?',[$id])->result_array()===$privateBefore,'legacy API validation preserves exact private profile bytes');
$events=[];hooks()->observer=static function($name,$args)use(&$events){if($name==='client_updated')$events[]=$args;};
$db->failOn='SELECT profile_json';
essentialCheck($model->update(['phonenumber'=>'11999990001'],$id)===true,'legacy API phone-only edit keeps its ordinary boolean result');
$db->failOn=null;hooks()->observer=null;
essentialCheck($db->query('SELECT * FROM '.$p.'company_client_profiles WHERE client_id=?',[$id])->result_array()===$privateBefore&&!str_contains(json_encode($events),'ROLLBACK')&&!str_contains(json_encode($events),'profile_json'),'phone-only API update does not return or publish private profile fields');
$emptyId=$model->add(['company'=>'Legacy API without profile','vat'=>'US-LEGACY']);
essentialCheck($model->update(['vat'=>'US-UNCHANGED'],$emptyId)===true&&$db->query('SELECT * FROM '.$p.'company_client_profiles WHERE client_id=?',[$emptyId])->result_array()===[],'legacy API with no persisted profile retains international VAT support and creates no profile');
// An unrelated issuer's row must not affect an unowned legacy client's primary profile.
$db->query('INSERT INTO '.$p.'company_client_profiles(issuer_id,client_id,profile_json) VALUES(?,?,?)',[82,$id,'{"person_type":"pj","trade_name":"FOREIGN-EXTRA"}']);
essentialCheck($model->update(['vat'=>'39053344705'],$id)===true,'legacy validation resolves primary only for an unowned client and ignores foreign profiles');
$db->query('DELETE FROM '.$p.'company_client_profiles WHERE issuer_id=? AND client_id=?',[82,$id]);
$model->db->rejectUpdate=true;
essentialThrows(fn()=>$model->update(['vat'=>'39053344705'],$id),'issuer_storage_failed','native VAT write failure cannot be reported as successful profile validation');$model->db->rejectUpdate=false;
// The extension remains private to the admin path even when the VAT is compatible.
essentialThrows(fn()=>$model->update(['vat'=>'39053344705','brazil_profile'=>['rg'=>'API-INJECTION']],$id),'company_client_access_denied','API without context cannot use the integrity guard to write private extras');
// Explicit clearing remains the authorized way to change a persisted PF into a PJ.
CompanyRequestScope::$available=true;
$transition=['vat'=>'98989898000177','brazil_profile'=>['person_type'=>'pj','rg'=>'']];
essentialCheck($model->update($transition,$id)===true&&$profile->read($primary,7,$id)['person_type']==='pj'&&$profile->read($primary,7,$id)['rg']==='','legacy explicit clearing permits PF to PJ transition');
CompanyRequestScope::$available=false;
essentialThrows(fn()=>$model->update(['vat'=>'39053344705'],$id),'company_client_invalid_input','legacy reverse transition without profile is also rejected');
// Installations that have not migrated the supplementary table keep their native API.
$db->query('DROP TABLE '.$p.'company_client_profiles');unset($db->data_cache['table_names']);
essentialCheck($model->update(['vat'=>'US-BEFORE-UPGRADE'],$emptyId)===true&&!$db->table_exists($p.'company_client_profiles'),'legacy VAT update without profile schema remains supported without DDL');
echo 'Real legacy client model profile: '.($GLOBALS['essentialChecks']-$start).' added checks ('.$db->driver().").\n";
