<?php
// Actual PHP controller queries with disposable SQLite. No CRM credentials or transport.
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__).'/application/');
class AdminController { public $db; }
class IssuerResourceAccess { public function __construct(...$args){} public function can(...$args){return true;} }
function db_prefix(){return 'tbl';}
require APPPATH.'controllers/admin/Clients.php';
final class RegressionDb {
    public PDO $pdo;
    public function __construct(){ $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    public function table_exists($table){return true;}
    public function query($sql,$params=[]){$q=$this->pdo->prepare($sql);$q->execute($params);return new class($q){public function __construct(private PDOStatement $q){}public function result_array(){return $this->q->fetchAll(PDO::FETCH_ASSOC);}};}
}
$db=new RegressionDb();
$db->pdo->exec('CREATE TABLE tblcompany_recurring(id INTEGER,issuer_id INTEGER,next_date TEXT,state TEXT,created_at TEXT,payload_json TEXT)');
$q=$db->pdo->prepare('INSERT INTO tblcompany_recurring VALUES(?,?,?,?,?,?)');
foreach([[1,7,1],[2,7,12],[3,7,123],[4,8,1]] as [$id,$issuer,$client])$q->execute([$id,$issuer,'2026-11-01','active','2026-10-31',json_encode(['client_id'=>$client],JSON_PRETTY_PRINT)]);
$client=(new ReflectionClass(Clients::class))->newInstanceWithoutConstructor();$client->db=$db;
$method=new ReflectionMethod(Clients::class,'nativeClientSectionData');
foreach([1=>1,12=>2,123=>3] as $id=>$expected){$data=$method->invoke($client,7,9,$id,'subscriptions');if(array_column($data['native_client_section']['rows'],'id')!==[$expected])throw new RuntimeException('Relation must match exact client and issuer, independent of JSON whitespace.');}
$source=file_get_contents(APPPATH.'controllers/admin/Clients.php');
if(!str_contains($source,"if(\$key==='payments')\$data['customer_tabs'][\$key]['view']='admin/clients/groups/native_payments';"))throw new RuntimeException('Payment tab must render its paginated view.');
// Test the production project relation SQL shape on both canonical metadata and near IDs.
$db->pdo->exec('CREATE TABLE tblcompany_sales_documents(id INTEGER,issuer_id INTEGER,kind TEXT,payload_json TEXT)');
$q=$db->pdo->prepare('INSERT INTO tblcompany_sales_documents VALUES(?,?,?,?)');
foreach([[1,7,1],[2,7,12],[3,8,1]] as [$id,$issuer,$project])$q->execute([$id,$issuer,'estimates',json_encode(['native_project'=>['source_kind'=>'company_project','source_id'=>$project]],JSON_PRETTY_PRINT)]);
$rows=$db->query("SELECT id FROM tblcompany_sales_documents WHERE issuer_id=? AND JSON_EXTRACT(payload_json, '$.native_project.source_kind')='company_project' AND JSON_EXTRACT(payload_json, '$.native_project.source_id')=CAST(? AS SIGNED)",[7,1])->result_array();
if(array_column($rows,'id')!==[1])throw new RuntimeException('Project lineage collision.');
echo "PR52 actual PHP controller/exact SQLite relation regression PASS\n";
