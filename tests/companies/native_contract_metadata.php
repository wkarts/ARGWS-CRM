<?php
/** Real draft/activation/copy/renewal, private field projections and standalone PDF. */
require __DIR__.'/contracts_operations.php';
$historicalContract=$contracts->get($a,7,$contractA);
$historicalRequests=$db->query('SELECT * FROM '.$p.'company_contract_requests ORDER BY request_key')->result_array();
require __DIR__.'/native_contract_metadata_schema_fixture.php';
$start=$GLOBALS['essentialChecks'];$fields=new NativeCompanyCustomFields($db,$p);$metadataKey=static fn($value)=>hash('sha256','contract-metadata-'.$value);
essentialCheck($contracts->get($a,7,$contractA)===$historicalContract&&$db->query('SELECT * FROM '.$p.'company_contract_requests ORDER BY request_key')->result_array()===$historicalRequests,'optional end-date migration preserves old contracts and durable request bytes');
$fieldIds=[];
foreach([['Public input','input',0,''],['Private input','input',1,''],['List','checkbox',0,'One, Two']]as$n=>[$name,$type,$private,$options]){
    $definition=NativeCompanyCustomFields::definition(['fieldto'=>'contracts','name'=>$name,'type'=>$type,'only_admin'=>$private,'options'=>$options,'show_on_table'=>1,'show_on_pdf'=>1,'show_on_client_portal'=>1]);
    $fieldIds[$n]=$fields->saveDefinition($a,7,$definition,null,null,substr($metadataKey('definition-'.$n),0,32))['id'];
}
$projects=[];
foreach([[$a,$ca,'Owned project'],[$b,$cb,'Foreign project']]as[$owner,$client,$name]){
    $db->query('INSERT INTO '.$p.'company_projects(issuer_id,client_id,responsible_id,name,description,status,start_date,due_date,revision,created_at,updated_at) VALUES(?,?,7,?,?,?, ?,?,1,?,?)',[$owner,$client,$name,'','active','2026-11-05','2027-11-05','2026-11-05 00:00:00','2026-11-05 00:00:00']);$projects[$owner]=(int)$db->insert_id();
}
$definitions=$fields->definitions($a,7,'contracts',['active'=>1],'create');
$metadata=['version'=>1,'description'=>'ADMIN DESCRIPTION NEVER PUBLIC','project'=>['source_kind'=>'company_project','source_id'=>$projects[$a],'source_revision'=>1],
    'custom_fields'=>['definitions_hash'=>CompanyContractMetadata::hash($definitions),'values'=>[$fieldIds[0]=>'ADMIN FORM FIELD',$fieldIds[1]=>'PRIVATE FIELD NEVER PUBLIC',$fieldIds[2]=>['One','Two']]]];
$input=array_replace($contractInput,['name'=>'Native optional draft','body'=>'','due_date'=>null,'native_metadata'=>$metadata]);
$id=$contracts->create($a,7,$input,$metadataKey('create'));$canonical=NativeCompanyResourceId::encode('company_contract',$id);$row=$contracts->get($a,7,$id);$admin=$contracts->getForAdmin($a,7,$id);
essentialCheck($row['body']===''&&$row['due_date']===null&&$row['snapshot_json']==='{}','information may be saved before contract content with a genuinely absent end date');
essentialCheck($admin['native_metadata']['description']===$metadata['description']&&$admin['native_metadata']['project']['source_id']===$projects[$a]&&count($admin['native_metadata']['custom_fields'])===3,'draft preserves the private description, typed project and custom fields');
$private=$db->query('SELECT * FROM '.$p.CompanyContractMetadataSchema::TABLE.' WHERE issuer_id=? AND contract_id=?',[$a,$id])->row_array();
essentialCheck(!str_contains(json_encode($row),'NEVER PUBLIC')&&!str_contains(json_encode($row),$private['content_hash'])&&array_keys($row)===array_keys($historicalContract),'public get retains its exact field names without private values or their hashes');
essentialCheck($contracts->create($a,7,$input,$metadataKey('create'))===$id,'new draft creation replay converges without recapturing metadata');
essentialThrows(fn()=>$contracts->transition($a,7,$id,1,'activate',$metadataKey('empty-content')),'company_contract_content_required','an empty draft is not emitted as an empty agreement');
$edited=$input;$edited['body']='PUBLIC CONTRACT BODY';$edited['native_metadata']['custom_fields']['values'][$fieldIds[2]]=[];
$contracts->update($a,7,$id,1,$edited,$metadataKey('edit'));$admin=$contracts->getForAdmin($a,7,$id);
$values=array_column($admin['native_metadata']['custom_fields'],'value','id');essentialCheck((int)$admin['revision']===2&&$values[$fieldIds[2]]===''&&$admin['due_date']===null,'draft edit clears an unchecked custom list without inventing an end date');
essentialCheck($contracts->update($a,7,$id,1,$edited,$metadataKey('edit'))===$id,'draft edit receipt preserves its original revision on retry');
essentialThrows(fn()=>$contracts->assertNativeMetadataWriteInOwned($db,$p,$a,7,$id,2),'native_custom_field_creation_denied','metadata proof is cleared when the final writer returns');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,CompanyContractMetadata::tables($db,$p),fn()=>$fields->saveContractMetadataInOwned($a,7,$id,2,$admin['native_metadata']['custom_fields'],$contracts)),'native_custom_field_creation_denied','opening an owned transaction cannot forge the contract revision proof');
$foreign=$edited;$foreign['native_metadata']['project']['source_id']=$projects[$b];essentialThrows(fn()=>$contracts->update($a,7,$id,2,$foreign,$metadataKey('foreign-project')),'company_project_access_denied','draft project cannot cross companies');
$stale=$edited;$stale['native_metadata']['project']['source_revision']=2;essentialThrows(fn()=>$contracts->update($a,7,$id,2,$stale,$metadataKey('stale-project')),'native_contract_project_stale','project revision is confirmed inside the contract writer');
$snapshots=static function()use($db,$p):array{$rows=[];foreach(['company_contracts',CompanyContractMetadataSchema::TABLE,'company_custom_field_values','company_contract_requests','company_contract_counters','issuers_audit']as$table)$rows[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return$rows;};
$before=$snapshots();$db->failOn='INSERT INTO '.$p.'company_contract_requests';
try{essentialThrows(fn()=>$contracts->update($a,7,$id,2,array_replace($edited,['name'=>'Must roll back']),$metadataKey('late-failure')),'issuer_storage_failed','late receipt failure aborts the complete draft update');}finally{$db->failOn=null;}
essentialCheck($snapshots()===$before,'failed draft update restores parent revision, metadata, field values and audit');
$contracts->transition($a,7,$id,2,'activate',$metadataKey('activate'));$issued=$contracts->get($a,7,$id);$issuedAdmin=$contracts->getForAdmin($a,7,$id);
essentialCheck($issued['due_date']===null&&json_decode($issued['snapshot_json'],true)['due_date']===null&&(int)$db->query('SELECT frozen FROM '.$p.CompanyContractMetadataSchema::TABLE.' WHERE issuer_id=? AND contract_id=?',[$a,$id])->row()->frozen===1,'activation freezes private metadata and keeps the absent end date in the public snapshot');
essentialCheck(array_keys(json_decode($issued['snapshot_json'],true))===array_keys(json_decode($historicalContract['snapshot_json'],true))&&!str_contains($issued['snapshot_json'],'ADMIN ')&&!str_contains($issued['snapshot_json'],'PRIVATE FIELD'),'new emitted public snapshot keeps the previous shape and no private metadata');
essentialThrows(fn()=>$contracts->update($a,7,$id,3,$edited,$metadataKey('issued-edit')),'company_contract_immutable','metadata cannot reopen an issued contract for editing');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,array_merge($fields->tableNames(),['company_contracts',CompanyContractMetadataSchema::TABLE]),fn()=>$fields->saveInOwnedTransaction($a,7,'contracts',$canonical,[$fieldIds[1]=>'changed'])),'native_contract_fields_require_draft_form','generic field mutation cannot alter private data outside the draft writer');

$db->query('INSERT INTO '.$p.'staff(staffid,active,admin,firstname,lastname) VALUES(17,1,0,?,?)',['Contract','member']);
$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,17,1)',[$a]);
foreach([['contracts','view'],['contracts','create'],['contracts','edit'],['customers','view'],['projects','view']]as[$feature,$cap])$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,17,?,?)',[$a,$feature,$cap]);
essentialCheck(!str_contains(json_encode($contracts->getForAdmin($a,17,$id)),'PRIVATE FIELD NEVER PUBLIC'),'ordinary contract member cannot read the captured administrative field');
$privateRef=NativeCompanyResourceId::decode('custom_field',$fieldIds[1]);$definitionRow=$db->query('SELECT definition_json FROM '.$p.'company_custom_fields WHERE issuer_id=? AND id=?',[$a,$privateRef['source_id']])->row_array();$definition=json_decode($definitionRow['definition_json'],true);$definition['only_admin']=0;
$fields->saveDefinition($a,7,$definition,$fieldIds[1],1);
essentialCheck(!str_contains(json_encode($contracts->getForAdmin($a,17,$id)),'PRIVATE FIELD NEVER PUBLIC')&&!array_key_exists($fieldIds[1],$fields->values($a,17,'contracts',$canonical)),'relaxing a field definition does not publish the captured private value through either reader');
$db->query('UPDATE '.$p.'company_projects SET name=?,revision=2 WHERE issuer_id=? AND id=?',['Current project title',$a,$projects[$a]]);
essentialCheck($contracts->create($a,7,$input,$metadataKey('create'))===$id&&$contracts->getForAdmin($a,7,$id)['native_metadata']['project']['name']==='Owned project','create replay and original project caption remain stable after unrelated definition/project edits');
$copy=$contracts->transition($a,17,$id,3,'copy',$metadataKey('copy'));$copied=$contracts->getForAdmin($a,7,$copy);
essentialCheck($copied['status']==='draft'&&$copied['due_date']===null&&$copied['native_metadata']['project']['source_revision']===2&&$copied['native_metadata']['description']===$metadata['description'],'copy keeps optional end date/private fields and refreshes a verified current project reference');
essentialCheck(in_array('PRIVATE FIELD NEVER PUBLIC',array_column($copied['native_metadata']['custom_fields'],'value'),true)&&!str_contains(json_encode($contracts->getForAdmin($a,17,$copy)),'PRIVATE FIELD NEVER PUBLIC'),'copy by an ordinary member preserves a private value without revealing it');
$renewed=$contracts->renew($a,7,$id,3,'2028-01-01','',$metadataKey('renew'));$renewal=$contracts->getForAdmin($a,7,$renewed);
essentialCheck($renewal['due_date']===null&&$renewal['native_metadata']===$copied['native_metadata']&&$contracts->get($a,7,$id)===$issued,'renewal copies private fields and typed project while leaving the issued snapshot and body unchanged');
$pdf=CompanyContractsPdf::render($issued);$temp=tempnam(sys_get_temp_dir(),'contract-metadata-');file_put_contents($temp,$pdf);$text=shell_exec('pdftotext '.escapeshellarg($temp).' -');unlink($temp);
essentialCheck(str_starts_with($pdf,'%PDF-')&&is_string($text)&&str_contains($text,'PUBLIC CONTRACT BODY')&&!str_contains($text,'ADMIN ')&&!str_contains($text,'PRIVATE FIELD')&&!str_contains($text,' até '),'actual PDF contains the public agreement and no private fields or fictitious final date');
$saved=$db->query('SELECT data_json,content_hash FROM '.$p.CompanyContractMetadataSchema::TABLE.' WHERE issuer_id=? AND contract_id=?',[$a,$id])->row_array();$tampered=json_decode($saved['data_json'],true);$tampered['description']='Changed storage';
$db->query('UPDATE '.$p.CompanyContractMetadataSchema::TABLE.' SET data_json=?,content_hash=? WHERE issuer_id=? AND contract_id=?',[json_encode($tampered,JSON_THROW_ON_ERROR),CompanyContractMetadata::hash($tampered),$a,$id]);
essentialThrows(fn()=>$contracts->getForAdmin($a,7,$id),'native_contract_metadata_unconfirmed','changing data and its direct hash cannot bypass the frozen audit binding');
$db->query('UPDATE '.$p.CompanyContractMetadataSchema::TABLE.' SET data_json=?,content_hash=? WHERE issuer_id=? AND contract_id=?',[$saved['data_json'],$saved['content_hash'],$a,$id]);
essentialCheck($contracts->get($a,7,$contractA)===$historicalContract,'the original historical DTO remains byte-for-byte stable after all new-field operations');
$type=(new NativeCompanyContractTypes($db,$p))->save($a,7,null,null,'Optional draft type',$metadataKey('type'));
$optional=array_replace($contractInput,['name'=>'Optional amount draft','contract_value'=>'12.50','currency'=>'BRL','contract_type'=>$type['id'],'native_metadata'=>['version'=>1]]);
$amountId=$contracts->create($a,7,$optional,$metadataKey('amount-create'));
$oldEdit=$contractInput;$oldEdit['contract_value']='';$contracts->update($a,7,$amountId,1,$oldEdit,$metadataKey('amount-old-client'));
essentialCheck(CompanyContractValue::stored(json_decode($contracts->get($a,7,$amountId)['snapshot_json'],true))===['contract_value'=>'12.50','currency'=>'BRL'],'old edit payload still preserves an omitted optional amount');
$clear=$oldEdit;$clear['contract_type']='';$clear['native_metadata']=['version'=>1,'clear_value'=>1];$contracts->update($a,7,$amountId,2,$clear,$metadataKey('amount-clear'));
essentialCheck($contracts->get($a,7,$amountId)['snapshot_json']==='{}'&&!array_key_exists('clear_value',$contracts->getForAdmin($a,7,$amountId)['native_metadata']),'new form explicitly clears amount/type without storing its command flag in either metadata or public snapshot');
$ambiguous=$optional;$ambiguous['native_metadata']['clear_value']=1;
essentialThrows(fn()=>$contracts->create($a,7,$ambiguous,$metadataKey('ambiguous-clear')),'native_contract_metadata_invalid','an explicit amount cannot coexist with its removal command');
$otherClient=$clients->create($a,7,['company'=>'Second contract client']);$reassign=$clear;$reassign['client_id']=$otherClient;$contracts->update($a,7,$amountId,3,$reassign,$metadataKey('client-reassign'));
essentialCheck((int)$contracts->get($a,7,$amountId)['client_id']===$otherClient,'compatible native information form can select another authorized customer on a draft');
$retarget=$edited;$retarget['client_id']=$otherClient;$retarget['native_metadata']=['version'=>1];
essentialThrows(fn()=>$contracts->update($a,7,$copy,1,$retarget,$metadataKey('mismatched-existing-project')),'native_contract_project_denied','changing a draft customer cannot retain a project belonging to its previous customer');
require_once APPPATH.'services/NativeCompanyContractIndex.php';
if(!function_exists('_l')){function _l($key){return$key;}}
$tableInput=['draw'=>'1','start'=>'0','length'=>'25','search'=>['value'=>'PRIVATE FIELD NEVER PUBLIC','regex'=>'false'],'order'=>[['column'=>'0','dir'=>'asc']],'columns'=>array_map(static fn($n)=>['data'=>(string)$n,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']],range(0,11))];
$index=new NativeCompanyContractIndex($db,$p);
essentialCheck($index->page($a,17,$tableInput)['recordsFiltered']===0,'table search cannot reveal a formerly private value after its current definition becomes public');
$tableInput['search']['value']='Owned project';$indexed=$index->page($a,7,$tableInput);
essentialCheck($indexed['recordsFiltered']===1&&$indexed['rows'][0]['id']===$canonical&&$indexed['rows'][0]['project_name']==='Owned project'&&(string)$indexed['rows'][0]['project_id']===NativeCompanyResourceId::encode('company_project',$projects[$a]),'original project column/search use the captured exact typed relation instead of every contract of the same customer');
$tableInput['search']['value']='Current project title';essentialCheck($index->page($a,7,$tableInput)['recordsFiltered']===2,'copy and renewal retain their independently captured current project title');
$hiddenField=$fields->saveDefinition($a,7,NativeCompanyCustomFields::definition(['fieldto'=>'contracts','name'=>'Historical inactive private','type'=>'input','only_admin'=>1]),null,null,substr($metadataKey('old-hidden-field'),0,32));
$oldDraft=$contracts->create($a,7,$contractInput,$metadataKey('owned-pre-metadata-draft'));$oldCanonical=NativeCompanyResourceId::encode('company_contract',$oldDraft);
IssuerTransaction::run($db,$p,array_merge($fields->tableNames(),['company_contracts']),fn()=>$fields->saveInOwnedTransaction($a,7,'contracts',$oldCanonical,[$hiddenField['id']=>'OLD PRIVATE PROJECTION']));
$inactive=NativeCompanyCustomFields::definition(['fieldto'=>'contracts','name'=>'Historical inactive private','type'=>'input','only_admin'=>1,'active'=>0]);$fields->saveDefinition($a,7,$inactive,$hiddenField['id'],1);
$oldDefinitions=$fields->contractFormDefinitions($a,17,$oldDraft,'edit');$nativeEdit=$contractInput;$nativeEdit['native_metadata']=['version'=>1,'description'=>'First native information edit','custom_fields'=>['definitions_hash'=>CompanyContractMetadata::hash($oldDefinitions),'values'=>[]]];
$contracts->update($a,17,$oldDraft,1,$nativeEdit,$metadataKey('first-native-edit'));
$oldData=json_decode($db->query('SELECT data_json FROM '.$p.CompanyContractMetadataSchema::TABLE.' WHERE issuer_id=? AND contract_id=?',[$a,$oldDraft])->row_array()['data_json'],true);
essentialCheck(in_array('OLD PRIVATE PROJECTION',array_column($oldData['custom_fields'],'value'),true)&&!str_contains(json_encode($contracts->getForAdmin($a,17,$oldDraft)),'OLD PRIVATE PROJECTION'),'first native edit of an existing owned draft preserves inactive private projections without revealing them');
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=17 AND feature=? AND capability=?',[$a,'projects','view']);
$preserve=array_replace($contractInput,['due_date'=>null,'native_metadata'=>['version'=>1,'description'=>'Allowed information edit without Projects permission']]);$contracts->update($a,17,$copy,1,$preserve,$metadataKey('preserve-hidden-project'));
essentialCheck($contracts->getForAdmin($a,7,$copy)['native_metadata']['project']['source_id']===$projects[$a]&&$contracts->getForAdmin($a,17,$copy)['native_metadata']['project']===null,'a draft edit without Projects permission preserves the proven relation without hydrating it');
echo 'Private contract metadata/draft/activation/copy/renewal: '.($GLOBALS['essentialChecks']-$start).' added / '.$GLOBALS['essentialChecks'].' cumulative PASS ('.$db->driver().').'.PHP_EOL;
