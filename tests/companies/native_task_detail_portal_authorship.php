<?php
/** Current original task modal, using persisted contact provenance and actor=0 historical comments. */
require __DIR__.'/native_task_detail_controller.php';
$source=NativeCompanyResourceId::decode('task',$canonical)['source_id'];$row=$tasks->get($a,7,$source);$projectId=(int)$row['project_id'];
require_once APPPATH.'services/CompanyPortalTaskWriteSchema.php';
// The exact historical author tables; their production FK migration has its own MySQL gate.
foreach(['company_portal_task_origins','company_portal_task_comments']as$table){$parts=CompanyPortalTaskWriteSchema::definitions($p)[$table];$parts=array_values(array_filter($parts,fn($part)=>!str_starts_with($part,'FOREIGN KEY')));if($db->driver()==='sqlite')$parts=array_map(fn($part)=>preg_replace('/ CHARACTER SET [a-z0-9]+ COLLATE [a-z0-9_]+/','',$part),$parts);$db->query('CREATE TABLE '.$p.$table.'('.implode(',',$parts).')');}
unset($db->data_cache['table_names']);
$db->query('UPDATE '.$p.'company_tasks SET assignee_id=NULL WHERE issuer_id=? AND id=?',[$a,$source]);$db->query('DELETE FROM '.$p.'company_task_assignees WHERE issuer_id=? AND task_id=?',[$a,$source]);
$db->query('INSERT INTO '.$p.'company_portal_task_origins(issuer_id,project_id,task_id,client_id,contact_id,author_name,billing_state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$a,$projectId,$source,$ca,123,'Portal Operation <script>','unbilled','2026-10-29 10:00:00']);
$db->query('INSERT INTO '.$p.'company_task_comments(issuer_id,task_id,actor_id,body,created_at) VALUES(?,?,0,?,?)',[$a,$source,'Contact literal <img>','2026-10-29 10:00:00']);$comment=(int)$db->insert_id();
$db->query('INSERT INTO '.$p.'company_portal_task_comments(issuer_id,project_id,task_id,client_id,contact_id,author_name,created_at,comment_id) VALUES(?,?,?,?,?,?,?,?)',[$a,$projectId,$source,$ca,123,'Portal Operation <script>','2026-10-29 10:00:00',$comment]);
$controller->input->verb='GET';$controller->get_task_data($canonical);$html=$controller->output->body;
essentialCheck(str_contains($html,'Criada pelo contato: Portal Operation &lt;script&gt;')&&str_contains($html,'Contato: Portal Operation &lt;script&gt;')&&str_contains($html,'Aguardando atribuição de responsável')&&!str_contains($html,'Funcionário #0')&&!str_contains($html,'Contact literal <img>'),'Actual original modal renders persisted task/comment contact authors, literal content and true unassigned state');
$db->query('DELETE FROM '.$p.'company_portal_task_comments WHERE comment_id=?',[$comment]);
try{$controller->get_task_data($canonical);throw new RuntimeException('Missing contact author was fabricated');}catch(LogicException$e){essentialCheck($e->getMessage()==='HTTP 403','Missing contact provenance fails closed without manufacturing staff zero');}
echo 'Original task modal: actual contact provenance and missing-author guard passed ('.$db->driver().').'.PHP_EOL;
