<?php
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/issuers/IssuerRegistry.php';
require_once APPPATH.'services/NativeCompanyWorkTags.php';
register_shutdown_function(static function():void{foreach($GLOBALS['essentialFixtures']??[]as$f)foreach(['company_work_tag_links','company_work_tags','taggables','tags','company_projects']as$t)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.$t);essentialCleanup();});
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
NativeCompanyWorkTagsSchema::ensure($db,$p);NativeCompanyWorkTagsSchema::ensure($db,$p);
$r=new IssuerRegistry($db,$p);$a=$r->saveIssuer(7,['legal_name'=>'Tags A','tax_id'=>'11222333000181']);$b=$r->saveIssuer(7,['legal_name'=>'Tags B','tax_id'=>'52998224725']);
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(51,1,0)");foreach([1,$a,$b]as$i){$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,51,1)",[$i]);$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,'projects','view')",[$i]);}
$db->query("CREATE TABLE {$p}company_projects(id INT PRIMARY KEY,issuer_id INT NOT NULL,name VARCHAR(100) NOT NULL)");$db->query("INSERT INTO {$p}company_projects VALUES(1,?,'A'),(2,?,'B')",[$a,$b]);
$db->query("CREATE TABLE {$p}tags(id INT PRIMARY KEY,name VARCHAR(100) NOT NULL)");$db->query("CREATE TABLE {$p}taggables(tag_id INT NOT NULL,rel_type VARCHAR(32) NOT NULL,rel_id INT NOT NULL)");$db->query("INSERT INTO {$p}tags VALUES(1,'Legacy orphan')");
foreach([[1,$a,'A visible',51],[2,$b,'B private',51],[3,$a,'A linked',7],[4,$a,'A unlinked',7]]as[$id,$i,$label,$actor])$db->query("INSERT INTO {$p}company_work_tags VALUES(?,?,'projects',?,?,?,1)",[$id,$i,$label,hash('sha256',mb_strtolower($label)), $actor]);
$db->query("INSERT INTO {$p}company_work_tag_links VALUES(?,3,'company_project',1,1)",[$a]);
$service=new NativeCompanyWorkTags($db,$p);$db->failOn='SELECT id,name FROM '.$p.'tags';$queryStart=count($db->queries);$result=$service->catalog($a,51);$db->failOn=null;
$catalogQueries=array_slice($db->queries,$queryStart);$linkReads=array_values(array_filter($catalogQueries,static fn($q)=>str_starts_with($q[0],'SELECT tag_id,source_kind,source_id FROM '.$p.'company_work_tag_links')));essentialCheck(count($linkReads)===2,'catalog batches link retrieval plus exact readback, not one query per tag');
essentialCheck($result['available_tags']===['A linked','A visible'],'A includes own and authorized-linked tags only');
essentialCheck(count(array_unique($result['available_tags_ids']))===2&&$result['available_tags_ids'][0]===NativeCompanyResourceId::encode('company_work_tag',3),'canonical tag ids retain lineage');
essentialCheck($service->catalog($b,51)['available_tags']===['B private'],'B excludes A and legacy');
essentialCheck($service->catalog(1,51)['available_tags']===[],'ordinary primary member cannot see orphan legacy catalog');
essentialCheck($service->catalog(1,7)['available_tags']===['Legacy orphan'],'proven primary administrator preserves orphan legacy catalog');
$before=$db->query("SELECT * FROM {$p}company_work_tags ORDER BY id")->result_array();NativeCompanyWorkTagsSchema::ensure($db,$p);essentialCheck($before===$db->query("SELECT * FROM {$p}company_work_tags ORDER BY id")->result_array(),'schema replay does not adopt or change tags');

$db->query("UPDATE {$p}company_work_tags SET label_hash=? WHERE id=1",[str_repeat('0',64)]);essentialThrows(fn()=>$service->catalog($a,51),'native_tags_invalid','tampered label identity fails closed');$db->query("UPDATE {$p}company_work_tags SET label_hash=? WHERE id=1",[hash('sha256','a visible')]);
$db->query("UPDATE {$p}company_work_tag_links SET source_id=2 WHERE tag_id=3");essentialCheck($service->catalog($a,51)['available_tags']===['A visible'],'cross-company link cannot authorize tag visibility');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=51",[$a]);essentialCheck($service->catalog($a,51)['available_tags']===[],'revoked feature excludes even own tags');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=51",[$a]);essentialThrows(fn()=>$service->catalog($a,51),'native_tags_denied','membership revocation denies catalog');
$db->query("ALTER TABLE {$p}company_work_tags ADD unexpected INT NULL");essentialThrows(fn()=>NativeCompanyWorkTagsSchema::assertValid($db,$p),'native_work_tags_schema_invalid','unexpected schema fails closed');
echo "Native work tags: {$GLOBALS['essentialChecks']} checks passed.\n";
