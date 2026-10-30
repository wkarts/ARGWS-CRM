<?php

declare(strict_types=1);
require_once __DIR__.'/support/TestPdo.php';
define('BASEPATH',dirname(__DIR__).'/system/');
require_once __DIR__.'/../application/services/InvoiceIssuerScope.php';
require_once __DIR__.'/../application/services/ClientIssuerScope.php';
require_once __DIR__.'/../application/services/InvoicePaymentWriter.php';
use app\services\InvoiceIssuerScope;

class App_Model
{
    public $db; public $load; public $clients_model; public $payments_model; public $email_schedule_model; public $payment_modes_model; public $invoices_model; public $session; public $input; public $expenses_model; public $client_groups_model; public $currencies_model;
}
require_once __DIR__.'/../application/models/Invoices_model.php';
require_once __DIR__.'/../application/models/Payments_model.php';
require_once __DIR__.'/../application/helpers/invoices_helper.php';
class CoreIssuerResult
{
    public function __construct(private array $rows) {}
    public function result_array(): array { return $this->rows; }
    public function result(): array { return array_map(fn($row)=>(object)$row,$this->rows); }
    public function row() { return isset($this->rows[0]) ? (object)$this->rows[0] : null; }
}
class CoreIssuerDb
{
    private array $selects=[]; private string $table=''; private array $joins=[]; private array $where=[]; private array $bindings=[]; private array $groups=[]; private int $affected=0;
    public array $queries=[]; public bool $lockAvailable=true;
    public function __construct(public PDO $pdo) { $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    public function table_exists($table): bool {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
            $q=$this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);return (bool)$q->fetch();
        }
        $q=$this->pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);return (bool)$q->fetch();
    }
    public function field_exists($field,$table): bool {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
            $columns=$this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);
            return in_array($field,array_column($columns,'name'),true);
        }
        $q=$this->pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$table,$field]);return (bool)$q->fetch();
    }
    public function query($sql,$bindings=[]): CoreIssuerResult {
        $this->queries[]=[$sql,$bindings];
        if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' && str_contains($sql,'GET_LOCK(')) return new CoreIssuerResult([['acquired'=>$this->lockAvailable?1:0]]);
        if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' && str_contains($sql,'RELEASE_LOCK(')) return new CoreIssuerResult([['released'=>1]]);
        $sql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?str_replace(['CURRENT_DATE - INTERVAL 7 DAY','RIGHT('],["date('now','-7 day')",'RIGHT_FN('],$sql):$sql;
        $q=$this->pdo->prepare($sql);foreach(array_values($bindings) as $i=>$value) $q->bindValue($i+1,$value,is_int($value)?PDO::PARAM_INT:($value===null?PDO::PARAM_NULL:PDO::PARAM_STR));$q->execute();$this->affected=$q->rowCount();
        return new CoreIssuerResult($q->columnCount() ? $q->fetchAll(PDO::FETCH_ASSOC):[]);
    }
    public function escape_like_str($value) { return str_replace(['!','%','_'],['!!','!%','!_'],$this->escape_str($value)); }
    public function like($column,$value) { return $this->where($column." LIKE '%".$this->escape_like_str($value)."%' ESCAPE '!'"); }
    public function limit($count) { return $this; } // Test fixtures stay below every exercised page limit.
    public function where_not_in($column,$values) { return $this->where($column.' NOT IN ('.implode(',',array_map('intval',$values)).')'); }
    public function escape_str($value) { return str_replace("'","''",(string)$value); }
    public function list_fields($table): array {
        if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') return array_column($this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),'name');
        return array_column($this->pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_ASSOC),'Field');
    }
    public function count_all_results($table): int { $this->select('COUNT(*) AS count'); return (int)$this->get($table)->row()->count; }
    public function select($columns='*') { $this->selects[]=$columns;return $this; }
    public function from($table) { $this->table=$table;return $this; }
    public function join($table,$on,$kind='') { $this->joins[]=strtoupper($kind).' JOIN '.$table.' ON '.$on;return $this; }
    public function where($key,$value=null,$escape=null) {
        if (is_array($key)) { foreach($key as $k=>$v) $this->where($k,$v);return $this; }
        if ($value===null) $this->where[]=$key;
        else { $this->where[]=$key.(preg_match('/(?:!=|<>|>=|<=|>|<|=)$/',trim($key))?' ?':' = ?');$this->bindings[]=$value; }
        return $this;
    }
    public function group_start() { $this->where[]='(';return $this; }
    public function group_end() { $this->where[]=')';return $this; }
    public function group_by($column) { $this->groups[]=$column;return $this; }
    public function affected_rows() { return $this->affected; }
    public function update($table,array $data) {
        if(!str_starts_with($table,db_prefix())) $table=db_prefix().$table;
        $sql='UPDATE '.$table.' SET '.implode(',',array_map(fn($key)=>$key.' = ?',array_keys($data)));
        if($this->where) $sql.=' WHERE '.implode(' AND ',$this->where);
        $args=array_merge(array_values($data),$this->bindings);$this->where=[];$this->bindings=[];
        $this->query($sql,$args);return true;
    }
    public function order_by($column,$direction='') { return $this; }
    public function get($table=''): CoreIssuerResult {
        $sql='SELECT '.($this->selects?implode(',',$this->selects):'*').' FROM '.($table?:$this->table).' '.implode(' ',$this->joins);
        if($this->where) $sql.=' WHERE '.str_replace(['( AND ',' AND )'],['(',')'],implode(' AND ',$this->where));
        if($this->groups) $sql.=' GROUP BY '.implode(',',$this->groups);
        $bindings=$this->bindings;$this->groups=[];$this->selects=[];$this->table='';$this->joins=[];$this->where=[];$this->bindings=[];
        return $this->query($sql,$bindings);
    }
}
class CoreIssuerHooks
{
    public $replacement=null;
    public function do_action($name,...$args) {}
    public function apply_filters($name,$value) { return $this->replacement??$value; }
}
class CoreIssuerLoad { public function model($name) {} }
class CoreIssuerClients { public function get($id) { return (object)['company'=>'Synthetic customer']; } }
class CoreIssuerPayments { public function get_invoice_payments($id) { return []; } }
class CoreIssuerEmails { public function get($id,$type) { return null; } }
class CoreIssuerModel extends Invoices_model { public function __construct() {} public function removeForTest($items,$id) { return $this->remove_items($items,$id); } public function saveForTest($items,$id) { return $this->save_items($items,$id); } }
class CorePaymentModel extends Payments_model { public function __construct() {} }
function is_staff_logged_in() { return false; }
function db_prefix() { return $GLOBALS['prefix']; }
function hooks() { return $GLOBALS['hooks']; }
function get_invoice_total_left_to_pay_unused() {} // Real helper is retained; test invoices use no payment hydration for missing rows.
function get_items_by_type($type,$id) { return []; }
function &get_instance() { return $GLOBALS['instance']; }
function get_staff_user_id() { return 1; }
function has_permission($feature,$staff,$capability) { return true; }
function total_rows($table,$where) {
 $db=$GLOBALS['instance']->db;
 if(is_array($where)) { foreach($where as $key=>$value) $db->where($key,$value); return $db->count_all_results($table); }
 return (int)$db->query('SELECT COUNT(*) AS count FROM '.$table.($where?' WHERE '.$where:''))->row()->count;
}
function get_option($name) { return 0; }
function get_decimal_places() { return 2; }
class CoreIssuerCredits { public function get_applied_invoice_credits($id) { return []; } }
$checks=0;
function same($expected,$actual,$label) { global $checks;$checks++;if($expected!==$actual)throw new RuntimeException($label.': '.var_export($actual,true)); }
function denied(callable $fn,$code) { try{$fn();}catch(DomainException $e){same($code,$e->getMessage(),$code);return;}throw new RuntimeException('Expected rejection'); }
$dsn=getenv('ISSUERS_TEST_DSN')?:'sqlite::memory:';
$pdo=TestPdo::connect($dsn,getenv('ISSUERS_TEST_USER')?:null,getenv('ISSUERS_TEST_PASSWORD')?:null);
$db=new CoreIssuerDb($pdo);$prefix='scope_'.bin2hex(random_bytes(4)).'_';$GLOBALS['prefix']=$prefix;$GLOBALS['hooks']=new CoreIssuerHooks();
same(false,$db->field_exists('master_staff_id',$prefix.'issuers_installation_identity'),'legacy fixture without company identity reports missing master field');
$db->query("CREATE TABLE {$prefix}invoices (id INT PRIMARY KEY, issuer_id INT NULL, currency INT, number INT, date VARCHAR(10), clientid INT, total INT, project_id INT, deleted_customer_name VARCHAR(50),hash VARCHAR(50))");
$db->query("CREATE TABLE {$prefix}currencies (id INT PRIMARY KEY,name VARCHAR(20))");
$db->query("INSERT INTO {$prefix}currencies VALUES (1,'BRL')");
$db->query("CREATE TABLE {$prefix}files (id INT PRIMARY KEY,rel_id INT,rel_type VARCHAR(20),visible_to_customer INT)");
$db->query("CREATE TABLE {$prefix}itemable (id INT PRIMARY KEY,rel_id INT,rel_type VARCHAR(20))");
foreach([[1,null],[2,11],[3,22]] as [$id,$issuer]) {
 $db->query("INSERT INTO {$prefix}invoices VALUES (?,?,1,?,'2026-10-18',100,500,0,'','synthetic')",[$id,$issuer,$id]);
 $db->query("INSERT INTO {$prefix}files VALUES (?,?, 'invoice',1)",[$id,$id]);
 $db->query("INSERT INTO {$prefix}itemable VALUES (?,?, 'invoice')",[$id,$id]);
}
foreach([[InvoiceIssuerScope::legacy(),[1]],[InvoiceIssuerScope::forIssuer(11),[2]],[InvoiceIssuerScope::forIssuer(22),[3]]] as [$scope,$expected]) {
 $predicate=$scope->predicate($prefix.'invoices');
 $rows=$db->query('SELECT id FROM '.$prefix.'invoices WHERE '.$predicate['sql'],$predicate['bindings'])->result_array();
 same($expected,array_map('intval',array_column($rows,'id')),'SQL scopes are disjoint');
 foreach([[null,1],[11,2],[22,3]] as [$issuer,$id]) same(in_array($id,$expected,true),$scope->contains(['issuer_id'=>$issuer]),'row membership');
}
same(false,InvoiceIssuerScope::legacy()->contains(['issuer_id'=>0]),'zero is not legacy');
same(false,InvoiceIssuerScope::forIssuer(1)->contains(['issuer_id'=>true]),'boolean is not issuer');
same([],InvoiceIssuerScope::legacyWriteData(['issuer_id'=>null]),'NULL input omitted for compatibility');
foreach([11,0,'',false,'legacy'] as $bad) denied(fn()=>InvoiceIssuerScope::legacyWriteData(['issuer_id'=>$bad]),'invoice_issuer_rollout_blocked');
$model=new CoreIssuerModel();$model->db=$db;$model->load=new CoreIssuerLoad();$model->clients_model=new CoreIssuerClients();$model->payments_model=new CoreIssuerPayments();$model->email_schedule_model=new CoreIssuerEmails();
$GLOBALS['instance']=(object)['invoices_model'=>$model,'db'=>$db,'payments_model'=>$model->payments_model,'credit_notes_model'=>new CoreIssuerCredits(),'load'=>$model->load];
same([1],array_map('intval',array_column($model->get(),'id')),'real core list is legacy-only');
same([1],array_map('intval',array_column($model->get('', $prefix . 'invoices.id=1 OR ' . $prefix . 'invoices.id=2'),'id')),'OR filter cannot escape issuer scope');
same(null,$model->get(2),'real core get blocks issuer X');
same(null,$model->get(3),'real core get blocks issuer Y');
same(1,(int)$model->get(1)->id,'real core get preserves legacy');
foreach([2,3] as $id) {
 same(false,$model->update(['issuer_id'=>null],$id),'update named issuer denied');
 same(false,$model->delete($id),'delete named issuer denied');
 same(false,$model->mark_as_cancelled($id),'cancel named issuer denied');
 same(false,$model->unmark_as_cancelled($id),'uncancel named issuer denied');
 same(false,$model->copy($id),'copy named issuer denied');
 same([],$model->get_invoice_activity($id),'activity is protected');
 same([],$model->get_invoice_recurring_invoices($id),'recurring children protected');
 same([],$model->get_attachments($id),'attachment list protected');
 same(null,$model->get_attachments('',$id),'attachment ID protected');
 same(null,$model->get_invoice_item($id),'item ID protected');
 same(false,user_can_view_invoice($id,1),'global admin invoice view does not bypass scope');
}
same(1,(int)$model->get_invoice_item(1)->id,'legacy item preserved');
same(1,(int)$model->get_attachments('',1)->id,'legacy attachment preserved');
same(true,user_can_view_invoice(1,1),'legacy core permissions preserved');
denied(fn()=>$model->add(['issuer_id'=>11]),'invoice_issuer_rollout_blocked');
denied(fn()=>$model->update(['issuer_id'=>11],1),'invoice_issuer_rollout_blocked');
$GLOBALS['hooks']->replacement=(object)['id'=>2,'issuer_id'=>11];
same(null,$model->get(3),'get_invoice filter cannot reintroduce named issuer');
$GLOBALS['hooks']->replacement=(object)['id'=>2];
same(null,$model->get(1),'get_invoice hook cannot hide ownership field and change identity');
$GLOBALS['hooks']->replacement=null;
$db->query("CREATE TABLE {$prefix}old_invoices (id INT PRIMARY KEY)");$db->query("INSERT INTO {$prefix}old_invoices VALUES (1)");
same('1=1',InvoiceIssuerScope::legacySql($db,$prefix.'old_invoices'),'pre-migration compatibility');
same(true,InvoiceIssuerScope::isLegacyInvoice($db,$prefix.'old_invoices',1),'old invoice remains legacy');
same(3,(int)$pdo->query("SELECT COUNT(*) FROM {$prefix}invoices")->fetchColumn(),'no cross-scope mutation');
// The same real SQL adapter exercises payment writes before any gateway or side effect.
$db->query("CREATE TABLE {$prefix}invoicepaymentrecords(id INT,invoiceid INT,paymentmode VARCHAR(20),amount INT,transactionid VARCHAR(20))");
$db->query("CREATE TABLE {$prefix}payment_modes(id VARCHAR(20),name VARCHAR(20))");
$db->query("INSERT INTO {$prefix}payment_modes VALUES ('manual','Manual')");
foreach([[1,1],[2,2],[3,3],[4,999]] as [$id,$invoiceId]) $db->query("INSERT INTO {$prefix}invoicepaymentrecords VALUES (?,?,'manual',10,'same')",[$id,$invoiceId]);
$payments=new CorePaymentModel();$payments->db=$db;$payments->load=$model->load;$payments->invoices_model=$model;
$payments->payment_modes_model=new class { public function get_payment_gateways($enabled) { return []; } };
$payments->session=new class { public function has_userdata($key) { return false; } };
same(1,(int)$payments->get(1)->paymentid,'legacy payment preserved');
same(true,$payments->transaction_exists('same'),'legacy transaction exists');
foreach([2,3,4] as $id) {
 same(false,$payments->get($id),'payment get before hydration');
 same(false,$payments->update([],$id),'payment update blocked');
 same(false,$payments->delete($id),'payment deletion blocked');
 same(false,$payments->send_invoice_payment_recorded($id,(object)[]),'receipt blocked');
}
foreach([2,3,999] as $id) {
 same([],$payments->get_invoice_payments($id),'payment list blocked');
 same(false,$payments->add(['invoiceid'=>$id]),'payment add blocked');
 same(false,$payments->process_payment(['invoiceid'=>$id,'paymentmode'=>'online']),'gateway not invoked');
 same(false,$payments->transaction_exists('same',$id),'transaction lookup isolated');
 same(0,$payments->add_batch_payment(['invoice'=>[['invoiceid'=>1],['invoiceid'=>$id]]]),'mixed batch rejected before any write');
 same(false,update_invoice_status($id),'status helper rejects hidden invoice');
 same(false,is_payment_mode_allowed_for_invoice('manual',$id),'payment mode helper rejects hidden invoice');
 same(false,found_invoice_mode([],$id),'mode discovery rejects hidden invoice');
 denied(fn()=>get_invoice_total_left_to_pay($id,100),'invoice_issuer_rollout_blocked');
 denied(fn()=>get_invoice_shortlink((object)['id'=>$id,'hash'=>'synthetic']),'invoice_issuer_rollout_blocked');
}
same([],get_invoice_item_taxes(2),'taxes of issuer X hidden');
same([],get_invoice_item_taxes(3),'taxes of issuer Y hidden');
same([], $model->check_for_merge_invoice(100,2),'merge rejects named target');
same(false,$payments->update(['invoiceid'=>2],1),'payment cannot move to another invoice');
same(false,$payments->process_payment(['invoiceid'=>2,'paymentmode'=>'online'],1),'conflicting payment invoice IDs rejected');
$GLOBALS['hooks']->replacement=['invoiceid'=>2,'amount'=>10];
same(false,$payments->add(['invoiceid'=>1,'amount'=>10]),'payment-recorded hook cannot redirect to named issuer');
$GLOBALS['hooks']->replacement=null;
same(4,(int)$pdo->query("SELECT COUNT(*) FROM {$prefix}invoicepaymentrecords")->fetchColumn(),'blocked payment paths did not write');
foreach([2,3,999] as $id) {
 denied(fn()=>$model->add(['invoices_to_merge'=>[$id]]),'invoice_reference_scope_invalid');
 denied(fn()=>$model->add(['is_recurring_from'=>$id]),'invoice_reference_scope_invalid');
 denied(fn()=>$model->update(['invoices_to_merge'=>[$id]],1),'invoice_reference_scope_invalid');
 denied(fn()=>$model->update(['removed_items'=>[$id]],1),'invoice_item_scope_invalid');
 denied(fn()=>$model->update(['items'=>[['itemid'=>$id]]],1),'invoice_item_scope_invalid');
 denied(fn()=>$model->removeForTest([$id],1),'invoice_item_scope_invalid');
 denied(fn()=>$model->saveForTest([['itemid'=>$id]],1),'invoice_item_scope_invalid');
}
// Execute the actual table producers, then run their generated WHERE/JOIN on the fixture.
function staff_can(...$args) { return true; } function staff_cant(...$args) { return false; }
function get_sql_select_client_company() { return db_prefix().'clients.company'; }
function get_table_custom_fields($rel) { return []; } function _l($key,...$args) { return $key; }
function get_client_user_id() { return 100; } function site_url($path) { return $path; } function e($value) { return htmlspecialchars((string)$value); }
class CapturedIssuerTable extends RuntimeException {}
class App_table_filter { public static function new(...$args) { return new self(); } public function __call($name,$args) { return $this; } }
class App_table { public static function find($name) { return $GLOBALS['issuerTable']; } }
class IssuerTableHarness {
 public $ci; private $callback;
 public function outputUsing($callback) { $this->callback=$callback;return $this; }
 public function setRules($rules) { return $this; }
 public function getWhereFromRules() { return 'AND ('.db_prefix().'invoices.id=1 OR '.db_prefix().'invoices.id=2 OR '.db_prefix().'invoices.id=3)'; }
 public function run($path) { $clientid='';$customFieldsColumns=[];include $path;if($this->callback){$fn=$this->callback;$this->callback=null;$fn(['clientid'=>'','customFieldsColumns'=>[]]);} }
}
function data_tables_init($columns,$key,$table,$joins,$where,$extra=[]) {
 $selection=($GLOBALS['tableProjection']??false)?implode(',',$columns):$table.'.'.$key;
 $sql='SELECT '.$selection.' FROM '.$table.' '.implode(' ',$joins).' WHERE 1=1 '.implode(' ',$where);
 $GLOBALS['tableRows']=$GLOBALS['instance']->db->query($sql)->result_array();
 $GLOBALS['tableColumns']=$columns;
 throw new CapturedIssuerTable();
}
$db->query("ALTER TABLE {$prefix}invoices ADD status INT DEFAULT 1");
$db->query("ALTER TABLE {$prefix}invoices ADD recurring INT DEFAULT 1");
$db->query("ALTER TABLE {$prefix}invoices ADD is_recurring_from INT NULL");
$db->query("CREATE TABLE {$prefix}clients(userid INT,company VARCHAR(20))");$db->query("INSERT INTO {$prefix}clients VALUES (100,'Synthetic')");
$db->query("CREATE TABLE {$prefix}projects(id INT,name VARCHAR(20))");
$ci=$GLOBALS['instance'];$ci->input=new class { public function post($key=null) { return null; } };
$table=new IssuerTableHarness();$table->ci=$ci;$GLOBALS['issuerTable']=$table;
// These option sources are separately guarded in Invoices_model; no options are needed for the table fixture.
$ci->invoices_model=new class { public function get_sale_agents() { return []; } public function get_invoices_years() { return []; } };
foreach(['invoices','payments','recurring_invoices'] as $name) {
 try{$table->run(__DIR__.'/../application/views/admin/tables/'.$name.'.php');throw new RuntimeException('Query not captured');}catch(CapturedIssuerTable $captured){}
 same([1],array_map('intval',array_column($GLOBALS['tableRows'],'id')),'actual '.$name.' table excludes named issuers and orphan payments');
}
$lastDateColumn=$GLOBALS['tableColumns'][6];
$db->query("UPDATE {$prefix}invoices SET is_recurring_from=1 WHERE id=2");
$row=$db->query('SELECT '.$lastDateColumn.' FROM '.$prefix.'invoices WHERE id=1')->row();
same(null,$row->last_date,'recurring last child date cannot reveal named child');
ob_start();include __DIR__.'/../application/views/themes/perfex/template_parts/invoices_stats.php';ob_end_clean();
same(1,$total_invoices,'portal total excludes named issuers');same(1,$total_open,'portal open count excludes named issuers');
same(1,get_invoices_percent_by_status(1)['total'],'dashboard status denominator legacy only');
same(1,get_invoices_percent_by_status(1)['total_by_status'],'dashboard numerator legacy only');
$ci->invoices_model=$model;
require_once __DIR__.'/../application/libraries/gdpr/Gdpr_invoices.php';
function get_country($id) { return $id; } function _prepare_items_array_for_export($items,$type) { return $items; }
function get_views_tracking($type,$id) { return []; } function get_tracked_emails($id,$type) { return []; }
$db->query("ALTER TABLE {$prefix}invoices ADD billing_country INT NULL");
$db->query("ALTER TABLE {$prefix}invoices ADD shipping_country INT NULL");
$db->query("CREATE TABLE {$prefix}customfields(id INT,fieldto VARCHAR(20),show_on_client_portal INT,field_order INT)");
$ci->currencies_model=new class { public function get($id) { return (object)['name'=>'BRL']; } };
$export=(new Gdpr_invoices())->export(100);
same([1],array_map('intval',array_column($export,'id')),'actual GDPR export excludes X/Y for shared customer');
same([1],array_map('intval',array_column($export[0]['payments'],'id')),'GDPR payments follow authorized invoice');
foreach([true,1.5,'0','01','999999999999999999999999999999'] as $invalid) {
 try{InvoiceIssuerScope::forIssuer($invalid);throw new RuntimeException('Invalid scope accepted');}
 catch(InvalidArgumentException $expected){same('invoice_issuer_invalid',$expected->getMessage(),'strict explicit scope');}
}
same(false,InvoiceIssuerScope::forIssuer(PHP_INT_MAX)->contains(['issuer_id'=>'999999999999999999999999999999']),'overflow ID cannot match');
echo "Core invoice scope: {$checks} checks passed (".$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)."); explicit X/Y/NULL and real model boundaries.\n";
