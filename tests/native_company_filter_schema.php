<?php
/** Actual PDO schema/replay checks; SQLite does not establish MySQL DDL behavior. */
require __DIR__.'/settings/company_settings.php';
require_once APPPATH.'services/NativeCompanyFilterSchema.php';
NativeCompanyFilterSchema::ensure($db,$p);NativeCompanyFilterSchema::ensure($db,$p);
$db->query("INSERT INTO {$p}company_native_filters(issuer_id,staff_id,identifier,view_name,name,is_shared,builder_json,revision) VALUES(41,12,'invoices','invoices','Synthetic',0,'{}',1)");
$id=(int)$db->insert_id();$before=$db->query('SELECT * FROM '.$p.'company_native_filters')->result_array();NativeCompanyFilterSchema::ensure($db,$p);essentialCheck($db->query('SELECT * FROM '.$p.'company_native_filters')->result_array()===$before,'schema replay preserves filter values');
$db->query("INSERT INTO {$p}company_native_filter_defaults VALUES(41,12,'invoices','invoices','company_filter',?)",[$id]);
try{$db->query("INSERT INTO {$p}company_native_filter_defaults VALUES(41,12,'invoices','invoices','company_filter',?)",[$id]);throw new LogicException('Duplicate default accepted');}catch(PDOException $e){essentialCheck(true,'one default per actor/company/table/view');}
if($db->driver()==='sqlite'){
    $db->query('ALTER TABLE '.$p.'company_native_filters ADD unknown TEXT');
    essentialThrows(fn()=>NativeCompanyFilterSchema::ensure($db,$p),'native_filter_schema_invalid','existing ambiguous schema is rejected without repair');
    essentialCheck($db->query('SELECT name FROM '.$p.'company_native_filters')->row()->name==='Synthetic','failed validation preserves records');
}
echo "Native filter schema: replay, uniqueness and malformed-existing rejection passed (".$db->driver().").\n";
