<?php
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyLeads.php';
require_once APPPATH.'services/CompanyClientRequestSchema.php';
require_once APPPATH.'services/CompanyPortalSchema.php';
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;
if($db->driver()!=='mysql'){fwrite(STDERR,"Private leads require a real MariaDB/MySQL fixture.\n");exit(2);}
register_shutdown_function(static function()use($db,$p):void{foreach(array_reverse(array_keys(CompanyLeadsSchema::definitions($p))) as $t)$db->pdo->exec('DROP TABLE IF EXISTS `'.$p.$t.'`');$db->pdo->exec('DROP TABLE IF EXISTS `'.$p.'leads`');essentialCleanup();});
IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$db->query("ALTER TABLE {$p}staff ADD firstname varchar(191), ADD lastname varchar(191), ADD email varchar(100)");$db->query("UPDATE {$p}staff SET firstname='Synthetic',lastname='Master',email='master@example.invalid'");
$db->query("DROP TABLE {$p}issuers_invoice_documents");$db->query("DROP TABLE {$p}clients");
$native=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(['clients'=>'userid','contacts'=>'id'] as $table=>$pk){if(!preg_match('/CREATE TABLE `tbl'.$table.'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$m))throw new RuntimeException('native fixture table missing');$sql=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$m[0]);$sql=str_replace('`'.$pk.'` int NOT NULL,','`'.$pk.'` int NOT NULL AUTO_INCREMENT PRIMARY KEY,',$sql);$db->query($sql);}
$db->query("INSERT INTO {$p}clients(userid,company,vat,datecreated,addedfrom,active) VALUES(9,'Preserved native customer',NULL,'2026-01-01 00:00:00',7,1)");
$db->query("CREATE TABLE {$p}leads(id int PRIMARY KEY,issuer_id int NULL,name varchar(191),email varchar(100)) ENGINE=InnoDB");$db->query("INSERT INTO {$p}leads VALUES(4242,NULL,'Legacy remains native','legacy@example.invalid')");
$registry=new IssuerRegistry($db,$p);$a=$registry->saveIssuer(7,['legal_name'=>'Leads A','tax_id'=>'12345678000195']);$b=$registry->saveIssuer(7,['legal_name'=>'Leads B','tax_id'=>'52998224725']);$primary=(new IssuerResourceAccess($db,$p))->primaryId();
CompanyClientRequestSchema::ensure($db,$p);CompanyPortalSchema::ensure($db,$p);CompanyApiSchema::ensure($db,$p);CompanyLeadsSchema::ensure($db,$p);CompanyLeadsSchema::ensure($db,$p);
unset($db->data_cache['table_names']);
$db->query("INSERT INTO {$p}staff(staffid,active,admin,firstname,lastname,email) VALUES(12,1,0,'Manager','A B','manager@example.invalid'),(13,1,1,'Legacy','Admin','admin@example.invalid'),(14,1,0,'Reader','A','reader@example.invalid'),(15,1,0,'Assignee','A','assignee@example.invalid')");
foreach([$a,$b] as $i){$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,12,1)",[$i]);foreach(['leads'=>['view','create','edit','delete'],'customers'=>['view','create','edit'],'company_api'=>['manage']] as $feature=>$caps)foreach($caps as $cap)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)",[$i,$feature,$cap]);}
foreach([14,15] as $id){$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,?,1)",[$a,$id]);$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,'leads','view')",[$a,$id]);}
$s=new CompanyLeads($db,$p);$key=static fn(string $v):string=>hash('sha256',$v);$stages=[];$sources=[];
foreach([$a,$b,$primary] as $i){foreach(['open','won','lost'] as $position=>$category){$stages[$i][$category]=$s->configure($i,7,'stage',null,null,['name'=>ucfirst($category),'category'=>$category,'position'=>$position,'active'=>1],$key('stage-'.$i.'-'.$category));$db->queries=[];}$sources[$i]=$s->configure($i,7,'source',null,null,['name'=>'Direct','active'=>1],$key('source-'.$i));$db->queries=[];}
function leadInput(int $issuer,array $replace=[]):array{global $stages,$sources;return array_replace(['name'=>'Synthetic contact','company'=>'Private prospect','email'=>'lead@example.invalid','phone'=>'+55 11 5555-0000','vat'=>'','description'=>'Private lead history','stage_id'=>$stages[$issuer]['open'],'source_id'=>$sources[$issuer],'assignee_id'=>0,'notify_assignee'=>0,'reminder_local'=>'','reminder_timezone'=>''],$replace);}
function leadCheck(bool $value,string $message):void{global$db;essentialCheck($value,$message);$db->queries=[];}
function leadThrows(callable $f,string $message,string $label):void{global$db;essentialThrows($f,$message,$label);$db->queries=[];}
function leadSnapshot():array{global$db,$p;$out=[];foreach(array_merge(array_keys(CompanyLeadsSchema::definitions($p)),['clients','contacts','issuers_clients','issuers_client_links','company_client_requests','company_portal_tokens','issuers_audit']) as $table)$out[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();$db->queries=[];return $out;}
$legacy=$db->query("SELECT * FROM {$p}leads")->result_array();$invoiceBefore=$db->query("SELECT * FROM {$p}invoices")->result_array();$db->queries=[];
