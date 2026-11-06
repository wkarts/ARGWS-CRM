<?php
/** Negative inputs, live authority and real rollback for the original Sales form adapter. */
require __DIR__.'/native_sales_original_form.php';
$boundaryStart=$GLOBALS['essentialChecks'];
$snap=function()use($db,$p):array{$result=[];foreach(['company_sales_documents','company_sales_revisions','company_sales_projection','company_custom_field_values','company_work_tags','company_work_tag_links','issuers_sequences','issuers_audit']as$table)$result[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY 1')->result_array();return$result;};
foreach(['estimates'=>'estimate','proposals'=>'proposal']as$kind=>$type){
    $form=$originalForms[$kind];$data=$form->data($a,7,'',$ca);$wire=originalSalesWire($kind,$data,$ca,$originalHeaders[$type],$originalLineField,$originalChoiceField);$before=$snap();
    $bad=$wire;$bad['save_and_send']='true';essentialThrows(fn()=>$form->save($a,7,$bad),'native_sales_form_delivery_requires_acceptance',$kind.' never silently turns send into save');
    $bad=$wire;$bad['status']='3';essentialThrows(fn()=>$form->save($a,7,$bad),'native_sales_status_requires_decision',$kind.' cannot bypass explicit acceptance from status selector');
    $bad=$wire;$bad['issuer_id']=(string)$b;essentialThrows(fn()=>$form->save($a,7,$bad),'native_estimate_form_invalid',$kind.' wire cannot replace issuer context');
    $bad=$wire;unset($bad['newitems']['4']['custom_fields']['items'][$originalLineField]);essentialThrows(fn()=>$form->save($a,7,$bad),'native_custom_field_required',$kind.' requires every actual mandatory line field');
    $bad=$wire;$bad['newitems']['4']['itemid']='42';essentialThrows(fn()=>$form->save($a,7,$bad),'native_estimate_form_invalid',$kind.' cannot inject another resource identity as a line position');
    $bad=$wire;$bad['newitems']['4']['custom_fields']['items'][$originalChoiceField]=['FORGED'];essentialThrows(fn()=>$form->save($a,7,$bad),'native_custom_field_invalid',$kind.' validates actual option definitions');
    $bad=$wire;$bad[$kind==='estimates'?'clientid':'rel_id']=(string)$cb;essentialThrows(fn()=>$form->save($a,7,$bad),'company_sales_recipient_denied',$kind.' rejects foreign customer before issuance');
    if($kind==='proposals'){$bad=$wire;$bad['rel_type']='lead';essentialThrows(fn()=>$form->save($a,7,$bad),'native_proposal_customer_required','lead conversion remains an explicit unresolved contract');}
    essentialCheck($snap()===$before,$kind.' rejected commands leave document, fields, tags, projection, sequence and audit untouched');
    $db->failOn='INSERT INTO '.$p.'company_work_tag_links';
    try{essentialThrows(fn()=>$form->save($a,7,$wire),'issuer_storage_failed',$kind.' late tag write veto rolls back full form');}finally{$db->failOn=null;}
    essentialCheck($snap()===$before,$kind.' failed tag compositor rolls back all canonical effects');
    $source=$originalSources[$kind];$canonical=NativeCompanyResourceId::encode('company_'.$type,$source);$data=$form->data($a,7,$canonical);
    $wire=originalSalesWire($kind,$data,$ca,$originalHeaders[$type],$originalLineField,$originalChoiceField);
    IssuerTransaction::run($db,$p,array_merge($fields->tableNames(),['company_sales_documents']),fn()=>$fields->saveInOwnedTransaction($a,7,$type,$canonical,[$originalHeaders[$type]=>'External metadata change']));
    $before=$snap();essentialThrows(fn()=>$form->save($a,7,$wire),'native_sales_metadata_stale',$kind.' detects metadata changed outside canonical payload');
    essentialCheck($snap()===$before,$kind.' stale metadata cannot overwrite an external header update');
    $current=$form->data($a,7,$canonical);$wire=originalSalesWire($kind,$current,$ca,$originalHeaders[$type],$originalLineField,$originalChoiceField);
    $lookup=new NativeCompanySalesCatalogLookup($db,$p,$a,7,$kind,$canonical,$current['native_sales_revision']);$form->save($a,7,$wire);
    essentialThrows(fn()=>$lookup->item(NativeCompanyResourceId::encode('company_item',1)),'company_sales_stale',$kind.' already constructed editor refuses stale document revision');
}

// Editor-only authority allows selection without adding catalogue CRUD permissions.
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12',[$a]);
$db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=12',[$a]);
foreach(['estimates','proposals']as$kind)foreach(['view','create']as$cap)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$a,$kind,$cap]);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'customers','view')",[$a]);
$item=NativeCompanyResourceId::encode('company_item',1);$foreignItem=NativeCompanyResourceId::encode('company_item',2);
IssuerTransaction::run($db,$p,$fields->tableNames(),fn()=>$fields->saveInOwnedTransaction($a,7,'items_pr',$item,[$originalLineField=>'Catalogue field',$originalChoiceField=>['A']]));
$editorCatalog=new NativeCompanyCatalogUi($db,$p,$a,12);
foreach(['estimates'=>'estimate','proposals'=>'proposal']as$kind=>$type){
    $lookup=new NativeCompanySalesCatalogLookup($db,$p,$a,12,$kind,'','');$selection=$editorCatalog->salesEditorChoices($kind);
    essentialCheck(count($selection['items_groups'])===1&&$lookup->item($item)['description']==='Original catalogue item'&&count($lookup->search('Original'))===1,$kind.' create-only actor selects own items and groups without items.view');
    essentialCheck(($lookup->fields($item)['values'][$originalLineField]??null)==='Catalogue field',$kind.' typed catalogue lookup exposes permitted line field values');
    essentialThrows(fn()=>$lookup->item($foreignItem),'company_catalog_denied',$kind.' selection cannot cross company');
    essentialThrows(fn()=>new NativeCompanySalesCatalogLookup($db,$p,$a,12,$kind,NativeCompanyResourceId::encode('company_'.$type,$originalSources[$kind]),str_repeat('a',64)),'native_sales_editor_denied',$kind.' create authority cannot masquerade as edit');
    essentialThrows(fn()=>new NativeCompanySalesCatalogLookup($db,$p,$b,12,$kind,'',''),'native_sales_editor_denied',$kind.' editor grant is scoped to selected company');
    $db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=12 AND feature=? AND capability=?',[$a,$kind,'create']);
    essentialThrows(fn()=>$lookup->fields($item),'native_sales_editor_denied',$kind.' live revocation stops existing typed lookup');
    $db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$a,$kind,'create']);
}
essentialThrows(fn()=>$editorCatalog->groups(),'company_catalog_denied','Sales lookup never grants ordinary item catalogue access');
essentialThrows(fn()=>$editorCatalog->saveItem(['description'=>'Forged','rate'=>'1']),'company_catalog_denied','Sales lookup never grants catalogue writes');

// Date/datetime custom controls use company localization while stored values remain exact SQL dates.
$dateFields=[];
foreach(['estimate'=>'date_picker','items'=>'date_picker_time']as$type=>$fieldType){$definition=NativeCompanyCustomFields::definition(['fieldto'=>$type,'name'=>'Original localized '.$type,'type'=>$fieldType]);$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,$type,$type.'_original_localized',json_encode($definition,JSON_THROW_ON_ERROR)]);$dateFields[$type]=NativeCompanyResourceId::encode('company_custom_field',(int)$db->insert_id());}
$settings->mutate($a,7,'update','dateformat','d/m/Y|%d/%m/%Y');$settings->mutate($a,7,'update','time_format','12');
$form=$originalForms['estimates'];$data=$form->data($a,7,'',$ca);$wire=originalSalesWire('estimates',$data,$ca,$originalHeaders['estimate'],$originalLineField,$originalChoiceField);
$wire['date']='31/01/2026';$wire['custom_fields']['estimate'][$dateFields['estimate']]='05/02/2026';$wire['newitems']['4']['custom_fields']['items'][$dateFields['items']]='05/02/2026 2:45 PM';
$localized=$form->save($a,7,$wire);$review=$sales->review($a,7,$localized['source_id'],'edit');
essentialCheck(($fields->values($a,7,'estimate',$localized['id'])[$dateFields['estimate']]??null)==='2026-02-05'&&$review['payload']['native_item_fields']['values'][0][$dateFields['items']]==='2026-02-05 14:45:00','original custom date/time wire respects selected company formats');
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'items','name'=>'New required field','type'=>'input','required'=>1]);$db->query('INSERT INTO '.$p.'company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,1)',[$a,'items','items_original_new_required',json_encode($definition,JSON_THROW_ON_ERROR)]);$required=NativeCompanyResourceId::encode('company_custom_field',(int)$db->insert_id());
$data=$form->data($a,7,$localized['id']);$rows=$data['native_sales_editor_items']->rows();
essentialCheck(!array_key_exists($required,$data['native_sales_editor_items']->values($rows[0])),'older form can render a newly mandatory field for user completion');
$missing=originalSalesWire('estimates',$data,$ca,$originalHeaders['estimate'],$originalLineField,$originalChoiceField);essentialThrows(fn()=>$form->save($a,7,$missing),'native_custom_field_required','newly mandatory definition is enforced when editing');
foreach($missing['newitems']as&$line)$line['custom_fields']['items'][$required]='Reviewed required value';unset($line);
essentialCheck($form->save($a,7,$missing)['id']===$localized['id'],'user can complete new mandatory values and preserve existing document identity');
echo 'Original Sales boundary/ACL/rollback/localization: '.($GLOBALS['essentialChecks']-$boundaryStart).' added checks ('.$db->driver().').'.PHP_EOL;
