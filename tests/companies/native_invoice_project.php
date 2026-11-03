<?php
require_once dirname(__DIR__).'/issuers_core_support.php';
register_shutdown_function(static function(){foreach($GLOBALS['essentialFixtures']??[] as$f)if($f->db->driver()==='mysql')foreach(['company_invoice_native_relations','company_projects','projects']as$t)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.$t);});
require __DIR__.'/invoice_drafts.php';
require_once APPPATH.'services/CompanyInvoiceProjectSchema.php';
$parts=CompanyProjectsSchema::definitions($p)['company_projects'];
$sql='CREATE TABLE '.$p.'company_projects('.implode(',',$parts).') ENGINE=InnoDB';
if($db->driver()==='sqlite')$sql=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$sql);
$db->query($sql);CompanyInvoiceProjectSchema::ensure($db,$p);
foreach([[$a,$ca],[$b,$cb]]as[$owner,$client]){
    $db->query('INSERT INTO '.$p.'company_projects(issuer_id,client_id,responsible_id,name,description,status,start_date,due_date,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,?)',
        [$owner,$client,7,'Synthetic selected project','','active','2026-01-01','2026-12-31','2026-01-01 00:00:00','2026-01-01 00:00:00']);
    $projectIds[$owner]=(int)$db->insert_id();
}
$base=['client_id'=>$ca,'currency'=>1,'date'=>'2026-01-01','due_date'=>'2026-02-01','items'=>[['description'=>'Synthetic project relation','qty'=>'1.00','rate'=>'10.00','taxes'=>[]]],'note'=>''];
$input=$base+['native_project'=>['source_kind'=>'company_project','source_id'=>$projectIds[$a],'source_revision'=>1]];
$id=$s->create($a,7,$input,hash('sha256','native-project-create'));
$read=fn()=>$db->query('SELECT * FROM '.$p.'company_invoice_native_relations WHERE invoice_id=?',[$id])->row_array();
essentialCheck((int)$read()['source_id']===$projectIds[$a]&&(int)$read()['client_id']===$ca,'owned project relation persisted alongside canonical invoice');
essentialCheck((int)$db->query('SELECT project_id FROM '.$p.'invoices WHERE id=?',[$id])->row()->project_id===0,'owned project ID never written into legacy native project_id column');
essentialCheck($s->create($a,7,$input,hash('sha256','native-project-create'))===$id,'identical relation retries same canonical receipt');
$wrong=$input;$wrong['native_project']['source_id']=$projectIds[$b];
essentialThrows(fn()=>$s->create($a,7,$wrong,hash('sha256','native-project-foreign')),'company_project_access_denied','B project cannot attach to A invoice');
$wrong=$input;$wrong['native_project']['source_revision']=2;
essentialThrows(fn()=>$s->create($a,7,$wrong,hash('sha256','native-project-stale')),'company_invoice_project_stale','stale project revision prevents invoice issue');
$wrong=$input;$wrong['native_project']['source_kind']='native_legacy_project';
essentialThrows(fn()=>$s->create($a,7,$wrong,hash('sha256','native-project-kind')),'company_invoice_project_invalid','unimplemented lineage cannot enter owned association');
$countsBefore=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$linksBefore=$db->query('SELECT * FROM '.$p.'company_invoice_native_relations ORDER BY invoice_id')->result_array();
$db->failOn='INSERT INTO '.$p.'issuers_audit';
try{essentialThrows(fn()=>$s->create($a,7,$input,hash('sha256','native-project-audit-fail')),'issuer_storage_failed','late audit failure rejects invoice and relation atomically');}
finally{$db->failOn=null;}
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$countsBefore&&$db->query('SELECT * FROM '.$p.'company_invoice_native_relations ORDER BY invoice_id')->result_array()===$linksBefore,'late failure leaves no invoice or link');
$calls=0;
$guard=function()use(&$calls,$db,$p,$projectIds,$a){if(++$calls===2)$db->query('UPDATE '.$p.'company_projects SET revision=2 WHERE id=?',[$projectIds[$a]]);};
essentialThrows(fn()=>$s->create($a,7,$input,hash('sha256','native-project-late-change'),$guard),'company_invoice_project_stale','final owned revalidation catches project mutation after audit/journal');
essentialCheck((int)$db->query('SELECT revision FROM '.$p.'company_projects WHERE id=?',[$projectIds[$a]])->row()->revision===1&&$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$countsBefore,'late source change rolls back both source and invoice transaction');
$wrongClient=$input;$wrongClient['client_id']=$cb;
essentialThrows(fn()=>$s->create($a,7,$wrongClient,hash('sha256','native-project-client')),'company_invoice_recipient_denied','private foreign customer cannot be used with owned project');
$old=$s->revision($a,7,$id);$detach=$base+['native_project'=>null];
$s->change($a,7,$id,$old,$detach);
essentialCheck($read()===[],'explicit null detaches without deleting project or invoice');
essentialThrows(fn()=>$s->change($a,7,$id,$old,$input),'company_invoice_stale','relation changes participate in invoice revision fence');
$s->change($a,7,$id,$s->revision($a,7,$id),$input);
$preview=$copy->preview($a,7,$id);
$review=['source_revision'=>$preview['revision'],'date'=>'2026-01-02','due_date'=>'2026-02-02','note'=>'Copied project relation','confirm_amounts'=>'1'];
$copied=$copy->create($a,7,$id,$review,hash('sha256','native-project-copy'));
$copiedLink=$db->query('SELECT source_id FROM '.$p.'company_invoice_native_relations WHERE invoice_id=?',[$copied['invoice_id']])->row_array();
essentialCheck((int)$copiedLink['source_id']===$projectIds[$a],'copy preserves real project relation instead of dropping selection');
$draft=$drafts->create($a,7,$input,hash('sha256','native-project-draft'));
essentialCheck($drafts->document($a,7,$draft)['payload']['native_project']===$input['native_project'],'private draft preserves explicit relation payload');
$draftInvoice=$drafts->issue($a,7,$draft,1,hash('sha256','native-project-draft-issue'));
essentialCheck((int)$db->query('SELECT source_id FROM '.$p.'company_invoice_native_relations WHERE invoice_id=?',[$draftInvoice])->row()->source_id===$projectIds[$a],'draft issuance uses same relation writer');
$badDraft=$input;$badDraft['native_project']['source_id']=$projectIds[$b];
essentialThrows(fn()=>$drafts->create($a,7,$badDraft,hash('sha256','native-project-draft-foreign')),'company_project_access_denied','cross-company reference rejected while saving draft, not only at issue');
$before=$read();$db->query('UPDATE '.$p.'company_projects SET revision=2 WHERE id=?',[$projectIds[$a]]);
essentialThrows(fn()=>$s->change($a,7,$id,$s->revision($a,7,$id),$input),'company_invoice_project_stale','updated project vetoes stale UI association');
essentialCheck($read()===$before,'failed project guard rolls back any relation edits');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'project selection never adopts legacy NULL invoice');
require_once APPPATH.'services/NativeCompanyInvoiceDraftProjection.php';
NativeCompanyInvoiceDraftProjectionSchema::ensure($db,$p);
(new NativeCompanyInvoiceDraftProjection($db,$p))->backfill();
require_once APPPATH.'services/NativeCompanyInvoiceRead.php';
$reader=new NativeCompanyInvoiceRead($db,$p);
$transport=NativeCompanyResourceId::encode('company_project',$projectIds[$a]);
$summary=$reader->summary($a,7,1,[2026],['project_id'=>$transport]);
$tableScope=$reader->page($a,7,['length'=>100,'order'=>'project'],['project_id'=>$transport]);
essentialCheck($tableScope['recordsTotal']===3&&count($tableScope['documents'])===3,'DataTables project scope uses canonical association before counts and paging');
essentialThrows(fn()=>$reader->page($b,7,[],['project_id'=>$transport]),'native_resource_reference_denied','foreign project table rejected before reading documents');
essentialThrows(fn()=>$reader->page($a,7,[],['customer_id'=>$cb]),'native_resource_reference_denied','foreign customer table rejected before reading documents');

essentialCheck($summary['total_result']['due']==='30.00'&&array_sum($summary['counts'])===3,'project totals include exactly source/copy/issued draft carrying explicit relation');
essentialThrows(fn()=>$reader->summary($b,7,1,[2026],['project_id'=>$transport]),'native_resource_reference_denied','B cannot query known A project totals');
essentialThrows(fn()=>$reader->summary($a,7,1,[2026],['project_id'=>$projectIds[$a]]),'native_resource_reference_denied','raw same-valued project ID cannot alias owned project namespace');
essentialThrows(fn()=>$reader->summary($a,7,1,[2026],['customer_id'=>$cb]),'native_resource_reference_denied','foreign private customer totals denied before invoice query');
essentialThrows(fn()=>$reader->summary($a,7,1,[2026],['project_id'=>$transport,'customer_id'=>$ca]),'native_company_invoice_query_invalid','ambiguous related scopes rejected rather than dropping one');
essentialCheck($reader->summary($a,7,1,[2026],['customer_id'=>$ca])['total_result']['due']!==null,'owned native customer scope resolves through central reference policy');
// Deliberately equal numeric IDs in separate stores prove that primary legacy totals do not alias owned relations.
$db->query('CREATE TABLE '.$p.'projects(id int NOT NULL PRIMARY KEY,clientid int NOT NULL,name varchar(191) NOT NULL)');
$db->query('INSERT INTO '.$p.'projects(id,clientid,name) VALUES(?,?,?)',[$projectIds[$a],9999,'Synthetic legacy project']);
$db->query('UPDATE '.$p.'invoices SET project_id=? WHERE id=42',[$projectIds[$a]]);
try{
 $primary=(new IssuerResourceAccess($db,$p))->primaryId();
 $legacyProject=$reader->summary($primary,7,1,[2026],['project_id'=>(string)$projectIds[$a]]);
 essentialCheck($legacyProject['total_result']['due']==='7.00'&&array_sum($legacyProject['counts'])===1,'primary raw project ID aggregates only exact legacy NULL invoice lineage');
 $legacyTable=$reader->page($primary,7,['order'=>'project'],['project_id'=>(string)$projectIds[$a]]);
 essentialCheck($legacyTable['recordsTotal']===1&&$legacyTable['documents'][0]['invoice_id']===42,'principal table scope never matches equal raw owned project ID');

 essentialThrows(fn()=>$reader->summary($primary,7,1,[2026],['project_id'=>$transport]),'native_resource_reference_denied','primary cannot use encoded secondary project despite equal raw ID');
}finally{$db->query('UPDATE '.$p.'invoices SET project_id=? WHERE id=42',[$legacyBefore[0]['project_id']]);}
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'legacy project test restores the exact unchanged NULL sentinel');
echo 'Native invoice project relation issuer/revision/retry/detach/rollback: '.$GLOBALS['essentialChecks']." cumulative checks PASS.\n";
