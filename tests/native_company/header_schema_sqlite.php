<?php
declare(strict_types=1);
require_once __DIR__.'/../../application/services/NativeCompanyHeaderSchema.php';
final class HeaderPhysicalRows{function __construct(private array$r){}function result_array(){return$this->r;}function row_array(){return$this->r[0]??[];}}
final class HeaderPhysicalDb{public PDO$pdo;function __construct(){$this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->pdo->exec('PRAGMA foreign_keys=ON');foreach(['issuers_registry'=>'id','staff'=>'staffid','contacts'=>'id']as$t=>$key)$this->pdo->exec('CREATE TABLE tbl'.$t.'('.$key.' INTEGER PRIMARY KEY)');}function query($sql,$args=[]){$s=$this->pdo->prepare($sql);$s->execute($args);return new HeaderPhysicalRows($s->columnCount()?$s->fetchAll(PDO::FETCH_ASSOC):[]);}}
$checks=0;function hp(bool$v){global$checks;if(!$v)throw new RuntimeException('header physical metadata assertion');$checks++;}
$db=new HeaderPhysicalDb;NativeCompanyHeaderSchema::ensure($db,'tbl');NativeCompanyHeaderSchema::assertValid($db,'tbl');$before=$db->query('SELECT name,sql FROM sqlite_master ORDER BY name')->result_array();NativeCompanyHeaderSchema::ensure($db,'tbl');hp($before===$db->query('SELECT name,sql FROM sqlite_master ORDER BY name')->result_array());
foreach([
 static fn($db)=>$db->query('ALTER TABLE tblcompany_native_notifications ADD COLUMN unexpected TEXT'),
 static fn($db)=>$db->query('ALTER TABLE tblcompany_native_search_history RENAME COLUMN query_text TO old_query'),
 static fn($db)=>$db->query('DROP INDEX tblcompany_native_notifications_notification_contact_actor'),
 static fn($db)=>$db->query('CREATE INDEX extra_header_idx ON tblcompany_native_notifications(body)'),
 static fn($db)=>$db->query('CREATE INDEX extra_header_partial ON tblcompany_native_notifications(body) WHERE issuer_id=2'),
 static fn($db)=>$db->query('CREATE INDEX extra_header_expression ON tblcompany_native_notifications(lower(body))'),
]as$damage){$db=new HeaderPhysicalDb;NativeCompanyHeaderSchema::ensure($db,'tbl');$damage($db);$snapshot=$db->query('SELECT name,sql FROM sqlite_master ORDER BY name')->result_array();try{NativeCompanyHeaderSchema::ensure($db,'tbl');throw new LogicException('corrupt schema accepted');}catch(RuntimeException$e){hp($e->getMessage()==='issuer_native_header_schema_invalid');}hp($snapshot===$db->query('SELECT name,sql FROM sqlite_master ORDER BY name')->result_array());}
$db=new HeaderPhysicalDb;NativeCompanyHeaderSchema::ensure($db,'tbl');$sql=$db->query("SELECT sql FROM sqlite_master WHERE name='tblcompany_native_notifications'")->row_array()['sql'];$indices=$db->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='tblcompany_native_notifications' AND sql IS NOT NULL")->result_array();$db->query('DROP TABLE tblcompany_native_notifications');$db->query(str_replace('REFERENCES `tblcontacts` (`id`)','REFERENCES `tblcontacts` (`id`) ON DELETE CASCADE',$sql));foreach($indices as$i)$db->query($i['sql']);try{NativeCompanyHeaderSchema::assertValid($db,'tbl');throw new LogicException('cascade accepted');}catch(RuntimeException$e){hp($e->getMessage()==='issuer_native_header_schema_invalid');}
foreach([
 ['`actor_kind` varchar(16) NOT NULL','`actor_kind` TEXT NOT NULL'],
 ['`issuer_id` int NOT NULL','`issuer_id` int NULL'],
 ['`read_at` datetime NULL','`read_at` datetime NULL DEFAULT CURRENT_TIMESTAMP'],
 ['`resource_id` bigint NOT NULL','`resource_id` int NOT NULL'],
 ['INTEGER PRIMARY KEY AUTOINCREMENT','INTEGER PRIMARY KEY'],
]as[$from,$to]){
 $db=new HeaderPhysicalDb;NativeCompanyHeaderSchema::ensure($db,'tbl');$sql=$db->query("SELECT sql FROM sqlite_master WHERE name='tblcompany_native_notifications'")->row_array()['sql'];$indices=$db->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='tblcompany_native_notifications' AND sql IS NOT NULL")->result_array();if(!str_contains($sql,$from))throw new LogicException('negative did not alter schema');$db->query('DROP TABLE tblcompany_native_notifications');$db->query(str_replace($from,$to,$sql));foreach($indices as$i)$db->query($i['sql']);try{NativeCompanyHeaderSchema::assertValid($db,'tbl');throw new LogicException('corrupt column accepted');}catch(RuntimeException$e){hp($e->getMessage()==='issuer_native_header_schema_invalid');}
}
echo "Header physical SQLite schema: $checks checks passed (replay, columns, exact indexes, expression/partial indexes, foreign delete policy, no repair). Not MySQL metadata proof.\n";
