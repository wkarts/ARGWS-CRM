<?php
declare(strict_types=1);
require __DIR__.'/knowledge_read.php';require_once __DIR__.'/../../application/services/NativeCompanyPublicKnowledge.php';
$db->query("ALTER TABLE tblissuers_registry ADD COLUMN settings_json TEXT DEFAULT '{}'");$db->query('CREATE TABLE tbloptions (name TEXT,value TEXT)');$db->query("INSERT INTO tbloptions VALUES('use_knowledge_base','1'),('knowledge_base_without_registration','1')");
$db->query('UPDATE tblissuers_registry SET settings_json=? WHERE id=2',[json_encode(['company_options'=>['use_knowledge_base'=>'1','knowledge_base_without_registration'=>'1']])]);
$public=new NativeCompanyPublicKnowledge($db,'tbl');$db->queries=[];kc($public->grouped(2)===[],'internal staff article never public');$deny(fn()=>$public->article(2,'article-a'));
$db->query('UPDATE tblcompany_kb_articles SET staff_article=0 WHERE id=1');$db->queries=[];$aPublic=$public->article(2,'article-a');kc($aPublic['articleid']===$a&&!isset($aPublic['actor_id'],$aPublic['revision'],$aPublic['issuer_id']),'public DTO allowlist');kc(!preg_grep('/FROM tbloptions|FROM tblknowledge_base\b|FROM tblknowledge_base_groups\b/',$db->queries),'named never inherits global option/catalog');
kc(count($public->grouped(2,'Changed'))===1&&$public->grouped(2,'B private')===[],'public search company-specific');$deny(fn()=>$public->article(2,'article-b'));$deny(fn()=>$public->article(3,'article-b'));$deny(fn()=>$public->article(0,'article-a'));
$db->query('UPDATE tblcompany_kb_articles SET group_source_id=2 WHERE id=1');kc($public->grouped(2)===[],'foreign group relation cannot publish owned body');$db->query('UPDATE tblcompany_kb_articles SET group_source_id=1 WHERE id=1');
$db->query('UPDATE tblcompany_kb_groups SET active=0 WHERE id=1');$deny(fn()=>$public->article(2,'article-a'));$db->query('UPDATE tblcompany_kb_groups SET active=1 WHERE id=1');
$db->query('UPDATE tblissuers_registry SET settings_json=? WHERE id=2',[json_encode(['company_options'=>['use_knowledge_base'=>'1','knowledge_base_without_registration'=>'0']])]);$deny(fn()=>$public->article(2,'article-a'));
$db->query('UPDATE tblissuers_registry SET settings_json=? WHERE id=2',[json_encode(['company_options'=>['use_knowledge_base'=>'0','knowledge_base_without_registration'=>'1']])]);$deny(fn()=>$public->article(2,'article-a'));
$db->query('UPDATE tblknowledge_base SET staff_article=0');kc($public->article(1,'historical')['articleid']==='1','explicit primary historical public article preserved');
$db->query('UPDATE tblissuers_registry SET active=0 WHERE id=1');$deny(fn()=>$public->article(1,'historical'));
echo "Public KB real SQLite: explicit publication/registration gates, staff-only isolation, A/B, active groups and primary provenance passed; authenticated contact and public HTTP bootstrap pending\n";
