<?php
require __DIR__.'/native_project_metadata_rate.php';
$service=new CompanyProjects($db,$p);$client=(int)$db->query('SELECT client_id FROM '.$p.'company_projects WHERE issuer_id=? AND id=?',[$a,$project])->row_array()['client_id'];
$form=['client_id'=>$client,'responsible_id'=>0,'name'=>'Native project, intentionally no assigned members','description'=>'','start_date'=>'2026-10-01','due_date'=>'2026-12-31','status'=>'active'];
$config=array_replace($input,['workflow_status'=>1]);
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(92,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,92,1)",[$a]);
foreach([['projects','view'],['projects','create'],['customers','view'],['invoices','view'],['invoices','create']]as[$f,$c])$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,92,?,?)",[$a,$f,$c]);
$key=$tk('native-create-only');$id=$service->createNative($a,92,$form,$config,$key);$row=$service->get($a,92,$id);
essentialCheck((int)$row['responsible_id']===0&&(int)$row['revision']===1,'Creating native project does not manufacture a responsible staff or membership');
essentialCheck(!(new IssuerResourceAccess($db,$p))->can($a,92,'projects','edit'),'Create-only actor has no edit grant');
essentialCheck($service->createNative($a,92,$form,$config,$key)===$id,'Native create replay returns original project without duplicate insert');
$record=$run(fn()=>$metadata->readInOwnedTransaction($a,92,$id));essentialCheck((int)$record['workflow_status']===1&&(int)$record['hourly_rate_id']>0,'Create-only actor declares actual phase and authorized canonical price in same unit');
essentialThrows(fn()=>$service->updateNative($a,92,$id,1,$form,1,$config,$tk('create-is-not-edit')),'company_project_access_denied','Creation permission cannot edit a previously created source');
essentialThrows(fn()=>$run(fn()=>$metadata->writeCreatedInOwnedTransaction($a,92,$id,$config,new stdClass())),'native_project_creation_proof_denied','A caller cannot manufacture a proof for an existing revision-one project');
essentialThrows(fn()=>$run(fn()=>$prices->declareHourlyInOwnedTransaction($a,92,$id,1,['currency'=>1,'rate'=>'70','round_mode'=>'none','round_minutes'=>0],$tk('forged-rate-create'),new stdClass())),'native_project_creation_proof_denied','A fake proof cannot bypass the price edit gate');
essentialThrows(fn()=>$service->create($a,92,$form,$tk('old-create-no-assignee')),'issuer_invalid_id','Historical API still requires its explicit responsible identity');
$updated=array_replace($form,['name'=>'Native unassigned project edited']);$service->updateNative($a,7,$id,1,$updated,1,$config,$tk('native-unassigned-edit'));
essentialCheck($service->get($a,7,$id)['name']===$updated['name'],'Authorized editor preserves explicit unassigned origin using durable native receipt');
$db->query('DELETE FROM '.$p.'company_project_requests WHERE issuer_id=? AND result_id=? AND operation=?',[$a,$id,'native_project_created']);
essentialThrows(fn()=>$service->updateNative($a,7,$id,2,$updated,2,$config,$tk('native-origin-missing')),'native_project_origin_unconfirmed','Missing creation provenance prevents preserving unexplained unassigned identity');
$before=$db->query('SELECT * FROM '.$p.'company_projects ORDER BY id')->result_array();$rates=$db->query('SELECT * FROM '.$p.'company_project_billing_rates ORDER BY id')->result_array();
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$service->createNative($a,92,$form,$config,$tk('native-create-audit-failure')),'issuer_storage_failed','Audit failure rolls back project, price and metadata');$db->failOn=null;
essentialCheck($db->query('SELECT * FROM '.$p.'company_projects ORDER BY id')->result_array()===$before&&$db->query('SELECT * FROM '.$p.'company_project_billing_rates ORDER BY id')->result_array()===$rates,'No partially created source or rate survives failure');
foreach(['completed'=>4,'cancelled'=>5]as$state=>$phase){$closed=$service->createNative($a,92,array_replace($form,['status'=>$state]),array_replace($config,['workflow_status'=>$phase]),$tk('native-create-'.$state));essentialCheck($service->get($a,92,$closed)['status']===$state,'Explicit native initial terminal phase is preserved without editing a historical closed source');}
if($db->driver()==='sqlite'){
$db->query("CREATE TRIGGER {$p}native_create_revoke AFTER INSERT ON {$p}company_projects WHEN NEW.responsible_id=0 BEGIN DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=NEW.issuer_id AND staff_id=92 AND feature='projects' AND capability='create'; END");
essentialThrows(fn()=>$service->createNative($a,92,$form,$config,$tk('native-create-grant-revoked')),'native_project_creation_proof_denied','Creation grant revoked during insert is checked before metadata/price');$db->query('DROP TRIGGER '.$p.'native_create_revoke');
essentialCheck((new IssuerResourceAccess($db,$p))->can($a,92,'projects','create'),'Rollback restores the pre-operation grant with no source write');
}
echo 'Native project creation authority: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
require_once APPPATH.'services/NativeCompanyWorkAssignments.php';
NativeCompanyWorkAssignmentsSchema::ensure($db,$p);
$db->query("ALTER TABLE {$p}staff ADD firstname VARCHAR(100) NOT NULL DEFAULT 'Synthetic'");$db->query("ALTER TABLE {$p}staff ADD lastname VARCHAR(100) NOT NULL DEFAULT 'Staff'");
$assignments=new NativeCompanyWorkAssignments($db,$p);$new=$service->createNative($a,92,$form,$config,$tk('native-create-members'),[7,92]);
$members=$assignments->read($a,92,'project',$new);essentialCheck(array_map('intval',array_column($members['members'],'staffid'))===[7,92],'Selected native project members are persisted atomically with create-only authority');
essentialCheck((int)$service->get($a,92,$new)['responsible_id']===0,'Selecting members does not invent the legacy singular responsible role');
$count=count($db->query('SELECT id FROM '.$p.'company_projects')->result_array());
essentialThrows(fn()=>$service->createNative($a,92,$form,$config,$tk('native-create-invalid-members'),[999]),'native_assignment_denied','Unknown or cross-issuer member cannot be assigned at creation');
essentialCheck(count($db->query('SELECT id FROM '.$p.'company_projects')->result_array())===$count,'Invalid member rolls back project, metadata and price');
essentialThrows(fn()=>$assignments->replace($a,92,'project',$new,$members['revision'],[]),'native_assignment_denied','Create capability cannot later change project membership');
echo 'Native project atomic member creation: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
