<?php
/** Actual Currencies controller/model + actual CI_Model/App_Model; SQL and HTTP transport are fixtures. */
require __DIR__.'/primary_catalog_models.php';
require APPPATH.'models/Currencies_model.php';
require APPPATH.'services/NativeCurrencyReferenceCatalog.php';
foreach(NativeCurrencyReferenceCatalog::tables($p)as$ref){
    if(!$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$ref['table']])->row_array())$db->query('CREATE TABLE '.$ref['table'].'(id INTEGER PRIMARY KEY,'.$ref['field'].' INT)');
    elseif(!in_array($ref['field'],array_column($db->query('PRAGMA table_info('.$ref['table'].')')->result_array(),'name'),true))$db->query('ALTER TABLE '.$ref['table'].' ADD '.$ref['field'].' INT');
}
$db->query("ALTER TABLE {$p}items ADD rate DECIMAL(15,2)");
$db->query("CREATE TABLE {$p}currencies(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,symbol TEXT,isdefault INT DEFAULT 0,decimal_separator TEXT,thousand_separator TEXT,placement TEXT)");
$db->query("INSERT INTO {$p}currencies(name,symbol,isdefault,decimal_separator,thousand_separator,placement) VALUES('BRL','R$',1,',','.','before')");
$db->query("CREATE TABLE {$p}company_currency_units(currency_id INT PRIMARY KEY,iso_code TEXT,basis TEXT,recorded_at TEXT,source_hash TEXT)");
class AdminController
{
    public $db;public $input;public $load;public $currencies_model;
    public function __construct(){
        $this->db=$GLOBALS['db'];$GLOBALS['CI']=$this;
        $this->input=new class{public array $data=[];public function post($key=null){return$key===null?$this->data:($this->data[$key]??null);}public function is_ajax_request():bool{return true;}};
        $this->load=new class($this){public function __construct(private $ci){}public function model($name):void{if($name!=='currencies_model')throw new RuntimeException('Unexpected model');$this->ci->currencies_model=new Currencies_model();}public function view($path,$data):void{$GLOBALS['currencyView']=[$path,$data];}};
    }
}
function _l($key,$args=null){return $key;}
function is_admin(){return true;}
function redirect($url,$method=null,$status=null):void{$GLOBALS['currencyRedirect']=$url;}
function set_alert($type,$message):void{$GLOBALS['currencyAlert']=[$type,$message];}
function admin_url($path=''){return'https://synthetic.invalid/admin/'.$path;}
require APPPATH.'controllers/admin/Currencies.php';
$releaseCurrencySettings=CompanySettingsRuntime::enterRequest($db,$p,$principal,7);
$c=new Currencies();$input=['currencyid'=>'','name'=>'USD','symbol'=>'$','placement'=>'before','decimal_separator'=>'.','thousand_separator'=>','];
$c->input->data=$input;ob_start();$c->manage();$response=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
essentialCheck($response['success']===true,'actual model creates currency through owner POST controller');
$id=(int)$db->query("SELECT id FROM {$p}currencies WHERE name='USD'")->row()->id;
$db->query("CREATE TRIGGER ignored_currency BEFORE INSERT ON {$p}currencies BEGIN SELECT RAISE(IGNORE); END");settingThrows(fn()=>$c->manage(),'issuer_currency_write_unconfirmed','ignored create cannot reuse previous currency ID');$db->query('DROP TRIGGER ignored_currency');

essentialCheck($db->field_exists('rate_currency_'.$id,$p.'items')&&!$db->pdo->inTransaction(),'DDL preparation completed outside owned transaction');
$c->input->data=['draw'=>'1','start'=>'0','length'=>'1','search'=>['value'=>'USD'],'order'=>[['column'=>'0','dir'=>'asc']]];$c->index();$render=$GLOBALS['currencyView'];essentialCheck($render[0]==='admin/tables/currencies'&&count($render[1]['native_currency_rows'])===1&&$render[1]['native_currency_total']===2,'same table receives actual public catalogue pagination');
$c->input->data=array_replace($input,['currencyid'=>(string)$id,'symbol'=>'US$']);ob_start();$c->manage();$edited=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);essentialCheck($edited['success']===true&&$db->query("SELECT symbol FROM {$p}currencies WHERE id=?",[$id])->row()->symbol==='US$','actual currency model edit works under settings lease');
$db->query("CREATE TRIGGER ignore_old_default BEFORE UPDATE ON {$p}currencies WHEN OLD.isdefault=1 AND NEW.isdefault=0 BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$c->make_base_currency($id),'issuer_currency_write_unconfirmed','ignored second default update cannot leave two defaults');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}currencies WHERE isdefault=1")->row()->n===1&&(int)$db->query("SELECT id FROM {$p}currencies WHERE isdefault=1")->row()->id===1,'failed default transition rolled back first update');$db->query('DROP TRIGGER ignore_old_default');
$c->make_base_currency($id);essentialCheck((int)$db->query("SELECT id FROM {$p}currencies WHERE isdefault=1")->row()->id===$id,'native base currency change keeps single base');
$c->delete($id);essentialCheck($GLOBALS['currencyAlert'][0]==='warning','native default deletion veto retained');
$c->make_base_currency(1);$db->query('UPDATE '.$p.'items SET rate_currency_'.$id.'=? WHERE id=1',['2.50']);$c->delete($id);essentialCheck($GLOBALS['currencyAlert'][0]==='warning'&&$db->query("SELECT id FROM {$p}currencies WHERE id=?",[$id])->row_array(),'historical price prevents currency deletion');
$db->query('UPDATE '.$p.'items SET rate_currency_'.$id.'=NULL WHERE id=1');$c->delete($id);essentialCheck(!$db->query("SELECT id FROM {$p}currencies WHERE id=?",[$id])->row_array()&&$db->field_exists('rate_currency_'.$id,$p.'items'),'unused currency deleted while historical column preserved');
$releaseCurrencySettings();echo "Actual native currency controller/model/bootstrap CRUD/base/history preservation: passed.\n";
