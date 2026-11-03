<?php
declare(strict_types=1);
require __DIR__.'/knowledge_write.php';
$db->query('CREATE TABLE tblknowedge_base_article_feedback (id INTEGER PRIMARY KEY,articleid INTEGER)');
$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');
foreach([1,2]as$i)$db->query("INSERT INTO tblissuers_resource_permissions VALUES(?,17,'knowledge_base','delete')",[$i]);
$g=$kb->group(2,17,$g['groupid']);$colored=$kb->change(2,17,false,$g['groupid'],$g['revision_token'],['color'=>'#ABCDEF','group_order'=>12]);kc($colored['color']==='#ABCDEF'&&$colored['group_order']===12,'native color/order change');
$deny(fn()=>$kb->change(2,17,false,$g['groupid'],$g['revision_token'],['active'=>0]));
$deny(fn()=>$kb->delete(2,17,false,$g['groupid'],$colored['revision_token']));
$off=$kb->change(2,17,true,$updated['articleid'],$updated['revision_token'],['active'=>0,'article_order'=>7]);kc($off['active_article']===0&&$off['article_order']===7,'native article status/order');
$deny(fn()=>$kb->change(2,17,true,$off['articleid'],$off['revision_token'],['issuer_id'=>3]));
$deny(fn()=>$kb->delete(2,17,true,$b,$off['revision_token']));
$db->query("INSERT INTO tblcompany_kb_feedback VALUES(1,2,'company_kb_article',?),(2,3,'company_kb_article',2)",[$off['source_id']]);
$kb->delete(2,17,true,$off['articleid'],$off['revision_token']);$deny(fn()=>$kb->article(2,17,$off['articleid']));kc(count($db->query('SELECT id FROM tblcompany_kb_feedback WHERE issuer_id=3')->result_array())===1,'foreign feedback preserved');
$kb->delete(2,17,false,$colored['groupid'],$colored['revision_token']);$deny(fn()=>$kb->group(2,17,$colored['groupid']));
$h=$kb->article(1,17,'1');$db->query('INSERT INTO tblknowedge_base_article_feedback VALUES(1,1)');$kb->delete(1,17,true,'1',$h['revision_token']);kc(!$db->query('SELECT id FROM tblknowedge_base_article_feedback')->result_array(),'historical primary feedback removed with deleted article');
kc($db->query('SELECT COUNT(*) n FROM tblviews_tracking WHERE rel_id=1')->row_array()['n']===2,'historical telemetry retained');
echo "Knowledge actual SQLite status/order/color/delete: stale and cross-issuer denials, referenced group protection, exact feedback cleanup and historical preservation passed.\n";
