<?php
require __DIR__.'/native_task_form_writer.php';
$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_standalone_tasks')->row_array()['n'];
$first=$standalone->createNative($a,7,$standInput,$standFields,$key('audit-first'));
$audit=$db->query('SELECT metadata_json FROM '.$p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? ORDER BY id DESC',[$a,$first,'company_standalone_task_native_created'])->row_array();
$metadata=json_decode($audit['metadata_json'],true,32,JSON_THROW_ON_ERROR);
essentialCheck(isset($metadata['task_priority_v1'])&&preg_match('/^[a-f0-9]{32}$/D',$metadata['audit_receipt_nonce']??'')===1,'Priority metadata preserved with unique insert receipt');
essentialCheck($standalone->createNative($a,7,$standInput,$standFields,$key('audit-first'))===$first,'Durable native task replay unchanged');
if($db->driver()==='sqlite'){
$db->query('CREATE TRIGGER '.$p."ignore_priority_receipt BEFORE INSERT ON ".$p."issuers_audit WHEN NEW.event='company_standalone_task_native_created' BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$standalone->createNative($a,7,$standInput,$standFields,$key('audit-ignored')),'issuer_task_priority_unconfirmed','Ignored INSERT cannot reuse previous audit ID');
$db->query('DROP TRIGGER '.$p.'ignore_priority_receipt');essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_standalone_tasks')->row_array()['n']===$before+1,'Rejected receipt rolls back task creation');
$db->query('CREATE TRIGGER '.$p."tamper_priority_receipt AFTER INSERT ON ".$p."issuers_audit WHEN NEW.event='company_standalone_task_native_created' BEGIN UPDATE ".$p."issuers_audit SET metadata_json='{}' WHERE id=NEW.id; END");
essentialThrows(fn()=>$standalone->createNative($a,7,$standInput,$standFields,$key('audit-tamper')),'issuer_task_priority_unconfirmed','Modified receipt cannot confirm priority write');
$db->query('DROP TRIGGER '.$p.'tamper_priority_receipt');essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_standalone_tasks')->row_array()['n']===$before+1,'Receipt tampering rolls back task creation');
}
echo 'Task priority audit actual receipt: '.$GLOBALS['essentialChecks']." cumulative checks passed.\n";
