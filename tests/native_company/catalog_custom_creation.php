<?php
require __DIR__.'/auxiliary_contract.php';
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='items' AND capability='edit'");
$fields=new NativeCompanyCustomFields($db,$p);
$def=NativeCompanyCustomFields::definition(['fieldto'=>'items','name'=>'Options','type'=>'checkbox','options'=>'Alpha,Beta']);
$db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(41,'items','items_options',?,1)",[json_encode($def,JSON_THROW_ON_ERROR)]);$fid=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
$c=new NativeCompanyCatalog($db,$p,41,12);
$id=$c->saveItem(['description'=>'Creator without edit','rate'=>'10','custom_fields'=>['items_pr'=>[$fid=>['Alpha','Beta']]]]);$native=NativeCompanyResourceId::encode('company_item',$id);
essentialCheck($fields->values(41,12,'items_pr',$native)===[$fid=>'Alpha, Beta'],'create grant writes required typed fields without pretending edit authorization');
$copy=$c->copyItem($id,1);essentialCheck($fields->values(41,12,'items_pr',NativeCompanyResourceId::encode('company_item',$copy))===[$fid=>'Alpha, Beta'],'copy decodes checkbox stored string through central typed input API');
settingThrows(fn()=>$c->saveItem(['description'=>'Denied edit','rate'=>'10'],$id,1),'company_catalog_denied','create-only grant cannot update existing source');
$tables=array_merge($fields->tableNames(),['company_items']);
settingThrows(fn()=>IssuerTransaction::run($db,$p,$tables,fn()=>$fields->saveCreatedInOwnedTransaction(41,12,'items_pr',$id,[$fid=>['Beta']],$c)),'native_custom_field_creation_denied','fresh source attestation expires after writer transaction');
essentialCheck($fields->values(41,12,'items_pr',$native)===[$fid=>'Alpha, Beta'],'replayed creator object cannot mutate existing custom values');
echo "Catalogue creation-only custom fields and typed copy attestation: passed.\n";
