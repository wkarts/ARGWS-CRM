<?php
require __DIR__.'/staff_read.php';require_once APPPATH.'services/NativeCompanyStaffRates.php';require_once APPPATH.'services/NativeCompanyStaffRateSchema.php';
NativeCompanyStaffRateSchema::ensure($db,$p);NativeCompanyStaffRateSchema::ensure($db,$p);
$db->query('UPDATE '.$p.'issuers_registry SET active=1 WHERE id=82');$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE staff_id=12');foreach([41,82]as$i)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$i,'tasks','view']);
$rates=new NativeCompanyStaffRates($db,$p);$before=$db->query('SELECT * FROM '.$p.'staff ORDER BY staffid')->result_array();
essentialCheck($rates->forOwnTimer(41,12)['hourly_rate']===null&&$rates->forOwnTimer(82,12)['hourly_rate']===null,'named memberships never copy global identity hourly rate');
IssuerTransaction::run($db,$p,$rates->tables(),fn()=>$rates->saveInOwned(41,7,12,'42.25',0));IssuerTransaction::run($db,$p,$rates->tables(),fn()=>$rates->saveInOwned(82,7,12,'13',0));
essentialCheck($rates->forOwnTimer(41,12)['hourly_rate']==='42.25'&&$rates->forOwnTimer(82,12)['hourly_rate']==='13.00','same shared login has separate explicit company rates');
essentialCheck($before===$db->query('SELECT * FROM '.$p.'staff ORDER BY staffid')->result_array(),'company rate writes leave global identity byte-exact');
essentialThrows(fn()=>IssuerTransaction::run($db,$p,$rates->tables(),fn()=>$rates->saveInOwned(41,7,12,'1',0)),'native_staff_rate_stale','rate revision rejects stale writer');
$primary=(new IssuerResourceAccess($db,$p))->primaryId();essentialCheck($rates->forOwnTimer($primary,7)['hourly_rate']==='15.25'&&$rates->forOwnTimer($primary,7)['source_kind']==='staff','principal retains only proven historical staff reference');
$db->query('CREATE TRIGGER ignore_rate_audit BEFORE INSERT ON '.$p.'issuers_audit BEGIN SELECT RAISE(IGNORE); END');essentialThrows(fn()=>IssuerTransaction::run($db,$p,$rates->tables(),fn()=>$rates->saveInOwned(41,7,12,'2',1)),'issuer_storage_failed','ignored audit rolls back rate');$db->query('DROP TRIGGER ignore_rate_audit');essentialCheck($rates->forOwnTimer(41,12)['hourly_rate']==='42.25','failed audit preserves prior rate');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12');essentialThrows(fn()=>$rates->forOwnTimer(41,12),'native_staff_rate_denied','revocation denies private rate');essentialCheck($rates->forOwnTimer(82,12)['hourly_rate']==='13.00','revocation in A does not mutate rate in B');
echo "Staff membership rate: nine real SQLite separation/provenance/CAS/audit checks passed.\n";
