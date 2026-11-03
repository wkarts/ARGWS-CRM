<?php
require __DIR__.'/native_clients.php';
$start=$GLOBALS['essentialChecks'];$fields=new NativeCompanyCustomFields($db,$p);
$definition=NativeCompanyCustomFields::definition(['fieldto'=>'customers','name'=>'Native detail','type'=>'input','required'=>'1']);
$db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,?)",[41,'customers','customers_native_detail',json_encode($definition,JSON_THROW_ON_ERROR),1]);
$field=NativeCompanyResourceId::encode('company_custom_field',$db->insert_id());
$form=array_replace($payload,['company'=>'Fields owner','request_key'=>hash('sha256','native-field-profile'),'custom_fields'=>['customers'=>[$field=>'initial']]]);
$id=$adapterA->save($form);essentialCheck($fields->storedInput(41,12,'customers',$id)===[$field=>'initial'],'same native customer POST saves required custom field atomically');
essentialCheck($adapterA->save($form)===$id,'replay returns identical customer without duplicate field values');
$without=$form;unset($without['native_customer_fields_revision']);essentialThrows(fn()=>$adapterA->save($without),'company_client_stale','removing field revision cannot bypass required fields');
$without=$form;unset($without['custom_fields']);essentialThrows(fn()=>$adapterA->save($without),'native_custom_field_required','omitting entire field payload cannot bypass required fields');
$before=$adapterA->details($id);$edit=array_replace($form,['company'=>'Atomic edited','revision'=>$before['revision'],'native_customer_fields_revision'=>$adapterA->fieldData($id)['native_customer_fields_revision'],'custom_fields'=>['customers'=>[$field=>'updated']]]);
$adapterA->save($edit,$id);essentialCheck($fields->storedInput(41,12,'customers',$id)===[$field=>'updated'],'same native edit persists field value');
$edit['revision']=$adapterA->details($id)['revision'];$edit['company']='Must rollback on stale field';
essentialThrows(fn()=>$adapterA->save($edit,$id),'company_client_stale','stale field revision rejects despite current core revision');
essentialCheck($adapterA->details($id)['client']['company']==='Atomic edited','stale participant rolls back native customer write');
$edit['native_customer_fields_revision']=$adapterA->fieldData($id)['native_customer_fields_revision'];$edit['company']='Must rollback on field write';$db->failOn=$p.'company_custom_field_values';
essentialThrows(fn()=>$adapterA->save($edit,$id),'issuer_storage_failed','field storage failure rejects combined native save');$db->failOn=null;
essentialCheck($adapterA->details($id)['client']['company']==='Atomic edited','participant failure rolls back customer and field changes');
// Existing resources with edit-only permission retain edit, without gaining ordinary view.
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='customers' AND capability<>'edit'");
$stored=$fields->storedInput(41,12,'customers',$id,'edit');essentialCheck($stored===[$field=>'updated'],'edit-only may read exact value revision for editing');
essentialThrows(fn()=>$fields->storedInput(41,12,'customers',$id),'native_custom_field_denied','edit-only does not imply ordinary view');
$edit['native_customer_fields_revision']=hash('sha256',json_encode($stored,JSON_THROW_ON_ERROR));$edit['company']='Edit-only owner';$edit['custom_fields']['customers'][$field]='edit-only';$adapterA->save($edit,$id);
essentialCheck($fields->storedInput(41,12,'customers',$id,'edit')===[$field=>'edit-only'],'edit-only native customer writer saves fields without implicit view');
echo 'Native client profile fields: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
