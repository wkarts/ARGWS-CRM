<?php
require __DIR__.'/../issuers_core_support.php';
require_once APPPATH.'services/issuers/IssuerRegistry.php';
require_once APPPATH.'services/NativeCompanyProjectMetadata.php';
register_shutdown_function(static function():void{foreach($GLOBALS['essentialFixtures']??[]as$f)foreach(['company_project_native_metadata','company_projects','company_tasks','currencies']as$t)$f->db->query('DROP TABLE IF EXISTS '.$f->prefix.$t);essentialCleanup();});
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$registry=new IssuerRegistry($db,$p);$a=$registry->saveIssuer(7,['legal_name'=>'Metadata A','tax_id'=>'11222333000181']);$b=$registry->saveIssuer(7,['legal_name'=>'Metadata B','tax_id'=>'52998224725']);
$db->query("CREATE TABLE {$p}company_projects(id INT NOT NULL PRIMARY KEY,issuer_id INT NOT NULL,status VARCHAR(16) NOT NULL,revision INT NOT NULL,UNIQUE(issuer_id,id))");
$db->query("CREATE TABLE {$p}company_tasks(id INT NOT NULL PRIMARY KEY,issuer_id INT NOT NULL,project_id INT NOT NULL)");
$db->query("CREATE TABLE {$p}currencies(id INT NOT NULL PRIMARY KEY)");$db->query("INSERT INTO {$p}currencies VALUES(1),(2)");
$db->query("INSERT INTO {$p}company_projects VALUES(1,?,'active',1),(2,?,'active',1)",[$a,$b]);unset($db->data_cache['table_names']);NativeCompanyProjectMetadataSchema::ensure($db,$p);
$service=new NativeCompanyProjectMetadata($db,$p);$run=fn($work)=>IssuerTransaction::run($db,$p,NativeCompanyProjectMetadata::tables($db,$p),$work);
$input=['workflow_status'=>2,'billing_type'=>1,'currency_id'=>1,'project_cost'=>'100.01','project_rate_per_hour'=>'0','estimated_hours'=>null,'progress_from_tasks'=>1,'progress'=>0];
essentialThrows(fn()=>$service->writeInOwnedTransaction($a,7,1,1,0,$input),'issuer_owned_transaction_required','No raw caller transaction');
essentialCheck($run(fn()=>$service->readInOwnedTransaction($a,7,1))===null,'No configuration is invented for historical active project');
$created=$run(fn()=>$service->writeInOwnedTransaction($a,7,1,1,0,$input));essentialCheck((int)$created['revision']===1&&$created['project_cost']==='100.01','Explicit configuration persisted exactly');
essentialCheck($run(fn()=>$service->readInOwnedTransaction($b,7,2))===null,'B remains untouched');
essentialThrows(fn()=>$run(fn()=>$service->writeInOwnedTransaction($b,7,1,1,0,$input)),'native_project_access_denied','Cross issuer row denied');
essentialThrows(fn()=>$run(fn()=>$service->writeInOwnedTransaction($a,7,1,2,1,$input)),'company_project_stale','Source revision required');
essentialThrows(fn()=>$run(fn()=>$service->writeInOwnedTransaction($a,7,1,1,0,$input)),'native_project_configuration_stale','Configuration compare-and-swap');
essentialThrows(fn()=>$run(fn()=>$service->writeInOwnedTransaction($a,7,1,1,1,array_replace($input,['currency_id'=>99]))),'native_project_currency_invalid','Currency reference real');
$changed=$run(fn()=>$service->writeInOwnedTransaction($a,7,1,1,1,array_replace($input,['workflow_status'=>3])));essentialCheck((int)$changed['workflow_status']===3&&(int)$changed['revision']===2,'Explicit hold state distinct from active lifecycle');
if($db->driver()==='sqlite'){
$db->query("CREATE TRIGGER {$p}metadata_audit_fault AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='native_project_configuration_updated' BEGIN UPDATE {$p}company_project_native_metadata SET progress=99 WHERE issuer_id=NEW.issuer_id AND project_id=NEW.subject_id; END");
essentialThrows(fn()=>$run(fn()=>$service->writeInOwnedTransaction($a,7,1,1,2,array_replace($input,['progress'=>5]))),'issuer_storage_failed','Mutation after audit detected and sanitized by transaction boundary');
$db->query('DROP TRIGGER '.$p.'metadata_audit_fault');essentialCheck($run(fn()=>$service->readInOwnedTransaction($a,7,1))===$changed,'Audit fault rolls back configuration');
}
echo "Native project metadata owned compositor: {$GLOBALS['essentialChecks']} checks passed.\n";
