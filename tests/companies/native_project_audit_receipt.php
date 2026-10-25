<?php
require __DIR__.'/native_blank_timer_assignment.php';
final class NativeAuditProbe extends CompanyProjectsStorage
{
 public function record(int $issuer,int $actor,int $id):void{$this->audit($issuer,$actor,$id,'company_project_test_receipt');}
}
$probe=new NativeAuditProbe($db,$p);$probe->record($a,7,1);$first=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id DESC LIMIT 1')->row_array();$probe->record($a,7,1);$secondReceipt=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id DESC LIMIT 1')->row_array();
essentialCheck($first['event']===$secondReceipt['event']&&(int)$first['actor_id']===7&&(int)$first['issuer_id']===$a&&(int)$first['subject_id']===1,'historical audit event and identities unchanged');
$audit1=json_decode($first['metadata_json'],true,512,JSON_THROW_ON_ERROR);$audit2=json_decode($secondReceipt['metadata_json'],true,512,JSON_THROW_ON_ERROR);essentialCheck(preg_match('/^[a-f0-9]{32}$/D',$audit1['operation_nonce'])===1&&$audit1['operation_nonce']!==$audit2['operation_nonce'],'identical actions carry unpredictable independent receipt nonce');
if($db->driver()==='sqlite'){
 $db->query("CREATE TRIGGER {$p}ignored_receipt BEFORE INSERT ON {$p}issuers_audit WHEN NEW.event='company_project_test_receipt' BEGIN SELECT RAISE(IGNORE); END");
 essentialThrows(fn()=>$probe->record($a,7,1),'issuer_project_write_unconfirmed','ignored INSERT cannot authenticate old insert id');$db->query('DROP TRIGGER '.$p.'ignored_receipt');
 $db->query("CREATE TRIGGER {$p}changed_receipt AFTER INSERT ON {$p}issuers_audit WHEN NEW.event='company_project_test_receipt' BEGIN UPDATE {$p}issuers_audit SET metadata_json='{}' WHERE id=NEW.id; END");
 essentialThrows(fn()=>$probe->record($a,7,1),'issuer_project_write_unconfirmed','affected row count alone cannot authenticate altered audit');$db->query('DROP TRIGGER '.$p.'changed_receipt');
}
echo "Native project audit receipt: {$GLOBALS['essentialChecks']} cumulative checks passed.\n";
