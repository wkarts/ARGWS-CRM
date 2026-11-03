<?php
/** Real authentication model regression, synthetic SQLite data and no external side effects.
 * Fixture collation implements ASCII case folding only; it is not a Unicode proof.
 * SQL identity/collation/index concurrency is covered separately on MySQL/MariaDB.
 */
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__) . '/application/');
require_once __DIR__ . '/support/TestPdo.php';
#[AllowDynamicProperties] class App_Model {}
class StaffAuthResult {
    public function __construct(private array $rows) {}
    public function result_array() { return $this->rows; }
    public function row() { return isset($this->rows[0]) ? (object)$this->rows[0] : null; }
}
class StaffAuthDb {
    public PDO $pdo;
    public string $dbdriver = 'sqlite';
    public bool $db_debug = false, $save_queries = false;
    private array $where = [], $values = [];
    private int $affected = 0;
    public function __construct() { $this->pdo = TestPdo::connect('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); TestPdo::registerSqliteCollation($this->pdo, 'utf8mb4_unicode_ci', static fn($a,$b)=>strcasecmp($a,$b)); }
    public function query($sql, $params=[]) {
        $st = $this->pdo->prepare($sql); $st->execute($params); $this->affected=$st->rowCount();
        return new StaffAuthResult($st->columnCount() ? $st->fetchAll(PDO::FETCH_ASSOC) : []);
    }
    public function where($key,$value) { $this->where[$key]=$value; return $this; }
    public function set($key,$value) { $this->values[$key]=$value; return $this; }
    public function get($table) {
        $where=$this->where; $this->where=[];
        return $this->query('SELECT * FROM '.$table.($where?' WHERE '.implode(' AND ',array_map(fn($k)=>$k.'=?',array_keys($where))):''),array_values($where));
    }
    public function update($table,$values=[]) {
        $values=array_merge($this->values,$values); $where=$this->where; $this->values=[];$this->where=[];
        $this->query('UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($values))).' WHERE '.implode(' AND ',array_map(fn($k)=>$k.'=?',array_keys($where))),array_merge(array_values($values),array_values($where))); return true;
    }
    public function affected_rows() { return $this->affected; }
}
class StaffAuthSession {
    public array $data=[];
    public function userdata($key) { return $this->data[$key]??null; }
    public function set_userdata($values,$value=null) { if(is_array($values))$this->data=array_merge($this->data,$values); else $this->data[$values]=$value; }
    public function has_userdata($key) { return array_key_exists($key,$this->data); }
    public function unset_userdata($keys) { foreach((array)$keys as$key)unset($this->data[$key]); }
}
function db_prefix(){return 'auth_';}
function app_hasher(){return new class {function CheckPassword($input,$hash){return hash_equals($hash,hash('sha256',$input));}};}
function app_generate_hash(){return 'synthetic-reset-key';}
function log_activity($message){}
function hooks(){return new class {function do_action($tag,...$args){if(isset($GLOBALS['authHook']))($GLOBALS['authHook'])($tag);}};}
function send_mail_template(...$args){$GLOBALS['authMails']++;return true;}
$GLOBALS['authMails']=0;
function is_logged_in(){return false;}
function is_vendor_logged_in(){return false;}
function is_staff_logged_in(){return false;}
function get_cookie(...$args){return $GLOBALS['authCookie']??null;}
function delete_cookie(...$args){$GLOBALS['authDeletedCookie']=true;}
function set_cookie(...$args){$GLOBALS['authRenewedCookie']=true;}

require APPPATH.'models/Authentication_model.php';
require dirname(__DIR__).'/modules/timesheets/models/Timesheets_model.php';
require dirname(__DIR__).'/modules/purchase/models/Authentication_vendor_model.php';
$db=new StaffAuthDb();
$db->query('CREATE TABLE auth_staff (staffid INTEGER PRIMARY KEY,email TEXT,firstname TEXT DEFAULT "Synthetic",lastname TEXT DEFAULT "Staff",password TEXT,active INTEGER,two_factor_auth_enabled INTEGER,two_factor_auth_code TEXT,two_factor_auth_code_requested TEXT,new_pass_key TEXT,new_pass_key_requested TEXT,last_ip TEXT,last_login TEXT)');
function seedAuth($db,$id,$email,$active=1,$factor=0){$db->query('INSERT INTO auth_staff(staffid,email,password,active,two_factor_auth_enabled,two_factor_auth_code,two_factor_auth_code_requested,new_pass_key,new_pass_key_requested) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$email,hash('sha256','synthetic-password'),$active,$factor,'synthetic-code',date('Y-m-d H:i:s'),'legacy-key',date('Y-m-d H:i:s')]);}
seedAuth($db,11,'person@example.invalid');
$model=(new ReflectionClass(Authentication_model::class))->newInstanceWithoutConstructor();
$model->db=$db;$model->session=new StaffAuthSession();$model->input=new class {function ip_address(){return '127.0.0.1';}};
$count=0;function authCheck($ok,$label){global$count;if(!$ok)throw new RuntimeException('FAILED '.$label);$count++;}
authCheck($model->login(' PERSON@example.invalid ','synthetic-password',false,true)===true,'canonical login succeeds');
authCheck($model->session->userdata('staff_user_id')===11,'correct unique staffid');
$model->session->data=[];
seedAuth($db,12,'PERSON@example.invalid',0);
authCheck($model->login('person@example.invalid','synthetic-password',false,true)===false,'inactive duplicate still ambiguous');
authCheck(!$model->session->userdata('staff_logged_in'),'ambiguous login creates no session');
authCheck($model->forgot_password('person@example.invalid',true)===false,'ambiguous recovery denied');
authCheck($GLOBALS['authMails']===0,'no ambiguous recovery email');
authCheck($model->can_reset_password(true,11,'legacy-key')===false,'old reset token cannot bypass duplicate');
authCheck($model->can_set_password(true,11,'legacy-key')===false,'old set token cannot bypass duplicate');
$db->query('DELETE FROM auth_staff WHERE staffid=12');
authCheck($model->can_reset_password(true,11,'legacy-key')===true,'unique reset token accepted');
authCheck($model->can_set_password(true,11,'legacy-key')===true,'unique set token accepted');
authCheck($model->forgot_password('person@example.invalid',true)===true,'unique recovery works');
authCheck($GLOBALS['authMails']===1,'one synthetic recovery effect');
$db->query('UPDATE auth_staff SET two_factor_auth_enabled=1 WHERE staffid=11');
$result=$model->login('PERSON@example.invalid','synthetic-password',false,true);
authCheck(is_array($result)&&!empty($result['two_factor_auth']),'2FA begins without login');
authCheck($model->session->userdata('tfa_staffid')===11,'challenge bound to staffid');
authCheck($model->session->userdata('_two_factor_auth_staff_email')==='person@example.invalid','challenge uses stored email');
authCheck($model->is_two_factor_code_valid('synthetic-code','person@example.invalid')===true,'unique challenge validates');
seedAuth($db,12,'other@example.invalid');
$model->session->set_userdata('tfa_staffid',12);
authCheck($model->pending_staff_identity()===null,'swapped staffid denied');
authCheck($model->is_two_factor_code_valid('synthetic-code','person@example.invalid')===false,'matching code cannot override challenge identity');
$model->session->set_userdata('tfa_staffid',11);
$db->query('UPDATE auth_staff SET email=? WHERE staffid=12',['PERSON@example.invalid']);
authCheck($model->pending_staff_identity()===null,'duplicate introduced after challenge denied');
authCheck($model->two_factor_auth_login($result['user'])===false,'2FA completion denies new ambiguity');
$db->query('UPDATE auth_staff SET email=? WHERE staffid=12',['other@example.invalid']);
authCheck($model->get_user_by_two_factor_auth_code('synthetic-code')->staffid===11,'same code on other staff does not change subject');
authCheck($model->get_user_by_two_factor_auth_code('')===null,'empty code denied');
authCheck($model->get_user_by_two_factor_auth_code('bad')===null,'wrong code denied');
authCheck($model->two_factor_auth_login($result['user'])===true,'unique challenge completes');
authCheck($model->session->userdata('staff_user_id')===11,'2FA correct identity');
authCheck(!$model->session->userdata('tfa_staffid'),'challenge cleared after success');
$model->session->data=[];$db->query('UPDATE auth_staff SET two_factor_auth_enabled=0 WHERE staffid=11');
$GLOBALS['authHook']=function($tag)use($db){if($tag==='before_staff_login')$db->query('UPDATE auth_staff SET email=? WHERE staffid=12',['person@example.invalid']);};
authCheck($model->login('person@example.invalid','synthetic-password',false,true)===false,'post-hook duplicate denies login');
authCheck(!$model->session->userdata('staff_logged_in'),'post-hook duplicate creates no session');
unset($GLOBALS['authHook']);
$timesheets=(new ReflectionClass(timesheets_model::class))->newInstanceWithoutConstructor();
$timesheets->db=$db;$timesheets->session=new StaffAuthSession();
$timesheets->load=new class {function model($name){}};
$timesheets->staff_model=new class {function get_staff_permissions($id){return [];}};
authCheck($timesheets->login('person@example.invalid','synthetic-password')===false,'timesheets denies duplicate');
authCheck(!$timesheets->session->userdata('staff_logged_in'),'timesheets no ambiguous session');
$db->query('UPDATE auth_staff SET email=? WHERE staffid=12',['other@example.invalid']);
$db->query('ALTER TABLE auth_staff ADD staff_email_identity TEXT');
$db->query('UPDATE auth_staff SET staff_email_identity=LOWER(email)');
$ts=$timesheets->login('PERSON@example.invalid','synthetic-password');
authCheck(is_array($ts)&&$ts['staffid']===11,'timesheets unique canonical login');
authCheck($ts['full_name']==='Synthetic Staff','timesheets API full_name contract preserved');
authCheck(!array_key_exists('staff_email_identity',$ts),'timesheets real login strips generated metadata from public result');
$vendor=(new ReflectionClass(Authentication_vendor_model::class))->newInstanceWithoutConstructor();
$vendor->load=new class {function model($name){}};$vendor->Authentication_model=$model;
$db->query('UPDATE auth_staff SET email=? WHERE staffid=12',['person@example.invalid']);
authCheck($vendor->login('person@example.invalid','synthetic-password',false,true)===false,'purchase staff login uses same ambiguity guard');
authCheck($vendor->forgot_password('person@example.invalid',true)===false,'purchase staff recovery uses same guard');
authCheck($vendor->can_reset_password(true,11,'synthetic-reset-key')===false,'purchase staff existing token uses same guard');
$model->session->data=[];
$model->load=new class {function helper($name){}};
$model->user_autologin=new class {function get(...$args){return (object)['id'=>11,'staff'=>1];}};
$GLOBALS['authCookie']=json_encode(['user_id'=>11,'key'=>'synthetic-cookie-key']);
$GLOBALS['authDeletedCookie']=false;$GLOBALS['authRenewedCookie']=false;
authCheck($model->autologin()===false,'core cookie denies duplicate identity');
authCheck(!$model->session->userdata('staff_logged_in')&&$GLOBALS['authDeletedCookie']&&!$GLOBALS['authRenewedCookie'],'core rejected cookie cleared without renewal');
$db->query('UPDATE auth_staff SET email=? WHERE staffid=12',['other@example.invalid']);
authCheck($model->autologin()===true,'core unique cookie remains supported');
authCheck($model->session->userdata('staff_user_id')===11,'core cookie retains staffid');
$vendor->db=$db;$vendor->session=new StaffAuthSession();$vendor->input=$model->input;
$vendor->load=new class {function model($name){} function helper($name){}};
$vendor->user_autologin=$model->user_autologin;
$GLOBALS['authCookie']=serialize(['user_id'=>11,'key'=>'synthetic-cookie-key']);
$db->query('UPDATE auth_staff SET email=? WHERE staffid=12',['person@example.invalid']);
authCheck($vendor->autologin()===false,'purchase cookie denies duplicate identity');
authCheck(!$vendor->session->userdata('staff_logged_in'),'purchase rejected cookie creates no session');
$db->query('UPDATE auth_staff SET email=? WHERE staffid=12',['other@example.invalid']);
authCheck($vendor->autologin()===true,'purchase unique staff cookie remains supported');
authCheck($vendor->session->userdata('staff_user_id')===11,'purchase cookie retains staffid');
echo 'Staff email authentication: '.$count.' checks passed (SQLite, synthetic effects; not MySQL collation/concurrency proof).'.PHP_EOL;
