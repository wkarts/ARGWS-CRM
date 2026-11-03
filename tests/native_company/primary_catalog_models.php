<?php
/** Actual native Taxes_model/Invoice_items_model, owned transaction and real PDO. CI query builder is a transport fixture. */
require __DIR__.'/primary_announcements.php';
require_once APPPATH.'services/NativeCompanyCatalogUi.php';
final class CatalogModelDb
{
    public PDO $pdo;private array $where=[];
    public function __construct(private $inner){$this->pdo=$inner->pdo;}
    public function __call($name,$args){return$this->inner->$name(...$args);}
    public function insert($table,$values){if(!str_starts_with($table,db_prefix()))$table=db_prefix().$table;return$this->inner->query('INSERT INTO '.$table.'('.implode(',',array_keys($values)).') VALUES('.implode(',',array_fill(0,count($values),'?')).')',array_values($values))!==false;}
    public function list_fields($table):array{return array_column($this->inner->query("PRAGMA table_info(".$table.")")->result_array(),"name");}
    public function where($key,$value):self{$this->where[$key]=$value;return$this;}
    public function get($table){$where=$this->where;$this->where=[];return$this->inner->query('SELECT * FROM '.$table.($where?' WHERE '.implode(' AND ',array_map(static fn($k)=>$k.(preg_match('/[!<>=]$/D',trim($k))?' ?':'=?'),array_keys($where))):''),array_values($where));}
    public function update($table,$values){if(!str_starts_with($table,db_prefix()))$table=db_prefix().$table;$where=$this->where;$this->where=[];return$this->inner->query('UPDATE '.$table.' SET '.implode(',',array_map(static fn($k)=>$k.(preg_match('/[!<>=]$/D',trim($k))?' ?':'=?'),array_keys($values))).' WHERE '.implode(' AND ',array_map(static fn($k)=>$k.(preg_match('/[!<>=]$/D',trim($k))?' ?':'=?'),array_keys($where))),array_merge(array_values($values),array_values($where)))!==false;}
    public function delete($table){$where=$this->where;$this->where=[];return$this->inner->query('DELETE FROM '.$table.' WHERE '.implode(' AND ',array_map(static fn($k)=>$k.(preg_match('/[!<>=]$/D',trim($k))?' ?':'=?'),array_keys($where))),array_values($where))!==false;}
}
$db=new CatalogModelDb($db);$ci->db=$db;$GLOBALS['db']=$db;
$db->query("CREATE TABLE {$p}taxes(id INTEGER PRIMARY KEY,name TEXT,taxrate DECIMAL(10,4))");
$db->query("CREATE TABLE {$p}items_groups(id INTEGER PRIMARY KEY,name TEXT)");
$db->query("CREATE TABLE {$p}items(id INTEGER PRIMARY KEY,description TEXT,tax INT,tax2 INT,group_id INT)");
$db->query("CREATE TABLE {$p}expenses(id INTEGER PRIMARY KEY,tax INT,tax2 INT)");
$db->query("CREATE TABLE {$p}subscriptions(id INTEGER PRIMARY KEY,tax_id INT,tax_id_2 INT)");
if(!$db->table_exists($p.'options'))$db->query("CREATE TABLE {$p}options(name TEXT PRIMARY KEY,value TEXT)");
$db->query("INSERT INTO {$p}options(name,value) VALUES('default_tax',?)",[serialize(['Historical|5'])]);
$db->query("INSERT INTO {$p}taxes VALUES(1,'Historical',5),(2,'Unused',3)");$db->query("INSERT INTO {$p}items_groups VALUES(1,'Historical group')");$db->query("INSERT INTO {$p}items VALUES(1,'Historical item',NULL,NULL,1)");
function total_rows($table,$where=[]){$db=$GLOBALS['CI']->db;return(int)$db->query('SELECT COUNT(*) n FROM '.$table.($where?' WHERE '.implode(' AND ',array_map(static fn($k)=>$k.(preg_match('/[!<>=]$/D',trim($k))?' ?':'=?'),array_keys($where))):''),array_values($where))->row()->n;}
function get_tax_by_id($id){return$GLOBALS['CI']->db->query('SELECT * FROM '.db_prefix().'taxes WHERE id=?',[$id])->row();}
function get_option($key){return$GLOBALS['CI']->db->query('SELECT value FROM '.db_prefix().'options WHERE name=?',[$key])->row()->value;}
function update_option($key,$value){$GLOBALS['CI']->db->query('UPDATE '.db_prefix().'options SET value=? WHERE name=?',[$value,$key]);return true;}
function is_reference_in_table($field,$table,$id){return total_rows($table,[$field=>$id])>0;}
require APPPATH.'models/Taxes_model.php';require APPPATH.'models/Invoice_items_model.php';
$ci->load=new class($ci){public function __construct(private $ci){}public function model($name):void{$this->ci->$name=match($name){'taxes_model'=>new Taxes_model(),'invoice_items_model'=>new Invoice_items_model(),default=>throw new RuntimeException('Unexpected model')};}};
$releaseCatalogSettings=CompanySettingsRuntime::enterRequest($db,$p,$principal,7);
$catalog=new NativeCompanyCatalogUi($db,$p,$principal,7,$ci);$tax=$catalog->taxes()[0];foreach($catalog->taxes()as$r)if($r['id']==='1')$tax=$r;
$catalog->saveTax(['name'=>'Updated','taxrate'=>'6'],'1',$tax['revision']);
essentialCheck(get_tax_by_id(1)->name==='Updated'&&unserialize(get_option('default_tax'))===['Updated|6'],'real native tax model preserves default_tax update contract');
settingThrows(fn()=>$catalog->saveTax(['name'=>'Stale','taxrate'=>'7'],'1',$tax['revision']),'company_catalog_stale','historical tax CAS rejects stale update');
$groups=$catalog->groups();foreach($groups as$r)if($r['id']==='1')$group=$r;$catalog->saveGroup(['name'=>'Updated group'],'1',$group['revision']);
foreach($catalog->groups()as$r)if($r['id']==='1')$group=$r;$catalog->delete('group','1',$group['revision']);
essentialCheck((int)$db->query("SELECT group_id FROM {$p}items WHERE id=1")->row()->group_id===0,'actual native group deletion clears historical membership only');
foreach($catalog->taxes()as$r)if($r['id']==='2')$unused=$r;$catalog->delete('tax','2',$unused['revision']);essentialCheck(!get_tax_by_id(2),'actual native tax deletion preserves reference checks');
foreach($catalog->taxes()as$r)if($r['id']==='1')$current=$r;
$db->query("INSERT INTO {$p}expenses(id,tax) VALUES(1,1)");settingThrows(fn()=>$catalog->saveTax(['name'=>'Used','taxrate'=>'8'],'1',$current['revision']),'company_catalog_referenced','native expense reference veto preserved');$db->query("DELETE FROM {$p}expenses WHERE id=1");
hooks()->add_action('before_update_tax',static function(){throw new RuntimeException('Unreviewed hook executed');});settingThrows(fn()=>$catalog->saveTax(['name'=>'Hook','taxrate'=>'8'],'1',$current['revision']),'native_legacy_hook_review_required','unreviewed hooks cannot mutate another company');hooks()->remove_all_filters('before_update_tax');
if($db->driver()==='sqlite'){
    $db->query("CREATE TRIGGER ignored_catalog_audit BEFORE INSERT ON {$p}issuers_audit BEGIN SELECT RAISE(IGNORE); END");
    settingThrows(fn()=>$catalog->saveTax(['name'=>'Rollback','taxrate'=>'8'],'1',$current['revision']),'issuer_catalog_write_unconfirmed','native model and default options rollback when audit missing');
    essentialCheck(get_tax_by_id(1)->name==='Updated'&&unserialize(get_option('default_tax'))===['Updated|6'],'native tax plus default option remain atomic');$db->query('DROP TRIGGER ignored_catalog_audit');
}
$releaseCatalogSettings();
echo "Principal native tax/default option and group actual CI/App model contracts: passed.\n";
