<?php
/** Real sales/estimate project binding and canonical invoice relation on disposable storage. */
require_once dirname(__DIR__).'/issuers_core_support.php';
register_shutdown_function(static function(){foreach($GLOBALS['essentialFixtures']??[] as$f)if($f->db->driver()==='mysql')foreach(['company_invoice_native_relations','company_projects']as$t)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.$t);});
require __DIR__.'/sales_preferences.php';
require_once APPPATH.'services/CompanyProjects.php';require_once APPPATH.'services/CompanyInvoiceProjectSchema.php';
$parts=CompanyProjectsSchema::definitions($p)['company_projects'];$sql='CREATE TABLE '.$p.'company_projects('.implode(',',$parts).') ENGINE=InnoDB';
if($db->driver()==='sqlite')$sql=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$sql);
$db->query($sql);CompanyInvoiceProjectSchema::ensure($db,$p);
foreach([[$a,$ca],[$b,$cb]] as [$owner,$client]){
 $db->query('INSERT INTO '.$p.'company_projects(issuer_id,client_id,responsible_id,name,description,status,start_date,due_date,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,?)',[$owner,$client,7,'Synthetic owned project','','active','2026-01-01','2026-12-31','2026-01-01 00:00:00','2026-01-01 00:00:00']);$projects[$owner]=(int)$db->insert_id();
}
$start=$GLOBALS['essentialChecks'];$input=array_replace($salesInput,['client_id'=>$ca,'native_project'=>['source_kind'=>'company_project','source_id'=>$projects[$a],'source_revision'=>1]]);
$id=$sales->create($a,7,'estimates',$input,hash('sha256','native-estimate-project'));$dto=$sales->document($a,7,$id);
essentialCheck($dto['payload']['native_project']===$input['native_project'],'original estimate project lineage is persisted with issuer and revision');
essentialCheck($sales->create($a,7,'estimates',$input,hash('sha256','native-estimate-project'))===$id,'replay preserves one source');
$foreign=$input;$foreign['native_project']['source_id']=$projects[$b];essentialThrows(fn()=>$sales->create($a,7,'estimates',$foreign,hash('sha256','native-estimate-foreign-project')),'company_project_access_denied','another company project cannot be linked');
$stale=$input;$stale['native_project']['source_revision']=2;essentialThrows(fn()=>$sales->create($a,7,'estimates',$stale,hash('sha256','native-estimate-stale-project')),'native_estimate_project_stale','stale project version cannot be linked');
$bad=$input;$bad['native_project']['source_kind']='native_legacy_project';essentialThrows(fn()=>$sales->create($a,7,'estimates',$bad,hash('sha256','native-estimate-project-kind')),'company_invoice_project_invalid','unreviewed historical lineage cannot be assumed owned');
$sales->decide($a,7,$id,'accepted');$portal=$portalSales->document($sessionA,$a,$id);essentialCheck(!isset($portal['payload']['native_project']),'private project reference is not exposed in portal DTO');
$invoice=$sales->convert($a,7,$id);$relation=$db->query('SELECT * FROM '.$p.'company_invoice_native_relations WHERE issuer_id=? AND invoice_id=?',[$a,$invoice])->row_array();
essentialCheck((int)$relation['source_id']===$projects[$a]&&(int)$relation['source_revision']===1&&(int)$relation['client_id']===$ca,'conversion persists reviewed project in canonical invoice relation');
essentialCheck($sales->convert($a,7,$id)===$invoice,'conversion replay does not duplicate the project relation');
$before=count($sales->page($a,7,'estimates')['items']);$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$sales->create($a,7,'estimates',$input,hash('sha256','native-estimate-project-rollback')),'issuer_storage_failed','audit failure aborts project-linked estimate');$db->failOn=null;
essentialCheck(count($sales->page($a,7,'estimates')['items'])===$before,'failed command leaves no project-linked estimate');
echo 'Native estimate project binding: '.($GLOBALS['essentialChecks']-$start)." added checks.\n";
