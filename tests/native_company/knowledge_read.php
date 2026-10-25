<?php
declare(strict_types=1);
require_once __DIR__.'/../../application/services/NativeCompanyKnowledge.php';
function kc(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
final class KnowledgeRows{public function __construct(private array $rows){}public function result_array():array{return $this->rows;}public function row_array():array{return $this->rows[0]??[];}}
final class KnowledgeDb{
 public array $queries=[];public PDO $pdo;public bool $db_debug=true,$save_queries=true;
 public function __construct(){$this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}
 public function query($sql,$params=[]){$this->queries[]=$sql;$s=$this->pdo->prepare(str_replace(' FOR UPDATE','',$sql));$s->execute($params);return new KnowledgeRows($s->columnCount()?$s->fetchAll(PDO::FETCH_ASSOC):[]);}
 public function table_exists($table){return (bool)$this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$table])->row_array();}
 public function field_exists($field,$table){foreach($this->query('PRAGMA table_info('.$table.')')->result_array() as $r)if($r['name']===$field)return true;return false;}
 public function insert_id(){return $this->pdo->lastInsertId();}public function trans_begin(){return $this->pdo->beginTransaction();}public function trans_commit(){return $this->pdo->commit();}public function trans_rollback(){return $this->pdo->rollBack();}public function trans_status(){return true;}
}
$db=new KnowledgeDb();
foreach([
 'views_tracking'=>'rel_type TEXT,rel_id INTEGER',
 'issuers_lock'=>'id INTEGER PRIMARY KEY',
 'issuers_registry'=>'id INTEGER PRIMARY KEY,active INTEGER',
 'staff'=>'staffid INTEGER PRIMARY KEY,active INTEGER,admin INTEGER',
 'issuers_staff'=>'issuer_id INTEGER,staff_id INTEGER,can_view INTEGER',
 'issuers_installation_identity'=>'id INTEGER PRIMARY KEY,primary_issuer_id INTEGER,master_staff_id INTEGER',
 'issuers_resource_permissions'=>'issuer_id INTEGER,staff_id INTEGER,feature TEXT,capability TEXT',
 'company_kb_legacy_group_links'=>'id INTEGER PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER,legacy_article_id INTEGER,group_source_kind TEXT,group_source_id INTEGER,revision INTEGER,actor_id INTEGER,created_at TEXT,updated_at TEXT,UNIQUE(issuer_id,legacy_article_id)',
 'company_kb_groups'=>'id INTEGER PRIMARY KEY,issuer_id INTEGER,name TEXT,description TEXT,group_slug TEXT,color TEXT,active INTEGER,group_order INTEGER,revision INTEGER',
 'company_kb_articles'=>'id INTEGER PRIMARY KEY,issuer_id INTEGER,group_source_kind TEXT,group_source_id INTEGER,subject TEXT,description TEXT,slug TEXT,active INTEGER,staff_article INTEGER,article_order INTEGER,created_at TEXT,revision INTEGER',
 'knowledge_base_groups'=>'groupid INTEGER PRIMARY KEY,name TEXT,description TEXT,group_slug TEXT,color TEXT,active INTEGER,group_order INTEGER',
 'knowledge_base'=>'articleid INTEGER PRIMARY KEY,articlegroup INTEGER,subject TEXT,description TEXT,slug TEXT,active INTEGER,staff_article INTEGER,article_order INTEGER,datecreated TEXT',
] as $table=>$ddl)$db->query('CREATE TABLE tbl'.$table.' ('.$ddl.')');
$db->query('INSERT INTO tblissuers_lock VALUES(1)');$db->query('INSERT INTO tblissuers_registry VALUES(1,1),(2,1),(3,1)');$db->query('INSERT INTO tblstaff VALUES(17,1,0),(18,1,0)');$db->query('INSERT INTO tblissuers_staff VALUES(1,17,1),(2,17,1),(3,18,1)');$db->query('INSERT INTO tblissuers_installation_identity VALUES(1,1,NULL)');
foreach([[1,17],[2,17],[3,18]] as [$issuer,$actor])$db->query("INSERT INTO tblissuers_resource_permissions VALUES(?,?,'knowledge_base','view')",[$issuer,$actor]);
$db->query("INSERT INTO tblcompany_kb_groups VALUES(1,2,'A','A description','group-a','#28B8DA',1,1,1),(2,3,'B','B private','group-b','#28B8DA',1,1,1),(3,1,'Principal new','','new','#28B8DA',1,2,1)");
$db->query("INSERT INTO tblcompany_kb_articles VALUES(1,2,'company_kb_group',1,'A subject','<p>A body</p>','article-a',1,1,1,'2026-01-01 10:00:00',1),(2,3,'company_kb_group',2,'B subject','B private','article-b',1,0,1,'2026-01-01 10:00:00',1),(3,1,'company_kb_group',3,'New primary','Primary new','primary-new',1,1,1,'2026-01-01 10:00:00',1)");
$db->query("INSERT INTO tblknowledge_base_groups VALUES(1,'Historical','','history','#28B8DA',1,1)");
$db->query("INSERT INTO tblviews_tracking VALUES('kb_article',1),('kb_article',1)");
$db->query("INSERT INTO tblknowledge_base VALUES(1,1,'Historical article','Private historical','historical',1,1,1,'2025-01-01 10:00:00')");
$kb=new NativeCompanyKnowledge($db,'tbl');$a=NativeCompanyResourceId::encode('company_kb_article',1);$b=NativeCompanyResourceId::encode('company_kb_article',2);
$db->queries=[];$rows=$kb->articles(2,17);kc(count($rows)===1&&$rows[0]['articleid']===$a&&$rows[0]['group_name']==='A','owned A exact DTO');
kc(!preg_grep('/(?:FROM|JOIN) tblknowledge_base\b|(?:FROM|JOIN) tblknowledge_base_groups\b/',$db->queries),'named never queries historical catalog');
kc(count($kb->articles(1,17))===2,'principal explicit historical plus owned');kc($kb->article(1,17,'1')['source_kind']==='kb_article','historical lineage');kc($kb->article(1,17,'1')['total_views']===2,'historical viewcount preserved');kc($rows[0]['views_provenance']==='new_telemetry_disabled'&&$rows[0]['total_views']===0,'owned zero comes from native disabled telemetry');
kc($kb->bySlug(2,17,'article-a')['articleid']===$a,'slug is issuer-scoped');kc(count($kb->articles(2,17,null,'A body'))===1,'native text search');
$deny=static function(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('denied action succeeded');};
$deny(fn()=>$kb->article(2,17,$b));$deny(fn()=>$kb->article(2,17,'1'));$deny(fn()=>$kb->articles(3,17));$deny(fn()=>$kb->bySlug(2,17,'../article-a'));
$db->query("UPDATE tblcompany_kb_articles SET group_source_id=2 WHERE id=1");$deny(fn()=>$kb->article(2,17,$a));$db->query("UPDATE tblcompany_kb_articles SET group_source_id=1 WHERE id=1");
$old=$kb->article(2,17,$a)['revision_token'];$db->query("UPDATE tblcompany_kb_articles SET subject='Changed without revision' WHERE id=1");kc($kb->article(2,17,$a)['revision_token']!==$old,'canonical content revision detects unversioned mutation');
$db->query("UPDATE tblcompany_kb_articles SET slug='historical' WHERE id=3");$deny(fn()=>$kb->bySlug(1,17,'historical'));
$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=17');$deny(fn()=>$kb->articles(2,17));$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
$db->query("DELETE FROM tblissuers_resource_permissions WHERE issuer_id=2 AND staff_id=17");$deny(fn()=>$kb->articles(2,17));
echo "Knowledge base actual SQLite reader: A/B, principal lineage, orphan/cross-company groups, slug collision, canonical revision, ACL/revocation passed. No public publication or writer claim.\n";
