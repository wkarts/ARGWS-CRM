<?php
declare(strict_types=1);
require __DIR__.'/knowledge_write.php';
foreach(['respondent_hash TEXT','answer INTEGER','created_at TEXT'] as $column)$db->query('ALTER TABLE tblcompany_kb_feedback ADD COLUMN '.$column);
$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');$now=1792944000;
kc($kb->feedback(2,17,$a,'1',$now),'owned staff feedback accepted');
kc(!$kb->feedback(2,17,$a,'0',$now+86400),'inclusive native24h limit preserved');kc($kb->feedback(2,17,$a,'0',$now+86401),'next rolling window accepts');
$deny(fn()=>$kb->feedback(2,17,$b,'1',$now));$deny(fn()=>$kb->feedback(2,17,$a,'true',$now));$deny(fn()=>$kb->feedback(3,17,$b,'1',$now));
kc($kb->feedback(1,17,'1','0',$now),'primary historical article retains own source lineage');
$row=$db->query('SELECT * FROM tblcompany_kb_feedback WHERE issuer_id=1')->row_array();kc($row['article_source_kind']==='kb_article'&&(int)$row['article_source_id']===1,'no adoption of historical article or IP feedback');
$before=$db->query('SELECT * FROM tblcompany_kb_feedback ORDER BY id')->result_array();$db->query("CREATE TRIGGER reject_feedback BEFORE INSERT ON tblcompany_kb_feedback BEGIN SELECT RAISE(ABORT,'synthetic'); END");try{$kb->feedback(2,17,$a,'1',$now+180000);throw new LogicException('failed insert succeeded');}catch(RuntimeException $e){kc($e->getMessage()==='issuer_storage_failed','normalized storage failure');}kc($db->query('SELECT * FROM tblcompany_kb_feedback ORDER BY id')->result_array()===$before,'storage failure leaves all feedback unchanged');$db->query('DROP TRIGGER reject_feedback');
$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=17');$deny(fn()=>$kb->feedback(2,17,$a,'1',$now+180000));
echo "Knowledge feedback real SQLite: A/B/actor guard, rolling24h, lineage and failed write rollback passed\n";
