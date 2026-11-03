<?php
require __DIR__.'/native_task_related_page.php';
$id=$standalone->createNative($a,7,array_replace($standInput,['assignee_id'=>7,'status'=>NativeCompanyTaskFormValues::lifecycle($page['rows'][0]['fields']['workflow_status'])]),$page['rows'][0]['fields'],$key('related-history-singleton'));$canonical=$standalone->get($a,7,$id)['assignee_id'];
$select=static function($page)use($id){foreach($page['rows']as$r)if($r['source_id']===$id)return$r;throw new RuntimeException('Missing historical task');};
essentialCheck($canonical!==null,'Fixture has actual historical singleton source');
$db->query('DELETE FROM '.$p.'company_standalone_assignees WHERE issuer_id=? AND task_id=?',[$a,$id]);$history=$select($pages->read($a,7,'ticket',$ticketCanonical,$wire,'2026-10-25'));
essentialCheck($history['facts']['assigned']===[(int)$canonical]&&!$history['facts']['not_assigned'],'Absent sidecar preserves proven canonical historical assignee without data adoption');
$db->query('INSERT INTO '.$p.'company_standalone_assignees(issuer_id,task_id,staff_id,active,revision,origin) VALUES(?,?,?,?,?,?)',[$a,$id,$canonical,0,1,'explicit_membership']);$revoked=$select($pages->read($a,7,'ticket',$ticketCanonical,$wire,'2026-10-25'));
essentialCheck($revoked['facts']['assigned']===[]&&$revoked['assignees']===[],'Explicit revoked membership never resurrects historical singleton');
essentialCheck((int)$db->query('SELECT assignee_id FROM '.$p.'company_standalone_tasks WHERE id=?',[$id])->row_array()['assignee_id']===(int)$canonical,'Projection never rewrites canonical historical source');
echo 'Related task historical assignee: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
