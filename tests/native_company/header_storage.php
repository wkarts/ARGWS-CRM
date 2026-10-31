<?php
declare(strict_types=1);
require_once __DIR__.'/../../application/services/NativeCompanyNotifications.php';
require_once __DIR__.'/../../application/services/NativeCompanySearchHistory.php';
function hc(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
final class HeaderRows{public function __construct(private array $rows){}public function result_array():array{return $this->rows;}public function row_array():array{return $this->rows[0]??[];}public function row():?object{return isset($this->rows[0])?(object)$this->rows[0]:null;}}
final class HeaderDb{
 public PDO $pdo;public bool $db_debug=true,$save_queries=true;
 public function __construct(){$this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}
 public function query($sql,$params=[]){$s=$this->pdo->prepare(str_replace(' FOR UPDATE','',$sql));$s->execute($params);return new HeaderRows($s->columnCount()?$s->fetchAll(PDO::FETCH_ASSOC):[]);}
 public function table_exists($table){return (bool)$this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$table])->row_array();}
 public function field_exists($field,$table){foreach($this->query('PRAGMA table_info('.$table.')')->result_array() as $r)if($r['name']===$field)return true;return false;}
 public function trans_begin(){return $this->pdo->beginTransaction();}public function trans_commit(){return $this->pdo->commit();}public function trans_rollback(){return $this->pdo->rollBack();}public function trans_status(){return true;}
}
$db=new HeaderDb();
foreach([
 'issuers_lock'=>'id INTEGER PRIMARY KEY',
 'issuers_registry'=>'id INTEGER PRIMARY KEY,active INTEGER',
 'staff'=>'staffid INTEGER PRIMARY KEY,active INTEGER,admin INTEGER',
 'issuers_staff'=>'issuer_id INTEGER,staff_id INTEGER,can_view INTEGER',
 'issuers_installation_identity'=>'id INTEGER PRIMARY KEY,primary_issuer_id INTEGER,master_staff_id INTEGER',
 'issuers_resource_permissions'=>'issuer_id INTEGER,staff_id INTEGER,feature TEXT,capability TEXT',
 'company_announcements'=>'issuer_id INTEGER,announcementid INTEGER,actor_id INTEGER,name TEXT,message TEXT,showtostaff INTEGER,revision INTEGER',
 'company_reminders'=>'id INTEGER,issuer_id INTEGER,staff INTEGER',
 'company_native_notifications'=>'id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER,recipient_id INTEGER,actor_kind TEXT,actor_id INTEGER,contact_actor_id INTEGER,source_kind TEXT,source_id INTEGER,resource_id INTEGER,event_key TEXT,title TEXT,body TEXT,created_at TEXT,read_at TEXT,inline_read_at TEXT,UNIQUE(issuer_id,recipient_id,event_key)',
 'company_native_search_history'=>'id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER,staff_id INTEGER,query_text TEXT,revision INTEGER,created_at TEXT,updated_at TEXT,UNIQUE(issuer_id,staff_id,query_text)',
] as $table=>$ddl)$db->query('CREATE TABLE tbl'.$table.' ('.$ddl.')');
$db->query('INSERT INTO tblissuers_lock VALUES(1)');$db->query('INSERT INTO tblissuers_registry VALUES(2,1),(3,1)');$db->query('INSERT INTO tblstaff VALUES(17,1,0),(18,1,0)');$db->query('INSERT INTO tblissuers_staff VALUES(2,17,1),(2,18,1),(3,18,1)');$db->query('INSERT INTO tblissuers_installation_identity VALUES(1,2,NULL)');
$db->query("INSERT INTO tblcompany_announcements VALUES(2,1,17,'<title>','<b>Owned A</b>',1,1),(3,2,18,'B','Private B',1,1)");
$n=new NativeCompanyNotifications($db,'tbl');
try{$n->recordInOwnedTransaction(2,17,18,'announcement',1);throw new LogicException('unowned accepted');}catch(RuntimeException $e){hc($e->getMessage()==='issuer_owned_transaction_required','owned required');}
$tables=NativeCompanyNotifications::tables('announcement');
$id=IssuerTransaction::run($db,'tbl',$tables,fn()=>$n->recordInOwnedTransaction(2,17,18,'announcement',1));
$again=IssuerTransaction::run($db,'tbl',$tables,fn()=>$n->recordInOwnedTransaction(2,17,18,'announcement',1));hc($id===$again,'idempotent event');
$r=$n->read(2,18);hc($r['unread']===1&&count($r['items'])===1&&$r['items'][0]['body']==='Owned A','owned event read');hc($n->read(3,18)['items']===[],'cross issuer no disclosure');hc($r['items'][0]['url']==='announcements/view/'.NativeCompanyResourceId::encode('company_announcement',1),'announcement disjoint native identity');
try{IssuerTransaction::run($db,'tbl',$tables,fn()=>$n->recordInOwnedTransaction(3,18,17,'announcement',2));throw new LogicException('cross recipient accepted');}catch(DomainException $e){}
$db->query("UPDATE tblcompany_announcements SET name='Changed without revision' WHERE announcementid=1");try{IssuerTransaction::run($db,'tbl',$tables,fn()=>$n->recordInOwnedTransaction(2,17,18,'announcement',1));throw new LogicException('conflict accepted');}catch(DomainException $e){hc($e->getMessage()==='issuer_notification_event_conflict','event conflict');}
$before=$db->query('SELECT revision FROM tblcompany_announcements WHERE announcementid=1')->row_array()['revision'];
try{IssuerTransaction::run($db,'tbl',$tables,function()use($db,$n){$db->query('UPDATE tblcompany_announcements SET revision=revision+1 WHERE announcementid=1');$n->recordInOwnedTransaction(2,17,18,'announcement',1);throw new DomainException('fixture_source_rollback');});}catch(DomainException $e){hc($e->getMessage()==='fixture_source_rollback','rollback fixture');}
hc($db->query('SELECT revision FROM tblcompany_announcements WHERE announcementid=1')->row_array()['revision']===$before,'source rolled back with event');hc(count($n->read(2,18)['items'])===1,'event rolled back with source');
$n->markRead(2,18,$id,'seen');$row=$n->read(2,18)['items'][0];hc($row['read_at']!==null&&$row['inline_read_at']===null,'badge separate from inline');
$n->markRead(2,18,$id,'inline');hc($n->read(2,18)['items'][0]['inline_read_at']!==null,'inline read persisted');
$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=18');try{$n->read(2,18);throw new LogicException('revoked accepted');}catch(DomainException $e){}$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=18');
$h=new NativeCompanySearchHistory($db,'tbl');$rows=$h->remember(2,18,'owned search');hc(count($rows)===1,'history stored');hc($h->read(3,18)===[],'history isolated');$old=$rows[0];$rows=$h->remember(2,18,'owned search');hc((int)$rows[0]['revision']===(int)$old['revision']+1,'stable ID revision');try{$h->remove(2,18,(int)$old['id'],(int)$old['revision']);throw new LogicException('stale removal accepted');}catch(DomainException $e){hc($e->getMessage()==='issuer_search_stale','stale denied');}
$h->remove(2,18,(int)$rows[0]['id'],(int)$rows[0]['revision']);hc($h->read(2,18)===[],'owned history removal');
for($i=0;$i<8;$i++)$h->remember(2,18,'search '.$i);
hc(count($h->read(2,18))===5,'history bounded');hc(count($db->query('SELECT id FROM tblcompany_native_search_history WHERE issuer_id=2 AND staff_id=18')->result_array())===5,'history pruning persisted');
echo "Native header SQLite service contracts passed: owned transaction, A/B, event replay/conflict, revocation, read-state separation, history revision. Not MySQL DDL/concurrency proof.\n";
