<?php
/** Original Sales wire and typed services against actual disposable SQL storage. No browser claim. */
require __DIR__.'/sales_mutations_setup.php';
require_once APPPATH.'services/NativeCompanySalesOriginalForm.php';
require_once APPPATH.'services/NativeCompanySalesProjection.php';
require_once APPPATH.'services/NativeCompanyAuxiliarySchema.php';
require_once APPPATH.'services/NativeCompanyWorkTagsSchema.php';
require_once APPPATH.'services/NativeCompanyResourceTags.php';
require_once APPPATH.'services/CompanyInvoiceProjectSchema.php';
require_once APPPATH.'services/CompanyProjectsSchema.php';
if(!function_exists('_l')){function _l($key,$args=[]){return$key;}} // Translation boundary only.
$originalFormStart=$GLOBALS['essentialChecks'];
foreach(['company_taxes','company_item_groups','company_items']as$table)if(!$db->table_exists($p.$table)){
    $ddl='CREATE TABLE '.$p.$table.'('.implode(',',NativeCompanyAuxiliarySchema::definitions($p)[$table]).') ENGINE=InnoDB';
    if($db->driver()==='sqlite')$ddl=preg_replace('/ CHARACTER SET utf8mb4 COLLATE utf8mb4_bin/','',$ddl);$db->query($ddl);
}
foreach(['tags'=>'id','taggables'=>'id','items'=>'id','items_groups'=>'id','taxes'=>'id','customfields'=>'id','customfieldsvalues'=>'id']as$table=>$key)if(!$db->table_exists($p.$table)){
    if(!preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match))throw new RuntimeException('Native schema missing '.$table);
    $ddl=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$match[0]);
    $ddl=str_replace('`'.$key.'` int NOT NULL,','`'.$key.'` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$ddl);
    if($db->driver()==='sqlite'){$ddl=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$ddl);$ddl=preg_replace('/enum\([^)]*\)/','varchar(50)',$ddl);}$db->query($ddl);
}
foreach(['firstname','lastname']as$column)if(!$db->field_exists($column,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$column." varchar(50) NOT NULL DEFAULT ''");
$db->query('UPDATE '.$p.'staff SET firstname=?,lastname=? WHERE staffid=7',['Synthetic','Administrator']);
NativeCompanyWorkTagsSchema::ensure($db,$p);NativeCompanyCustomFieldSchema::ensure($db,$p);
if(!$db->table_exists($p.'company_projects')){
    $ddl='CREATE TABLE '.$p.'company_projects('.implode(',',CompanyProjectsSchema::definitions($p)['company_projects']).') ENGINE=InnoDB';
    if($db->driver()==='sqlite')$ddl=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$ddl);$db->query($ddl);
}
CompanyInvoiceProjectSchema::ensure($db,$p);
NativeCompanySalesProjectionSchema::ensure($db,$p);(new NativeCompanySalesProjection($db,$p))->backfill();
unset($db->data_cache['table_names']);
$fields=new NativeCompanyCustomFields($db,$p);$tags=new NativeCompanyResourceTags($db,$p);$originalHeaders=[];
foreach(['estimate','proposal','invoice']as$type){
    $definition=NativeCompanyCustomFields::definition(['fieldto'=>$type,'name'=>'Original Header','type'=>'input','show_on_client_portal'=>1,'show_on_pdf'=>1]);
    $db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,$type,$type.'_original_header',json_encode($definition,JSON_THROW_ON_ERROR)]);
    $originalHeaders[$type]=NativeCompanyResourceId::encode('company_custom_field',(int)$db->insert_id());
}
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'items','name'=>'Original line field','type'=>'input','required'=>1]);
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'items','items_original_line',json_encode($definition,JSON_THROW_ON_ERROR)]);
$originalLineField=NativeCompanyResourceId::encode('company_custom_field',(int)$db->insert_id());
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'items','name'=>'Selected line choices','type'=>'checkbox','options'=>'A, B']);
$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'items','items_original_choices',json_encode($definition,JSON_THROW_ON_ERROR)]);
$originalChoiceField=NativeCompanyResourceId::encode('company_custom_field',(int)$db->insert_id());
$db->query('INSERT INTO '.$p.'company_taxes(id,issuer_id,name,taxrate,revision) VALUES(1,?,?,?,1)',[$a,'VAT "<safe>','5.0000']);
$db->query('INSERT INTO '.$p.'company_item_groups(id,issuer_id,name,revision) VALUES(1,?,?,1)',[$a,'Original group']);
$db->query('INSERT INTO '.$p.'company_items(id,issuer_id,description,long_description,rate,unit,tax,tax2,group_id,currency_rates_json,revision) VALUES(1,?,?,?,?,?,1,NULL,1,?,1)',[$a,'Original catalogue item','Long description','50.0000','hour','{}']);
$db->query('INSERT INTO '.$p.'company_item_groups(id,issuer_id,name,revision) VALUES(2,?,?,1)',[$b,'Private B group']);
$db->query('INSERT INTO '.$p.'company_items(id,issuer_id,description,long_description,rate,unit,tax,tax2,group_id,currency_rates_json,revision) VALUES(2,?,?,?,?,?,NULL,NULL,2,?,1)',[$b,'Private B item','Private B details','90.0000','hour','{}']);
$originalSelection=new NativeCompanySalesSequencePreferences($db,$p);
$settings->mutate($a,7,'update','dateformat','Y-m-d|%Y-%m-%d');
/** Exact controls from the existing form; no fabricated catalogue/invoice line identity. */
function originalSalesWire(string $kind,array $data,int $client,string $header,string $lineField,string $choiceField):array{
    $wire=['_native_sales_id'=>$data['native_sales_id'],'_native_sales_revision'=>$data['native_sales_revision'],'_native_sales_request_key'=>$data['native_sales_request_key'],
        '_native_sales_number_revision'=>$data['native_sales_number_selection']['revision'],'_native_sales_number'=>$data['native_sales_number_selection']['next_number'],
        '_native_sales_metadata_revision'=>$data['native_sales_metadata_revision'],'number'=>str_pad($data['native_sales_number_selection']['next_number'],6,'0',STR_PAD_LEFT),
        'clientid'=>(string)$client,'project_id'=>'','currency'=>'1','date'=>'2026-01-31','expirydate'=>'','status'=>'1','reference_no'=>'REF-original',
        'newitems'=>['4'=>['order'=>'1','description'=>'Original <literal> item','long_description'=>'Long line details','qty'=>'2','rate'=>'50','unit'=>'hour','taxname'=>['VAT "<safe>|5.0000'],
            'custom_fields'=>['items'=>[$lineField=>'Private line value',$choiceField=>['A','B']]]],
            '19'=>['order'=>'2','description'=>'Optional line','long_description'=>'Optional details','qty'=>'1','rate'=>'20','unit'=>'unit','taxname'=>[],'is_optional'=>'1','is_selected'=>'0',
                'custom_fields'=>['items'=>[$lineField=>'Optional private value',$choiceField=>['B']]]]],
        'discount_percent'=>'0','discount_total'=>'0.00','discount_type'=>'','adjustment'=>'','subtotal'=>'100.00','total'=>'105.00',
        'tags'=>'Original, Reviewed','show_quantity_as'=>'2','custom_fields'=>[$kind==='estimates'?'estimate':'proposal'=>[$header=>'Header value']]];
    if($kind==='estimates')$wire+=['clientnote'=>'Original note','terms'=>'Original terms','adminnote'=>'Internal note','sale_agent'=>'7','billing_street'=>'Billing original'];
    else{unset($wire['clientid'],$wire['expirydate'],$wire['number'],$wire['reference_no']);$wire+=['rel_type'=>'customer','rel_id'=>(string)$client,'subject'=>'Proposal subject','proposal_to'=>'Client original','address'=>'Proposal address','city'=>'City','state'=>'State','zip'=>'40000','country'=>'0','email'=>'original@example.invalid','phone'=>'12345','assigned'=>'7','allow_comments'=>'on','open_till'=>'','content'=>'<p>Original <strong>proposal</strong></p>{proposal_items}<script>alert(1)</script>'];}
    if($data['native_sales_id']!=='')$wire+=['_native_sales_reason'=>'Reviewed original fields','_native_sales_confirmed'=>'1'];
    return$wire;
}
$originalSources=[];$originalForms=[];$originalWires=[];
foreach(['estimates'=>'estimate','proposals'=>'proposal']as$kind=>$type){
    $form=new NativeCompanySalesOriginalForm($db,$p,$kind);$data=$form->data($a,7,'',$ca);$wire=originalSalesWire($kind,$data,$ca,$originalHeaders[$type],$originalLineField,$originalChoiceField);
    essentialCheck($data['native_sales_editor_items']instanceof NativeCompanySalesEditorItems&&$data['native_sales_editor_items']->rows()===[],'original '.$kind.' create uses empty typed line snapshot');
    essentialCheck(str_contains(json_encode($data['items']),'Original catalogue item')&&!str_contains(json_encode($data),'Private B item'),'original '.$kind.' data contains only owned catalogue and labels');
    $saved=$form->save($a,7,$wire);$source=$saved['source_id'];$review=$sales->review($a,7,$source,'edit');$document=$sales->document($a,7,$source);
    essentialCheck($document['kind']===$kind&&CompanyInvoiceAmounts::minor((string)$document['total'])===10500&&$document['payload']['due_date']===null,'original '.$kind.' saves amounts and optional expiry through canonical writer');
    essentialCheck($form->save($a,7,$wire)===$saved,'original '.$kind.' create replay keeps number and identity');
    essentialCheck(($fields->values($a,7,$type,$saved['id'])[$originalHeaders[$type]]??null)==='Header value'&&array_column($tags->read($a,7,$type,$saved['id'])['tags'],'name')===['Original','Reviewed'],'original '.$kind.' stores real header values and tags');
    essentialCheck(count($review['payload']['native_item_fields']['values'])===2&&!isset($document['payload']['native_item_fields'],$document['payload']['native_custom_fields']),'original '.$kind.' private line/header values stay outside generic DTO');
    if($kind==='proposals')essentialCheck($document['payload']['native_proposal']['subject']==='Proposal subject'&&$document['payload']['native_proposal']['proposal_to']==='Client original'&&!str_contains($document['payload']['native_proposal']['content'],'script')&&str_contains($document['payload']['native_proposal']['content'],'<strong>proposal</strong>'),'proposal keeps original metadata and sanitizes rich content');
    else essentialCheck($document['payload']['native_fields']['terms']==='Original terms'&&$document['payload']['native_fields']['billing_street']==='Billing original','estimate retains original note terms and billing');
    $edit=$form->data($a,7,$saved['id']);$rows=$edit['native_sales_editor_items']->rows();
    essentialCheck(count($rows)===2&&!isset($rows[0]['id'])&&$edit['native_sales_editor_items']->values($rows[0])[$originalChoiceField]==='A, B','original '.$kind.' editor uses positions only and formats checkbox values');
    $changed=originalSalesWire($kind,$edit,$ca,$originalHeaders[$type],$originalLineField,$originalChoiceField);$changed['newitems']['4']['qty']='3';$changed['subtotal']='150.00';$changed['total']='157.50';$changed['tags']='Changed, Reviewed';$changed['custom_fields'][$type][$originalHeaders[$type]]='Edited header';$changed['newitems']['4']['custom_fields']['items'][$originalLineField]='Edited private line';
    $form->save($a,7,$changed);$after=$sales->document($a,7,$source);
    essentialCheck($after['number']===$document['number']&&CompanyInvoiceAmounts::minor((string)$after['total'])===15750&&$after['context']===$document['context'],'original '.$kind.' edit preserves number/fiscal snapshot and recalculates full lines');
    essentialCheck(($fields->values($a,7,$type,$saved['id'])[$originalHeaders[$type]]??null)==='Edited header'&&array_column($tags->read($a,7,$type,$saved['id'])['tags'],'name')===['Changed','Reviewed'],'original '.$kind.' edit atomically updates real metadata');
    essentialCheck($form->save($a,7,$changed)===$saved,'original '.$kind.' edit replay survives advanced revision');
    $stale=$changed;$stale['_native_sales_request_key']=hash('sha256','original-stale-'.$kind);essentialThrows(fn()=>$form->save($a,7,$stale),'company_sales_stale','original '.$kind.' old review cannot overwrite new version');
    essentialThrows(fn()=>$form->data($b,7,$saved['id']),'company_sales_denied','original '.$kind.' edit cannot read another company');
    $copiedReview=$sales->review($a,7,$source,'copy');$copied=$sales->copy($a,7,$source,$copiedReview['payload'],['source_revision'=>$copiedReview['source_revision'],'destination_revision'=>$copiedReview['destination_revision'],'reason'=>'Reviewed original copy','copy_confirmed'=>'1'],hash('sha256','original-copy-'.$kind));
    $copiedPayload=$sales->review($a,7,$copied,'edit')['payload'];essentialCheck($copiedPayload['native_item_fields']===$copiedReview['payload']['native_item_fields']&&$copiedPayload['native_custom_fields']===$copiedReview['payload']['native_custom_fields'],'original '.$kind.' copy preserves private metadata under canonical lineage');
    $sales->decide($a,7,$source,'accepted');essentialCheck($form->save($a,7,$changed)===$saved,'original '.$kind.' committed edit replay survives later acceptance');
    $invoice=$sales->convert($a,7,$source);$actual=$db->query("SELECT id FROM {$p}itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id",[$invoice])->result_array();
    essentialCheck(count($actual)===2&&($fields->values($a,7,'items',(int)$actual[0]['id'])[$originalLineField]??null)==='Edited private line'&&($fields->values($a,7,'items',(int)$actual[1]['id'])[$originalLineField]??null)==='Optional private value','original '.$kind.' converts line metadata onto real canonical invoice items');
    essentialCheck(($fields->values($a,7,'invoice',$invoice)[$originalHeaders['invoice']]??null)==='Edited header'&&$sales->convert($a,7,$source)===$invoice,'original '.$kind.' header transfer and conversion replay preserve destination');
    $audit=$db->query("SELECT metadata_json FROM {$p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_sales_item_transfer_completed'",[$a,$source])->result_array();essentialCheck(count($audit)===1&&!str_contains(json_encode($audit),'Edited private line')&&!str_contains(json_encode($audit),'Optional private value'),'original '.$kind.' transfer audit stores commitments without field contents');
    $originalSources[$kind]=$copied;$originalForms[$kind]=$form;$originalWires[$kind]=$wire;
}
echo 'Original Sales forms, persistence, edits, copy, transfer and replay: '.($GLOBALS['essentialChecks']-$originalFormStart).' added checks ('.$db->driver().').'.PHP_EOL;
