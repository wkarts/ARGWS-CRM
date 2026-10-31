<?php
declare(strict_types=1);
require __DIR__.'/knowledge_read.php';
require_once __DIR__.'/../../application/services/NativeCompanyKnowledgePresentation.php';
$db->query("INSERT INTO tblissuers_resource_permissions VALUES(2,17,'knowledge_base','view')");
$db->query('CREATE TABLE tbluser_meta (staff_id INTEGER,meta_key TEXT,meta_value TEXT)');$db->query("INSERT INTO tbluser_meta VALUES(17,'kb-articles-table-last-order','[[2,\"desc\"]]'),(18,'kb-articles-table-last-order','foreign')");
$presenter=new NativeCompanyKnowledgePresentation($db,'tbl');$dto=$presenter->catalog(2,17);
kc($dto['native_knowledge_table_order']==='', 'secondary company does not inherit the global table preference');
$db->query("INSERT INTO tbluser_meta VALUES(17,'issuer:2:kb-articles-table-last-order','[[2,\"desc\"]]')");$dto=$presenter->catalog(2,17);
kc($dto['native_knowledge_table_order']==='[[2,"desc"]]','actual private staff table preference only');
kc($dto['native_knowledge_counts']['184683593729']===1&&$dto['native_knowledge_group_count']===1,'catalog counts are owned actual rows');
$db->query('UPDATE tblcompany_kb_articles SET active=0 WHERE issuer_id=2');$dto=$presenter->catalog(2,17);kc($dto['native_knowledge_articles']['184683593729']===[]&&$dto['native_knowledge_counts']['184683593729']===1,'native viewer kanban excludes inactive while count preserves all');
$db->query("INSERT INTO tblissuers_resource_permissions VALUES(2,17,'knowledge_base','edit')");$dto=$presenter->catalog(2,17);kc(count($dto['native_knowledge_articles']['184683593729'])===1,'editor sees inactive owned article');
function site_url($path){return 'https://example.test/subpath/'.$path;}
kc(NativeCompanyKnowledgePresentation::publicUrl('article-a',2)==='https://example.test/subpath/knowledge-base/article/article-a?issuer_id=2','same public route with explicit company and subpath');
$deny(fn()=>NativeCompanyKnowledgePresentation::publicUrl('../b',2));
// Execute the actual shared group partial with global DB counters forbidden.
define('BASEPATH',true);function _l($v){return $v;}function form_open(...$args){return '<form>';}function form_close(){return '</form>';}function form_hidden($k,$v){return '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';}function admin_url($v=''){return '/admin/'.$v;}function render_input($name,$label,$value='',$type='text'){return '<input name="'.htmlspecialchars($name).'" value="'.htmlspecialchars((string)$value).'">';}function render_color_picker(...$a){return '';}function render_textarea(...$a){return '';}function total_rows(...$args){throw new RuntimeException('Global row query forbidden');}
$native_knowledge_context='fixture-context';$native_knowledge_csrf='fixture-csrf';extract($dto);ob_start();include __DIR__.'/../../application/views/admin/knowledge_base/group.php';$html=ob_get_clean();kc(str_contains($html,'name="group_order" value="2"'),'actual native modal receives owned count without global query');
$db->query('UPDATE tblissuers_staff SET can_view=0 WHERE issuer_id=2 AND staff_id=17');$deny(fn()=>$presenter->catalog(2,17));
echo "Knowledge presentation actual SQLite DTO and shared PHP group partial passed; browser/controller admission pending\n";
