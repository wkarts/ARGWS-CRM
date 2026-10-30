<?php
declare(strict_types=1);
define('ASAAS_FIXTURES_ONLY', true);
require __DIR__ . '/asaas_payment_origin_integration.php';
require_once __DIR__ . '/../modules/asaas/libraries/Asaas/AdminSettingsPolicy.php';
require_once __DIR__ . '/../modules/asaas/libraries/Asaas/EnvironmentSettingsSchema.php';
use Asaas\EnvironmentSettings as Profiles;
use Asaas\EnvironmentSettingsSchema as Schema;
use Asaas\AdminSettingsPolicy as Policy;
use Asaas\Sdk\Http\Client;

// Actual native gateway definitions include active/label/default_selected in each profile.
[$gw, $adapter, $db] = fixture();
$definitions = $gw->getSettings();
$names = array_column($definitions, 'name');
check(count(Profiles::DEFAULTS) === 26 && count($names) === 55, '26 complete settings per environment plus two API keys and one selector');
check(count($names) === count(array_unique($names)), 'no duplicate native field names');
foreach (Profiles::DEFAULTS as $field => $default) {
    foreach (Profiles::ENVIRONMENTS as $environment) {
        check(in_array(Profiles::option($field, $environment), $names, true), 'native renderer receives explicit '.$environment.'/'.$field);
    }
    check(!in_array('paymentmethod_asaas_' . $field, $names, true), 'shared operational field removed from renderer');
}
$encrypted = array_values(array_column(array_filter($definitions, fn($d) => $d['encrypted'] ?? false), 'name'));
check($encrypted === ['paymentmethod_asaas_api_key', 'paymentmethod_asaas_api_key_sandbox'], 'existing encrypted credential names unchanged');
foreach ($definitions as $definition) {
    if (in_array($definition['asaas_setting'] ?? '', Profiles::READ_ONLY, true)) {
        check($definition['type'] === 'input' && ($definition['field_attributes']['disabled'] ?? false), 'unsupported/informational options never rendered as enabled radios');
    }
}

// Every field is resolved independently and toggling only changes the selector.
foreach (Profiles::DEFAULTS as $field => $default) {
    profile_option('production', $field, 'production_' . $field);
    profile_option('sandbox', $field, 'sandbox_' . $field);
}
$snapshot = $db->tables['tbloptions'];
foreach (['0' => 'production', '1' => 'sandbox', '0 ' => null, '1' => 'sandbox'] as $selector => $environment) {
    $GLOBALS['options']['paymentmethod_asaas_sandbox'] = (string) $selector;
    if ($environment === null) {
        throws(fn() => $gw->selectedEnvironment(), 'invalid selector is not silently trimmed/defaulted');
        check($gw->getSetting('active') === '0', 'invalid selector keeps framework mode disabled for repair');
        continue;
    }
    foreach (Profiles::DEFAULTS as $field => $default) {
        check($gw->getSetting($field) === $environment . '_' . $field, 'active profile reads '.$field.' from '.$environment);
    }
}
check($db->tables['tbloptions'] === $snapshot, 'switching selector never overwrites either profile');
profile_option('production', 'description', '');
check($gw->getSettingForEnvironment('description', 'production') === '', 'explicit empty is not defaulted');
throws(fn() => $gw->getSettingForEnvironment('unknown', 'sandbox'), 'unknown profile field fails closed');
throws(fn() => $gw->getSettingForEnvironment('description', 'testing'), 'unknown environment fails closed');
throws(fn() => $gw->getSettingForOrigin('description', ['issuer_id'=>91,'provider'=>'asaas','channel'=>'api','environment'=>'sandbox']), 'named company never inherits global profile');

// Additive upgrade, fresh install, replay and failure recovery on the native options contract.
$migration = new FakeDB(); $migration->tables['tbloptions'] = [
    ['name'=>'paymentmethod_asaas_description','value'=>''],
    ['name'=>'paymentmethod_asaas_active','value'=>'1'],
    ['name'=>'paymentmethod_asaas_discount_value','value'=>'0'],
    ['name'=>'paymentmethod_asaas_api_key','value'=>'synthetic_encrypted_do_not_copy'],
    ['name'=>Profiles::option('label','production'),'value'=>'Already configured'],
];
Schema::ensure($migration, 'tbl');
$read = static function ($name) use ($migration) { return $migration->get_where('tbloptions',['name'=>$name])->row_array()['value'] ?? null; };
foreach (Profiles::ENVIRONMENTS as $environment) {
    check($read(Profiles::option('description',$environment)) === '', 'migration retains intentionally empty description');
    check($read(Profiles::option('active',$environment)) === '1', 'migration copies native activation independently');
    check($read(Profiles::option('discount_value',$environment)) === '0', 'migration preserves explicit zero');
    check($read(Profiles::option('fine_value',$environment)) === '0.00', 'missing legacy field uses exact default');
}
check($read(Profiles::option('label','production')) === 'Already configured', 'existing profile never reset');
check($read('paymentmethod_asaas_api_key') === 'synthetic_encrypted_do_not_copy', 'existing cipher untouched');
check(!array_filter($migration->tables['tbloptions'], fn($row) => str_contains($row['name'],'profile_') && str_contains($row['value'],'synthetic_encrypted')), 'schema never copies secrets into profiles');
$snapshot = $migration->tables['tbloptions']; Schema::ensure($migration,'tbl');
check($migration->tables['tbloptions'] === $snapshot, 'replay is insert-only and stable');
$fresh = new FakeDB(); $fresh->tables['tbloptions']=[]; Schema::ensure($fresh,'tbl');
check(count($fresh->tables['tbloptions']) === 53, 'clean install has two full profiles and initialization marker');
$broken = new FakeDB(); $broken->tables['tbloptions']=[]; $broken->failInsert='tbloptions';
throws(fn() => Schema::ensure($broken,'tbl'), 'failed insert prevents successful migration marker');
check($broken->tables['tbloptions']===[], 'failure never claims initialized');
$broken->failInsert=null; Schema::ensure($broken,'tbl');
check(count($broken->tables['tbloptions'])===53, 'retry after failure finishes missing defaults safely');

// Native registration/POST cannot run an untracked migration or default over legacy settings.
function add_option($name, $value = '', $autoload = 1) {
    global $ci, $options;
    if ($ci->db->get_where('tbloptions', ['name'=>$name])->row_array()) return false;
    $ci->db->insert('tbloptions', ['name'=>$name,'value'=>(string)$value,'autoload'=>$autoload]);
    $options[$name]=(string)$value;
    return true;
}
[$gw,$adapter,$db]=fixture();
$db->tables['tbloptions']=[['name'=>'paymentmethod_asaas_active','value'=>'1'],['name'=>'paymentmethod_asaas_description','value'=>'Legacy custom description']];
$ci->input=new class {function get($key){return null;}};
$ci->uri=new class {function segment($key){return null;}};
unset($GLOBALS['options']['paymentmethod_asaas_profile_schema']);
$unmigrated=$db->tables['tbloptions'];
check($gw->initMode([])===[], 'unmigrated gateway is not registered for new operations');
check($db->tables['tbloptions']===$unmigrated, 'registration GET performs no profile/default writes without native migration');
throws(fn()=>Policy::validate(['settings'=>[Profiles::option('active','sandbox')=>'1']]), 'POST blocked until tracked migration has initialized profile schema');
throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original']), 'unmigrated new issuance blocked before provider');
check(Client::$calls===[], 'unmigrated issue does not send API requests');
Schema::ensure($db,'tbl');
$GLOBALS['options']['paymentmethod_asaas_profile_schema']='1';
$modes=$gw->initMode([]);
check(count($modes)===1 && $modes[0]['active']==='1', 'actual native registration activates selected migrated profile');
check($gw->getSettingForEnvironment('description','production')==='Legacy custom description'
    && $gw->getSettingForEnvironment('description','sandbox')==='Legacy custom description', 'native migration copied before registration defaults');
profile_option('production','description','Configured production');
$snapshot=$db->tables['tbloptions'];$gw->initMode([]);
check($db->tables['tbloptions']===$snapshot, 'native reinitialization never resets configured profile');

// Real SQLite storage: the same helper uses CI-compatible queries and a UNIQUE options key.
class ProfileSqliteResult {
    public function __construct(private array $rows) {}
    public function row_array(){return $this->rows[0]??null;}
}
class ProfileSqliteDb {
    public ?int $failAfter=null;
    public function __construct(public PDO $pdo) {}
    public function table_exists($name){$s=$this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$name]);return $s->fetchColumn()!==false;}
    public function field_exists($name,$table){return in_array($name,array_column($this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),'name'),true);}
    public function get_where($table,$where){$s=$this->pdo->prepare('SELECT * FROM '.$table.' WHERE '.implode(' AND ',array_map(fn($name)=>$name.'=?',array_keys($where))));$s->execute(array_values($where));return new ProfileSqliteResult($s->fetchAll(PDO::FETCH_ASSOC));}
    public function insert($table,$row){if($this->failAfter!==null && $this->failAfter--===0)return false;$s=$this->pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')');return $s->execute(array_values($row));}
}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE native_options (id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT UNIQUE NOT NULL,value TEXT NOT NULL,autoload INTEGER NOT NULL DEFAULT 1)');
$sqlite=new ProfileSqliteDb($pdo);
$sqlite->insert('native_options',['name'=>'paymentmethod_asaas_description','value'=>'']);
$sqlite->insert('native_options',['name'=>'paymentmethod_asaas_interest_value','value'=>'2.37']);
$sqlite->failAfter=7;
throws(fn()=>Schema::ensure($sqlite,'native_'),'partial SQL insert failure is reported');
check(!$sqlite->get_where('native_options',['name'=>'paymentmethod_asaas_profile_schema'])->row_array(),'partial SQL migration never marks completion');
$sqlite->failAfter=null;Schema::ensure($sqlite,'native_');
check((int)$pdo->query('SELECT COUNT(*) FROM native_options')->fetchColumn()===55,'SQL retry completes exactly missing options');
foreach(Profiles::ENVIRONMENTS as $environment) {
    check($sqlite->get_where('native_options',['name'=>Profiles::option('description',$environment)])->row_array()['value']==='','SQL persistence preserves empty option');
    check($sqlite->get_where('native_options',['name'=>Profiles::option('interest_value',$environment)])->row_array()['value']==='2.37','SQL persistence copies exact decimal');
}
$sqlBefore=$pdo->query('SELECT * FROM native_options ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);Schema::ensure($sqlite,'native_');
check($pdo->query('SELECT * FROM native_options ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$sqlBefore,'SQL replay leaves values and row identities unchanged');

// POST allowlist and unsupported controls: neither forged scope nor unsupported behavior is saved.
foreach (Profiles::ENVIRONMENTS as $environment) {
    $values=[];
    foreach (Profiles::DEFAULTS as $field=>$default) $values[Profiles::option($field,$environment)]=$default;
    $result=Policy::validate(['settings'=>$values]);
    foreach (Profiles::READ_ONLY as $field) check(!array_key_exists(Profiles::option($field,$environment),$result['settings']), 'read-only payload ignored without clobbering preserved value');
    check(count($result['settings'])===count(Profiles::DEFAULTS)-count(Profiles::READ_ONLY), 'every supported profile field accepted');
}
foreach (['paymentmethod_asaas_profile_testing_active','paymentmethod_asaas_profile_sandbox_unknown','paymentmethod_asaas_debug','paymentmethod_asaas_initialized','paymentmethod_asaas_webhook_secret','paymentmethod_asaas_api_key_company_91'] as $name) {
    throws(fn()=>Policy::validate(['settings'=>[$name=>'1']]), 'unknown/shared/secret-scope injection rejected');
}
foreach ([['active','true'],['active',1],['currencies','USD'],['interest_value','-1'],['fine_value','1e2'],['discount_value','0,20'],['discount_days','1.5'],['diasdevencimento',[]],['label',str_repeat('a',192)],['description',str_repeat('a',501)]] as [$field,$value]) {
    throws(fn()=>Policy::validate(['settings'=>[Profiles::option($field,'production')=>$value]]), 'invalid profile field is rejected: '.$field);
}
$partial=['settings'=>[Profiles::option('description','production')=>'production edited']];
check(Policy::validate($partial)===$partial, 'partial POST never synthesizes missing values for either environment');

// Origin flags and diagnostics remain independent after a selector change.
[$gw,$adapter,$db]=fixture(); known($adapter,$db);
$GLOBALS['options']['paymentmethod_asaas_sandbox']='0';
profile_option('production','delete_charge','0');profile_option('sandbox','delete_charge','1');
Client::$responses=[wallet(),payment(),['id'=>'pay_original','deleted'=>true]];
check(asaas_guard_invoice_mutation(true,1,'delete')===true, 'original sandbox deletion policy wins over inactive production policy');
check(array_unique(array_column(Client::$calls,3))===['sandbox'], 'removal client stays bound to original sandbox');
[$gw,$adapter,$db]=fixture();known($adapter,$db);$GLOBALS['options']['paymentmethod_asaas_sandbox']='0';
profile_option('sandbox','file_log_enabled','1');profile_option('production','file_log_enabled','0');
$GLOBALS['asaas_test_logs']=[];Client::$responses=[wallet(),payment()];$adapter->getPayment('pay_original');
check(count(array_filter($GLOBALS['asaas_test_logs'],fn($row)=>$row[0]==='info'))===1,'request file diagnostic follows origin profile');
$invoke=new ReflectionMethod($gw,'withInvoiceProfile');
$inside=$invoke->invoke($gw,1,function()use($gw){return [$gw->getProvider()->env(),$gw->getSetting('file_log_enabled')];});
check($inside===['sandbox','1'] && $gw->getProvider()->env()==='production', 'bounded invoice scope resolves origin and restores active context');
try {$invoke->invoke($gw,1,static function(){throw new RuntimeException('synthetic');});} catch (RuntimeException $expected) {}
check($gw->getProvider()->env()==='production','exception restores profile context');

// A new payment uses the selected profile, with native activation and BRL gates.
foreach ([['active','0'],['currencies','USD']] as [$field,$value]) {
    [$gw,$adapter,$db]=fixture();profile_option('sandbox',$field,$value);
    throws(fn()=>$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original','billingType'=>'PIX']), 'invalid new operation denied for '.$field);
    check(Client::$calls===[], 'new operation rejected before provider for '.$field);
}
[$gw,$adapter,$db]=fixture();$GLOBALS['options']['paymentmethod_asaas_sandbox']='0';Client::$responses=[wallet(),customer(),payment()];
$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original','billingType'=>'PIX']);
check(array_unique(array_column(Client::$calls,3))===['production'], 'new issuance uses explicitly selected production');
check($adapter->originStore()->byInvoice(1)['environment']==='production', 'new origin stores selected environment immutably');

// The legacy common payment flow creates a boleto-backed Pix even when the boleto link is hidden.
[$gw,$adapter,$db]=fixture();profile_option('sandbox','billet_only','0');profile_option('sandbox','card_only','0');profile_option('sandbox','pix_only','1');
Client::$responses=[wallet(),customer(),payment()];
$adapter->createPayment(['externalReference'=>'invoice_hash','customer'=>'cus_original','billingType'=>'BOLETO']);
check(count(array_filter(Client::$calls,fn($call)=>$call[0]==='POST'))===1,'profile split preserves legacy boleto-backed Pix issuance contract');

// Missing profile rows use the native NULL contract and fall back without TypeError.
[$gw,$adapter,$db]=fixture();
require_once FCPATH.'system/database/DB_result.php';
$nullDb=new class extends FakeDB {
    public function get_where($table,$where){
        if($table==='tbloptions')return new class extends CI_DB_result {public function __construct(){$this->num_rows=0;}};
        return parent::get_where($table,$where);
    }
};
$nullDb->tables=$db->tables;$ci->db=$nullDb;
check($nullDb->get_where('tbloptions',[])->row_array()===null,'real CI result represents an absent option as NULL');
check($gw->getSettingForEnvironment('description','production')===Profiles::DEFAULTS['description'],'gateway missing-row fallback normalizes native NULL');

// A generic hook must leave an active nominal-company context untouched.
[$gw,$adapter,$db]=fixture();
if (!class_exists('CompanySettingsRuntime', false)) {
    class CompanySettingsRuntime {
        public static bool $enabled=true, $primary=false;
        public static function active(){return self::$enabled;}
        public static function isPrimary(){return self::$primary;}
    }
}
$ci->load=new class {public int $calls=0; public function library(...$args){$this->calls++;}};
$ci->asaas_gateway=new class {public int $calls=0; public function getSetting($name){$this->calls++;return '0';}};
$snapshot=$db->tables;
check(asaas_after_invoice_added(91)===91,'legacy invoice hook leaves nominal-company ID unchanged');
check($ci->load->calls===0&&$ci->asaas_gateway->calls===0&&$db->tables===$snapshot&&Client::$calls===[],'nominal hook does not load/read gateway, mutate database or call provider');
CompanySettingsRuntime::$primary=true;asaas_after_invoice_added(91);
check($ci->load->calls===1&&$ci->asaas_gateway->calls===1,'primary context retains native hook dispatch');
CompanySettingsRuntime::$enabled=false;asaas_after_invoice_added(91);
check($ci->load->calls===2&&$ci->asaas_gateway->calls===2,'legacy context retains native hook dispatch');

echo "Asaas independent environment profiles: {$checks} assertions passed; synthetic options/clients only.\n";
