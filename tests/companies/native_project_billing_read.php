<?php
require __DIR__.'/project_fixed_billing.php';
$db->query('INSERT INTO '.$p.'staff(staffid,active,admin) VALUES(93,1,0)');$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,93,1)',[$a]);foreach(['tasks','projects','customers','invoices']as$feature)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,93,?,?)',[$a,$feature,'view']);
$taskId=(int)$db->query('SELECT task_id FROM '.$p.'company_task_billing_links WHERE issuer_id=? AND state=? ORDER BY task_id LIMIT 1',[$a,'converted'])->row_array()['task_id'];
foreach([[$billing,$taskId],[$fixed,$fp]]as[$reader,$id]){
 $start=count($db->queries);$read=$reader->readStatus($a,93,$id);$queries=array_slice($db->queries,$start);
 essentialCheck($read['state']==='converted'&&$read['invoice_id']>0&&is_array($read['invoice_current']),'Read-only authorized actor sees actual canonical invoice lifecycle');
 essentialCheck(!isset($read['can_release'])&&!array_filter($queries,fn($q)=>preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP)\b/i',$q[0])),'Financial read projection neither grants actions nor writes storage');
 essentialThrows(fn()=>$reader->status($a,93,$id),'company_task_billing_denied','Existing privileged billing status contract was not relaxed');
 essentialThrows(fn()=>$reader->readStatus($b,93,$id),'company_task_billing_read_denied','Read-only financial projection requires exact company membership and grants');
}
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=93 AND feature=?',[$a,'invoices']);essentialThrows(fn()=>$billing->readStatus($a,93,$taskId),'company_task_billing_read_denied','Revoked invoice read authority is checked on every projection');
echo 'Native project verified read-only financial evidence: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,93,?,?)',[$a,'invoices','view']);
$candidateTask=$makeTask($a,$pa,'native-reader-quote');$candidateOrigin=$billing->prepare($a,7,$candidateTask,4,$ti,$tk('native-reader-quote-prepare'));
$beforeCandidates=$db->query('SELECT * FROM '.$p.'company_task_billing_origins ORDER BY id')->result_array();$queriesBefore=count($db->queries);$candidate=$billing->readCandidate($a,93,$candidateTask);
essentialCheck($candidate['available']&&$candidate['origin_id']===$candidateOrigin&&$candidate['task_revision']===4,'Native candidate uses existing reviewed quote and current source/fences, not task status alone');
essentialCheck(!array_filter(array_slice($db->queries,$queriesBefore),fn($q)=>preg_match('/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP)\b/i',$q[0]))&&$db->query('SELECT * FROM '.$p.'company_task_billing_origins ORDER BY id')->result_array()===$beforeCandidates,'Candidate listing does not prepare, reserve or change quote origins');
$receiptKey=hash('sha256','company-task-invoice:'.$a.':'.$candidateTask);$receipt=$db->query('SELECT * FROM '.$p.'company_invoice_requests WHERE issuer_id=? ORDER BY request_key LIMIT 1',[$a])->row_array();$receipt['request_key']=$receiptKey;$db->insert($p.'company_invoice_requests',$receipt);essentialThrows(fn()=>$billing->readCandidate($a,93,$candidateTask),'issuer_task_billing_unconfirmed','An existing canonical invoice receipt vetoes a superficially ready link');$db->query('DELETE FROM '.$p.'company_invoice_requests WHERE request_key=?',[$receiptKey]);
$billing->convert($a,7,$candidateTask,$candidateOrigin);essentialCheck($billing->readCandidate($a,93,$candidateTask)['available']===false,'Converted candidate is no longer offered for another charge');
essentialThrows(fn()=>$billing->readCandidate($b,93,$candidateTask),'company_task_billing_read_denied','Candidate cannot cross company through a known task ID');
echo 'Native project actual existing billing candidates: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
require_once APPPATH.'services/NativeCompanyProjectRead.php';require_once APPPATH.'services/NativeCompanyResourceReferences.php';
$listTask=$makeTask($a,$pa,'native-selector-quote');$listOrigin=$billing->prepare($a,7,$listTask,4,$ti,$tk('native-selector-prepare'));
$selector=new NativeCompanyProjectRead($db,$p);$selected=$selector->billingCandidates($a,93,NativeCompanyResourceId::encode('company_project',$pa),$ca);$selectedById=array_column($selected['items'],null,'source_id');
essentialCheck(isset($selectedById[$listTask])&&$selectedById[$listTask]['billing_origin_id']===$listOrigin&&!isset($selectedById[$candidateTask]),'Native project selector offers real ready quote and excludes already-converted task');
essentialCheck($selectedById[$listTask]['source_revision']===4&&$selected['basis']==='existing_whole_task_quotes_only','Selector preserves actual source revision and explicit quote-only basis');
essentialThrows(fn()=>$selector->billingCandidates($a,93,NativeCompanyResourceId::encode('company_project',$pa),$cb),'native_resource_reference_denied','Invoice selector cannot swap in another company customer');
echo 'Native invoice project existing-quote selector: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
