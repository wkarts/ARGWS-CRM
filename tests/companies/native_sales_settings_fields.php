<?php
require __DIR__.'/clients.php';
require_once APPPATH.'services/NativeCompanyCustomFields.php';
NativeCompanyCustomFieldSchema::ensure($db,$p);
$fields=new NativeCompanyCustomFields($db,$p);$start=$GLOBALS['essentialChecks'];
foreach([[41,'Field A'],[82,'Field B']] as [$issuer,$name]){
    $definition=NativeCompanyCustomFields::definition(['fieldto'=>'proposal','name'=>$name,'type'=>'input']);
    $db->query("INSERT INTO {$p}company_custom_fields(issuer_id,fieldto,slug,definition_json,revision) VALUES(?,?,?,?,?)",[$issuer,'proposal','proposal_field_'.(string)$issuer,json_encode($definition,JSON_THROW_ON_ERROR),1]);
}
$aFields=$fields->definitionsForSalesSettings(41,7,'proposal');$bFields=$fields->definitionsForSalesSettings(82,7,'proposal');
essentialCheck(count($aFields)===1&&$aFields[0]['name']==='Field A','A sees only A proposal field definition');
essentialCheck(count($bFields)===1&&$bFields[0]['name']==='Field B','B sees only B proposal field definition');
essentialThrows(fn()=>$fields->definitionsForSalesSettings(41,12,'proposal'),'native_custom_field_denied','actor without settings permission sees no field definitions');
essentialThrows(fn()=>$fields->definitionsForSalesSettings(41,7,'customers'),'native_custom_field_denied','sales-only helper cannot project other resource types');
$db->query("UPDATE {$p}company_custom_fields SET definition_json=? WHERE issuer_id=41",[json_encode(NativeCompanyCustomFields::definition(['fieldto'=>'proposal','name'=>'Hidden','type'=>'input','active'=>0]),JSON_THROW_ON_ERROR)]);
essentialCheck($fields->definitionsForSalesSettings(41,7,'proposal')===[],'inactive definitions omitted');
echo 'Native sales settings custom field catalog: '.($GLOBALS['essentialChecks']-$start)." added checks; no field values exposed.\n";
