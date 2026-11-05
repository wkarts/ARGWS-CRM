<?php
/** Real issuance/reader/ledger and native schemas; no browser, SMTP or external financial call. */
require __DIR__.'/credits_operations.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
require_once APPPATH.'services/CompanyCreditMetadata.php';
require_once APPPATH.'services/CompanyCredits.php';
$start=$GLOBALS['essentialChecks'];$reader=new CompanyCredits($db,$p);$fields=new NativeCompanyCustomFields($db,$p);
$historical=$reader->get($a,7,$credit);
$historicalReceipt=$db->query('SELECT * FROM '.$p.'company_credit_documents WHERE credit_id=?',[$credit])->row_array();
$ids=[];
foreach ([['credit_note','Public field',0,1],['credit_note','Admin field',1,1],['credit_note','Internal field',0,0],['items','Line public',0,1],['items','Line admin',1,1]] as $n=>[$to,$name,$private,$pdf]) {
    $definition=NativeCompanyCustomFields::definition(['fieldto'=>$to,'name'=>$name,'type'=>'input','only_admin'=>$private,'show_on_pdf'=>$pdf,'show_on_table'=>1]);
    $saved=$fields->saveDefinition($a,7,$definition,null,null,substr(hash('sha256','credit-metadata-field-'.$n),0,32));$ids[$n]=$saved['id'];
}
foreach ([[$a,$ca,'A project'],[$b,$cb,'B project']] as [$owner,$client,$name]) {
    $db->query('INSERT INTO '.$p.'company_projects(issuer_id,client_id,responsible_id,name,description,start_date,due_date,status,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,?)',[$owner,$client,7,$name,'','2026-10-19','2026-11-19','active','2026-10-19 00:00:00','2026-10-19 00:00:00']);$projects[$owner]=(int)$db->insert_id();
}
$make=function(int $actor=7)use($fields,$a,$ids,$projects):array {
    $header=$fields->definitions($a,$actor,'credit_note',['active'=>1],'create');$lines=$fields->creditItemDefinitions($a,$actor,'create',['active'=>1]);
    $headerValues=[$ids[0]=>'PUBLIC-METADATA',$ids[2]=>'INTERNAL-METADATA'];$lineValues=[$ids[3]=>'PUBLIC-LINE'];
    if($actor===7){$headerValues[$ids[1]]='ADMIN-SECRET-METADATA';$lineValues[$ids[4]]='ADMIN-SECRET-LINE';}
    return ['version'=>1,'reference_no'=>'REF-402','adminnote'=>'ADMIN-NOTE-SECRET','terms'=>'Explicit customer terms','billing_street'=>'Billing street','shipping_street'=>'Shipping street',
        'include_shipping'=>1,'show_shipping_on_credit_note'=>1,'show_quantity_as'=>2,'project'=>['source_kind'=>'company_project','source_id'=>$projects[$a],'source_revision'=>1],
        'items'=>[['long_description'=>"Full detail <script>\nSecond line",'unit'=>'hour']],
        'custom_fields'=>['definitions_hash'=>CompanyCreditMetadata::hash($header),'values'=>$headerValues],
        'item_fields'=>['definitions_hash'=>CompanyCreditMetadata::hash($lines),'values'=>[$lineValues]]];
};
$request=$ci+['native_metadata'=>$make()];$key=hash('sha256','credit-metadata-issue');
$new=$writer->issueCompanyCredit($a,7,$request,$key);
$public=$reader->get($a,7,$new);$admin=$reader->getForAdmin($a,7,$new);
essentialCheck($public['items']===$historical['items']&&array_keys($public)===array_keys($historical),'public financial DTO and line shape remain unchanged for metadata credits');
essentialCheck(!str_contains(json_encode($public),'ADMIN-')&&!str_contains(json_encode($public),'INTERNAL-METADATA'),'private metadata never enters the public DTO');
essentialCheck($admin['metadata']['reference_no']==='REF-402'&&$admin['metadata']['adminnote']==='ADMIN-NOTE-SECRET'&&$admin['metadata']['items'][0]['unit']==='hour','administrative reader roundtrips reference, private note and line unit');
essentialCheck($admin['identity']['terms']==='Explicit customer terms'&&$admin['metadata']['items'][0]['long_description']==="Full detail <script>\nSecond line",'terms and multiline description survive issuance exactly');
essentialCheck($admin['metadata']['project']['name']==='A project'&&(int)$db->query('SELECT project_id FROM '.$p.'creditnotes WHERE id=?',[$new])->row()->project_id===0,'typed owned project snapshot never becomes a legacy project foreign key');
essentialCheck(count($admin['metadata']['custom_fields'])===3&&count($admin['metadata']['item_fields'][0])===2,'header and line custom values persist under their separate credit namespaces');
$receipt=$db->query('SELECT * FROM '.$p.'company_credit_documents WHERE credit_id=?',[$new])->row_array();$stored=json_decode($receipt['items_json'],true,32,JSON_THROW_ON_ERROR);
$full=['input'=>$stored['native_metadata'],'snapshot'=>$stored['native_snapshot']];
$pdfFields=CompanyCreditMetadata::present($db,$p,$a,7,$full,'pdf');
essentialCheck(!isset($pdfFields['adminnote'])&&array_column($pdfFields['custom_fields'],'value')===['PUBLIC-METADATA']&&array_column($pdfFields['item_fields'][0],'value')===['PUBLIC-LINE'],'PDF presentation excludes private and non-PDF fields even for an administrator');
essentialCheck($reader->get($a,7,$credit)===$historical&&$db->query('SELECT * FROM '.$p.'company_credit_documents WHERE credit_id=?',[$credit])->row_array()===$historicalReceipt,'historical DTO and persisted receipt bytes remain unchanged');
essentialCheck($writer->issueCompanyCredit($a,7,$ci,$ck('issue'))===$credit,'historical creation retry retains its original hash and credit ID');
essentialCheck($writer->issueCompanyCredit($a,7,$request,$key)===$new,'metadata retry returns the same durable credit');
$changed=$request;$changed['native_metadata']['reference_no']='OTHER';essentialThrows(fn()=>$writer->issueCompanyCredit($a,7,$changed,$key),'company_credit_request_conflict','reference cannot change under the same idempotency key');
$changed=$request;$changed['native_metadata']['project']['source_id']=$projects[$b];essentialThrows(fn()=>$writer->issueCompanyCredit($a,7,$changed,hash('sha256','credit-metadata-foreign-project')),'company_project_access_denied','foreign company project cannot enter the snapshot');
$changed=$request;$changed['native_metadata']['project']['source_revision']=2;essentialThrows(fn()=>$writer->issueCompanyCredit($a,7,$changed,hash('sha256','credit-metadata-stale-project')),'company_credit_project_stale','project revision is confirmed inside the issuance transaction');
essentialThrows(fn()=>$writer->assertNativeCreditCreationInOwned($db,$p,$a,7,$new),'native_custom_field_creation_denied','creation authority is cleared before the writer returns');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,array_merge($fields->tableNames(),['creditnotes','company_credit_documents','itemable']),fn()=>$fields->saveCreatedCreditInOwnedTransaction($a,7,$new,null,[$ids[0]=>'forged'],$writer)),'native_custom_field_creation_denied','a caller-owned transaction cannot forge a fresh credit proof');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,array_merge($fields->tableNames(),['creditnotes','company_credit_documents']),fn()=>$fields->saveInOwnedTransaction($a,7,'credit_note',$new,[$ids[0]=>'changed'])),'company_credit_immutable','generic custom-field edit cannot rewrite newly emitted metadata');

// A create-only credit actor has no invoice, catalogue or edit grant.
$db->query('INSERT INTO '.$p.'staff(staffid,active,admin) VALUES(17,1,0)');$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,17,1)',[$a]);
foreach ([['credit_notes','view'],['credit_notes','create'],['customers','view'],['projects','view']] as [$feature,$cap]) $db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,17,?,?)',[$a,$feature,$cap]);
$restricted=$ci+['native_metadata'=>$make(17)];$own=$writer->issueCompanyCredit($a,17,$restricted,hash('sha256','credit-metadata-create-only'));
essentialCheck($reader->getForAdmin($a,17,$own)['metadata']['custom_fields'][0]['value']==='PUBLIC-METADATA','create-only actor persists authorized custom values without invoice/item/edit permission');
essentialCheck(!str_contains(json_encode($reader->getForAdmin($a,17,$new)['metadata']),'ADMIN-SECRET'),'ordinary credit viewer cannot hydrate captured administrative custom fields');
$forged=$restricted;$forged['native_metadata']['custom_fields']['values'][$ids[1]]='private';essentialThrows(fn()=>$writer->issueCompanyCredit($a,17,$forged,hash('sha256','credit-metadata-forged-private')),'native_custom_field_denied','hidden custom field ID cannot be submitted by an ordinary actor');

$counts=static function()use($db,$p):array{$out=[];foreach(['creditnotes','itemable','company_credit_documents','company_custom_field_values','issuers_sequences','issuers_audit','company_financial_events']as$table)$out[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();return$out;};
$bounded=$request;$bounded['native_metadata']['terms']=str_repeat('T',10000);$boundedId=$writer->issueCompanyCredit($a,7,$bounded,hash('sha256','credit-metadata-terms-boundary'));
essentialCheck($reader->getForAdmin($a,7,$boundedId)['identity']['terms']===$bounded['native_metadata']['terms'],'optional terms preserve the full existing identity limit');
$bounded['native_metadata']['terms'].='T';$before=$counts();
essentialThrows(fn()=>$writer->issueCompanyCredit($a,7,$bounded,hash('sha256','credit-metadata-terms-overflow')),'company_credit_metadata_invalid','terms beyond the canonical identity limit are rejected at input normalization');
essentialCheck($counts()===$before,'invalid terms do not allocate a number, write a note, or persist custom values');
$before=$counts();$db->failOn='INSERT INTO '.$p.'issuers_audit';
try{essentialThrows(fn()=>$writer->issueCompanyCredit($a,7,$request,hash('sha256','credit-metadata-audit-rollback')),'issuer_storage_failed','late metadata audit failure aborts issuance');}finally{$db->failOn=null;}
essentialCheck($counts()===$before,'late failure rolls back note, sequence, lines, custom values, metadata and journal');

$trigger='credit_header_'.substr(hash('sha256',$p),0,16);$before=$counts();
$db->query($db->driver()==='sqlite'?'CREATE TRIGGER '.$trigger.' AFTER INSERT ON '.$p."creditnotes BEGIN UPDATE ".$p."creditnotes SET reference_no='altered by fixture' WHERE id=NEW.id; END":'CREATE TRIGGER '.$trigger.' BEFORE INSERT ON '.$p."creditnotes FOR EACH ROW SET NEW.reference_no='altered by fixture'");
try{essentialThrows(fn()=>$writer->issueCompanyCredit($a,7,$request,hash('sha256','credit-metadata-header-tamper')),'company_credit_balance_review','actual SQL trigger changing native reference prevents confirming issuance');}finally{$db->query('DROP TRIGGER '.$trigger);}
essentialCheck($counts()===$before,'header readback failure rolls back the complete document, sequence, CF and journal');

$db->query('UPDATE '.$p.'company_projects SET name=?,revision=2 WHERE id=? AND issuer_id=?',['Renamed now',$projects[$a],$a]);
essentialCheck($writer->issueCompanyCredit($a,7,$request,$key)===$new&&$reader->getForAdmin($a,7,$new)['metadata']['project']['name']==='A project','retry and historical title survive later project changes without recapturing data');
$tampered=$stored;$tampered['native_snapshot']['project']['name']='Tampered title';$db->query('UPDATE '.$p.'company_credit_documents SET items_json=? WHERE credit_id=?',[json_encode($tampered,JSON_THROW_ON_ERROR),$new]);
essentialThrows(fn()=>$reader->get($a,7,$new),'company_credit_balance_review','derived snapshot is bound to the immutable metadata audit');
$db->query('UPDATE '.$p.'company_credit_documents SET items_json=? WHERE credit_id=?',[$receipt['items_json'],$new]);
$line=$db->query('SELECT id FROM '.$p.'itemable WHERE rel_id=? AND rel_type=?',[$new,'credit_note'])->row()->id;
$db->query('UPDATE '.$p.'itemable SET unit=? WHERE id=?',['tampered',$line]);essentialThrows(fn()=>$reader->getForAdmin($a,7,$new),'company_credit_balance_review','administrative reader rejects divergence from native item projection');$db->query('UPDATE '.$p.'itemable SET unit=? WHERE id=?',['hour',$line]);
essentialCheck(str_starts_with($reader->pdf($a,7,$new),'%PDF-'),'metadata document still renders a real authorized PDF');
echo 'Credit immutable optional metadata: '.($GLOBALS['essentialChecks']-$start).' added / '.$GLOBALS['essentialChecks'].' cumulative checks PASS ('.$db->driver().').'.PHP_EOL;
